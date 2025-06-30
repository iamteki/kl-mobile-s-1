{{-- resources/views/frontend/packages/show.blade.php --}}
@extends('layouts.app')

@section('title', $package->name . ' - Event Package | KL Mobile Events')

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('packages.index') }}">Event Packages</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $package->name }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Package Detail Section -->
    <div class="container package-detail-page" data-package-id="{{ $package->id }}">
        <div class="row">
            <!-- Package Images Gallery -->
            <div class="col-lg-6">
                <div class="package-gallery">
                    @if($package->getMedia('packages')->count() > 0)
                        <div class="main-image-container">
                            <img src="{{ $package->getFirstMediaUrl('packages', 'large') }}" 
                                 alt="{{ $package->name }}" 
                                 class="main-image" 
                                 id="mainPackageImage">
                            @if($package->is_featured)
                                <div class="image-badges">
                                    <span class="badge-custom">Most Popular</span>
                                </div>
                            @endif
                        </div>
                        
                        @if($package->getMedia('packages')->count() > 1)
                            <div class="thumbnail-container">
                                @foreach($package->getMedia('packages') as $index => $media)
                                    <div class="thumbnail {{ $index == 0 ? 'active' : '' }}" 
                                         onclick="changePackageImage('{{ $media->getUrl('large') }}')">
                                        <img src="{{ $media->getUrl('thumb') }}" alt="View {{ $index + 1 }}">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="package-icon-display">
                            <i class="{{ $package->icon ?? 'fas fa-gift' }}"></i>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Package Info -->
            <div class="col-lg-6">
                <div class="package-info-section">
                    <div class="package-header">
                        <h1 class="package-title">{{ $package->name }}</h1>
                        <p class="package-tagline">{{ $package->tagline }}</p>
                    </div>

                    <div class="price-section">
                        <div class="price-display">
                            <span class="currency">LKR</span>
                            <span class="amount">{{ number_format($package->price) }}</span>
                            <span class="period">{{ $package->price_type }}</span>
                        </div>
                        <div class="price-note">
                            <i class="fas fa-info-circle"></i>
                            Price includes delivery & setup within Colombo
                        </div>
                    </div>

                    <div class="package-highlights">
                        <div class="highlight-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <span class="label">Capacity</span>
                                <span class="value">{{ $package->min_capacity }}-{{ $package->max_capacity }} Guests</span>
                            </div>
                        </div>
                        <div class="highlight-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <span class="label">Duration</span>
                                <span class="value">{{ $package->duration }} Hours</span>
                            </div>
                        </div>
                        <div class="highlight-item">
                            <i class="fas fa-truck"></i>
                            <div>
                                <span class="label">Setup Time</span>
                                <span class="value">{{ $package->setup_time }} Hours Before</span>
                            </div>
                        </div>
                    </div>

                    <div class="package-description">
                        <h5>Package Overview</h5>
                        <p>{{ $package->description }}</p>
                    </div>

                    <!-- Booking Form -->
                    <div class="booking-form-section">
                        <h5>Check Availability & Book</h5>
                        <form id="packageBookingForm" action="{{ route('cart.add-package') }}" method="POST">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ $package->id }}">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Event Date</label>
                                    <input type="date" 
                                           class="form-control" 
                                           name="event_date" 
                                           id="eventDate" 
                                           min="{{ date('Y-m-d', strtotime('+3 days')) }}"
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Number of Guests</label>
                                    <input type="number" 
                                           class="form-control" 
                                           name="guest_count" 
                                           id="guestCount"
                                           min="{{ $package->min_capacity }}"
                                           max="{{ $package->max_capacity }}"
                                           value="{{ $package->min_capacity }}"
                                           required>
                                </div>
                            </div>

                            <div class="availability-status mt-3" id="availabilityStatus">
                                <!-- Availability will be shown here -->
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-4" id="addToCartBtn">
                                <i class="fas fa-shopping-cart me-2"></i>Add Package to Cart
                            </button>
                        </form>

                        <div class="booking-note">
                            <i class="fas fa-shield-alt"></i>
                            <span>Free cancellation up to 48 hours before event</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Package Contents -->
        <div class="package-contents-section">
            <div class="container">
                <h2 class="section-title">What's Included</h2>
                <div class="row g-4">
                    @foreach($package->items->groupBy('category') as $category => $items)
                        <div class="col-lg-6">
                            <div class="content-category">
                                <h5 class="category-title">
                                    <i class="{{ $categoryIcons[$category] ?? 'fas fa-box' }}"></i>
                                    {{ $category }}
                                </h5>
                                <ul class="items-list">
                                    @foreach($items as $item)
                                        <li>
                                            <div class="item-info">
                                                <span class="quantity">{{ $item->quantity }}x</span>
                                                <span class="name">{{ $item->product->name }}</span>
                                                @if($item->notes)
                                                    <span class="notes">({{ $item->notes }})</span>
                                                @endif
                                            </div>
                                            <a href="{{ route('products.show', $item->product->slug) }}" 
                                               class="view-item" 
                                               target="_blank">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Additional Services -->
                @if($package->additional_services)
                    <div class="additional-services">
                        <h5>Additional Services Included</h5>
                        <div class="services-grid">
                            @foreach($package->additional_services as $service)
                                <div class="service-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>{{ $service }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Package Gallery -->
        @if($package->gallery && count($package->gallery) > 0)
            <div class="package-gallery-section">
                <div class="container">
                    <h2 class="section-title">Event Gallery</h2>
                    <div class="gallery-grid">
                        @foreach($package->gallery as $image)
                            <div class="gallery-item">
                                <img src="{{ $image['url'] }}" 
                                     alt="{{ $image['caption'] ?? 'Event photo' }}"
                                     onclick="openLightbox('{{ $image['url'] }}')">
                                @if($image['caption'])
                                    <div class="gallery-caption">{{ $image['caption'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Related Packages -->
        @if($relatedPackages->count() > 0)
            <div class="related-packages-section">
                <div class="container">
                    <h2 class="section-title">Similar Packages</h2>
                    <div class="row g-4">
                        @foreach($relatedPackages as $related)
                            <div class="col-lg-4">
                                @include('components.package-card', ['package' => $related])
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Lightbox Modal -->
    <div id="lightboxModal" class="lightbox-modal" onclick="closeLightbox()">
        <span class="close-lightbox">&times;</span>
        <img id="lightboxImage" src="" alt="">
    </div>
@endsection

@push('styles')
<style>
/* Package Detail Styles */
.package-detail-page {
    padding: 50px 0;
}

/* Gallery Section */
.package-gallery {
    position: sticky;
    top: 100px;
}

.main-image-container {
    position: relative;
    border-radius: 15px;
    overflow: hidden;
    background: var(--bg-card);
    margin-bottom: 20px;
}

.main-image {
    width: 100%;
    height: auto;
    display: block;
}

.package-icon-display {
    height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-dark) 100%);
    border-radius: 15px;
}

.package-icon-display i {
    font-size: 120px;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.image-badges {
    position: absolute;
    top: 20px;
    left: 20px;
}

.badge-custom {
    background: var(--primary-purple);
    color: white;
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 14px;
}

.thumbnail-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
}

.thumbnail {
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid var(--border-dark);
    transition: all 0.3s;
}

.thumbnail:hover,
.thumbnail.active {
    border-color: var(--primary-purple);
}

.thumbnail img {
    width: 100%;
    height: 80px;
    object-fit: cover;
}

/* Package Info */
.package-info-section {
    padding-left: 50px;
}

.package-header {
    margin-bottom: 30px;
}

.package-title {
    color: var(--off-white);
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 10px;
}

.package-tagline {
    color: var(--text-gray);
    font-size: 18px;
}

/* Price Section */
.price-section {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    border: 1px solid var(--border-dark);
}

.price-display {
    text-align: center;
    margin-bottom: 15px;
}

.price-display .currency {
    color: var(--text-gray);
    font-size: 20px;
    margin-right: 5px;
}

.price-display .amount {
    font-size: 48px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.price-display .period {
    color: var(--text-gray);
    font-size: 16px;
    display: block;
}

.price-note {
    text-align: center;
    color: var(--success-green);
    font-size: 14px;
}

.price-note i {
    margin-right: 5px;
}

/* Package Highlights */
.package-highlights {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.highlight-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: var(--bg-card);
    border-radius: 10px;
    border: 1px solid var(--border-dark);
}

.highlight-item i {
    font-size: 24px;
    color: var(--primary-purple);
}

.highlight-item .label {
    display: block;
    color: var(--text-gray);
    font-size: 12px;
    text-transform: uppercase;
}

.highlight-item .value {
    display: block;
    color: var(--off-white);
    font-weight: 600;
}

/* Package Description */
.package-description {
    margin-bottom: 30px;
}

.package-description h5 {
    color: var(--off-white);
    font-size: 20px;
    margin-bottom: 15px;
}

.package-description p {
    color: var(--text-gray);
    line-height: 1.8;
}

/* Booking Form */
.booking-form-section {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    border: 1px solid var(--border-dark);
}

.booking-form-section h5 {
    color: var(--off-white);
    font-size: 20px;
    margin-bottom: 20px;
}

.booking-form-section .form-label {
    color: var(--text-gray);
    font-size: 14px;
    margin-bottom: 8px;
}

.booking-form-section .form-control {
    background: var(--bg-dark);
    border: 1px solid var(--border-dark);
    color: var(--off-white);
    padding: 12px 15px;
}

.booking-form-section .form-control:focus {
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 0.2rem rgba(147, 51, 234, 0.25);
}

.availability-status {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
}

.availability-status.available {
    background: rgba(34, 197, 94, 0.1);
    color: var(--success-green);
    border: 1px solid var(--success-green);
}

.availability-status.unavailable {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-red);
    border: 1px solid var(--danger-red);
}

.booking-note {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
    padding: 15px;
    background: rgba(34, 197, 94, 0.1);
    border-radius: 8px;
    color: var(--success-green);
    font-size: 14px;
}

/* Package Contents Section */
.package-contents-section {
    background: var(--bg-darker);
    padding: 80px 0;
    margin-top: 80px;
}

.section-title {
    color: var(--off-white);
    font-size: 32px;
    font-weight: 700;
    text-align: center;
    margin-bottom: 50px;
}

.content-category {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 25px;
    height: 100%;
    border: 1px solid var(--border-dark);
}

.category-title {
    color: var(--off-white);
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-title i {
    color: var(--primary-purple);
}

.items-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.items-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-dark);
}

