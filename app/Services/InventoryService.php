<?php
// app/Services/InventoryService.php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\BookingItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Enums\InventoryTransactionType;
use DB;
use Exception;

class InventoryService
{
    /**
     * Create inventory record for a product
     */
    public function createInventory(Product $product, int $quantity, int $variationId = null)
    {
        return Inventory::create([
            'product_id' => $product->id,
            'variation_id' => $variationId,
            'total_quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'maintenance_quantity' => 0,
            'minimum_quantity' => $product->stock_alert_threshold ?? 5,
            'location' => 'Main Warehouse',
            'notes' => null
        ]);
    }
    
    /**
     * Update inventory quantity
     */
    public function updateQuantity(Inventory $inventory, int $newQuantity, string $reason = null)
    {
        DB::transaction(function () use ($inventory, $newQuantity, $reason) {
            $difference = $newQuantity - $inventory->total_quantity;
            $oldQuantity = $inventory->total_quantity;
            
            // Update inventory
            $inventory->update([
                'total_quantity' => $newQuantity,
                'available_quantity' => $inventory->available_quantity + $difference
            ]);
            
            // Create transaction record
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'type' => $difference > 0 ? InventoryTransactionType::PURCHASE : InventoryTransactionType::ADJUSTMENT,
                'quantity' => abs($difference),
                'balance_before' => $oldQuantity,
                'balance_after' => $newQuantity,
                'notes' => $reason,
                'performed_by' => auth()->id()
            ]);
            
            // Check if low stock
            $this->checkLowStock($inventory);
        });
    }
    
    /**
     * Reserve inventory for booking
     */
    public function reserveInventory(BookingItem $bookingItem)
    {
        DB::transaction(function () use ($bookingItem) {
            $inventory = Inventory::where('product_id', $bookingItem->product_id)
                ->where('variation_id', $bookingItem->variation_id)
                ->lockForUpdate()
                ->first();
                
            if (!$inventory) {
                throw new Exception('Inventory not found for product');
            }
            
            if ($inventory->available_quantity < $bookingItem->quantity) {
                throw new Exception('Insufficient inventory available');
            }
            
            // Update inventory
            $inventory->update([
                'available_quantity' => $inventory->available_quantity - $bookingItem->quantity,
                'reserved_quantity' => $inventory->reserved_quantity + $bookingItem->quantity
            ]);
            
            // Create transaction
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'booking_id' => $bookingItem->booking_id,
                'type' => InventoryTransactionType::RESERVATION,
                'quantity' => $bookingItem->quantity,
                'balance_before' => $inventory->available_quantity + $bookingItem->quantity,
                'balance_after' => $inventory->available_quantity,
                'notes' => "Reserved for booking #{$bookingItem->booking->booking_number}",
                'performed_by' => auth()->id()
            ]);
            
            // Check if low stock
            $this->checkLowStock($inventory);
        });
    }
    
    /**
     * Release inventory reservation
     */
    public function releaseInventory(BookingItem $bookingItem)
    {
        DB::transaction(function () use ($bookingItem) {
            $inventory = Inventory::where('product_id', $bookingItem->product_id)
                ->where('variation_id', $bookingItem->variation_id)
                ->lockForUpdate()
                ->first();
                
            if (!$inventory) {
                return;
            }
            
            // Update inventory
            $inventory->update([
                'available_quantity' => $inventory->available_quantity + $bookingItem->quantity,
                'reserved_quantity' => max(0, $inventory->reserved_quantity - $bookingItem->quantity)
            ]);
            
            // Create transaction
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'booking_id' => $bookingItem->booking_id,
                'type' => InventoryTransactionType::RELEASE,
                'quantity' => $bookingItem->quantity,
                'balance_before' => $inventory->available_quantity - $bookingItem->quantity,
                'balance_after' => $inventory->available_quantity,
                'notes' => "Released from booking #{$bookingItem->booking->booking_number}",
                'performed_by' => auth()->id()
            ]);
        });
    }
    
    /**
     * Mark items as delivered
     */
    public function markAsDelivered(BookingItem $bookingItem)
    {
        DB::transaction(function () use ($bookingItem) {
            $inventory = Inventory::where('product_id', $bookingItem->product_id)
                ->where('variation_id', $bookingItem->variation_id)
                ->lockForUpdate()
                ->first();
                
            if (!$inventory) {
                return;
            }
            
            // Move from reserved to delivered (out of inventory)
            $inventory->update([
                'reserved_quantity' => max(0, $inventory->reserved_quantity - $bookingItem->quantity)
            ]);
            
            // Create transaction
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'booking_id' => $bookingItem->booking_id,
                'type' => InventoryTransactionType::DELIVERY,
                'quantity' => $bookingItem->quantity,
                'balance_before' => $inventory->available_quantity,
                'balance_after' => $inventory->available_quantity,
                'notes' => "Delivered for booking #{$bookingItem->booking->booking_number}",
                'performed_by' => auth()->id()
            ]);
        });
    }
    
    /**
     * Mark items as returned
     */
    public function markAsReturned(BookingItem $bookingItem, int $returnedQuantity = null, int $damagedQuantity = 0)
    {
        DB::transaction(function () use ($bookingItem, $returnedQuantity, $damagedQuantity) {
            $inventory = Inventory::where('product_id', $bookingItem->product_id)
                ->where('variation_id', $bookingItem->variation_id)
                ->lockForUpdate()
                ->first();
                
            if (!$inventory) {
                return;
            }
            
            $returnedQuantity = $returnedQuantity ?? $bookingItem->quantity;
            $goodQuantity = $returnedQuantity - $damagedQuantity;
            
            // Update inventory
            $inventory->update([
                'available_quantity' => $inventory->available_quantity + $goodQuantity,
                'damaged_quantity' => $inventory->damaged_quantity + $damagedQuantity
            ]);
            
            // Create transaction for return
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'booking_id' => $bookingItem->booking_id,
                'type' => InventoryTransactionType::RETURN,
                'quantity' => $returnedQuantity,
                'balance_before' => $inventory->available_quantity - $goodQuantity,
                'balance_after' => $inventory->available_quantity,
                'notes' => "Returned from booking #{$bookingItem->booking->booking_number}" . 
                          ($damagedQuantity > 0 ? " ({$damagedQuantity} damaged)" : ""),
                'performed_by' => auth()->id()
            ]);
            
            // Create transaction for damage if any
            if ($damagedQuantity > 0) {
                $this->createTransaction([
                    'inventory_id' => $inventory->id,
                    'booking_id' => $bookingItem->booking_id,
                    'type' => InventoryTransactionType::DAMAGE,
                    'quantity' => $damagedQuantity,
                    'balance_before' => $inventory->damaged_quantity - $damagedQuantity,
                    'balance_after' => $inventory->damaged_quantity,
                    'notes' => "Damaged items from booking #{$bookingItem->booking->booking_number}",
                    'performed_by' => auth()->id()
                ]);
            }
        });
    }
    
    /**
     * Move items to maintenance
     */
    public function moveToMaintenance(Inventory $inventory, int $quantity, string $reason = null)
    {
        DB::transaction(function () use ($inventory, $quantity, $reason) {
            if ($inventory->available_quantity < $quantity) {
                throw new Exception('Insufficient available quantity');
            }
            
            $inventory->update([
                'available_quantity' => $inventory->available_quantity - $quantity,
                'maintenance_quantity' => $inventory->maintenance_quantity + $quantity
            ]);
            
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'type' => InventoryTransactionType::MAINTENANCE,
                'quantity' => $quantity,
                'balance_before' => $inventory->available_quantity + $quantity,
                'balance_after' => $inventory->available_quantity,
                'notes' => $reason ?? 'Moved to maintenance',
                'performed_by' => auth()->id()
            ]);
        });
    }
    
    /**
     * Return items from maintenance
     */
    public function returnFromMaintenance(Inventory $inventory, int $quantity)
    {
        DB::transaction(function () use ($inventory, $quantity) {
            if ($inventory->maintenance_quantity < $quantity) {
                throw new Exception('Insufficient maintenance quantity');
            }
            
            $inventory->update([
                'available_quantity' => $inventory->available_quantity + $quantity,
                'maintenance_quantity' => $inventory->maintenance_quantity - $quantity
            ]);
            
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'type' => InventoryTransactionType::MAINTENANCE_RETURN,
                'quantity' => $quantity,
                'balance_before' => $inventory->available_quantity - $quantity,
                'balance_after' => $inventory->available_quantity,
                'notes' => 'Returned from maintenance',
                'performed_by' => auth()->id()
            ]);
        });
    }
    
    /**
     * Write off damaged items
     */
    public function writeOffDamaged(Inventory $inventory, int $quantity, string $reason)
    {
        DB::transaction(function () use ($inventory, $quantity, $reason) {
            if ($inventory->damaged_quantity < $quantity) {
                throw new Exception('Insufficient damaged quantity');
            }
            
            $inventory->update([
                'damaged_quantity' => $inventory->damaged_quantity - $quantity,
                'total_quantity' => $inventory->total_quantity - $quantity
            ]);
            
            $this->createTransaction([
                'inventory_id' => $inventory->id,
                'type' => InventoryTransactionType::WRITE_OFF,
                'quantity' => $quantity,
                'balance_before' => $inventory->total_quantity + $quantity,
                'balance_after' => $inventory->total_quantity,
                'notes' => $reason,
                'performed_by' => auth()->id()
            ]);
        });
    }
    
    /**
     * Create inventory transaction
     */
    protected function createTransaction(array $data)
    {
        return InventoryTransaction::create($data);
    }
    
    /**
     * Check for low stock and notify
     */
    protected function checkLowStock(Inventory $inventory)
    {
        if ($inventory->available_quantity <= $inventory->minimum_quantity) {
            // Trigger low stock notification
            event(new \App\Events\LowStockAlert($inventory));
        }
    }
    
    /**
     * Get inventory status
     */
    public function getInventoryStatus(Product $product, int $variationId = null)
    {
        $inventory = Inventory::where('product_id', $product->id)
            ->where('variation_id', $variationId)
            ->first();
            
        if (!$inventory) {
            return [
                'status' => 'out_of_stock',
                'available' => 0,
                'reserved' => 0,
                'total' => 0
            ];
        }
        
        $status = 'in_stock';
        if ($inventory->available_quantity == 0) {
            $status = 'out_of_stock';
        } elseif ($inventory->available_quantity <= $inventory->minimum_quantity) {
            $status = 'low_stock';
        }
        
        return [
            'status' => $status,
            'available' => $inventory->available_quantity,
            'reserved' => $inventory->reserved_quantity,
            'maintenance' => $inventory->maintenance_quantity,
            'damaged' => $inventory->damaged_quantity,
            'total' => $inventory->total_quantity
        ];
    }
    
    /**
     * Get inventory report
     */
    public function getInventoryReport($filters = [])
    {
        $query = Inventory::with(['product', 'variation']);
        
        if (!empty($filters['category_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }
        
        if (!empty($filters['low_stock_only'])) {
            $query->whereRaw('available_quantity <= minimum_quantity');
        }
        
        if (!empty($filters['out_of_stock_only'])) {
            $query->where('available_quantity', 0);
        }
        
        $inventories = $query->get();
        
        return [
            'total_products' => $inventories->count(),
            'total_value' => $inventories->sum(function ($inv) {
                return $inv->total_quantity * ($inv->product->base_price ?? 0);
            }),
            'low_stock_items' => $inventories->filter(function ($inv) {
                return $inv->available_quantity <= $inv->minimum_quantity;
            })->count(),
            'out_of_stock_items' => $inventories->where('available_quantity', 0)->count(),
            'items' => $inventories
        ];
    }
    
    /**
     * Perform inventory audit
     */
    public function performAudit(Inventory $inventory, int $actualQuantity, string $notes = null)
    {
        $difference = $actualQuantity - $inventory->total_quantity;
        
        if ($difference != 0) {
            DB::transaction(function () use ($inventory, $actualQuantity, $difference, $notes) {
                $oldTotal = $inventory->total_quantity;
                
                // Update inventory
                $inventory->update([
                    'total_quantity' => $actualQuantity,
                    'available_quantity' => $inventory->available_quantity + $difference
                ]);
                
                // Create audit transaction
                $this->createTransaction([
                    'inventory_id' => $inventory->id,
                    'type' => InventoryTransactionType::AUDIT,
                    'quantity' => abs($difference),
                    'balance_before' => $oldTotal,
                    'balance_after' => $actualQuantity,
                    'notes' => "Audit adjustment: " . ($difference > 0 ? '+' : '') . $difference . ". " . $notes,
                    'performed_by' => auth()->id()
                ]);
            });
        }
        
        return $difference;
    }
}