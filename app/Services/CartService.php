<?php
// app/Services/CartService.php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Package;
use App\Models\ProductVariation;
use App\Services\PricingService;
use App\Services\AvailabilityService;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class CartService
{
    protected $cart;
    protected $pricingService;
    protected $availabilityService;
    
    public function __construct(PricingService $pricingService, AvailabilityService $availabilityService)
    {
        $this->pricingService = $pricingService;
        $this->availabilityService = $availabilityService;
        $this->cart = $this->getCurrentCart();
    }
    
    /**
     * Get the current cart
     */
    public function getCart()
    {
        return $this->cart->load([
            'items.product.category',
            'items.product.media',
            'items.variation',
            'items.package.items.product'
        ]);
    }
    
    /**
     * Get or create current cart
     */
    protected function getCurrentCart()
    {
        if (auth()->check()) {
            // For authenticated users
            $cart = Cart::where('user_id', auth()->id())
                ->where('status', 'active')
                ->first();
                
            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => auth()->id(),
                    'session_id' => session()->getId(),
                    'status' => 'active'
                ]);
            }
        } else {
            // For guests
            $sessionId = session()->getId();
            $cart = Cart::where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();
                
            if (!$cart) {
                $cart = Cart::create([
                    'session_id' => $sessionId,
                    'status' => 'active'
                ]);
            }
        }
        
        return $cart;
    }
    
    /**
     * Add product to cart
     */
    public function addProduct(Product $product, int $quantity, int $variationId = null, Carbon $startDate = null, Carbon $endDate = null)
    {
        // Validate product is active
        if ($product->status !== 'active') {
            throw new Exception('Product is not available');
        }
        
        // Validate quantity
        if ($quantity < $product->min_rental_quantity) {
            throw new Exception("Minimum rental quantity is {$product->min_rental_quantity}");
        }
        
        if ($product->max_rental_quantity && $quantity > $product->max_rental_quantity) {
            throw new Exception("Maximum rental quantity is {$product->max_rental_quantity}");
        }
        
        // Check availability if dates provided
        if ($startDate && $endDate) {
            $availability = $this->availabilityService->checkAvailability(
                $product,
                $startDate,
                $endDate,
                $quantity,
                $variationId
            );
            
            if (!$availability['available']) {
                throw new Exception($availability['message'] ?? 'Product not available for selected dates');
            }
        }
        
        // Calculate rental days and price
        $rentalDays = $startDate && $endDate ? $startDate->diffInDays($endDate) + 1 : 1;
        $unitPrice = $this->pricingService->getProductPrice($product, $variationId, $rentalDays);
        
        // Check if item already exists in cart
        $existingItem = $this->cart->items()
            ->where('product_id', $product->id)
            ->where('variation_id', $variationId)
            ->first();
            
        if ($existingItem) {
            // Update existing item
            $newQuantity = $existingItem->quantity + $quantity;
            
            // Recheck availability for new quantity
            if ($startDate && $endDate) {
                $availability = $this->availabilityService->checkAvailability(
                    $product,
                    $startDate,
                    $endDate,
                    $newQuantity,
                    $variationId
                );
                
                if (!$availability['available']) {
                    throw new Exception($availability['message'] ?? 'Insufficient quantity available');
                }
            }
            
            $existingItem->update([
                'quantity' => $newQuantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $newQuantity,
                'notes' => json_encode([
                    'start_date' => $startDate?->format('Y-m-d'),
                    'end_date' => $endDate?->format('Y-m-d'),
                    'rental_days' => $rentalDays
                ])
            ]);
            
            $cartItem = $existingItem;
        } else {
            // Create new cart item
            $cartItem = $this->cart->items()->create([
                'product_id' => $product->id,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
                'notes' => json_encode([
                    'start_date' => $startDate?->format('Y-m-d'),
                    'end_date' => $endDate?->format('Y-m-d'),
                    'rental_days' => $rentalDays
                ])
            ]);
        }
        
        $this->updateCartDates($startDate, $endDate);
        $this->updateCartTotals();
        
        return $cartItem;
    }
    
    /**
     * Add package to cart
     */
    public function addPackage(Package $package, int $quantity, Carbon $startDate = null, Carbon $endDate = null)
    {
        // Validate package is active
        if ($package->status !== 'active') {
            throw new Exception('Package is not available');
        }
        
        // Check availability for all package items
        if ($startDate && $endDate) {
            foreach ($package->items as $item) {
                $availability = $this->availabilityService->checkAvailability(
                    $item->product,
                    $startDate,
                    $endDate,
                    $item->quantity * $quantity,
                    $item->variation_id
                );
                
                if (!$availability['available']) {
                    throw new Exception("Package item '{$item->product->name}' is not available for selected dates");
                }
            }
        }
        
        // Calculate rental days and price
        $rentalDays = $startDate && $endDate ? $startDate->diffInDays($endDate) + 1 : 1;
        $packagePrice = $this->pricingService->getPackagePrice($package, $rentalDays);
        
        // Create cart item for package
        $cartItem = $this->cart->items()->create([
            'package_id' => $package->id,
            'quantity' => $quantity,
            'unit_price' => $packagePrice,
            'total_price' => $packagePrice * $quantity,
            'notes' => json_encode([
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
                'rental_days' => $rentalDays
            ])
        ]);
        
        $this->updateCartDates($startDate, $endDate);
        $this->updateCartTotals();
        
        return $cartItem;
    }
    
    /**
     * Update cart item quantity
     */
    public function updateItemQuantity(int $itemId, int $quantity)
    {
        $item = $this->cart->items()->findOrFail($itemId);
        
        if ($quantity <= 0) {
            return $this->removeItem($itemId);
        }
        
        // Check if product or package
        if ($item->product_id) {
            $product = $item->product;
            
            // Validate quantity
            if ($quantity < $product->min_rental_quantity) {
                throw new Exception("Minimum rental quantity is {$product->min_rental_quantity}");
            }
            
            if ($product->max_rental_quantity && $quantity > $product->max_rental_quantity) {
                throw new Exception("Maximum rental quantity is {$product->max_rental_quantity}");
            }
            
            // Check availability if dates are set
            $notes = json_decode($item->notes, true);
            if (!empty($notes['start_date']) && !empty($notes['end_date'])) {
                $startDate = Carbon::parse($notes['start_date']);
                $endDate = Carbon::parse($notes['end_date']);
                
                $availability = $this->availabilityService->checkAvailability(
                    $product,
                    $startDate,
                    $endDate,
                    $quantity,
                    $item->variation_id
                );
                
                if (!$availability['available']) {
                    throw new Exception($availability['message'] ?? 'Insufficient quantity available');
                }
            }
        }
        
        // Update item
        $item->update([
            'quantity' => $quantity,
            'total_price' => $item->unit_price * $quantity
        ]);
        
        $this->updateCartTotals();
        
        return $item;
    }
    
    /**
     * Remove item from cart
     */
    public function removeItem(int $itemId)
    {
        $item = $this->cart->items()->findOrFail($itemId);
        $item->delete();
        
        $this->updateCartTotals();
        
        return true;
    }
    
    /**
     * Clear cart
     */
    public function clearCart()
    {
        $this->cart->items()->delete();
        $this->cart->update([
            'event_date' => null,
            'return_date' => null,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0
        ]);
        
        return true;
    }
    
    /**
     * Update cart dates
     */
    protected function updateCartDates($startDate, $endDate)
    {
        if ($startDate && $endDate) {
            $currentEventDate = $this->cart->event_date ? Carbon::parse($this->cart->event_date) : null;
            $currentReturnDate = $this->cart->return_date ? Carbon::parse($this->cart->return_date) : null;
            
            // Update to earliest start date and latest end date
            if (!$currentEventDate || $startDate->lt($currentEventDate)) {
                $this->cart->event_date = $startDate;
            }
            
            if (!$currentReturnDate || $endDate->gt($currentReturnDate)) {
                $this->cart->return_date = $endDate;
            }
            
            $this->cart->save();
        }
    }
    
    /**
     * Update cart totals
     */
    protected function updateCartTotals()
    {
        $subtotal = $this->cart->items()->sum('total_price');
        
        // Calculate tax
        $taxRate = config('settings.tax_rate', 6) / 100;
        $taxAmount = $subtotal * $taxRate;
        
        // Apply any discounts
        $discountAmount = $this->calculateDiscounts($subtotal);
        
        // Calculate total
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        
        $this->cart->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount
        ]);
    }
    
    /**
     * Calculate discounts
     */
    protected function calculateDiscounts($subtotal)
    {
        $discountAmount = 0;
        
        // Apply coupon if exists
        if ($this->cart->coupon_code) {
            // Implement coupon logic here
        }
        
        // Apply bulk discount
        if ($subtotal > 5000) {
            $discountAmount += $subtotal * 0.05; // 5% discount for orders over 5000
        } elseif ($subtotal > 3000) {
            $discountAmount += $subtotal * 0.03; // 3% discount for orders over 3000
        }
        
        return $discountAmount;
    }
    
    /**
     * Apply coupon code
     */
    public function applyCoupon(string $code)
    {
        // Validate coupon code
        // This would check against a coupons table
        
        $this->cart->update(['coupon_code' => $code]);
        $this->updateCartTotals();
        
        return true;
    }
    
    /**
     * Remove coupon
     */
    public function removeCoupon()
    {
        $this->cart->update(['coupon_code' => null]);
        $this->updateCartTotals();
        
        return true;
    }
    
    /**
     * Merge guest cart with user cart
     */
    public function mergeCarts(string $sessionId, int $userId)
    {
        $guestCart = Cart::where('session_id', $sessionId)
            ->where('status', 'active')
            ->whereNull('user_id')
            ->first();
            
        if (!$guestCart) {
            return;
        }
        
        $userCart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();
            
        if (!$userCart) {
            // Just assign the guest cart to the user
            $guestCart->update(['user_id' => $userId]);
            return;
        }
        
        // Merge items from guest cart to user cart
        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('variation_id', $guestItem->variation_id)
                ->where('package_id', $guestItem->package_id)
                ->first();
                
            if ($existingItem) {
                // Update quantity
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $guestItem->quantity,
                    'total_price' => $existingItem->unit_price * ($existingItem->quantity + $guestItem->quantity)
                ]);
            } else {
                // Move item to user cart
                $guestItem->update(['cart_id' => $userCart->id]);
            }
        }
        
        // Delete guest cart
        $guestCart->delete();
        
        // Update user cart totals
        $this->cart = $userCart;
        $this->updateCartTotals();
    }
    
    /**
     * Get cart summary
     */
    public function getCartSummary()
    {
        $cart = $this->getCart();
        
        return [
            'item_count' => $cart->items->sum('quantity'),
            'unique_items' => $cart->items->count(),
            'subtotal' => $cart->subtotal,
            'tax_amount' => $cart->tax_amount,
            'discount_amount' => $cart->discount_amount,
            'total_amount' => $cart->total_amount,
            'event_date' => $cart->event_date,
            'return_date' => $cart->return_date,
            'has_coupon' => !empty($cart->coupon_code)
        ];
    }
    
    /**
     * Validate cart for checkout
     */
    public function validateForCheckout()
    {
        $cart = $this->getCart();
        $errors = [];
        
        if ($cart->items->count() == 0) {
            $errors[] = 'Cart is empty';
        }
        
        if (!$cart->event_date || !$cart->return_date) {
            $errors[] = 'Event dates are required';
        }
        
        // Check availability for all items
        foreach ($cart->items as $item) {
            if ($item->product_id) {
                $notes = json_decode($item->notes, true);
                if (!empty($notes['start_date']) && !empty($notes['end_date'])) {
                    $availability = $this->availabilityService->checkAvailability(
                        $item->product,
                        Carbon::parse($notes['start_date']),
                        Carbon::parse($notes['end_date']),
                        $item->quantity,
                        $item->variation_id
                    );
                    
                    if (!$availability['available']) {
                        $errors[] = "Product '{$item->product->name}' is no longer available for selected dates";
                    }
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}