.items-list li:last-child {
    border-bottom: none;
}

.item-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.item-info .quantity {
    background: var(--primary-purple);
    color: white;
    padding: 2px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
}

.item-info .name {
    color: var(--off-white);
}

.item-info .notes {
    color: var(--text-gray);
    font-size: 14px;
}

.view-item {
    color: var(--text-gray);
    transition: color 0.3s;
}

.view-item:hover {
    color: var(--primary-purple);
}

/* Additional Services */
.additional-services {
    margin-top: 40px;
    padding: 30px;
    background: var(--bg-card);
    border-radius: 15px;
    border: 1px solid var(--border-dark);
}

.additional-services h5 {
    color: var(--off-white);
    font-size: 20px;
    margin-bottom: 20px;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-gray);
}

.service-item i {
    color: var(--success-green);
}

/* Gallery Section */
.package-gallery-section {
    padding: 80px 0;
    background: var(--bg-dark);
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.gallery-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s;
}

.gallery-item:hover {
    transform: scale(1.05);
}

.gallery-item img {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

.gallery-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);
    color: white;
    padding: 20px 15px 15px;
    font-size: 14px;
}

/* Related Packages */
.related-packages-section {
    padding: 80px 0;
}

/* Lightbox */
.lightbox-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 2000;
    cursor: pointer;
}

