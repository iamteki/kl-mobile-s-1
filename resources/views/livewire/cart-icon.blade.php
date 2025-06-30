{{-- resources/views/livewire/cart-icon.blade.php --}}
<div class="cart-icon-wrapper">
    <a href="{{ route('cart.index') }}" class="cart-icon" title="View Cart">
        <i class="fas fa-shopping-cart"></i>
        @if($itemCount > 0)
            <span class="cart-badge">{{ $itemCount }}</span>
        @endif
        <span class="cart-total d-none d-md-inline-block ms-2">
            LKR {{ number_format($totalAmount, 2) }}
        </span>
    </a>
</div>

<style>
.cart-icon-wrapper {
    position: relative;
    margin-left: 20px;
}

.cart-icon {
    color: var(--off-white);
    text-decoration: none;
    position: relative;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s;
}

.cart-icon:hover {
    color: var(--secondary-purple);
    transform: translateY(-2px);
}

.cart-icon i {
    font-size: 1.25rem;
}

.cart-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--primary-purple);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
    animation: pulse 2s infinite;
}

.cart-total {
    font-size: 0.9rem;
    font-weight: 500;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(147, 51, 234, 0.4);
    }
    70% {
        transform: scale(1.1);
        box-shadow: 0 0 0 10px rgba(147, 51, 234, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(147, 51, 234, 0);
    }
}
</style>