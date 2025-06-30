<?php
// app/Livewire/ProductSearch.php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;

class ProductSearch extends Component
{
    use WithPagination;
    
    public $search = '';
    public $category = '';
    public $priceMin = '';
    public $priceMax = '';
    public $sortBy = 'name';
    public $availability = '';
    public $showFilters = false;
    
    public $categories;
    
    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => ''],
        'priceMin' => ['except' => ''],
        'priceMax' => ['except' => ''],
        'sortBy' => ['except' => 'name'],
    ];
    
    protected $paginationTheme = 'bootstrap';
    
    public function mount()
    {
        $this->categories = Category::where('parent_id', null)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingCategory()
    {
        $this->resetPage();
    }
    
    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }
    
    public function resetFilters()
    {
        $this->search = '';
        $this->category = '';
        $this->priceMin = '';
        $this->priceMax = '';
        $this->sortBy = 'name';
        $this->availability = '';
        $this->resetPage();
    }
    
    public function render()
    {
        $query = Product::query()
            ->where('status', 'active')
            ->with(['category', 'media', 'inventory']);
        
        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }
        
        // Category filter
        if ($this->category) {
            $categoryIds = Category::where('id', $this->category)
                ->orWhere('parent_id', $this->category)
                ->pluck('id');
                
            $query->whereIn('category_id', $categoryIds);
        }
        
        // Price filter
        if ($this->priceMin) {
            $query->where('base_price', '>=', $this->priceMin);
        }
        
        if ($this->priceMax) {
            $query->where('base_price', '<=', $this->priceMax);
        }
        
        // Availability filter
        if ($this->availability === 'in_stock') {
            $query->whereHas('inventory', function ($q) {
                $q->where('available_quantity', '>', 0);
            });
        } elseif ($this->availability === 'out_of_stock') {
            $query->whereHas('inventory', function ($q) {
                $q->where('available_quantity', '=', 0);
            });
        }
        
        // Sorting
        switch ($this->sortBy) {
            case 'price_low':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('base_price', 'desc');
                break;
            case 'popular':
                $query->withCount('bookings')
                    ->orderBy('bookings_count', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }
        
        $products = $query->paginate(12);
        
        return view('livewire.product-search', [
            'products' => $products
        ]);
    }
}