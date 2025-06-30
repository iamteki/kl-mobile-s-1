<?php
// app/Http/Controllers/Frontend/CartController.php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Package;
use App\Services\CartService;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CartController extends Controller
{
    protected $cartService;
    protected $availabilityService;
    
    public function __construct(CartService $cartService, AvailabilityService $availabilityService)
    {
        $this->cartService = $cartService;
        $this->availabilityService = $availabilityService;
    }
    
    public function index()
    {
        $cart = $this->cartService->getCart();
        
        return view('frontend.cart.index', compact('cart'));
    }
    
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required_without:package_id|exists:products,id',
            'package_id' => 'required_without:product_id|exists:packages,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'quantity' => 'required|integer|min:1',
            'start_date' => 'nullable|date|after:today',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);
        
        try {
            if ($request->product_id) {
                $product = Product::findOrFail($request->product_id);
                
                // Check availability if dates provided
                if ($request->start_date && $request->end_date) {
                    $availability = $this->availabilityService->checkAvailability(
                        $product,
                        Carbon::parse($request->start_date),
                        Carbon::parse($request->end_date),
                        $request->quantity,
                        $request->variation_id
                    );
                    
                    if (!$availability['available']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Product not available for selected dates'
                        ], 400);
                    }
                }
                
                $cartItem = $this->cartService->addProduct(
                    $product,
                    $request->quantity,
                    $request->variation_id,
                    $request->start_date,
                    $request->end_date
                );
            } else {
                $package = Package::findOrFail($request->package_id);
                
                $cartItem = $this->cartService->addPackage(
                    $package,
                    $request->quantity,
                    $request->start_date,
                    $request->end_date
                );
            }
            
            $cart = $this->cartService->getCart();
            
            return response()->json([
                'success' => true,
                'message' => 'Item added to cart',
                'cartCount' => $cart->items->sum('quantity'),
                'cartTotal' => $cart->items->sum('total_price')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);
        
        try {
            $this->cartService->updateQuantity($itemId, $request->quantity);
            
            $cart = $this->cartService->getCart();
            
            return response()->json([
                'success' => true,
                'cartCount' => $cart->items->sum('quantity'),
                'subtotal' => $cart->items->sum('total_price'),
                'tax' => $cart->items->sum('total_price') * 0.06, // 6% tax
                'total' => $cart->items->sum('total_price') * 1.06
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function remove($itemId)
    {
        try {
            $this->cartService->removeItem($itemId);
            
            $cart = $this->cartService->getCart();
            
            return response()->json([
                'success' => true,
                'cartCount' => $cart->items->sum('quantity'),
                'subtotal' => $cart->items->sum('total_price'),
                'tax' => $cart->items->sum('total_price') * 0.06,
                'total' => $cart->items->sum('total_price') * 1.06
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}