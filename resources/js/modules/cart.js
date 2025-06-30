// ===================================
// resources/js/modules/cart.js
// Cart functionality
// ===================================

export function initCart() {
    // Add to cart buttons
    const addToCartBtns = document.querySelectorAll('.btn-add-to-cart');
    
    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', handleAddToCart);
    });

    // Cart page functionality
    if (document.querySelector('.cart-page')) {
        initCartPage();
    }
}

function handleAddToCart(e) {
    e.preventDefault();
    
    const btn = e.currentTarget;
    const productData = collectProductData(btn);
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(productData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartIcon(data.cartCount);
            showNotification('Product added to cart!', 'success');
            
            // Reset button
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
        } else {
            showNotification(data.message || 'Error adding to cart', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
        }
    })
    .catch(error => {
        console.error('Add to cart error:', error);
        showNotification('Error adding to cart', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
    });
}

function collectProductData(btn) {
    const productCard = btn.closest('[data-product-id]');
    const productId = productCard.dataset.productId;
    
    // For product detail page
    if (document.querySelector('.product-detail-page')) {
        const selectedVariation = document.querySelector('.variation-option.selected');
        const startDate = document.getElementById('startDate')?.value;
        const endDate = document.getElementById('endDate')?.value;
        const quantity = document.getElementById('quantity')?.value || 1;
        
        return {
            product_id: productId,
            variation_id: selectedVariation?.dataset.variationId,
            start_date: startDate,
            end_date: endDate,
            quantity: quantity
        };
    }
    
    // For category/listing pages
    return {
        product_id: productId,
        quantity: 1
    };
}

function updateCartIcon(count) {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        cartCount.textContent = count;
        cartCount.classList.add('animate-bounce');
        setTimeout(() => {
            cartCount.classList.remove('animate-bounce');
        }, 500);
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

function initCartPage() {
    // Quantity updates
    const qtyInputs = document.querySelectorAll('.cart-qty-input');
    qtyInputs.forEach(input => {
        input.addEventListener('change', updateCartItem);
    });

    // Remove items
    const removeButtons = document.querySelectorAll('.btn-remove-item');
    removeButtons.forEach(btn => {
        btn.addEventListener('click', removeCartItem);
    });
}

function updateCartItem(e) {
    const input = e.target;
    const itemId = input.dataset.itemId;
    const quantity = input.value;
    
    fetch(`/cart/update/${itemId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ quantity })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartTotals(data);
        }
    });
}

function removeCartItem(e) {
    const btn = e.currentTarget;
    const itemId = btn.dataset.itemId;
    
    if (!confirm('Remove this item from cart?')) return;
    
    fetch(`/cart/remove/${itemId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove row
            btn.closest('.cart-item').remove();
            updateCartTotals(data);
            
            // Show empty message if no items
            if (data.cartCount === 0) {
                location.reload();
            }
        }
    });
}

function updateCartTotals(data) {
    // Update subtotal, tax, total
    document.querySelector('.cart-subtotal').textContent = `LKR ${data.subtotal.toLocaleString()}`;
    document.querySelector('.cart-tax').textContent = `LKR ${data.tax.toLocaleString()}`;
    document.querySelector('.cart-total').textContent = `LKR ${data.total.toLocaleString()}`;
    
    // Update cart icon
    updateCartIcon(data.cartCount);
}