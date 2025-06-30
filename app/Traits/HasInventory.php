<?php

namespace App\Traits;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasInventory
{
    /**
     * Boot the trait.
     */
    public static function bootHasInventory()
    {
        static::created(function ($model) {
            // Create inventory record when product is created
            $model->inventory()->create([
                'total_quantity' => 0,
                'available_quantity' => 0,
                'reserved_quantity' => 0,
                'maintenance_quantity' => 0,
            ]);
        });
    }

    /**
     * Get the inventory record.
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'product_id');
    }

    /**
     * Get all inventory records (for products with variations).
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }

    /**
     * Get inventory transactions.
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'product_id');
    }

    /**
     * Get available quantity.
     */
    public function getAvailableQuantity($variationId = null): int
    {
        if ($variationId) {
            $inventory = $this->inventories()
                ->where('product_variation_id', $variationId)
                ->first();
        } else {
            $inventory = $this->inventory;
        }

        return $inventory ? $inventory->available_quantity : 0;
    }

    /**
     * Get total quantity.
     */
    public function getTotalQuantity($variationId = null): int
    {
        if ($variationId) {
            $inventory = $this->inventories()
                ->where('product_variation_id', $variationId)
                ->first();
        } else {
            $inventory = $this->inventory;
        }

        return $inventory ? $inventory->total_quantity : 0;
    }

    /**
     * Check if item is in stock.
     */
    public function isInStock($quantity = 1, $variationId = null): bool
    {
        return $this->getAvailableQuantity($variationId) >= $quantity;
    }

    /**
     * Check if item is low in stock.
     */
    public function isLowStock($variationId = null): bool
    {
        $available = $this->getAvailableQuantity($variationId);
        $threshold = $this->stock_alert_threshold ?? 5;
        
        return $available <= $threshold && $available > 0;
    }

    /**
     * Check if item is out of stock.
     */
    public function isOutOfStock($variationId = null): bool
    {
        return $this->getAvailableQuantity($variationId) === 0;
    }

    /**
     * Update inventory quantity.
     */
    public function updateInventory(int $quantity, string $type = 'adjustment', array $data = []): InventoryTransaction
    {
        $inventory = $this->inventory;
        
        if (!$inventory) {
            $inventory = $this->inventory()->create([
                'total_quantity' => 0,
                'available_quantity' => 0,
                'reserved_quantity' => 0,
                'maintenance_quantity' => 0,
            ]);
        }

        $balanceBefore = $inventory->available_quantity;
        
        // Update quantities based on type
        switch ($type) {
            case 'booking':
                $inventory->available_quantity -= $quantity;
                $inventory->reserved_quantity += $quantity;
                break;
                
            case 'return':
                $inventory->available_quantity += $quantity;
                $inventory->reserved_quantity -= $quantity;
                break;
                
            case 'maintenance':
                $inventory->available_quantity -= $quantity;
                $inventory->maintenance_quantity += $quantity;
                break;
                
            case 'maintenance_return':
                $inventory->available_quantity += $quantity;
                $inventory->maintenance_quantity -= $quantity;
                break;
                
            case 'damage':
                $inventory->total_quantity -= $quantity;
                if ($inventory->available_quantity >= $quantity) {
                    $inventory->available_quantity -= $quantity;
                } else {
                    $inventory->available_quantity = 0;
                }
                break;
                
            case 'adjustment':
                $inventory->total_quantity = $quantity;
                $inventory->available_quantity = $quantity - $inventory->reserved_quantity - $inventory->maintenance_quantity;
                break;
        }
        
        $inventory->save();
        
        // Create transaction record
        return $inventory->transactions()->create([
            'booking_id' => $data['booking_id'] ?? null,
            'user_id' => $data['user_id'] ?? auth()->id(),
            'type' => $type,
            'quantity' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $inventory->available_quantity,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Reserve inventory for booking.
     */
    public function reserveInventory(int $quantity, $bookingId, $variationId = null): bool
    {
        if (!$this->isInStock($quantity, $variationId)) {
            return false;
        }

        $this->updateInventory($quantity, 'booking', [
            'booking_id' => $bookingId,
            'reason' => 'Reserved for booking #' . $bookingId,
        ]);

        return true;
    }

    /**
     * Release inventory from booking.
     */
    public function releaseInventory(int $quantity, $bookingId, $variationId = null): bool
    {
        $this->updateInventory($quantity, 'return', [
            'booking_id' => $bookingId,
            'reason' => 'Released from booking #' . $bookingId,
        ]);

        return true;
    }

    /**
     * Get inventory status.
     */
    public function getInventoryStatus($variationId = null): string
    {
        if ($this->isOutOfStock($variationId)) {
            return 'out_of_stock';
        } elseif ($this->isLowStock($variationId)) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Get inventory status badge.
     */
    public function getInventoryStatusBadge($variationId = null): string
    {
        $status = $this->getInventoryStatus($variationId);
        
        return match($status) {
            'out_of_stock' => '<span class="badge bg-danger">Out of Stock</span>',
            'low_stock' => '<span class="badge bg-warning">Low Stock</span>',
            'in_stock' => '<span class="badge bg-success">In Stock</span>',
        };
    }

    /**
     * Get availability for date range.
     */
    public function getAvailabilityForDateRange(Carbon $startDate, Carbon $endDate, $variationId = null): int
    {
        $bookings = $this->bookingItems()
            ->whereHas('booking', function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('event_date', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->where('event_date', '<=', $startDate)
                             ->whereRaw('DATE_ADD(event_date, INTERVAL rental_days DAY) >= ?', [$startDate]);
                      });
                })
                ->whereIn('status', ['confirmed', 'processing', 'delivered']);
            });

        if ($variationId) {
            $bookings->where('product_variation_id', $variationId);
        }

        $bookedQuantity = $bookings->sum('quantity');
        $totalQuantity = $this->getTotalQuantity($variationId);
        
        return max(0, $totalQuantity - $bookedQuantity);
    }
}