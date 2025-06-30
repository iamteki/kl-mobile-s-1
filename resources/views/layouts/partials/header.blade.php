{{-- resources/views/layouts/partials/header.blade.php --}}
<!-- Header Top Bar -->
<div class="header-top">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="contact-info">
                    <span><i class="fas fa-phone me-2"></i>{{ config('settings.contact_phone', '+94 11 234 5678') }}</span>
                    <span class="ms-3"><i class="fas fa-envelope me-2"></i>{{ config('settings.contact_email', 'info@klmobileevents.com') }}</span>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <span><i class="fas fa-clock me-2"></i>Mon-Sat: 9:00 AM - 6:00 PM</span>
                <span class="ms-3">
                    <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Main Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="KL Mobile Events" height="40" class="d-none">
            <span class="brand-text">KL Mobile Events</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
                </li>
                
                <!-- Equipment Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        Equipment
                    </a>
                    <ul class="dropdown-menu mega-menu">
                        <div class="row">
                            @php
                                $equipmentCategories = \App\Models\Category::where('type', 'equipment')
                                    ->where('parent_id', null)
                                    ->where('status', 'active')
                                    ->with(['children' => function($q) {
                                        $q->where('status', 'active')->orderBy('name');
                                    }])
                                    ->orderBy('name')
                                    ->get();
                            @endphp
                            
                            @foreach($equipmentCategories->chunk(ceil($equipmentCategories->count() / 2)) as $chunk)
                                <div class="col-md-6">
                                    @foreach($chunk as $category)
                                        <div class="mega-menu-section">
                                            <h6 class="mega-menu-title">
                                                <a href="{{ route('categories.show', $category->slug) }}">
                                                    <i class="{{ $category->icon ?? 'fas fa-box' }} me-2"></i>
                                                    {{ $category->name }}
                                                </a>
                                            </h6>
                                            @if($category->children->count() > 0)
                                                <ul class="mega-menu-list">
                                                    @foreach($category->children as $child)
                                                        <li>
                                                            <a href="{{ route('categories.show', $child->slug) }}">
                                                                {{ $child->name }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <div class="mega-menu-footer">
                            <a href="{{ route('categories.index', ['type' => 'equipment']) }}" class="btn btn-sm btn-primary">
                                View All Equipment <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </ul>
                </li>
                
                <!-- Services Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        Services
                    </a>
                    <ul class="dropdown-menu">
                        @php
                            $serviceCategories = \App\Models\Category::where('type', 'service')
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->get();
                        @endphp
                        
                        @foreach($serviceCategories as $category)
                            <li>
                                <a class="dropdown-item" href="{{ route('categories.show', $category->slug) }}">
                                    <i class="{{ $category->icon ?? 'fas fa-concierge-bell' }} me-2"></i>
                                    {{ $category->name }}
                                </a>
                            </li>
                        @endforeach
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('categories.index', ['type' => 'service']) }}">
                                View All Services
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Packages -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('packages.*') ? 'active' : '' }}" href="{{ route('packages.index') }}">
                        Packages
                    </a>
                </li>
                
                <!-- About -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">
                        About
                    </a>
                </li>
                
                <!-- Contact -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">
                        Contact
                    </a>
                </li>
            </ul>
            
            <!-- Right Side -->
            <div class="d-flex align-items-center">
                <!-- Cart Icon -->
                <livewire:cart-icon />
                
                <!-- User Menu -->
                @guest
                    <a href="" class="btn btn-outline-light btn-sm ms-3">
                        <i class="fas fa-user me-2"></i>Login
                    </a>
                @else
                    <div class="dropdown ms-3">
                        <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>{{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('account.dashboard') }}">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('account.bookings') }}">
                                    <i class="fas fa-calendar-check me-2"></i>My Bookings
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('account.profile') }}">
                                    <i class="fas fa-user-edit me-2"></i>Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endguest
                
                <!-- Book Now Button -->
                <a href="{{ route('booking.create') }}" class="btn btn-primary ms-3 book-now-btn">
                    <i class="fas fa-calendar-plus me-2"></i>Book Now
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
/* Header Styles */
.header-top {
    background-color: var(--bg-darker);
    color: var(--text-gray);
    padding: 10px 0;
    font-size: 14px;
    border-bottom: 1px solid var(--border-dark);
}

.header-top i {
    color: var(--primary-purple);
}

.social-link {
    color: var(--text-gray);
    margin: 0 5px;
    transition: color 0.3s;
}

.social-link:hover {
    color: var(--primary-purple);
}

/* Navigation */
.navbar {
    background-color: var(--bg-dark) !important;
    padding: 15px 0;
    border-bottom: 1px solid var(--border-dark);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.navbar.scrolled {
    background-color: rgba(10, 10, 10, 0.95) !important;
    backdrop-filter: blur(20px);
    padding: 10px 0;
    box-shadow: 0 2px 30px rgba(147, 51, 234, 0.2);
}

.brand-text {
    font-weight: 700;
    font-size: 24px;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Mega Menu */
.mega-menu {
    width: 600px;
    padding: 20px;
    background-color: var(--bg-card);
    border: 1px solid var(--border-dark);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.mega-menu-section {
    margin-bottom: 20px;
}

.mega-menu-title {
    color: var(--primary-purple);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.mega-menu-title a {
    color: inherit;
    text-decoration: none;
}

.mega-menu-title a:hover {
    color: var(--secondary-purple);
}

.mega-menu-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.mega-menu-list li {
    margin-bottom: 5px;
}

.mega-menu-list a {
    color: var(--text-gray);
    text-decoration: none;
    font-size: 13px;
    transition: all 0.3s;
    display: block;
    padding: 3px 0;
}

.mega-menu-list a:hover {
    color: var(--secondary-purple);
    transform: translateX(5px);
}

.mega-menu-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-dark);
    text-align: center;
}

/* Book Now Button */
.book-now-btn {
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--accent-violet) 100%);
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
}

.book-now-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(147, 51, 234, 0.4);
}

/* Responsive */
@media (max-width: 991px) {
    .mega-menu {
        width: 100%;
    }
    
    .header-top {
        display: none;
    }
    
    .book-now-btn {
        width: 100%;
        margin-top: 10px;
    }
}
</style>

<script>
// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('mainNav');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});
</script>