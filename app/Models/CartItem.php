<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'item_type',
        'product_id',
        'product_variation_id',
        'package_id',
        'quantity',
        'unit_price',
        'total_price',
        'rental_days',
        'item_data',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'rental_days' => 'integer',
        'item_data' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Recalculate total price
            $item->total_price = $item->unit_price * $item->quantity;
        });
    }

    /**
     * Get the cart this item belongs to.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
     * Get item name.
     */
    public function getNameAttribute(): string
    {
        if ($this->item_data && isset($this->item_data['name'])) {
            return $this->item_data['name'];
        }

        if ($this->isProduct() && $this->product) {
            $name = $this->product->name;
            if ($this->productVariation) {
                $name .= ' - ' . $this->productVariation->name;
            }
            return $name;
        }

        if ($this->isPackage() && $this->package) {
            return $this->package->name;
        }

        return 'Unknown Item';
    }

    /**
     * Get item SKU.
     */
    public function getSkuAttribute(): string
    {
        if ($this->item_data && isset($this->item_data['sku'])) {
            return $this->item_data['sku'];
        }

        if ($this->productVariation) {
            return $this->productVariation->sku;
        }

        if ($this->product) {
            return $this->product->sku;
        }

        if ($this->package) {
            return 'PKG-' . $this->package->id;
        }

        return '';
    }

    /**
     * Get item image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->item_data && isset($this->item_data['image'])) {
            return $this->item_data['image'];
        }

        if ($this->product) {
            return $this->product->primary_image;
        }

        if ($this->package) {
            return $this->package->image ? asset('storage/' . $this->package->image) : null;
        }

        return null;
    }

    /**
     * Get the daily rate.
     */
    public function getDailyRateAttribute(): float
    {
        return $this->unit_price;
    }

    /**
     * Update rental days and recalculate price.
     */
    public function updateRentalDays(int $days): void
    {
        if ($days <= 0) {
            $days = 1;
        }

        $this->rental_days = $days;

        // Recalculate price based on rental duration
        if ($this->isProduct() && $this->product) {
            $this->unit_price = $this->product->getRentalPriceWithVariation($days, $this->productVariation) / $days;
        }

        $this->save();
    }

    /**
     * Check availability for the item.
     */
    public function checkAvailability(\Carbon\Carbon $date): bool
    {
        if ($this->isProduct() && $this->product) {
            $available = $this->product->getAvailabilityForDateRange(
                $date,
                $date,
                $this->product_variation_id
            );
            return $available >= $this->quantity;
        }

        if ($this->isPackage() && $this->package) {
            return $this->package->isAvailable($date, $this->quantity);
        }

        return false;
    }

    /**
     * Get a summary of the item.
     */
    public function getSummaryAttribute(): string
    {
        $summary = $this->name;
        
        if ($this->quantity > 1) {
            $summary .= ' (x' . $this->quantity . ')';
        }
        
        if ($this->rental_days > 1) {
            $summary .= ' - ' . $this->rental_days . ' days';
        }
        
        return $summary;
    }

    /**
     * Calculate savings if any.
     */
    public function getSavingsAttribute(): float
    {
        if ($this->isPackage() && $this->package) {
            $originalPrice = $this->package->original_price ?? $this->package->price;
            return max(0, ($originalPrice - $this->unit_price) * $this->quantity);
        }

        return 0;
    }

    /**
     * Clone item to another cart.
     */
    public function cloneToCart(Cart $cart): self
    {
        $newItem = $this->replicate();
        $newItem->cart_id = $cart->id;
        $newItem->save();
        
        return $newItem;
    }
}