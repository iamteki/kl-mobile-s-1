<?php
// app/Services/CartService.php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Package;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CartService
{
    protected $cart;
    
    public function __construct()
    {
        $this->cart = $this->getCurrentCart();
    }
    
    public function getCart()
    {
        return $this->cart->load(['items.product', 'items.variation', 'items.package']);
    }
    
    public function addProduct(Product $product, $quantity, $variationId = null, $startDate = null, $endDate = null)
    {
        $unitPrice = $this->calculateUnitPrice($product, $variationId, $startDate, $endDate);
        
        $cartItem = $this->cart->items()->updateOrCreate(
            [
                'product_id' => $product->id,
                'variation_id' => $variationId,
            ],
            [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
                'notes' => json_encode([
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ])
            ]
        );
        
        $this->updateCartDates($startDate, $endDate);
        
        return $cartItem;
    }
    
    public function addPackage(Package $package, $quantity, $startDate = null, $endDate = null)
    {
        $cartItem = $this->cart->items()->updateOrCreate(
            [
                'package_id' => $package->id,
            ],
            [
                'quantity' => $quantity,
                'unit_price' => $package->price,
                'total_price' => $package->price * $quantity,
                'notes' => json_encode([
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ])
            ]
        );
        
        $this->updateCartDates($startDate, $endDate);
        
        return $cartItem;
    }
    
    public function updateQuantity($itemId, $quantity)
    {
        $item = $this->cart->items()->findOrFail($itemId);
        
        $item->update([
            'quantity' => $quantity,
            'total_price' => $item->unit_price * $quantity
        ]);
        
        return $item;
    }
    
    public function removeItem($itemId)
    {
        return $this->cart->items()->where('id', $itemId)->delete();
    }
    
    public function clearCart()
    {
        $this->cart->items()->delete();
        $this->cart->update([
            'event_date' => null,
            'event_type' => null,
            'venue' => null,
            'number_of_pax' => null
        ]);
    }
    
    protected function getCurrentCart()
    {
        if (auth()->check()) {
            $customerId = auth()->user()->customer?->id;
            
            return Cart::firstOrCreate(
                ['customer_id' => $customerId],
                ['expires_at' => Carbon::now()->addDays(7)]
            );
        } else {
            $sessionId = session()->getId();
            
            return Cart::firstOrCreate(
                ['session_id' => $sessionId],
                ['expires_at' => Carbon::now()->addDays(7)]
            );
        }
    }
    
    protected function calculateUnitPrice($product, $variationId, $startDate, $endDate)
    {
        $basePrice = $product->base_price;
        
        // Apply variation pricing
        if ($variationId) {
            $variation = $product->variations()->find($variationId);
            if ($variation) {
                if ($variation->price) {
                    $basePrice = $variation->price;
                } elseif ($variation->price_modifier) {
                    if ($variation->price_modifier_type == 'percentage') {
                        $basePrice += ($basePrice * $variation->price_modifier / 100);
                    } else {
                        $basePrice += $variation->price_modifier;
                    }
                }
            }
        }
        
        // Apply multi-day pricing if applicable
        if ($startDate && $endDate) {
            $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            
            if ($days >= 6 && $product->price_per_month) {
                $basePrice = $product->price_per_month / 30;
            } elseif ($days >= 3 && $product->price_per_week) {
                $basePrice = $product->price_per_week / 7;
            }
        }
        
        return $basePrice;
    }
    
    protected function updateCartDates($startDate, $endDate)
    {
        if ($startDate && !$this->cart->event_date) {
            $this->cart->update(['event_date' => $startDate]);
        }
    }
}