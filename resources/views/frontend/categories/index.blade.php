{{-- resources/views/frontend/categories/index.blade.php --}}
@extends('layouts.app')

@section('title', 'All Equipment Categories - KL Mobile Events')

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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="page-title">Equipment Categories</h1>
            <p class="page-subtitle">Professional event equipment for every occasion</p>
        </div>
    </section>

    <!-- Categories Grid -->
    <section class="categories-section py-5">
        <div class="container">
            <!-- Parent Categories -->
            @foreach($parentCategories as $parent)
                <div class="category-group mb-5">
                    <h2 class="category-group-title">{{ $parent->name }}</h2>
                    <div class="row g-4">
                        @foreach($parent->children as $category)
                            <div class="col-lg-3 col-md-6">
                                <a href="{{ route('categories.show', $category->slug) }}" class="category-card-link">
                                    <div class="category-card h-100">
                                        @if($category->getFirstMediaUrl('categories'))
                                            <div class="category-image">
                                                <img src="{{ $category->getFirstMediaUrl('categories', 'thumb') }}" 
                                                     alt="{{ $category->name }}" 
                                                     class="img-fluid">
                                                <div class="category-overlay">
                                                    <span class="view-products">View Products</span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="category-icon-wrapper">
                                                <i class="{{ $category->icon ?? 'fas fa-box' }}"></i>
                                            </div>
                                        @endif
                                        <div class="category-content">
                                            <h4 class="category-name">{{ $category->name }}</h4>
                                            <p class="category-description">{{ $category->description }}</p>
                                            <div class="category-stats">
                                                <span class="product-count">
                                                    <i class="fas fa-cube"></i> {{ $category->products_count }} Products
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <!-- Categories without parent -->
            @if($standaloneCategories->count() > 0)
                <div class="category-group">
                    <h2 class="category-group-title">Other Equipment</h2>
                    <div class="row g-4">
                        @foreach($standaloneCategories as $category)
                            <div class="col-lg-3 col-md-6">
                                <a href="{{ route('categories.show', $category->slug) }}" class="category-card-link">
                                    <div class="category-card h-100">
                                        @if($category->getFirstMediaUrl('categories'))
                                            <div class="category-image">
                                                <img src="{{ $category->getFirstMediaUrl('categories', 'thumb') }}" 
                                                     alt="{{ $category->name }}" 
                                                     class="img-fluid">
                                                <div class="category-overlay">
                                                    <span class="view-products">View Products</span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="category-icon-wrapper">
                                                <i class="{{ $category->icon ?? 'fas fa-box' }}"></i>
                                            </div>
                                        @endif
                                        <div class="category-content">
                                            <h4 class="category-name">{{ $category->name }}</h4>
                                            <p class="category-description">{{ $category->description }}</p>
                                            <div class="category-stats">
                                                <span class="product-count">
                                                    <i class="fas fa-cube"></i> {{ $category->products_count }} Products
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h3 class="cta-title">Need help choosing equipment?</h3>
            <p class="cta-description">Our experts are here to help you plan the perfect event</p>
            <div class="cta-buttons">
                <a href="{{ route('contact') }}" class="btn btn-primary">Get Expert Advice</a>
                <a href="{{ route('packages.index') }}" class="btn btn-outline-primary">View Packages</a>
            </div>
        </div>
    </section>
@endsection

@push('styles')
<style>
/* Page Header */
.page-header {
    background: linear-gradient(180deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
    padding: 80px 0;
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 50%, rgba(147, 51, 234, 0.1) 0%, transparent 50%);
}

.page-title {
    font-size: 48px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 20px;
}

.page-subtitle {
    font-size: 20px;
    color: var(--text-gray);
}

/* Category Groups */
.category-group {
    margin-bottom: 60px;
}

.category-group-title {
    color: var(--off-white);
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-dark);
    position: relative;
}

.category-group-title::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100px;
    height: 2px;
    background: var(--primary-purple);
}

/* Category Cards */
.category-card-link {
    text-decoration: none;
    display: block;
    height: 100%;
}

.category-card {
    background: var(--bg-card);
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s;
    border: 1px solid var(--border-dark);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(147, 51, 234, 0.3);
    border-color: var(--primary-purple);
}

/* Category Image */
.category-image {
    height: 200px;
    overflow: hidden;
    position: relative;
    background: var(--bg-dark);
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.category-card:hover .category-image img {
    transform: scale(1.1);
}

.category-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(147, 51, 234, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.category-card:hover .category-overlay {
    opacity: 1;
}

.view-products {
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Category Icon */
.category-icon-wrapper {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
}

.category-icon-wrapper i {
    font-size: 64px;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    transition: transform 0.3s;
}

.category-card:hover .category-icon-wrapper i {
    transform: scale(1.2);
}

/* Category Content */
.category-content {
    padding: 25px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.category-name {
    color: var(--off-white);
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
}

.category-description {
    color: var(--text-gray);
    font-size: 14px;
    line-height: 1.6;
    flex-grow: 1;
    margin-bottom: 15px;
}

.category-stats {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: auto;
}

.product-count {
    color: var(--primary-purple);
    font-size: 14px;
    font-weight: 500;
}

.product-count i {
    margin-right: 5px;
}

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-dark) 100%);
    padding: 80px 0;
    margin-top: 80px;
    border-top: 1px solid var(--border-dark);
}

.cta-title {
    color: var(--off-white);
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 20px;
}

.cta-description {
    color: var(--text-gray);
    font-size: 18px;
    margin-bottom: 30px;
}

.cta-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 36px;
    }
    
    .category-group-title {
        font-size: 24px;
    }
    
    .cta-title {
        font-size: 28px;
    }
    
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .cta-buttons .btn {
        width: 200px;
    }
}
</style>
@endpush