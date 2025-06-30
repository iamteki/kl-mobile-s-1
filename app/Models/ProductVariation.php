<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'type',
        'value',
        'price',
        'price_modifier',
        'price_modifier_type',
        'weight_modifier',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_modifier' => 'decimal:2',
        'weight_modifier' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Variation types.
     */
    const TYPES = [
        'size' => 'Size',
        'color' => 'Color',
        'power' => 'Power',
        'capacity' => 'Capacity',
        'model' => 'Model',
    ];

    /**
     * Get the product this variation belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the inventory for this variation.
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /**
     * Get booking items for this variation.
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Get cart items for this variation.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get package items that use this variation.
     */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    /**
     * Scope to get only active variations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the display name (e.g., "Size: Large").
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->type_label . ': ' . $this->value;
    }

    /**
     * Get the full name including product name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->product->name . ' - ' . $this->name;
    }

    /**
     * Calculate the final price for this variation.
     */
    public function getFinalPriceAttribute(): float
    {
        if ($this->price !== null) {
            return $this->price;
        }

        $basePrice = $this->product->price;

        if ($this->price_modifier !== null) {
            if ($this->price_modifier_type === 'percentage') {
                return $basePrice + ($basePrice * $this->price_modifier / 100);
            } else {
                return $basePrice + $this->price_modifier;
            }
        }

        return $basePrice;
    }

    /**
     * Calculate the final weight for this variation.
     */
    public function getFinalWeightAttribute(): ?float
    {
        if (!$this->product->weight_kg) {
            return null;
        }

        return $this->product->weight_kg + ($this->weight_modifier ?? 0);
    }

    /**
     * Get available quantity.
     */
    public function getAvailableQuantityAttribute(): int
    {
        return $this->inventory ? $this->inventory->available_quantity : 0;
    }

    /**
     * Check if variation is in stock.
     */
    public function isInStock(int $quantity = 1): bool
    {
        return $this->available_quantity >= $quantity;
    }

    /**
     * Get the price difference from base product.
     */
    public function getPriceDifferenceAttribute(): float
    {
        return $this->final_price - $this->product->price;
    }

    /**
     * Get the price difference display string.
     */
    public function getPriceDifferenceDisplayAttribute(): string
    {
        $difference = $this->price_difference;

        if ($difference > 0) {
            return '+LKR ' . number_format($difference, 2);
        } elseif ($difference < 0) {
            return '-LKR ' . number_format(abs($difference), 2);
        }

        return '';
    }

    /**
     * Create inventory record for this variation.
     */
    public function createInventory(int $quantity = 0): Inventory
    {
        return $this->inventory()->create([
            'product_id' => $this->product_id,
            'total_quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'maintenance_quantity' => 0,
        ]);
    }

    /**
     * Get variation by type and value.
     */
    public static function findByTypeAndValue(int $productId, string $type, string $value): ?self
    {
        return static::where('product_id', $productId)
            ->where('type', $type)
            ->where('value', $value)
            ->first();
    }

    /**
     * Generate SKU based on product SKU and variation.
     */
    public function generateSku(): string
    {
        $productSku = $this->product->sku;
        $typePrefix = strtoupper(substr($this->type, 0, 2));
        $valuePrefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $this->value), 0, 3));
        
        return $productSku . '-' . $typePrefix . $valuePrefix;
    }

    /**
     * Clone this variation for another product.
     */
    public function cloneForProduct(Product $product): self
    {
        $clone = $this->replicate();
        $clone->product_id = $product->id;
        $clone->sku = $clone->generateSku();
        $clone->save();

        // Create inventory for the cloned variation
        $clone->createInventory();

        return $clone;
    }
}