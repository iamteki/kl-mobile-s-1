<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'item_type',
        'product_id',
        'product_variation_id',
        'package_id',
        'item_name',
        'item_sku',
        'quantity',
        'unit_price',
        'total_price',
        'rental_days',
        'item_attributes',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'rental_days' => 'integer',
        'item_attributes' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Calculate total price
            if (!$item->total_price) {
                $item->total_price = $item->unit_price * $item->quantity;
            }

            // Set item name and SKU if not provided
            if (!$item->item_name) {
                if ($item->product) {
                    $item->item_name = $item->product->name;
                    if ($item->productVariation) {
                        $item->item_name .= ' - ' . $item->productVariation->name;
                    }
                } elseif ($item->package) {
                    $item->item_name = $item->package->name;
                }
            }

            if (!$item->item_sku) {
                if ($item->product) {
                    $item->item_sku = $item->productVariation ? 
                        $item->productVariation->sku : 
                        $item->product->sku;
                } elseif ($item->package) {
                    $item->item_sku = 'PKG-' . $item->package->id;
                }
            }
        });
    }

    /**
     * Get the booking this item belongs to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the product (if item is a product).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variation.
     */
    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    /**
     * Get the package (if item is a package).
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Check if this is a product item.
     */
    public function isProduct(): bool
    {
        return $this->item_type === 'product' && $this->product_id !== null;
    }

    /**
     * Check if this is a package item.
     */
    public function isPackage(): bool
    {
        return $this->item_type === 'package' && $this->package_id !== null;
    }

    /**
     * Check if this is a service item.
     */
    public function isService(): bool
    {
        return $this->item_type === 'service';
    }

    /**
     * Get the item model (product or package).
     */
    public function getItemAttribute()
    {
        if ($this->isProduct()) {
            return $this->product;
        } elseif ($this->isPackage()) {
            return $this->package;
        }

        return null;
    }

    /**
     * Get the rental end date.
     */
    public function getRentalEndDateAttribute(): \Carbon\Carbon
    {
        return $this->booking->event_date->copy()->addDays($this->rental_days - 1);
    }

    /**
     * Get formatted rental period.
     */
    public function getRentalPeriodAttribute(): string
    {
        if ($this->rental_days === 1) {
            return '1 day';
        }

        return $this->rental_days . ' days';
    }

    /**
     * Get the daily rate.
     */
    public function getDailyRateAttribute(): float
    {
        return $this->total_price / $this->rental_days;
    }

    /**
     * Release inventory for this item.
     */
    public function releaseInventory(): bool
    {
        if (!$this->isProduct() || !$this->product) {
            return false;
        }

        return $this->product->releaseInventory(
            $this->quantity,
            $this->booking_id,
            $this->product_variation_id
        );
    }

    /**
     * Reserve inventory for this item.
     */
    public function reserveInventory(): bool
    {
        if (!$this->isProduct() || !$this->product) {
            return false;
        }

        return $this->product->reserveInventory(
            $this->quantity,
            $this->booking_id,
            $this->product_variation_id
        );
    }

    /**
     * Calculate the price for a different rental period.
     */
    public function calculatePriceForDays(int $days): float
    {
        if ($this->isProduct() && $this->product) {
            return $this->product->getRentalPriceWithVariation($days, $this->productVariation) * $this->quantity;
        }

        // For packages and services, use proportional pricing
        $dailyRate = $this->total_price / $this->rental_days;
        return $dailyRate * $days;
    }

    /**
     * Get a summary description of the item.
     */
    public function getSummaryAttribute(): string
    {
        $summary = $this->item_name;
        
        if ($this->quantity > 1) {
            $summary .= ' (x' . $this->quantity . ')';
        }
        
        if ($this->rental_days > 1) {
            $summary .= ' - ' . $this->rental_period;
        }
        
        return $summary;
    }

    /**
     * Duplicate this item for a new booking.
     */
    public function duplicateForBooking(Booking $booking): BookingItem
    {
        $newItem = $this->replicate();
        $newItem->booking_id = $booking->id;
        $newItem->save();
        
        return $newItem;
    }
}