<!-- Header Top Bar -->
<div class="header-top">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <i class="fas fa-phone me-2"></i> {{ config('settings.contact_phone') }}
                <span class="ms-3"><i class="fas fa-envelope me-2"></i> {{ config('settings.contact_email') }}</span>
            </div>
            <div class="col-md-6 text-end">
                <i class="fas fa-clock me-2"></i> Mon-Sat: 9:00 AM - 6:00 PM
            </div>
        </div>
    </div>
</div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Equipment</a>
                    <ul class="dropdown-menu">
                        @foreach($equipmentCategories as $category)
                            <li><a class="dropdown-item" href="{{ route('categories.show', $category->slug) }}">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Services</a>
                    <ul class="dropdown-menu">
                        @foreach($serviceCategories as $category)
                            <li><a class="dropdown-item" href="{{ route('categories.show', $category->slug) }}">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('packages.index') }}">Packages</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('about') }}">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('contact') }}">Contact</a>
                </li>
            </ul>
            <div class="d-flex">
                <livewire:cart-icon />
                @guest
                    <a href="{{ route('login') }}" class="btn btn-outline-primary me-2">Login</a>
                @else
                    <a href="{{ route('account.dashboard') }}" class="btn btn-outline-primary me-2">My Account</a>
                @endguest
                <a href="{{ route('booking.create') }}" class="btn btn-primary">Book Now</a>
            </div>
        </div>
    </div>
</nav>