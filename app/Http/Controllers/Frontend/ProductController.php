<?php
// app/Http/Controllers/Frontend/ProductController.php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProductController extends Controller
{
    protected $availabilityService;
    
    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }
    
    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->with([
                'category.parent',
                'variations.inventory',
                'attributes.template',
                'inventory',
                'media'
            ])
            ->withCount('bookings')
            ->firstOrFail();
            
        // Increment view count
        $product->increment('views_count');
        
        // Get related products
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->with(['category', 'inventory'])
            ->limit(4)
            ->get();
            
        return view('frontend.products.show', compact('product', 'relatedProducts'));
    }
    
    public function checkAvailability(Request $request, Product $product)
    {
        $request->validate([
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'quantity' => 'required|integer|min:1',
            'variation_id' => 'nullable|exists:product_variations,id'
        ]);
        
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        
        $availability = $this->availabilityService->checkAvailability(
            $product,
            $startDate,
            $endDate,
            $request->quantity,
            $request->variation_id
        );
        
        return response()->json([
            'available' => $availability['available'],
            'quantity' => $availability['quantity'],
            'message' => $availability['message'] ?? null
        ]);
    }
}