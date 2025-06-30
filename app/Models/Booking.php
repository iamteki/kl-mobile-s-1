<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number',
        'customer_id',
        'user_id',
        'event_date',
        'event_type',
        'event_venue',
        'number_of_pax',
        'installation_time',
        'event_start_time',
        'dismantle_time',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'delivery_charge',
        'total_amount',
        'status',
        'payment_status',
        'customer_notes',
        'admin_notes',
        'cancellation_reason',
        'confirmed_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'installation_time' => 'datetime:H:i',
        'event_start_time' => 'datetime:H:i',
        'dismantle_time' => 'datetime:H:i',
        'number_of_pax' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Booking statuses.
     */
    const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    /**
     * Payment statuses.
     */
    const PAYMENT_STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'partial' => 'Partial',
        'refunded' => 'Refunded',
    ];

    /**
     * Event types.
     */
    const EVENT_TYPES = [
        'wedding' => 'Wedding',
        'birthday' => 'Birthday Party',
        'corporate' => 'Corporate Event',
        'concert' => 'Concert',
        'festival' => 'Festival',
        'conference' => 'Conference',
        'exhibition' => 'Exhibition',
        'private' => 'Private Party',
        'other' => 'Other',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = self::generateBookingNumber();
            }
        });

        static::updating(function ($booking) {
            // Update status timestamps
            if ($booking->isDirty('status')) {
                switch ($booking->status) {
                    case 'confirmed':
                        $booking->confirmed_at = now();
                        break;
                    case 'delivered':
                        $booking->delivered_at = now();
                        break;
                    case 'completed':
                        $booking->completed_at = now();
                        break;
                    case 'cancelled':
                        $booking->cancelled_at = now();
                        break;
                }
            }
        });
    }

    /**
     * Get the customer for this booking.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created this booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all items in this booking.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Get all payments for this booking.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the latest payment.
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Get inventory transactions for this booking.
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Scope to get bookings by status.
     */
    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        
        return $query->where('status', $status);
    }

    /**
     * Scope to get paid bookings.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope to get upcoming bookings.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', today())
            ->whereIn('status', ['confirmed', 'processing']);
    }

    /**
     * Scope to get past bookings.
     */
    public function scopePast($query)
    {
        return $query->where('event_date', '<', today());
    }

    /**
     * Generate a unique booking number.
     */
    public static function generateBookingNumber(): string
    {
        do {
            $number = 'BK' . date('Ymd') . Str::upper(Str::random(4));
        } while (self::where('booking_number', $number)->exists());

        return $number;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get the payment status label.
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    /**
     * Get the event type label.
     */
    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    /**
     * Get the status badge HTML.
     */
    public function getStatusBadgeAttribute(): string
    {
        $class = match($this->status) {
            'pending' => 'warning',
            'confirmed' => 'info',
            'processing' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'secondary',
            default => 'secondary',
        };

        return '<span class="badge bg-' . $class . '">' . $this->status_label . '</span>';
    }

    /**
     * Get the payment status badge HTML.
     */
    public function getPaymentStatusBadgeAttribute(): string
    {
        $class = match($this->payment_status) {
            'pending' => 'warning',
            'paid' => 'success',
            'partial' => 'info',
            'refunded' => 'danger',
            default => 'secondary',
        };

        return '<span class="badge bg-' . $class . '">' . $this->payment_status_label . '</span>';
    }

    /**
     * Check if booking can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Check if booking can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled', 'refunded']) 
            && $this->event_date->isFuture();
    }

    /**
     * Check if booking can be delivered.
     */
    public function canBeDelivered(): bool
    {
        return $this->status === 'processing' 
            && $this->payment_status === 'paid';
    }

    /**
     * Check if booking is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->payment_status === 'pending' 
            && $this->created_at->diffInDays(now()) > 3;
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->delivery_charge - $this->discount_amount;
        $this->save();
    }

    /**
     * Confirm the booking.
     */
    public function confirm(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        // Reserve inventory for all items
        foreach ($this->items as $item) {
            if (!$item->reserveInventory()) {
                // Rollback if any item fails
                foreach ($this->items as $rolledItem) {
                    if ($rolledItem->id === $item->id) break;
                    $rolledItem->releaseInventory();
                }
                return false;
            }
        }

        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return true;
    }

    /**
     * Cancel the booking.
     */
    public function cancel(string $reason = null): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        // Release inventory
        foreach ($this->items as $item) {
            $item->releaseInventory();
        }

        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        return true;
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered(): bool
    {
        if (!$this->canBeDelivered()) {
            return false;
        }

        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return true;
    }

    /**
     * Complete the booking.
     */
    public function complete(): bool
    {
        if ($this->status !== 'delivered') {
            return false;
        }

        // Release inventory
        foreach ($this->items as $item) {
            $item->releaseInventory();
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update customer statistics
        $this->customer->updateStatistics();

        return true;
    }

    /**
     * Get the delivery schedule.
     */
    public function getDeliveryScheduleAttribute(): array
    {
        return [
            'date' => $this->event_date->format('Y-m-d'),
            'installation_time' => $this->installation_time,
            'event_start' => $this->event_start_time,
            'dismantle_time' => $this->dismantle_time,
        ];
    }

    /**
     * Get the booking duration in hours.
     */
    public function getDurationInHoursAttribute(): float
    {
        $start = $this->event_date->copy()->setTimeFromTimeString($this->installation_time);
        $end = $this->event_date->copy()->setTimeFromTimeString($this->dismantle_time);
        
        if ($end < $start) {
            $end->addDay();
        }
        
        return $start->diffInHours($end);
    }

    /**
     * Duplicate this booking.
     */
    public function duplicate(): self
    {
        $newBooking = $this->replicate([
            'booking_number',
            'status',
            'payment_status',
            'confirmed_at',
            'delivered_at',
            'completed_at',
            'cancelled_at',
        ]);
        
        $newBooking->booking_number = self::generateBookingNumber();
        $newBooking->status = 'pending';
        $newBooking->payment_status = 'pending';
        $newBooking->save();

        // Duplicate items
        foreach ($this->items as $item) {
            $item->duplicateForBooking($newBooking);
        }

        $newBooking->calculateTotals();

        return $newBooking;
    }
}