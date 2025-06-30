{{-- resources/views/livewire/product-search.blade.php --}}
<div class="product-search-component">
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="search-box">
                    <input 
                        type="text" 
                        wire:model.debounce.300ms="search" 
                        class="form-control search-input"
                        placeholder="Search products by name, description, or SKU..."
                    >
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <select wire:model="sortBy" class="form-select">
                    <option value="name">Sort by Name</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="popular">Most Popular</option>
                    <option value="newest">Newest First</option>
                </select>
            </div>
            <div class="col-md-3">
                <button 
                    wire:click="toggleFilters" 
                    class="btn btn-outline-primary w-100"
                >
                    <i class="fas fa-filter me-2"></i>
                    {{ $showFilters ? 'Hide' : 'Show' }} Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Advanced Filters -->
    @if($showFilters)
        <div class="advanced-filters mb-4" wire:transition>
            <div class="filter-card">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select wire:model="category" class="form-select">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @if($cat->children->count() > 0)
                                    @foreach($cat->children as $subcat)
                                        <option value="{{ $subcat->id }}">-- {{ $subcat->name }}</option>
                                    @endforeach
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Min Price</label>
                        <input 
                            type="number" 
                            wire:model="priceMin" 
                            class="form-control" 
                            placeholder="0"
                        >
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Price</label>
                        <input 
                            type="number" 
                            wire:model="priceMax" 
                            class="form-control" 
                            placeholder="Any"
                        >
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Availability</label>
                        <select wire:model="availability" class="form-select">
                            <option value="">All Products</option>
                            <option value="in_stock">In Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button 
                            wire:click="resetFilters" 
                            class="btn btn-secondary w-100"
                        >
                            <i class="fas fa-redo me-2"></i>Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Loading State -->
    <div wire:loading.flex class="loading-overlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid">
        @if($products->count() > 0)
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        @include('frontend.products.partials.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-5">
                {{ $products->links() }}
            </div>
        @else
            <div class="no-results text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>No products found</h4>
                <p class="text-muted">Try adjusting your search or filters</p>
                <button wire:click="resetFilters" class="btn btn-primary mt-3">
                    Clear Filters
                </button>
            </div>
        @endif
    </div>
</div>

<style>
.product-search-component {
    position: relative;
}

.search-box {
    position: relative;
}

.search-input {
    padding-left: 45px;
    background-color: var(--bg-card);
    border-color: var(--border-dark);
    color: var(--off-white);
}

.search-input:focus {
    background-color: var(--bg-card);
    border-color: var(--primary-purple);
    color: var(--off-white);
    box-shadow: 0 0 0 0.25rem rgba(147, 51, 234, 0.25);
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-gray);
}

.filter-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border-dark);
    border-radius: 8px;
    padding: 20px;
}

.advanced-filters {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(10, 10, 10, 0.8);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
}

.no-results {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 60px 20px;
}

/* Form controls dark theme */
.form-select,
.form-control {
    background-color: var(--bg-card);
    border-color: var(--border-dark);
    color: var(--off-white);
}

.form-select:focus,
.form-control:focus {
    background-color: var(--bg-card);
    border-color: var(--primary-purple);
    color: var(--off-white);
    box-shadow: 0 0 0 0.25rem rgba(147, 51, 234, 0.25);
}

.form-select option {
    background-color: var(--bg-dark);
    color: var(--off-white);
}

.form-label {
    color: var(--text-gray);
    font-weight: 500;
    margin-bottom: 0.5rem;
}
</style>