.lightbox-modal img {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    max-width: 90%;
    max-height: 90%;
    border-radius: 10px;
}

.close-lightbox {
    position: absolute;
    top: 20px;
    right: 40px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s;
}

.close-lightbox:hover {
    color: var(--primary-purple);
}

/* Responsive */
@media (max-width: 991px) {
    .package-info-section {
        padding-left: 0;
        margin-top: 30px;
    }
    
    .package-gallery {
        position: static;
    }
    
    .package-highlights {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .package-title {
        font-size: 28px;
    }
    
    .price-display .amount {
        font-size: 36px;
    }
    
    .gallery-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Change main image
function changePackageImage(imageUrl) {
    document.getElementById('mainPackageImage').src = imageUrl;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.classList.remove('active');
    });
    event.target.closest('.thumbnail').classList.add('active');
}

// Lightbox functionality
function openLightbox(imageUrl) {
    const modal = document.getElementById('lightboxModal');
    const modalImg = document.getElementById('lightboxImage');
    modal.style.display = 'block';
    modalImg.src = imageUrl;
}

function closeLightbox() {
    document.getElementById('lightboxModal').style.display = 'none';
}

// Availability check
document.addEventListener('DOMContentLoaded', function() {
    const eventDateInput = document.getElementById('eventDate');
    const guestCountInput = document.getElementById('guestCount');
    const availabilityStatus = document.getElementById('availabilityStatus');
    const addToCartBtn = document.getElementById('addToCartBtn');
    
    function checkAvailability() {
        const eventDate = eventDateInput.value;
        const guestCount = guestCountInput.value;
        
        if (!eventDate) return;
        
        // Show loading
        availabilityStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking availability...';
        availabilityStatus.className = 'availability-status mt-3';
        
        // Make AJAX request
        fetch(`/packages/{{ $package->id }}/check-availability`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                event_date: eventDate,
                guest_count: guestCount
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                availabilityStatus.innerHTML = '<i class="fas fa-check-circle"></i> Package available for your date!';
                availabilityStatus.className = 'availability-status mt-3 available';
                addToCartBtn.disabled = false;
            } else {
                availabilityStatus.innerHTML = '<i class="fas fa-times-circle"></i> Package not available for this date';
                availabilityStatus.className = 'availability-status mt-3 unavailable';
                addToCartBtn.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error checking availability:', error);
        });
    }
    
    // Check availability on date change
    eventDateInput.addEventListener('change', checkAvailability);
    guestCountInput.addEventListener('change', checkAvailability);
});
</script>
@endpush