<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'transaction_id',
        'payment_method',
        'type',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_response',
        'reference_number',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Payment types.
     */
    const TYPES = [
        'payment' => 'Payment',
        'refund' => 'Full Refund',
        'partial_refund' => 'Partial Refund',
    ];

    /**
     * Payment statuses.
     */
    const STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Payment methods.
     */
    const METHODS = [
        'card' => 'Credit/Debit Card',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            // Generate transaction ID if not set
            if (!$payment->transaction_id) {
                $payment->transaction_id = 'TXN' . date('YmdHis') . strtoupper(substr(uniqid(), -4));
            }
        });

        static::updated(function ($payment) {
            // Update booking payment status when payment is completed
            if ($payment->isDirty('status') && $payment->status === 'completed') {
                $payment->updateBookingPaymentStatus();
            }
        });
    }

    /**
     * Get the booking this payment belongs to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get the payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::METHODS[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Get the status badge HTML.
     */
    public function getStatusBadgeAttribute(): string
    {
        $class = match($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };

        return '<span class="badge bg-' . $class . '">' . $this->status_label . '</span>';
    }

    /**
     * Scope to get completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get payments by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is a refund.
     */
    public function isRefund(): bool
    {
        return in_array($this->type, ['refund', 'partial_refund']);
    }

    /**
     * Mark payment as completed.
     */
    public function markAsCompleted(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => 'completed',
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => 'failed',
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
        ]);
    }

    /**
     * Update booking payment status.
     */
    protected function updateBookingPaymentStatus(): void
    {
        $booking = $this->booking;
        
        if (!$booking) {
            return;
        }

        $totalPaid = $booking->payments()
            ->completed()
            ->where('type', 'payment')
            ->sum('amount');

        $totalRefunded = $booking->payments()
            ->completed()
            ->whereIn('type', ['refund', 'partial_refund'])
            ->sum('amount');

        $netPaid = $totalPaid - $totalRefunded;

        if ($netPaid >= $booking->total_amount) {
            $booking->update(['payment_status' => 'paid']);
        } elseif ($netPaid > 0) {
            $booking->update(['payment_status' => 'partial']);
        } elseif ($totalRefunded >= $totalPaid && $totalRefunded > 0) {
            $booking->update(['payment_status' => 'refunded']);
        } else {
            $booking->update(['payment_status' => 'pending']);
        }
    }

    /**
     * Process refund.
     */
    public function processRefund(float $amount = null): ?self
    {
        if (!$this->isSuccessful() || $this->isRefund()) {
            return null;
        }

        $refundAmount = $amount ?? $this->amount;
        $type = $refundAmount < $this->amount ? 'partial_refund' : 'refund';

        return $this->booking->payments()->create([
            'transaction_id' => 'REF' . date('YmdHis') . strtoupper(substr(uniqid(), -4)),
            'payment_method' => $this->payment_method,
            'type' => $type,
            'amount' => $refundAmount,
            'currency' => $this->currency,
            'status' => 'pending',
            'gateway' => $this->gateway,
            'reference_number' => $this->transaction_id,
            'notes' => 'Refund for transaction ' . $this->transaction_id,
        ]);
    }

    /**
     * Get Stripe payment intent.
     */
    public function getStripePaymentIntent(): ?string
    {
        if ($this->gateway !== 'stripe' || !$this->gateway_response) {
            return null;
        }

        return $this->gateway_response['payment_intent_id'] ?? null;
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Get net amount (considering type).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->isRefund() ? -$this->amount : $this->amount;
    }

    /**
     * Create payment record from Stripe webhook.
     */
    public static function createFromStripeWebhook(array $data): ?self
    {
        // Extract booking ID from metadata
        $bookingId = $data['metadata']['booking_id'] ?? null;
        
        if (!$bookingId || !$booking = Booking::find($bookingId)) {
            return null;
        }

        return self::create([
            'booking_id' => $bookingId,
            'transaction_id' => $data['id'],
            'payment_method' => 'card',
            'type' => 'payment',
            'amount' => $data['amount'] / 100, // Convert from cents
            'currency' => strtoupper($data['currency']),
            'status' => $data['status'] === 'succeeded' ? 'completed' : 'failed',
            'gateway' => 'stripe',
            'gateway_response' => $data,
            'paid_at' => $data['status'] === 'succeeded' ? now() : null,
        ]);
    }
}