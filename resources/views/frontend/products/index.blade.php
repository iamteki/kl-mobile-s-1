{{-- resources/views/frontend/products/index.blade.php --}}
@extends('layouts.app')

@section('title', 'All Equipment - KL Mobile Events')

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">All Equipment</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 filters-column">
                <button class="btn btn-close btn-close-white float-end d-lg-none mb-3" onclick="toggleFilters()"></button>
                
                <div class="filters-section">
                    <h5 class="filters-title">Filter Equipment</h5>
                    
                    <!-- Categories -->
                    <div class="filter-group" data-filter-type="category">
                        <h6>Categories</h6>
                        @foreach($categories as $category)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       id="cat{{ $category->id }}" 
                                       value="{{ $category->id }}"
                                       {{ in_array($category->id, request('categories', [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="cat{{ $category->id }}">
                                    {{ $category->name }} ({{ $category->products_count }})
                                </label>
                            </div>
                            @if($category->children->count() > 0)
                                <div class="ms-3">
                                    @foreach($category->children as $child)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="cat{{ $child->id }}" 
                                                   value="{{ $child->id }}"
                                                   {{ in_array($child->id, request('categories', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="cat{{ $child->id }}">
                                                {{ $child->name }} ({{ $child->products_count }})
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <!-- Price Range -->
                    <div class="filter-group">
                        <h6>Price Range (per day)</h6>
                        <div class="price-range">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" 
                                           name="min_price" 
                                           placeholder="Min" 
                                           value="{{ request('min_price') }}">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" 
                                           name="max_price" 
                                           placeholder="Max" 
                                           value="{{ request('max_price') }}">
                                </div>
                            </div>
                            <button class="btn btn-sm btn-primary w-100 mt-3 price-range-apply">Apply</button>
                        </div>
                    </div>

                    <!-- Brands -->
                    <div class="filter-group" data-filter-type="brand">
                        <h6>Brands</h6>
                        @foreach($brands as $brand)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       id="brand{{ $brand->id }}" 
                                       value="{{ $brand->name }}"
                                       {{ in_array($brand->name, request('brands', [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="brand{{ $brand->id }}">
                                    {{ $brand->name }} ({{ $brand->products_count }})
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <!-- Availability -->
                    <div class="filter-group" data-filter-type="availability">
                        <h6>Availability</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   id="available" 
                                   value="in_stock"
                                   {{ request('availability') == 'in_stock' ? 'checked' : '' }}>
                            <label class="form-check-label" for="available">
                                In Stock Only
                            </label>
                        </div>
                    </div>

                    <!-- Clear Filters -->
                    <button class="btn btn-outline-primary w-100 mt-3 clear-filters-btn">
                        <i class="fas fa-times me-2"></i>Clear All Filters
                    </button>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <!-- Mobile Filter Toggle -->
                <button class="mobile-filter-toggle" onclick="toggleFilters()">
                    <i class="fas fa-filter me-2"></i>Show Filters
                </button>

                <!-- Search and Sort Bar -->
                <div class="search-sort-bar">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <form action="{{ route('products.index') }}" method="GET" class="search-form">
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           placeholder="Search equipment..." 
                                           value="{{ request('search') }}">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <div class="sort-options">
                                <select class="form-select" name="sort" onchange="updateSort(this.value)">
                                    <option value="">Sort by: Featured</option>
                                    <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                                    <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                                    <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>Name: A to Z</option>
                                    <option value="name_desc" {{ request('sort') == 'name_desc' ? 'selected' : '' }}>Name: Z to A</option>
                                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Count -->
                <div class="results-info">
                    <p class="results-count">Showing {{ $products->count() }} of {{ $products->total() }} results</p>
                    @if(request()->anyFilled(['search', 'categories', 'brands', 'min_price', 'max_price']))
                        <div class="active-filters">
                            @if(request('search'))
                                <span class="filter-tag">
                                    Search: {{ request('search') }}
                                    <a href="{{ route('products.index', request()->except('search')) }}" class="remove-filter">×</a>
                                </span>
                            @endif
                            @if(request('min_price') || request('max_price'))
                                <span class="filter-tag">
                                    Price: LKR {{ request('min_price', 0) }} - {{ request('max_price', '∞') }}
                                    <a href="{{ route('products.index', request()->except(['min_price', 'max_price'])) }}" class="remove-filter">×</a>
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Products Grid -->
                <div class="row g-4 products-grid">
                    @forelse($products as $product)
                        <div class="col-lg-4 col-md-6">
                            @include('components.product-card', ['product' => $product])
                        </div>
                    @empty
                        <div class="col-12 text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No products found matching your criteria.</p>
                                <a href="{{ route('products.index') }}" class="btn btn-primary mt-3">
                                    View All Products
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                @if($products->hasPages())
                    <nav aria-label="Product pagination" class="mt-5">
                        {{ $products->withQueryString()->links() }}
                    </nav>
                @endif
            </div>
        </div>
    </div>

    <!-- Filters Overlay for Mobile -->
    <div class="filters-overlay" onclick="toggleFilters()"></div>
@endsection

@push('styles')
<style>
/* Search and Sort Bar */
.search-sort-bar {
    background: var(--bg-card);
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
    border: 1px solid var(--border-dark);
}

.search-form .form-control {
    background-color: var(--bg-dark);
    border: 1px solid var(--border-dark);
    color: var(--off-white);
    padding: 12px 20px;
}

.search-form .form-control:focus {
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 0.2rem rgba(147, 51, 234, 0.25);
}

.search-form .form-control::placeholder {
    color: var(--text-gray);
}

/* Results Info */
.results-info {
    margin-bottom: 30px;
}

.results-count {
    color: var(--text-gray);
    font-size: 16px;
    margin-bottom: 15px;
}

.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    background: var(--bg-card);
    border: 1px solid var(--primary-purple);
    color: var(--primary-purple);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
}

.remove-filter {
    margin-left: 8px;
    color: var(--primary-purple);
    text-decoration: none;
    font-weight: bold;
    font-size: 18px;
    line-height: 1;
}

.remove-filter:hover {
    color: var(--secondary-purple);
}

/* Empty State */
.empty-state {
    padding: 60px 20px;
}

.empty-state i {
    color: var(--border-dark);
}

.empty-state p {
    font-size: 18px;
    margin-bottom: 0;
}

/* Filters Title */
.filters-title {
    color: var(--off-white);
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-dark);
    position: relative;
}

.filters-title::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 50px;
    height: 2px;
    background: var(--primary-purple);
}

/* Mobile Filters */
.mobile-filter-toggle {
    display: none;
    width: 100%;
    padding: 12px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border-dark);
    color: var(--off-white);
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 600;
    transition: all 0.3s;
}

.mobile-filter-toggle:hover {
    background: var(--primary-purple);
    border-color: var(--primary-purple);
}

@media (max-width: 991px) {
    .mobile-filter-toggle {
        display: block;
    }
    
    .filters-column {
        position: fixed;
        top: 0;
        left: -100%;
        width: 300px;
        height: 100vh;
        background: var(--bg-dark);
        z-index: 1050;
        overflow-y: auto;
        transition: left 0.3s;
        padding: 20px;
    }
    
    .filters-column.show {
        left: 0;
    }
    
    .filters-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
    }
    
    .filters-overlay.show {
        display: block;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .search-sort-bar .row {
        gap: 15px;
    }
    
    .search-sort-bar .col-md-6 {
        width: 100%;
    }
}
</style>
@endpush

@push('scripts')
<script>
function updateSort(value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set('sort', value);
    } else {
        url.searchParams.delete('sort');
    }
    window.location.href = url.toString();
}

// Filters are initialized in the main app.js through the filters module
</script>
@endpush