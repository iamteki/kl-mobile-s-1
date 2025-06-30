<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Package;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::where('status', 'active')
            ->where('parent_id', null)
            ->orderBy('sort_order')
            ->get();
            
        $featuredProducts = Product::where('status', 'active')
            ->where('is_featured', true)
            ->with(['category', 'media'])
            ->limit(8)
            ->get();
            
        $packages = Package::where('status', 'active')
            ->where('is_featured', true)
            ->limit(3)
            ->get();
            
        return view('frontend.home.index', compact('categories', 'featuredProducts', 'packages'));
    }
}