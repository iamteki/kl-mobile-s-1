<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'product_id',
        'product_variation_id',
        'quantity',
        'price_override',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_override' => 'decimal:2',
    ];

    /**
     * Get the package this item belongs to.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the product in this package item.
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
     * Get the display name for this item.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->product ? $this->product->name : 'Unknown Product';
        
        if ($this->productVariation) {
            $name .= ' - ' . $this->productVariation->name;
        }
        
        if ($this->quantity > 1) {
            $name .= ' (x' . $this->quantity . ')';
        }
        
        return $name;
    }

    /**
     * Get the SKU for this item.
     */
    public function getSkuAttribute(): string
    {
        if ($this->productVariation) {
            return $this->productVariation->sku;
        }
        
        return $this->product ? $this->product->sku : '';
    }

    /**
     * Get the unit price for this item.
     */
    public function getUnitPriceAttribute(): float
    {
        if ($this->price_override !== null) {
            return $this->price_override;
        }
        
        if ($this->product) {
            return $this->product->getPriceWithVariation($this->productVariation);
        }
        
        return 0;
    }

    /**
     * Get the total price for this item.
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * Check if this item is available for a specific date.
     */
    public function isAvailable(\Carbon\Carbon $date): bool
    {
        if (!$this->product) {
            return false;
        }
        
        $availableQuantity = $this->product->getAvailabilityForDateRange(
            $date,
            $date,
            $this->product_variation_id
        );
        
        return $availableQuantity >= $this->quantity;
    }

    /**
     * Get availability for date range.
     */
    public function getAvailabilityForDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): int
    {
        if (!$this->product) {
            return 0;
        }
        
        $availableQuantity = $this->product->getAvailabilityForDateRange(
            $startDate,
            $endDate,
            $this->product_variation_id
        );
        
        // Return how many complete sets of this item are available
        return intval($availableQuantity / $this->quantity);
    }

    /**
     * Clone this item to another package.
     */
    public function cloneToPackage(Package $package): self
    {
        return $package->items()->create([
            'product_id' => $this->product_id,
            'product_variation_id' => $this->product_variation_id,
            'quantity' => $this->quantity,
            'price_override' => $this->price_override,
            'notes' => $this->notes,
        ]);
    }

    /**
     * Update quantity and recalculate package price if needed.
     */
    public function updateQuantity(int $quantity): void
    {
        $this->update(['quantity' => $quantity]);
        
        // Optionally trigger package price recalculation
        if ($this->package) {
            $this->package->touch();
        }
    }

    /**
     * Get savings amount if price is overridden.
     */
    public function getSavingsAttribute(): float
    {
        if ($this->price_override === null || !$this->product) {
            return 0;
        }
        
        $originalPrice = $this->product->getPriceWithVariation($this->productVariation);
        $savings = ($originalPrice - $this->price_override) * $this->quantity;
        
        return max(0, $savings);
    }

    /**
     * Get percentage discount if price is overridden.
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if ($this->price_override === null || !$this->product) {
            return null;
        }
        
        $originalPrice = $this->product->getPriceWithVariation($this->productVariation);
        
        if ($originalPrice <= 0) {
            return null;
        }
        
        $discount = (($originalPrice - $this->price_override) / $originalPrice) * 100;
        
        return round(max(0, $discount), 2);
    }
}