{{-- resources/views/frontend/packages/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Event Packages - KL Mobile Events')

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Event Packages</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="page-title">Event Packages</h1>
            <p class="page-subtitle">Complete solutions for weddings, corporate events, and celebrations</p>
        </div>
    </section>

    <!-- Package Categories -->
    <section class="package-categories">
        <div class="container">
            <div class="category-tabs">
                <button class="category-tab active" data-category="all">All Packages</button>
                <button class="category-tab" data-category="wedding">Weddings</button>
                <button class="category-tab" data-category="corporate">Corporate</button>
                <button class="category-tab" data-category="birthday">Birthdays</button>
                <button class="category-tab" data-category="concert">Concerts</button>
                <button class="category-tab" data-category="custom">Custom Events</button>
            </div>
        </div>
    </section>

    <!-- Packages Grid -->
    <section class="packages-section">
        <div class="container">
            <div class="row g-4" id="packagesGrid">
                @foreach($packages as $package)
                    <div class="col-lg-4 package-item" data-category="{{ $package->category }}">
                        <div class="package-card {{ $package->is_featured ? 'featured' : '' }}">
                            @if($package->is_featured)
                                <span class="package-badge">Most Popular</span>
                            @endif
                            
                            <div class="package-header">
                                <i class="{{ $package->icon ?? 'fas fa-gift' }} package-icon"></i>
                                <h3 class="package-name">{{ $package->name }}</h3>
                                <p class="package-tagline">{{ $package->tagline }}</p>
                            </div>
                            
                            <div class="package-price">
                                <span class="currency">LKR</span>
                                <span class="amount">{{ number_format($package->price) }}</span>
                                <span class="period">{{ $package->price_type }}</span>
                            </div>
                            
                            <div class="package-description">
                                <p>{{ $package->short_description }}</p>
                            </div>
                            
                            <div class="package-features">
                                <h6>Package Includes:</h6>
                                <ul class="features-list">
                                    @foreach($package->items->take(5) as $item)
                                        <li>
                                            <i class="fas fa-check"></i>
                                            <span>{{ $item->quantity }}x {{ $item->product->name }}</span>
                                        </li>
                                    @endforeach
                                    @if($package->items->count() > 5)
                                        <li class="more-items">
                                            <i class="fas fa-plus"></i>
                                            <span>{{ $package->items->count() - 5 }} more items</span>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                            
                            <div class="package-capacity">
                                <div class="capacity-item">
                                    <i class="fas fa-users"></i>
                                    <span>{{ $package->min_capacity }}-{{ $package->max_capacity }} Guests</span>
                                </div>
                                <div class="capacity-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>{{ $package->duration }} Hours</span>
                                </div>
                            </div>
                            
                            <div class="package-actions">
                                <a href="{{ route('packages.show', $package->slug) }}" 
                                   class="btn {{ $package->is_featured ? 'btn-primary' : 'btn-outline-primary' }} w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($packages->isEmpty())
                <div class="empty-state">
                    <i class="fas fa-gift fa-4x text-muted mb-3"></i>
                    <p class="text-muted">No packages available at the moment.</p>
                    <a href="{{ route('contact') }}" class="btn btn-primary mt-3">
                        Contact Us for Custom Package
                    </a>
                </div>
            @endif
        </div>
    </section>

    <!-- Custom Package CTA -->
    <section class="custom-package-cta">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="cta-title">Need a Custom Package?</h2>
                <p class="cta-description">
                    We can create a personalized package that perfectly matches your event requirements and budget
                </p>
                <div class="cta-features">
                    <div class="cta-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Tailored to Your Needs</span>
                    </div>
                    <div class="cta-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Competitive Pricing</span>
                    </div>
                    <div class="cta-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Expert Consultation</span>
                    </div>
                </div>
                <a href="{{ route('contact') }}" class="btn btn-primary btn-lg mt-4">
                    Get Custom Quote
                </a>
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
    background: radial-gradient(circle at 70% 50%, rgba(147, 51, 234, 0.1) 0%, transparent 50%);
}

/* Category Tabs */
.package-categories {
    margin-bottom: 50px;
}

.category-tabs {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
    padding: 30px;
    background: var(--bg-card);
    border-radius: 15px;
    border: 1px solid var(--border-dark);
}

.category-tab {
    padding: 10px 25px;
    background: var(--bg-dark);
    border: 1px solid var(--border-dark);
    color: var(--text-gray);
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s;
    cursor: pointer;
}

.category-tab:hover {
    color: var(--secondary-purple);
    border-color: var(--secondary-purple);
}

.category-tab.active {
    background: var(--primary-purple);
    color: white;
    border-color: var(--primary-purple);
}

/* Package Cards */
.package-card {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: all 0.3s;
    border: 1px solid var(--border-dark);
    position: relative;
    overflow: hidden;
}

.package-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(147, 51, 234, 0.3);
    border-color: var(--primary-purple);
}

