{{-- resources/views/frontend/products/show.blade.php --}}
@extends('layouts.app')

@section('title', $product->name . ' - KL Mobile Equipment Rental')

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Equipment</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('categories.show', $product->category->slug) }}">{{ $product->category->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Product Detail Section -->
    <div class="container my-5 product-detail-page" data-product-id="{{ $product->id }}">
        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6">
                <div class="product-images">
                    <div class="main-image-container">
                        <img src="{{ $product->getFirstMediaUrl('products', 'large') }}" 
                             alt="{{ $product->name }}" 
                             class="main-image" 
                             id="mainImage">
                        @if($product->is_featured)
                            <div class="image-badges">
                                <span class="badge-custom">Popular Choice</span>
                            </div>
                        @endif
                        <div class="zoom-hint">
                            <i class="fas fa-search-plus"></i>
                            <span>Hover to zoom</span>
                        </div>
                    </div>
                    
                    @if($product->getMedia('products')->count() > 1)
                        <div class="thumbnail-container">
                            @foreach($product->getMedia('products') as $index => $media)
                                <div class="thumbnail {{ $index == 0 ? 'active' : '' }}" onclick="changeImage('{{ $media->getUrl('large') }}')">
                                    <img src="{{ $media->getUrl('thumb') }}" alt="View {{ $index + 1 }}">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info-section">
                    <div class="product-category">{{ $product->category->parent ? $product->category->parent->name . ' / ' : '' }}{{ $product->category->name }}</div>
                    <h1 class="product-title">{{ $product->name }}</h1>
                    
                    <div class="product-meta">
                        <span class="sku">SKU: {{ $product->sku }}</span>
                        <livewire:product-availability :product="$product" />
                        @if($product->bookings_count > 0)
                            <div class="rating">
                                <div class="stars">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star{{ $i <= 4.5 ? '' : '-half-alt' }}"></i>
                                    @endfor
                                </div>
                                <span class="rating-text">4.5 ({{ $product->bookings_count }} rentals)</span>
                            </div>
                        @endif
                    </div>

                    <p class="product-description text-gray mb-4">
                        {{ $product->short_description }}
                    </p>

                    <!-- Pricing Section -->
                    <div class="pricing-section">
                        <div class="price-display" data-base-price="{{ $product->base_price }}">LKR {{ number_format($product->base_price) }}</div>
                        <div class="price-note">Per day rental • Minimum 1 day</div>
                        
                        @if($product->price_per_week || $product->price_per_month)
                            <div class="pricing-tiers">
                                <div class="tier-card">
                                    <div class="tier-days">1-2 days</div>
                                    <div class="tier-price">LKR {{ number_format($product->base_price) }}/day</div>
                                </div>
                                @if($product->price_per_week)
                                    <div class="tier-card">
                                        <div class="tier-days">3-5 days</div>
                                        <div class="tier-price">LKR {{ number_format($product->price_per_week / 7) }}/day</div>
                                        <div class="tier-save">Save 10%</div>
                                    </div>
                                @endif
                                @if($product->price_per_month)
                                    <div class="tier-card">
                                        <div class="tier-days">6+ days</div>
                                        <div class="tier-price">LKR {{ number_format($product->price_per_month / 30) }}/day</div>
                                        <div class="tier-save">Save 20%</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Variations -->
                    @if($product->variations->count() > 0)
                        <div class="variations-section">
                            @foreach($product->variations->groupBy('type') as $type => $variations)
                                <div class="variation-group">
                                    <div class="variation-label">Select {{ ucfirst(str_replace('_', ' ', $type)) }}:</div>
                                    <div class="variation-options">
                                        @foreach($variations as $variation)
                                            <div class="variation-option {{ $loop->first ? 'selected' : '' }} {{ $variation->inventory->available_quantity == 0 ? 'unavailable' : '' }}" 
                                                 data-variation-id="{{ $variation->id }}"
                                                 data-price-modifier="{{ $variation->price ?? $variation->price_modifier }}">
                                                <div>{{ $variation->value }}</div>
                                                @if($variation->price)
                                                    <div class="variation-price-diff">LKR {{ number_format($variation->price) }}/day</div>
                                                @elseif($variation->price_modifier)
                                                    <div class="variation-price-diff">{{ $variation->price_modifier_type == 'percentage' ? $variation->price_modifier . '%' : '+LKR ' . number_format($variation->price_modifier) }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Booking Section -->
                    <div class="booking-section">
                        <h5 class="mb-3">Book This Equipment</h5>
                        
                        <form id="addToCartForm">
                            @csrf
                            <div class="date-inputs">
                                <div class="form-group">
                                    <label>Rental Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Rental End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="end_date" required>
                                </div>
                            </div>

                            <div class="quantity-selector">
                                <label>Quantity:</label>
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn" onclick="decreaseQty()">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="qty-input" value="1" min="1" max="{{ $product->inventory->available_quantity }}" id="quantity" name="quantity">
                                    <button type="button" class="qty-btn" onclick="increaseQty()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <span class="max-available">({{ $product->inventory->available_quantity }} available)</span>
                            </div>

                            <!-- Calendar View -->
                            <livewire:availability-calendar :product="$product" />
                        </form>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="btn btn-primary btn-add-to-cart" data-product-id="{{ $product->id }}">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Add to Cart
                        </button>
                        <button class="btn-outline" onclick="document.getElementById('addToCartForm').submit()">
                            Book Now
                        </button>
                        <button class="btn-icon" title="Add to Wishlist">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>

                    <!-- Additional Info -->
                    <div class="mt-4 p-3 bg-card rounded">
                        <small class="text-gray">
                            <i class="fas fa-info-circle me-2"></i>
                            Free delivery within Kuala Lumpur • Setup assistance available • 
                            Insurance required for rentals over LKR 50,000
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs -->
        <div class="tabs-section">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#specifications">Specifications</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#included">What's Included</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#requirements">Requirements</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Specifications Tab -->
                <div class="tab-pane fade show active" id="specifications">
                    <div class="specs-table">
                        <table>
                            <thead>
                                <tr>
                                    <th colspan="2">Technical Specifications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($product->attributes as $attribute)
                                    <tr>
                                        <td>{{ $attribute->template->name }}</td>
                                        <td>{{ $attribute->value }}{{ $attribute->template->unit ? ' ' . $attribute->template->unit : '' }}</td>
                                    </tr>
                                @endforeach
                                @if($product->weight_kg)
                                    <tr>
                                        <td>Weight</td>
                                        <td>{{ $product->weight_kg }} kg</td>
                                    </tr>
                                @endif
                                @if($product->dimensions)
                                    <tr>
                                        <td>Dimensions</td>
                                        <td>{{ $product->dimensions['length'] ?? '' }} x {{ $product->dimensions['width'] ?? '' }} x {{ $product->dimensions['height'] ?? '' }} mm</td>
                                    </tr>
                                @endif
                                @if($product->power_requirements)
                                    <tr>
                                        <td>Power Requirements</td>
                                        <td>{{ $product->power_requirements }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Features Tab -->
                <div class="tab-pane fade" id="features">
                    <div class="features-grid">
                        {!! $product->detailed_description !!}
                    </div>
                </div>

                <!-- What's Included Tab -->
                <div class="tab-pane fade" id="included">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Standard Package Includes:</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> {{ $product->name }}</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> All necessary cables and connectors</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Basic setup instructions</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Protective covers during transport</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Optional Add-ons:</h5>
                            <ul class="list-unstyled">
                                @if($product->requires_operator)
                                    <li class="mb-2"><i class="fas fa-plus text-primary me-2"></i> Professional Operator</li>
                                @endif
                                <li class="mb-2"><i class="fas fa-plus text-primary me-2"></i> Extended warranty coverage</li>
                                <li class="mb-2"><i class="fas fa-plus text-primary me-2"></i> Early morning delivery</li>
                                <li class="mb-2"><i class="fas fa-plus text-primary me-2"></i> Late night pickup</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Requirements Tab -->
                <div class="tab-pane fade" id="requirements">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Venue Requirements:</h5>
                            <ul class="list-unstyled">
                                @if($product->power_requirements)
                                    <li class="mb-2"><i class="fas fa-bolt text-warning me-2"></i> Power: {{ $product->power_requirements }}</li>
                                @endif
                                <li class="mb-2"><i class="fas fa-ruler text-info me-2"></i> Space: Check dimensions in specifications</li>
                                <li class="mb-2"><i class="fas fa-door-open text-success me-2"></i> Access: Ground floor or elevator access for equipment</li>
                                <li class="mb-2"><i class="fas fa-shield-alt text-primary me-2"></i> Security: Secure storage area if overnight setup</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Rental Terms:</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-id-card text-primary me-2"></i> Valid ID and deposit required</li>
                                <li class="mb-2"><i class="fas fa-clock text-info me-2"></i> Setup time: {{ $product->setup_time_hours ?? '1-2' }} hours before event</li>
                                <li class="mb-2"><i class="fas fa-truck text-success me-2"></i> Delivery: Free within KL city center</li>
                                <li class="mb-2"><i class="fas fa-file-contract text-warning me-2"></i> Damage waiver available at checkout</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
            <div class="related-products">
                <h3 class="mb-4">Frequently Rented Together</h3>
                <div class="row g-4">
                    @foreach($relatedProducts as $related)
                        <div class="col-lg-3 col-md-6">
                            @include('components.product-card', ['product' => $related, 'compact' => true])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        // Product page specific scripts are handled in the main app.js
    </script>
@endpush