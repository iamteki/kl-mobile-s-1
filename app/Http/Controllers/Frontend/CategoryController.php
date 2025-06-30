<?php
// app/Http/Controllers/Frontend/CategoryController.php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\AttributeTemplate;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('status', 'active')
            ->whereNull('parent_id')
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();
            
        return view('frontend.categories.index', compact('categories'));
    }

    public function show(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)
            ->where('status', 'active')
            ->with(['children', 'attributeTemplates'])
            ->firstOrFail();
            
        // Get all categories for sidebar
        $allCategories = Category::where('status', 'active')
            ->whereNull('parent_id')
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();
            
        // Build query
        $query = Product::where('status', 'active')
            ->where(function($q) use ($category) {
                $q->where('category_id', $category->id)
                  ->orWhereIn('category_id', $category->children->pluck('id'));
            })
            ->with(['category', 'inventory', 'variations', 'attributes.template']);
            
        // Apply filters
        $this->applyFilters($query, $request);
        
        // Apply sorting
        $this->applySorting($query, $request);
        
        // Get products with pagination
        $products = $query->paginate(12);
        
        // Get filter data
        $brands = $this->getBrands($category);
        $attributeFilters = $this->getAttributeFilters($category);
        
        // AJAX response for filters
        if ($request->ajax()) {
            $html = view('frontend.categories.partials.products-grid', compact('products'))->render();
            return response()->json([
                'html' => $html,
                'count' => $products->total()
            ]);
        }
        
        return view('frontend.categories.show', compact(
            'category',
            'allCategories',
            'products',
            'brands',
            'attributeFilters'
        ));
    }
    
    private function applyFilters($query, Request $request)
    {
        // Subcategory filter
        if ($request->has('subcategory')) {
            $subcategories = explode(',', $request->subcategory);
            $query->whereIn('category_id', $subcategories);
        }
        
        // Brand filter
        if ($request->has('brand')) {
            $brands = explode(',', $request->brand);
            $query->whereHas('attributes', function($q) use ($brands) {
                $q->whereHas('template', function($q2) {
                    $q2->where('slug', 'brand');
                })->whereIn('value', $brands);
            });
        }
        
        // Price range
        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }
        
        // Availability
        if ($request->has('availability') && $request->availability == 'in_stock') {
            $query->whereHas('inventory', function($q) {
                $q->where('available_quantity', '>', 0);
            });
        }
        
        // Dynamic attribute filters
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'attr_')) {
                $attributeSlug = str_replace('attr_', '', $key);
                $values = explode(',', $value);
                
                $query->whereHas('attributes', function($q) use ($attributeSlug, $values) {
                    $q->whereHas('template', function($q2) use ($attributeSlug) {
                        $q2->where('slug', $attributeSlug);
                    })->whereIn('value', $values);
                });
            }
        }
    }
    
    private function applySorting($query, Request $request)
    {
        switch ($request->get('sort', 'featured')) {
            case 'price_low':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('base_price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('is_featured', 'desc')
                      ->orderBy('sort_order', 'asc');
        }
    }
    
    private function getBrands($category)
    {
        return Product::where('status', 'active')
            ->where(function($q) use ($category) {
                $q->where('category_id', $category->id)
                  ->orWhereIn('category_id', $category->children->pluck('id'));
            })
            ->join('product_attributes', 'products.id', '=', 'product_attributes.product_id')
            ->join('attribute_templates', 'product_attributes.attribute_template_id', '=', 'attribute_templates.id')
            ->where('attribute_templates.slug', 'brand')
            ->selectRaw('product_attributes.value as brand, COUNT(*) as count')
            ->groupBy('product_attributes.value')
            ->pluck('count', 'brand');
    }
    
    private function getAttributeFilters($category)
    {
        return AttributeTemplate::where('category_id', $category->id)
            ->where('is_filterable', true)
            ->where('type', 'select')
            ->orWhere('type', 'multiselect')
            ->get();
    }
}