.package-card.featured {
    border-color: var(--primary-purple);
    background: linear-gradient(135deg, var(--bg-card) 0%, rgba(147, 51, 234, 0.1) 100%);
}

/* Package Badge */
.package-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: var(--primary-purple);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

/* Package Header */
.package-header {
    text-align: center;
    margin-bottom: 25px;
}

.package-icon {
    font-size: 48px;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 15px;
}

.package-name {
    color: var(--off-white);
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
}

.package-tagline {
    color: var(--text-gray);
    font-size: 14px;
}

/* Package Price */
.package-price {
    text-align: center;
    margin-bottom: 25px;
    padding: 20px;
    background: var(--bg-dark);
    border-radius: 10px;
}

.package-price .currency {
    color: var(--text-gray);
    font-size: 16px;
    margin-right: 5px;
}

.package-price .amount {
    font-size: 36px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.package-price .period {
    color: var(--text-gray);
    font-size: 14px;
    display: block;
}

/* Package Description */
.package-description {
    color: var(--text-gray);
    margin-bottom: 25px;
    flex-grow: 1;
}

/* Package Features */
.package-features {
    margin-bottom: 25px;
}

.package-features h6 {
    color: var(--off-white);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
    text-transform: uppercase;
}

.features-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.features-list li {
    color: var(--text-gray);
    font-size: 14px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.features-list li i {
    color: var(--success-green);
    font-size: 12px;
}

.features-list .more-items {
    color: var(--primary-purple);
    font-weight: 500;
}

/* Package Capacity */
.package-capacity {
    display: flex;
    justify-content: space-around;
    padding: 15px;
    background: var(--bg-dark);
    border-radius: 10px;
    margin-bottom: 25px;
}

.capacity-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-gray);
    font-size: 14px;
}

.capacity-item i {
    color: var(--primary-purple);
}

/* Custom Package CTA */
.custom-package-cta {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-dark) 100%);
    padding: 80px 0;
    margin-top: 80px;
    border-top: 1px solid var(--border-dark);
}

.cta-content {
    max-width: 800px;
    margin: 0 auto;
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
    margin-bottom: 40px;
}

.cta-features {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.cta-feature {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--off-white);
    font-size: 16px;
}

.cta-feature i {
    color: var(--success-green);
    font-size: 20px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .category-tabs {
        padding: 20px;
    }
    
    .category-tab {
        padding: 8px 20px;
        font-size: 14px;
    }
    
    .package-price .amount {
        font-size: 28px;
    }
    
    .cta-title {
        font-size: 28px;
    }
    
    .cta-features {
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Package filtering
document.addEventListener('DOMContentLoaded', function() {
    const categoryTabs = document.querySelectorAll('.category-tab');
    const packageItems = document.querySelectorAll('.package-item');
    
    categoryTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            categoryTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Filter packages
            const category = this.dataset.category;
            
            packageItems.forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = 'block';
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                }
            });
        });
    });
});
</script>
@endpush