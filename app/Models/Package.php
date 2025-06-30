<?php

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Package extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'original_price',
        'image',
        'is_featured',
        'sort_order',
        'included_services',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'included_services' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            if (empty($package->slug)) {
                $package->slug = Str::slug($package->name);
            }
        });

        static::updating(function ($package) {
            if ($package->isDirty('name') && empty($package->slug)) {
                $package->slug = Str::slug($package->name);
            }
        });
    }

    /**
     * Get the category this package belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the products in this package.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'package_items')
            ->withPivot('quantity', 'price_override', 'product_variation_id', 'notes')
            ->withTimestamps();
    }

    /**
     * Get package items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    /**
     * Get all booking items for this package.
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Scope to get only active packages.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get featured packages.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get the package URL.
     */
    public function getUrlAttribute(): string
    {
        return route('packages.show', $this->slug);
    }

    /**
     * Get the discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }

        return round((($this->original_price - $this->price) / $this->original_price) * 100, 0);
    }

    /**
     * Get the savings amount.
     */
    public function getSavingsAmountAttribute(): ?float
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }

        return $this->original_price - $this->price;
    }

    /**
     * Calculate the original price based on individual items.
     */
    public function calculateOriginalPrice(): float
    {
        $total = 0;

        foreach ($this->items as $item) {
            if ($item->product) {
                $price = $item->product->price;
                
                if ($item->productVariation) {
                    $price = $item->product->getPriceWithVariation($item->productVariation);
                }
                
                $total += $price * $item->quantity;
            }
        }

        return $total;
    }

    /**
     * Check if all items in the package are available.
     */
    public function isAvailable(\Carbon\Carbon $date, int $quantity = 1): bool
    {
        foreach ($this->items as $item) {
            if (!$item->product) {
                continue;
            }

            $availableQuantity = $item->product->getAvailabilityForDateRange(
                $date,
                $date,
                $item->product_variation_id
            );

            if ($availableQuantity < ($item->quantity * $quantity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get availability for date range.
     */
    public function getAvailabilityForDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): int
    {
        $maxQuantity = PHP_INT_MAX;

        foreach ($this->items as $item) {
            if (!$item->product) {
                continue;
            }

            $availableQuantity = $item->product->getAvailabilityForDateRange(
                $startDate,
                $endDate,
                $item->product_variation_id
            );

            $possiblePackages = intval($availableQuantity / $item->quantity);
            $maxQuantity = min($maxQuantity, $possiblePackages);
        }

        return $maxQuantity === PHP_INT_MAX ? 0 : $maxQuantity;
    }

    /**
     * Add a product to the package.
     */
    public function addProduct(Product $product, int $quantity = 1, ?int $variationId = null, ?float $priceOverride = null): PackageItem
    {
        return $this->items()->create([
            'product_id' => $product->id,
            'product_variation_id' => $variationId,
            'quantity' => $quantity,
            'price_override' => $priceOverride,
        ]);
    }

    /**
     * Remove a product from the package.
     */
    public function removeProduct(Product $product, ?int $variationId = null): bool
    {
        return $this->items()
            ->where('product_id', $product->id)
            ->where('product_variation_id', $variationId)
            ->delete();
    }

    /**
     * Update product quantity in the package.
     */
    public function updateProductQuantity(Product $product, int $quantity, ?int $variationId = null): bool
    {
        return $this->items()
            ->where('product_id', $product->id)
            ->where('product_variation_id', $variationId)
            ->update(['quantity' => $quantity]);
    }

    /**
     * Sync package items.
     */
    public function syncItems(array $items): void
    {
        $this->items()->delete();

        foreach ($items as $item) {
            $this->items()->create($item);
        }
    }

    /**
     * Get formatted included services.
     */
    public function getFormattedIncludedServicesAttribute(): array
    {
        return $this->included_services ?: [];
    }

    /**
     * Clone the package.
     */
    public function duplicate(): Package
    {
        $clone = $this->replicate();
        $clone->name = $this->name . ' (Copy)';
        $clone->slug = Str::slug($clone->name);
        $clone->is_featured = false;
        $clone->save();

        // Copy items
        foreach ($this->items as $item) {
            $clone->items()->create($item->toArray());
        }

        // Copy media
        foreach ($this->media as $media) {
            $clone->media()->create($media->toArray());
        }

        return $clone;
    }
}