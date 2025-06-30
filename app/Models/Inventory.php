<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'product_variation_id',
        'total_quantity',
        'available_quantity',
        'reserved_quantity',
        'maintenance_quantity',
        'location',
        'notes',
        'last_checked_at',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'available_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'maintenance_quantity' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($inventory) {
            // Ensure quantities are not negative
            $inventory->total_quantity = max(0, $inventory->total_quantity);
            $inventory->available_quantity = max(0, $inventory->available_quantity);
            $inventory->reserved_quantity = max(0, $inventory->reserved_quantity);
            $inventory->maintenance_quantity = max(0, $inventory->maintenance_quantity);
        });
    }

    /**
     * Get the product this inventory belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variation this inventory belongs to.
     */
    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    /**
     * Get all transactions for this inventory.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the display name for this inventory item.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->product->name;
        
        if ($this->productVariation) {
            $name .= ' - ' . $this->productVariation->name;
        }
        
        return $name;
    }

    /**
     * Get the SKU for this inventory item.
     */
    public function getSkuAttribute(): string
    {
        if ($this->productVariation) {
            return $this->productVariation->sku;
        }
        
        return $this->product->sku;
    }

    /**
     * Check if inventory is low.
     */
    public function isLowStock(): bool
    {
        $threshold = $this->product->stock_alert_threshold ?? 5;
        return $this->available_quantity <= $threshold && $this->available_quantity > 0;
    }

    /**
     * Check if inventory is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->available_quantity === 0;
    }

    /**
     * Get the inventory status.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        } elseif ($this->isLowStock()) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Get the inventory status badge HTML.
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'out_of_stock' => '<span class="badge bg-danger">Out of Stock</span>',
            'low_stock' => '<span class="badge bg-warning">Low Stock</span>',
            'in_stock' => '<span class="badge bg-success">In Stock</span>',
        };
    }

    /**
     * Adjust inventory quantity.
     */
    public function adjustQuantity(int $adjustment, string $reason = null): InventoryTransaction
    {
        $balanceBefore = $this->available_quantity;
        
        $this->total_quantity += $adjustment;
        $this->available_quantity += $adjustment;
        $this->save();
        
        return $this->transactions()->create([
            'type' => 'adjustment',
            'quantity' => abs($adjustment),
            'balance_before' => $balanceBefore,
            'balance_after' => $this->available_quantity,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Move items to maintenance.
     */
    public function moveToMaintenance(int $quantity, string $reason = null): ?InventoryTransaction
    {
        if ($this->available_quantity < $quantity) {
            return null;
        }
        
        $balanceBefore = $this->available_quantity;
        
        $this->available_quantity -= $quantity;
        $this->maintenance_quantity += $quantity;
        $this->save();
        
        return $this->transactions()->create([
            'type' => 'maintenance',
            'quantity' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->available_quantity,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Return items from maintenance.
     */
    public function returnFromMaintenance(int $quantity, string $reason = null): ?InventoryTransaction
    {
        if ($this->maintenance_quantity < $quantity) {
            return null;
        }
        
        $balanceBefore = $this->available_quantity;
        
        $this->available_quantity += $quantity;
        $this->maintenance_quantity -= $quantity;
        $this->save();
        
        return $this->transactions()->create([
            'type' => 'maintenance_return',
            'quantity' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->available_quantity,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Record damaged items.
     */
    public function recordDamage(int $quantity, string $reason = null): ?InventoryTransaction
    {
        if ($this->total_quantity < $quantity) {
            return null;
        }
        
        $balanceBefore = $this->available_quantity;
        
        $this->total_quantity -= $quantity;
        
        // Deduct from available first, then reserved, then maintenance
        if ($this->available_quantity >= $quantity) {
            $this->available_quantity -= $quantity;
        } else {
            $fromAvailable = $this->available_quantity;
            $remaining = $quantity - $fromAvailable;
            $this->available_quantity = 0;
            
            if ($this->reserved_quantity >= $remaining) {
                $this->reserved_quantity -= $remaining;
            } else {
                $fromReserved = $this->reserved_quantity;
                $remaining -= $fromReserved;
                $this->reserved_quantity = 0;
                
                $this->maintenance_quantity = max(0, $this->maintenance_quantity - $remaining);
            }
        }
        
        $this->save();
        
        return $this->transactions()->create([
            'type' => 'damage',
            'quantity' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->available_quantity,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Get inventory health percentage.
     */
    public function getHealthPercentageAttribute(): float
    {
        if ($this->total_quantity === 0) {
            return 0;
        }
        
        $healthy = $this->available_quantity + $this->reserved_quantity;
        return round(($healthy / $this->total_quantity) * 100, 2);
    }

    /**
     * Get utilization percentage.
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->total_quantity === 0) {
            return 0;
        }
        
        return round(($this->reserved_quantity / $this->total_quantity) * 100, 2);
    }

    /**
     * Perform inventory check.
     */
    public function performCheck(int $actualQuantity, string $notes = null): void
    {
        if ($actualQuantity !== $this->total_quantity) {
            $adjustment = $actualQuantity - $this->total_quantity;
            $this->adjustQuantity($adjustment, 'Inventory check - ' . ($notes ?: 'Periodic check'));
        }
        
        $this->last_checked_at = now();
        $this->save();
    }
}