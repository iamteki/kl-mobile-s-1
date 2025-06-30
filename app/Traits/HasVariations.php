<?php

namespace App\Traits;

use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasVariations
{
    /**
     * Get all variations for this product.
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)->orderBy('sort_order');
    }

    /**
     * Get active variations.
     */
    public function activeVariations(): HasMany
    {
        return $this->variations()->where('status', 'active');
    }

    /**
     * Check if product has variations.
     */
    public function hasVariations(): bool
    {
        return $this->variations()->exists();
    }

    /**
     * Check if product has active variations.
     */
    public function hasActiveVariations(): bool
    {
        return $this->activeVariations()->exists();
    }

    /**
     * Get variations by type.
     */
    public function getVariationsByType(string $type)
    {
        return $this->variations()->where('type', $type)->get();
    }

    /**
     * Get variation types.
     */
    public function getVariationTypes(): array
    {
        return $this->variations()
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->toArray();
    }

    /**
     * Get default variation.
     */
    public function getDefaultVariation(): ?ProductVariation
    {
        return $this->variations()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Get price with variation.
     */
    public function getPriceWithVariation(?ProductVariation $variation = null): float
    {
        if (!$variation) {
            return $this->price;
        }

        if ($variation->price !== null) {
            return $variation->price;
        }

        if ($variation->price_modifier !== null) {
            if ($variation->price_modifier_type === 'percentage') {
                return $this->price + ($this->price * $variation->price_modifier / 100);
            } else {
                return $this->price + $variation->price_modifier;
            }
        }

        return $this->price;
    }

    /**
     * Get rental price with variation.
     */
    public function getRentalPriceWithVariation(int $days, ?ProductVariation $variation = null): float
    {
        $basePrice = $this->getRentalPrice($days);
        
        if (!$variation) {
            return $basePrice;
        }

        // If variation has its own price, calculate rental based on that
        if ($variation->price !== null) {
            $variationProduct = clone $this;
            $variationProduct->price = $variation->price;
            $variationProduct->price_per_day = $variation->price;
            $variationProduct->price_per_week = $variation->price * 6;
            $variationProduct->price_per_month = $variation->price * 20;
            return $variationProduct->getRentalPrice($days);
        }

        // Apply modifier to the calculated rental price
        if ($variation->price_modifier !== null) {
            if ($variation->price_modifier_type === 'percentage') {
                return $basePrice + ($basePrice * $variation->price_modifier / 100);
            } else {
                // For fixed modifier, apply it proportionally to the rental duration
                $dailyModifier = $variation->price_modifier;
                return $basePrice + ($dailyModifier * $days);
            }
        }

        return $basePrice;
    }

    /**
     * Get variation by SKU.
     */
    public function getVariationBySku(string $sku): ?ProductVariation
    {
        return $this->variations()->where('sku', $sku)->first();
    }

    /**
     * Get variation options for display.
     */
    public function getVariationOptions(): array
    {
        $options = [];
        
        foreach ($this->getVariationTypes() as $type) {
            $options[$type] = $this->activeVariations()
                ->where('type', $type)
                ->get()
                ->map(function ($variation) {
                    return [
                        'id' => $variation->id,
                        'value' => $variation->value,
                        'name' => $variation->name,
                        'price' => $this->getPriceWithVariation($variation),
                        'sku' => $variation->sku,
                        'available' => $variation->inventory ? $variation->inventory->available_quantity : 0,
                    ];
                })
                ->toArray();
        }
        
        return $options;
    }

    /**
     * Create variation.
     */
    public function createVariation(array $data): ProductVariation
    {
        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = $this->sku . '-' . strtoupper(substr($data['type'], 0, 1)) . '-' . time();
        }

        // Set sort order
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = $this->variations()->max('sort_order') + 1;
        }

        $variation = $this->variations()->create($data);

        // Create inventory record for variation
        if ($this->hasInventory) {
            $this->inventories()->create([
                'product_variation_id' => $variation->id,
                'total_quantity' => 0,
                'available_quantity' => 0,
                'reserved_quantity' => 0,
                'maintenance_quantity' => 0,
            ]);
        }

        return $variation;
    }

    /**
     * Sync variations.
     */
    public function syncVariations(array $variations): void
    {
        $existingIds = [];

        foreach ($variations as $variationData) {
            if (isset($variationData['id'])) {
                // Update existing
                $variation = $this->variations()->find($variationData['id']);
                if ($variation) {
                    $variation->update($variationData);
                    $existingIds[] = $variation->id;
                }
            } else {
                // Create new
                $variation = $this->createVariation($variationData);
                $existingIds[] = $variation->id;
            }
        }

        // Delete removed variations
        $this->variations()
            ->whereNotIn('id', $existingIds)
            ->delete();
    }

    /**
     * Get total stock across all variations.
     */
    public function getTotalStock(): int
    {
        if (!$this->hasVariations()) {
            return $this->inventory ? $this->inventory->available_quantity : 0;
        }

        return $this->variations()
            ->join('inventory', 'product_variations.id', '=', 'inventory.product_variation_id')
            ->sum('inventory.available_quantity');
    }

    /**
     * Check if any variation is in stock.
     */
    public function hasAnyVariationInStock(): bool
    {
        if (!$this->hasVariations()) {
            return $this->isInStock();
        }

        return $this->variations()
            ->join('inventory', 'product_variations.id', '=', 'inventory.product_variation_id')
            ->where('inventory.available_quantity', '>', 0)
            ->exists();
    }
}