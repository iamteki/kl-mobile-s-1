{{-- resources/views/frontend/categories/show.blade.php --}}
@extends('layouts.app')

@section('title', $category->name . ' Rental - KL Mobile')

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Equipment</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Category Header -->
    <section class="category-header">
        <div class="container text-center">
            <i class="{{ $category->icon ?? 'fas fa-box' }} category-icon"></i>
            <h1 class="text-white mb-3">{{ $category->name }} Rental</h1>
            <p class="text-muted lead">{{ $category->description }}</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 filters-column">
                <button class="btn btn-close btn-close-white float-end d-lg-none mb-3" onclick="toggleFilters()"></button>
                
                <div class="filters-section">
                    <!-- Categories -->
                    <div class="filter-group">
                        <h6>All Equipment Categories</h6>
                        <ul class="categories-list">
                            @foreach($allCategories as $cat)
                                <li>
                                    <a href="{{ route('categories.show', $cat->slug) }}" class="{{ $cat->id == $category->id ? 'active' : '' }}">
                                        {{ $cat->name }}
                                        <span class="category-count">{{ $cat->products_count }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if($category->children->count() > 0)
                        <!-- Subcategory Filter -->
                        <div class="filter-group" data-filter-type="subcategory">
                            <h6>Subcategory</h6>
                            @foreach($category->children as $subcategory)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="{{ $subcategory->id }}" id="sub_{{ $subcategory->id }}">
                                    <label class="form-check-label" for="sub_{{ $subcategory->id }}">
                                        {{ $subcategory->name }} ({{ $subcategory->products_count }})
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($brands->count() > 0)
                        <!-- Brand Filter -->
                        <div class="filter-group" data-filter-type="brand">
                            <h6>Brand</h6>
                            @foreach($brands as $brand => $count)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="{{ $brand }}" id="brand_{{ Str::slug($brand) }}">
                                    <label class="form-check-label" for="brand_{{ Str::slug($brand) }}">
                                        {{ $brand }} ({{ $count }})
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @foreach($attributeFilters as $filter)
                        <div class="filter-group" data-filter-type="{{ $filter->slug }}">
                            <h6>{{ $filter->name }}</h6>
                            @foreach($filter->options as $option)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="{{ $option }}" id="{{ $filter->slug }}_{{ Str::slug($option) }}">
                                    <label class="form-check-label" for="{{ $filter->slug }}_{{ Str::slug($option) }}">
                                        {{ $option }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <!-- Price Range -->
                    <div class="filter-group">
                        <h6>Price Range (per day)</h6>
                        <div class="price-range">
                            <input type="number" name="min_price" placeholder="Min" min="0">
                            <span style="color: var(--text-gray);">-</span>
                            <input type="number" name="max_price" placeholder="Max" min="0">
                        </div>
                        <button class="btn btn-sm btn-primary w-100 mt-3 price-range-apply">Apply</button>
                    </div>

                    <!-- Availability -->
                    <div class="filter-group" data-filter-type="availability">
                        <h6>Availability</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="available" value="in_stock" checked>
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

                <!-- Sort and View Options -->
                <div class="sort-section">
                    <div class="results-count">
                        Showing {{ $products->count() }} of {{ $products->total() }} results
                    </div>
                    <div class="sort-options">
                        <select class="form-select" name="sort" onchange="this.form.submit()">
                            <option value="featured" {{ request('sort') == 'featured' ? 'selected' : '' }}>Sort by: Featured</option>
                            <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>Name: A to Z</option>
                            <option value="name_desc" {{ request('sort') == 'name_desc' ? 'selected' : '' }}>Name: Z to A</option>
                            <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest First</option>
                        </select>
                        <div class="view-options">
                            <button class="view-btn active" title="Grid View">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn" title="List View">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="row g-4 products-grid">
                    @forelse($products as $product)
                        <div class="col-lg-4 col-md-6">
                            @include('components.product-card', ['product' => $product])
                        </div>
                    @empty
                        <div class="col-12 text-center py-5">
                            <p class="text-muted">No products found matching your criteria.</p>
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

@push('scripts')
    <script>
        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Filters are initialized in the main app.js
        });
    </script>
@endpush