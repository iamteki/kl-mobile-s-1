<?php
// app/Services/AvailabilityService.php

namespace App\Services;

use App\Models\Product;
use App\Models\Inventory;
use App\Models\Booking;
use App\Models\BookingItem;
use Carbon\Carbon;
use DB;

class AvailabilityService
{
    public function checkAvailability(Product $product, Carbon $startDate, Carbon $endDate, $quantity, $variationId = null)
    {
        // Get inventory record
        $inventory = Inventory::where('product_id', $product->id)
            ->where('variation_id', $variationId)
            ->first();
            
        if (!$inventory) {
            return [
                'available' => false,
                'quantity' => 0,
                'message' => 'Product not found in inventory'
            ];
        }
        
        // Get total quantity
        $totalQuantity = $inventory->total_quantity;
        
        // Get booked quantity for the date range
        $bookedQuantity = $this->getBookedQuantity($product->id, $variationId, $startDate, $endDate);
        
        // Calculate available quantity
        $availableQuantity = $totalQuantity - $bookedQuantity - $inventory->maintenance_quantity - $inventory->damaged_quantity;
        
        return [
            'available' => $availableQuantity >= $quantity,
            'quantity' => $availableQuantity,
            'message' => $availableQuantity < $quantity ? 'Only ' . $availableQuantity . ' units available' : null
        ];
    }
    
    public function getBookedQuantity($productId, $variationId, Carbon $startDate, Carbon $endDate)
    {
        return BookingItem::where('product_id', $productId)
            ->where('variation_id', $variationId)
            ->whereHas('booking', function($query) use ($startDate, $endDate) {
                $query->where('booking_status', '!=', 'cancelled')
                    ->where(function($q) use ($startDate, $endDate) {
                        // Check for overlapping dates
                        $q->whereBetween('event_date', [$startDate, $endDate])
                          ->orWhereBetween('dismantle_date', [$startDate, $endDate])
                          ->orWhere(function($q2) use ($startDate, $endDate) {
                              $q2->where('event_date', '<=', $startDate)
                                 ->where('dismantle_date', '>=', $endDate);
                          });
                    });
            })
            ->sum('quantity');
    }
    
    public function getAvailabilityCalendar(Product $product, $month, $year, $variationId = null)
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $calendar = [];
        
        $inventory = Inventory::where('product_id', $product->id)
            ->where('variation_id', $variationId)
            ->first();
            
        if (!$inventory) {
            return $calendar;
        }
        
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();
            
            $bookedQuantity = $this->getBookedQuantity($product->id, $variationId, $dayStart, $dayEnd);
            $availableQuantity = $inventory->total_quantity - $bookedQuantity - $inventory->maintenance_quantity - $inventory->damaged_quantity;
            
            $calendar[$currentDate->format('Y-m-d')] = [
                'date' => $currentDate->format('Y-m-d'),
                'available' => $availableQuantity > 0,
                'quantity' => $availableQuantity
            ];
            
            $currentDate->addDay();
        }
        
        return $calendar;
    }
    
    public function reserveInventory(BookingItem $bookingItem)
    {
        $inventory = Inventory::where('product_id', $bookingItem->product_id)
            ->where('variation_id', $bookingItem->variation_id)
            ->lockForUpdate()
            ->first();
            
        if (!$inventory) {
            throw new \Exception('Inventory not found');
        }
        
        $inventory->reserved_quantity += $bookingItem->quantity;
        $inventory->available_quantity -= $bookingItem->quantity;
        $inventory->save();
        
        // Create inventory transaction
        $inventory->transactions()->create([
            'booking_id' => $bookingItem->booking_id,
            'type' => 'reservation',
            'quantity' => $bookingItem->quantity,
            'balance_before' => $inventory->available_quantity + $bookingItem->quantity,
            'balance_after' => $inventory->available_quantity,
            'performed_by' => auth()->id()
        ]);
    }
    
    public function releaseInventory(BookingItem $bookingItem)
    {
        $inventory = Inventory::where('product_id', $bookingItem->product_id)
            ->where('variation_id', $bookingItem->variation_id)
            ->lockForUpdate()
            ->first();
            
        if (!$inventory) {
            return;
        }
        
        $inventory->reserved_quantity -= $bookingItem->quantity;
        $inventory->available_quantity += $bookingItem->quantity;
        $inventory->save();
        
        // Create inventory transaction
        $inventory->transactions()->create([
            'booking_id' => $bookingItem->booking_id,
            'type' => 'release',
            'quantity' => $bookingItem->quantity,
            'balance_before' => $inventory->available_quantity - $bookingItem->quantity,
            'balance_after' => $inventory->available_quantity,
            'performed_by' => auth()->id()
        ]);
    }
}