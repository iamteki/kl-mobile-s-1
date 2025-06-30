<?php

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\HasInventory;
use App\Traits\HasVariations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasMedia, HasInventory, HasVariations;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'min_rental_quantity',
        'max_rental_quantity',
        'price_per_day',
        'price_per_week',
        'price_per_month',
        'is_featured',
        'is_package_only',
        'requires_operator',
        'setup_time_hours',
        'weight_kg',
        'dimensions',
        'power_requirements',
        'status',
        'stock_alert_threshold',
        'views_count',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'price' => 'decimal:2',
        'price_per_day' => 'decimal:2',
        'price_per_week' => 'decimal:2',
        'price_per_month' => 'decimal:2',
        'setup_time_hours' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_package_only' => 'boolean',
        'requires_operator' => 'boolean',
        'min_rental_quantity' => 'integer',
        'max_rental_quantity' => 'integer',
        'stock_alert_threshold' => 'integer',
        'views_count' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            
            // Set default prices if not provided
            if (empty($product->price_per_day)) {
                $product->price_per_day = $product->price;
            }
            if (empty($product->price_per_week)) {
                $product->price_per_week = $product->price_per_day * 6;
            }
            if (empty($product->price_per_month)) {
                $product->price_per_month = $product->price_per_day * 20;
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Get the category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all attributes for this product.
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * Get all booking items for this product.
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Get all packages that include this product.
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_items')
            ->withPivot('quantity', 'price_override', 'product_variation_id', 'notes')
            ->withTimestamps();
    }

    /**
     * Scope to get only active products.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get featured products.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get products available for individual rental.
     */
    public function scopeAvailableForRental($query)
    {
        return $query->where('is_package_only', false);
    }

    /**
     * Get the product URL.
     */
    public function getUrlAttribute(): string
    {
        return route('products.show', $this->slug);
    }

    /**
     * Get the primary image URL.
     */
    public function getPrimaryImageAttribute(): ?string
    {
        $media = $this->media()->where('is_primary', true)->first();
        
        if (!$media) {
            $media = $this->media()->first();
        }
        
        return $media ? asset('storage/' . $media->path) : null;
    }

    /**
     * Get rental price based on duration.
     */
    public function getRentalPrice(int $days): float
    {
        if ($days >= 30) {
            $months = floor($days / 30);
            $remainingDays = $days % 30;
            return ($this->price_per_month * $months) + ($this->price_per_day * $remainingDays);
        } elseif ($days >= 7) {
            $weeks = floor($days / 7);
            $remainingDays = $days % 7;
            return ($this->price_per_week * $weeks) + ($this->price_per_day * $remainingDays);
        }
        
        return $this->price_per_day * $days;
    }

    /**
     * Get custom attribute value by template slug.
     */
    public function getCustomAttributeValue(string $slug)
    {
        $attribute = $this->attributes()
            ->whereHas('template', function ($query) use ($slug) {
                $query->where('slug', $slug);
            })
            ->first();

        return $attribute ? $attribute->value : null;
    }

    /**
     * Set custom attribute value by template slug.
     */
    public function setCustomAttributeValue(string $slug, $value)
    {
        $template = AttributeTemplate::where('slug', $slug)
            ->where('category_id', $this->category_id)
            ->first();

        if (!$template) {
            return false;
        }

        return $this->attributes()->updateOrCreate(
            ['attribute_template_id' => $template->id],
            ['value' => $value]
        );
    }

    /**
     * Check if product needs operator.
     */
    public function needsOperator(): bool
    {
        return $this->requires_operator;
    }

    /**
     * Get formatted dimensions.
     */
    public function getFormattedDimensionsAttribute(): ?string
    {
        if (!$this->dimensions) {
            return null;
        }

        $dims = $this->dimensions;
        
        if (isset($dims['length']) && isset($dims['width']) && isset($dims['height'])) {
            return "{$dims['length']} x {$dims['width']} x {$dims['height']} cm";
        }
        
        return null;
    }
}