{{-- resources/views/frontend/cart/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Shopping Cart - KL Mobile Events')

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Cart Section -->
    <div class="container cart-page py-5">
        <h1 class="page-title mb-5">Shopping Cart</h1>

        @if($cartItems->count() > 0)
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="cart-items">
                        @foreach($cartItems as $item)
                            @include('frontend.cart.partials.cart-item', ['item' => $item])
                        @endforeach
                    </div>

                    <!-- Cart Actions -->
                    <div class="cart-actions">
                        <a href="{{ route('products.index') }}" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                        <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="fas fa-trash me-2"></i>Clear Cart
                        </button>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h4 class="summary-title">Order Summary</h4>
                        
                        <!-- Event Details Summary -->
                        @if(session('event_details'))
                            <div class="event-details-summary">
                                <h6>Event Information</h6>
                                <div class="detail-item">
                                    <span>Date:</span>
                                    <span>{{ session('event_details.date') }}</span>
                                </div>
                                <div class="detail-item">
                                    <span>Duration:</span>
                                    <span>{{ session('event_details.duration') }} days</span>
                                </div>
                            </div>
                        @endif

                        <!-- Price Breakdown -->
                        <div class="price-breakdown">
                            <div class="price-item">
                                <span>Subtotal</span>
                                <span>LKR {{ number_format($subtotal) }}</span>
                            </div>
                            @if($discount > 0)
                                <div class="price-item discount">
                                    <span>Discount</span>
                                    <span>-LKR {{ number_format($discount) }}</span>
                                </div>
                            @endif
                            <div class="price-item">
                                <span>Delivery</span>
                                <span>{{ $deliveryFee > 0 ? 'LKR ' . number_format($deliveryFee) : 'Free' }}</span>
                            </div>
                            <div class="price-item">
                                <span>Setup Fee</span>
                                <span>{{ $setupFee > 0 ? 'LKR ' . number_format($setupFee) : 'Included' }}</span>
                            </div>
                            @if($damageWaiver > 0)
                                <div class="price-item">
                                    <span>Damage Waiver <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Optional protection against accidental damage"></i></span>
                                    <span>LKR {{ number_format($damageWaiver) }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Coupon Code -->
                        <div class="coupon-section">
                            @if(!session('coupon'))
                                <form action="{{ route('cart.apply-coupon') }}" method="POST" class="coupon-form">
                                    @csrf
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               name="coupon_code" 
                                               placeholder="Enter coupon code">
                                        <button class="btn btn-outline-primary" type="submit">Apply</button>
                                    </div>
                                </form>
                            @else
                                <div class="applied-coupon">
                                    <span class="coupon-code">{{ session('coupon.code') }}</span>
                                    <form action="{{ route('cart.remove-coupon') }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-remove-coupon">×</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        <!-- Total -->
                        <div class="total-section">
                            <div class="total-item">
                                <span>Total</span>
                                <span class="total-amount">LKR {{ number_format($total) }}</span>
                            </div>
                            <p class="tax-note">Inclusive of all taxes</p>
                        </div>

                        <!-- Checkout Button -->
                        <a href="{{ route('checkout.index') }}" class="btn btn-primary btn-lg w-100">
                            Proceed to Checkout
                            <i class="fas fa-arrow-right ms-2"></i>
                        </a>

                        <!-- Security Badges -->
                        <div class="security-badges">
                            <img src="{{ asset('images/secure-checkout.png') }}" alt="Secure Checkout">
                            <img src="{{ asset('images/stripe-badge.png') }}" alt="Stripe Secure">
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty Cart -->
            <div class="empty-cart text-center py-5">
                <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
                <h3 class="mb-3">Your cart is empty</h3>
                <p class="text-muted mb-4">Looks like you haven't added any items to your cart yet.</p>
                <div class="empty-cart-actions">
                    <a href="{{ route('products.index') }}" class="btn btn-primary">
                        Browse Equipment
                    </a>
                    <a href="{{ route('packages.index') }}" class="btn btn-outline-primary">
                        View Packages
                    </a>
                </div>
            </div>
        @endif
    </div>

    <!-- Clear Cart Modal -->
    <div class="modal fade" id="clearCartModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Clear Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove all items from your cart?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="{{ route('cart.clear') }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Clear Cart</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
/* Cart Page Styles */
.cart-page {
    min-height: 60vh;
}

.page-title {
    color: var(--off-white);
    font-size: 36px;
    font-weight: 700;
}

/* Cart Items */
.cart-items {
    margin-bottom: 30px;
}

/* Cart Actions */
.cart-actions {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--border-dark);
}

/* Cart Summary */
.cart-summary {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    border: 1px solid var(--border-dark);
    position: sticky;
    top: 100px;
}

.summary-title {
    color: var(--off-white);
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 25px;
}

/* Event Details Summary */
.event-details-summary {
    background: var(--bg-dark);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.event-details-summary h6 {
    color: var(--primary-purple);
    font-size: 14px;
    margin-bottom: 10px;
    text-transform: uppercase;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    color: var(--text-gray);
    font-size: 14px;
    margin-bottom: 5px;
}

/* Price Breakdown */
.price-breakdown {
    padding: 20px 0;
    border-top: 1px solid var(--border-dark);
    border-bottom: 1px solid var(--border-dark);
}

.price-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    color: var(--text-gray);
}

.price-item:last-child {
    margin-bottom: 0;
}

.price-item.discount {
    color: var(--success-green);
}

.price-item i {
    font-size: 12px;
    margin-left: 5px;
    cursor: help;
}

/* Coupon Section */
.coupon-section {
    padding: 20px 0;
    border-bottom: 1px solid var(--border-dark);
}

.coupon-form .form-control {
    background: var(--bg-dark);
    border: 1px solid var(--border-dark);
    color: var(--off-white);
}

.coupon-form .form-control:focus {
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 0.2rem rgba(147, 51, 234, 0.25);
}

.applied-coupon {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(34, 197, 94, 0.1);
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid var(--success-green);
}

.coupon-code {
    color: var(--success-green);
    font-weight: 600;
    text-transform: uppercase;
}

.btn-remove-coupon {
    background: none;
    border: none;
    color: var(--success-green);
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

/* Total Section */
.total-section {
    padding: 20px 0;
}

.total-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.total-item span:first-child {
    color: var(--off-white);
    font-size: 20px;
    font-weight: 600;
}

.total-amount {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.tax-note {
    color: var(--text-gray);
    font-size: 12px;
    margin: 0;
}

/* Security Badges */
.security-badges {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-dark);
}

.security-badges img {
    height: 30px;
    opacity: 0.7;
}

/* Empty Cart */
.empty-cart {
    padding: 80px 20px;
}

.empty-cart h3 {
    color: var(--off-white);
}

.empty-cart-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Modal Styles */
.modal-content {
    background: var(--bg-card);
    color: var(--off-white);
    border: 1px solid var(--border-dark);
}

.modal-header {
    border-bottom: 1px solid var(--border-dark);
}

.modal-footer {
    border-top: 1px solid var(--border-dark);
}

.btn-close {
    filter: invert(1);
}

/* Responsive */
@media (max-width: 991px) {
    .cart-summary {
        position: static;
        margin-top: 30px;
    }
}

@media (max-width: 576px) {
    .page-title {
        font-size: 28px;
    }
    
    .cart-actions {
        flex-direction: column;
        gap: 10px;
    }
    
    .cart-actions .btn {
        width: 100%;
    }
    
    .empty-cart-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .empty-cart-actions .btn {
        width: 200px;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Clear cart confirmation
function clearCart() {
    const modal = new bootstrap.Modal(document.getElementById('clearCartModal'));
    modal.show();
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush