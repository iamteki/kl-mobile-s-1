{{-- resources/views/frontend/home/index.blade.php --}}
@extends('layouts.app')

@section('title', 'KL Mobile Events - Professional Event Equipment Rental & DJ Services')

@section('content')
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Rent <span>Event Equipment</span> & Book <span>Professional Services</span></h1>
                    <p class="lead mb-4">Complete event solutions with instant booking and real-time availability. From sound systems to professional DJs - we've got everything covered.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="{{ route('categories.index') }}" class="btn btn-primary btn-lg">Browse Equipment</a>
                        <a href="{{ route('packages.index') }}" class="btn btn-outline-light btn-lg">View Packages</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="{{ asset('images/hero-event.jpg') }}" alt="Event Equipment" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="container">
        <div class="search-section">
            <h3 class="text-center mb-4 text-white">Quick Equipment Search</h3>
            <form action="{{ route('search') }}" method="GET" class="search-form">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select name="category" class="form-select">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="event_date" class="form-control" placeholder="Event Date" min="{{ date('Y-m-d', strtotime('+3 days')) }}">
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="guests" class="form-control" placeholder="Number of Guests" min="1">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="category-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-white">Equipment Categories</h2>
                <p class="text-muted">Browse our extensive inventory of professional event equipment</p>
            </div>
            <div class="row g-4">
                @foreach($categories as $category)
                    <div class="col-lg-3 col-md-6">
                        <a href="{{ route('categories.show', $category->slug) }}" class="text-decoration-none">
                            <div class="category-card">
                                <i class="{{ $category->icon ?? 'fas fa-box' }}"></i>
                                <h4>{{ $category->name }}</h4>
                                <p class="text-muted">{{ $category->description }}</p>
                                <span class="btn btn-outline-primary btn-sm">View All</span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="product-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-white">Popular Equipment</h2>
                <p class="text-muted">Most rented items this month</p>
            </div>
            <div class="row g-4">
                @foreach($featuredProducts as $product)
                    <div class="col-lg-3 col-md-6">
                        @include('components.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="package-section" id="packages">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-white">Event Packages</h2>
                <p class="text-muted">Complete solutions for your events</p>
            </div>
            <div class="row g-4">
                @foreach($packages as $package)
                    <div class="col-lg-4">
                        @include('components.package-card', ['package' => $package])
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-white">How It Works</h2>
                <p class="text-muted">Simple booking process with instant confirmation</p>
            </div>
            <div class="row g-4">
                <div class="col-md-3 text-center">
                    <div class="step-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h5 class="text-white">1. Browse & Select</h5>
                    <p class="text-muted">Choose equipment or packages based on your event needs</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h5 class="text-white">2. Check Availability</h5>
                    <p class="text-muted">Real-time availability for your event date</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h5 class="text-white">3. Book & Pay</h5>
                    <p class="text-muted">Secure payment via Stripe with instant confirmation</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h5 class="text-white">4. Delivery & Setup</h5>
                    <p class="text-muted">We deliver and set up everything at your venue</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Need Help Planning Your Event?</h2>
            <p class="lead mb-4">Our experts are here to help you choose the right equipment and services</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="tel:{{ config('settings.contact_phone') }}" class="btn btn-light btn-lg">
                    <i class="fas fa-phone me-2"></i>Call Us Now
                </a>
                <a href="https://wa.me/{{ config('settings.whatsapp_number') }}" class="btn btn-outline-light btn-lg">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp
                </a>
            </div>
        </div>
    </section>
@endsection