{{-- resources/views/frontend/cart/partials/cart-item.blade.php --}}
<div class="cart-item" data-item-id="{{ $item->id }}">
    <div class="row align-items-center">
        <!-- Item Image -->
        <div class="col-md-2">
            <div class="item-image">
                @if($item->itemable_type === 'App\Models\Product')
                    <img src="{{ $item->itemable->getFirstMediaUrl('products', 'thumb') }}" 
                         alt="{{ $item->itemable->name }}"
                         class="img-fluid">
                @else
                    <div class="package-icon">
                        <i class="{{ $item->itemable->icon ?? 'fas fa-gift' }}"></i>
                    </div>
                @endif
            </div>
        </div>

        <!-- Item Details -->
        <div class="col-md-4">
            <div class="item-details">
                <h5 class="item-name">
                    @if($item->itemable_type === 'App\Models\Product')
                        <a href="{{ route('products.show', $item->itemable->slug) }}">
                            {{ $item->itemable->name }}
                        </a>
                    @else
                        <a href="{{ route('packages.show', $item->itemable->slug) }}">
                            {{ $item->itemable->name }}
                        </a>
                    @endif
                </h5>
                
                @if($item->variation)
                    <div class="item-variation">
                        <span class="variation-label">Option:</span>
                        <span class="variation-value">{{ $item->variation->name }}</span>
                    </div>
                @endif

                @if($item->itemable_type === 'App\Models\Product')
                    <div class="item-specs">
                        @foreach($item->itemable->attributes->take(2) as $attr)
                            <span class="spec-item">
                                <i class="{{ $attr->template->icon ?? 'fas fa-check' }}"></i>
                                {{ $attr->value }}{{ $attr->template->unit }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="package-info">
                        <span class="info-item">
                            <i class="fas fa-users"></i>
                            {{ $item->itemable->min_capacity }}-{{ $item->itemable->max_capacity }} Guests
                        </span>
                        <span class="info-item">
                            <i class="fas fa-clock"></i>
                            {{ $item->itemable->duration }} Hours
                        </span>
                    </div>
                @endif

                <!-- Rental Period -->
                <div class="rental-period">
                    <i class="fas fa-calendar-alt"></i>
                    <span>{{ \Carbon\Carbon::parse($item->start_date)->format('M d, Y') }}</span>
                    <span class="mx-1">to</span>
                    <span>{{ \Carbon\Carbon::parse($item->end_date)->format('M d, Y') }}</span>
                    <span class="rental-days">({{ $item->rental_days }} days)</span>
                </div>
            </div>
        </div>

        <!-- Quantity -->
        <div class="col-md-2">
            <div class="quantity-control">
                <label class="quantity-label">Quantity</label>
                <div class="quantity-wrapper">
                    <button type="button" class="qty-btn" onclick="updateQuantity({{ $item->id }}, 'decrease')">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" 
                           class="qty-input" 
                           value="{{ $item->quantity }}" 
                           min="1" 
                           max="{{ $item->itemable->inventory->available_quantity ?? 99 }}"
                           onchange="updateQuantity({{ $item->id }}, 'set', this.value)">
                    <button type="button" class="qty-btn" onclick="updateQuantity({{ $item->id }}, 'increase')">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Price -->
        <div class="col-md-2">
            <div class="item-price text-center">
                <div class="price-per-unit">
                    LKR {{ number_format($item->price_per_day) }}/day
                </div>
                <div class="total-price">
                    LKR {{ number_format($item->subtotal) }}
                </div>
            </div>
        </div>

        <!-- Remove -->
        <div class="col-md-2 text-end">
            <button type="button" class="btn-remove" onclick="removeItem({{ $item->id }})">
                <i class="fas fa-trash"></i>
                <span>Remove</span>
            </button>
        </div>
    </div>

    <!-- Item Notes -->
    @if($item->notes)
        <div class="item-notes">
            <i class="fas fa-sticky-note"></i>
            <span>{{ $item->notes }}</span>
        </div>
    @endif

    <!-- Availability Warning -->
    @if($item->itemable_type === 'App\Models\Product' && $item->itemable->inventory->available_quantity < $item->quantity)
        <div class="availability-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Only {{ $item->itemable->inventory->available_quantity }} units available</span>
        </div>
    @endif
</div>

<style>
/* Cart Item Styles */
.cart-item {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    border: 1px solid var(--border-dark);
    transition: all 0.3s;
}

.cart-item:hover {
    border-color: var(--primary-purple);
    box-shadow: 0 5px 20px rgba(147, 51, 234, 0.1);
}

/* Item Image */
.item-image {
    width: 100%;
    max-width: 120px;
    height: 100px;
    border-radius: 10px;
    overflow: hidden;
    background: var(--bg-dark);
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.package-icon {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.package-icon i {
    font-size: 48px;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Item Details */
.item-details {
    padding: 0 20px;
}

.item-name {
    font-size: 18px;
    margin-bottom: 10px;
}

.item-name a {
    color: var(--off-white);
    text-decoration: none;
    transition: color 0.3s;
}

.item-name a:hover {
    color: var(--primary-purple);
}

.item-variation {
    display: flex;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 14px;
}

.variation-label {
    color: var(--text-gray);
}

.variation-value {
    color: var(--primary-purple);
    font-weight: 500;
}

.item-specs,
.package-info {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.spec-item,
.info-item {
    color: var(--text-gray);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.spec-item i,
.info-item i {
    color: var(--primary-purple);
    font-size: 12px;
}

.rental-period {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-gray);
    font-size: 14px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border-dark);
}

.rental-period i {
    color: var(--primary-purple);
}

.rental-days {
    color: var(--primary-purple);
    font-weight: 500;
}

/* Quantity Control */
.quantity-control {
    text-align: center;
}

.quantity-label {
    display: block;
    color: var(--text-gray);
    font-size: 12px;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.quantity-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.qty-btn {
    width: 35px;
    height: 35px;
    background: var(--bg-dark);
    border: 1px solid var(--border-dark);
    color: var(--text-gray);
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.qty-btn:hover {
    background: var(--primary-purple);
    border-color: var(--primary-purple);
    color: white;
}

.qty-input {
    width: 60px;
    height: 35px;
    background: var(--bg-dark);
    border: 1px solid var(--border-dark);
    color: var(--off-white);
    text-align: center;
    border-radius: 5px;
    font-weight: 600;
}

.qty-input:focus {
    outline: none;
    border-color: var(--primary-purple);
}

/* Hide number input arrows */
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Item Price */
.item-price {
    text-align: right;
}

.price-per-unit {
    color: var(--text-gray);
    font-size: 14px;
    margin-bottom: 5px;
}

.total-price {
    color: var(--off-white);
    font-size: 20px;
    font-weight: 700;
}

/* Remove Button */
.btn-remove {
    background: none;
    border: none;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
}

.btn-remove:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-red);
}

.btn-remove i {
    font-size: 16px;
}

.btn-remove span {
    font-size: 14px;
    font-weight: 500;
}

/* Item Notes */
.item-notes {
    margin-top: 15px;
    padding: 10px 15px;
    background: var(--bg-dark);
    border-radius: 8px;
    color: var(--text-gray);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.item-notes i {
    color: var(--warning-yellow);
}

/* Availability Warning */
.availability-warning {
    margin-top: 15px;
    padding: 10px 15px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--danger-red);
    border-radius: 8px;
    color: var(--danger-red);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Loading State */
.cart-item.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .cart-item .row {
        gap: 20px;
    }
    
    .cart-item .col-md-2,
    .cart-item .col-md-4 {
        width: 100%;
        max-width: 100%;
        margin-bottom: 15px;
    }
    
    .item-image {
        margin: 0 auto;
    }
    
    .item-details {
        padding: 0;
        text-align: center;
    }
    
    .item-specs,
    .package-info,
    .rental-period {
        justify-content: center;
    }
    
    .quantity-control {
        margin: 20px 0;
    }
    
    .item-price {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .btn-remove {
        width: 100%;
        justify-content: center;
        background: var(--bg-dark);
    }
}
</style>

<script>
// Update item quantity
function updateQuantity(itemId, action, value = null) {
    const item = document.querySelector(`[data-item-id="${itemId}"]`);
    const input = item.querySelector('.qty-input');
    const currentQty = parseInt(input.value);
    const maxQty = parseInt(input.max);
    let newQty = currentQty;
    
    if (action === 'increase' && currentQty < maxQty) {
        newQty = currentQty + 1;
    } else if (action === 'decrease' && currentQty > 1) {
        newQty = currentQty - 1;
    } else if (action === 'set' && value) {
        newQty = Math.max(1, Math.min(parseInt(value), maxQty));
    }
    
    if (newQty !== currentQty) {
        // Update input
        input.value = newQty;
        
        // Show loading state
        item.classList.add('loading');
        
        // Make AJAX request
        fetch(`/cart/update/${itemId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ quantity: newQty })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                location.reload(); // Or update specific elements
            } else {
                // Revert on error
                input.value = currentQty;
                alert('Error updating quantity');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            input.value = currentQty;
        })
        .finally(() => {
            item.classList.remove('loading');
        });
    }
}

// Remove item from cart
function removeItem(itemId) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        const item = document.querySelector(`[data-item-id="${itemId}"]`);
        item.classList.add('loading');
        
        fetch(`/cart/remove/${itemId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Animate removal
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    location.reload(); // Or remove element and update totals
                }, 300);
            } else {
                alert('Error removing item');
                item.classList.remove('loading');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            item.classList.remove('loading');
        });
    }
}
</script>