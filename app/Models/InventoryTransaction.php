<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'booking_id',
        'user_id',
        'type',
        'quantity',
        'balance_before',
        'balance_after',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    /**
     * Transaction types.
     */
    const TYPES = [
        'booking' => 'Booking Reservation',
        'return' => 'Booking Return',
        'adjustment' => 'Stock Adjustment',
        'maintenance' => 'Moved to Maintenance',
        'maintenance_return' => 'Returned from Maintenance',
        'damage' => 'Damaged/Lost',
    ];

    /**
     * Get the inventory this transaction belongs to.
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * Get the booking associated with this transaction.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the user who created this transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the change amount (positive or negative).
     */
    public function getChangeAttribute(): int
    {
        return $this->balance_after - $this->balance_before;
    }

    /**
     * Get formatted change display.
     */
    public function getChangeDisplayAttribute(): string
    {
        $change = $this->change;
        
        if ($change > 0) {
            return '+' . $change;
        } elseif ($change < 0) {
            return (string) $change;
        }
        
        return '0';
    }

    /**
     * Get the transaction icon.
     */
    public function getIconAttribute(): string
    {
        return match($this->type) {
            'booking' => 'calendar-check',
            'return' => 'calendar-x',
            'adjustment' => 'pencil',
            'maintenance' => 'wrench',
            'maintenance_return' => 'wrench',
            'damage' => 'alert-triangle',
            default => 'info',
        };
    }

    /**
     * Get the transaction color class.
     */
    public function getColorClassAttribute(): string
    {
        return match($this->type) {
            'booking' => 'warning',
            'return' => 'success',
            'adjustment' => 'info',
            'maintenance' => 'secondary',
            'maintenance_return' => 'primary',
            'damage' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the product associated with this transaction.
     */
    public function getProductAttribute(): ?Product
    {
        return $this->inventory?->product;
    }

    /**
     * Get the product variation associated with this transaction.
     */
    public function getProductVariationAttribute(): ?ProductVariation
    {
        return $this->inventory?->productVariation;
    }

    /**
     * Scope to get transactions by type.
     */
    public function scopeOfType($query, $type)
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        }
        
        return $query->where('type', $type);
    }

    /**
     * Scope to get recent transactions.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get summary text for the transaction.
     */
    public function getSummaryAttribute(): string
    {
        $product = $this->product;
        $variation = $this->productVariation;
        
        $productName = $product ? $product->name : 'Unknown Product';
        if ($variation) {
            $productName .= ' - ' . $variation->name;
        }
        
        $summary = $this->type_label . ': ' . $this->quantity . ' unit(s) of ' . $productName;
        
        if ($this->booking) {
            $summary .= ' (Booking #' . $this->booking->booking_number . ')';
        }
        
        return $summary;
    }

    /**
     * Create a reverse transaction.
     */
    public function reverse(string $reason = null): ?self
    {
        // Determine reverse type
        $reverseType = match($this->type) {
            'booking' => 'return',
            'maintenance' => 'maintenance_return',
            default => null,
        };
        
        if (!$reverseType) {
            return null;
        }
        
        $inventory = $this->inventory;
        
        return $inventory->transactions()->create([
            'booking_id' => $this->booking_id,
            'user_id' => auth()->id(),
            'type' => $reverseType,
            'quantity' => $this->quantity,
            'balance_before' => $inventory->available_quantity,
            'balance_after' => $inventory->available_quantity + $this->quantity,
            'reason' => $reason ?: 'Reversal of transaction #' . $this->id,
            'notes' => 'Reversed from transaction #' . $this->id,
        ]);
    }
}