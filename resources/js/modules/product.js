// ===================================
// resources/js/modules/product.js
// Product page functionality
// ===================================

export function initProductPage() {
    // Image gallery
    initImageGallery();
    
    // Quantity controls
    initQuantityControls();
    
    // Variation selection
    initVariationSelection();
    
    // Date picker
    initDatePicker();
    
    // Calendar
    initAvailabilityCalendar();
}

function initImageGallery() {
    const mainImage = document.getElementById('mainImage');
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImageContainer = document.querySelector('.main-image-container');
    
    if (!mainImage) return;

    // Thumbnail click
    window.changeImage = function(src) {
        mainImage.src = src;
        
        // Update active thumbnail
        thumbnails.forEach(thumb => thumb.classList.remove('active'));
        event.currentTarget.classList.add('active');
    };

    // Zoom on hover
    if (mainImageContainer) {
        mainImageContainer.addEventListener('mousemove', (e) => {
            const rect = mainImageContainer.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            
            mainImage.style.transformOrigin = `${x}% ${y}%`;
            mainImage.style.transform = 'scale(1.5)';
        });

        mainImageContainer.addEventListener('mouseleave', () => {
            mainImage.style.transform = 'scale(1)';
        });
    }
}

function initQuantityControls() {
    const quantityInput = document.getElementById('quantity');
    
    if (!quantityInput) return;

    window.increaseQty = function() {
        const max = parseInt(quantityInput.max);
        const current = parseInt(quantityInput.value);
        if (current < max) {
            quantityInput.value = current + 1;
            quantityInput.dispatchEvent(new Event('change'));
        }
    };

    window.decreaseQty = function() {
        const min = parseInt(quantityInput.min);
        const current = parseInt(quantityInput.value);
        if (current > min) {
            quantityInput.value = current - 1;
            quantityInput.dispatchEvent(new Event('change'));
        }
    };

    // Update price on quantity change
    quantityInput.addEventListener('change', calculatePrice);
}

function initVariationSelection() {
    const variationOptions = document.querySelectorAll('.variation-option:not(.unavailable)');
    
    variationOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected from siblings
            this.parentElement.querySelectorAll('.variation-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            // Add selected to clicked
            this.classList.add('selected');
            
            // Update price if needed
            const priceModifier = this.dataset.priceModifier;
            if (priceModifier) {
                updateVariationPrice(priceModifier);
            }
            
            // Check availability for selected variation
            checkVariationAvailability(this.dataset.variationId);
        });
    });
}

function initDatePicker() {
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (!startDate || !endDate) return;

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    startDate.min = today;
    endDate.min = today;

    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
        checkAvailability();
        calculatePrice();
    });

    endDate.addEventListener('change', function() {
        checkAvailability();
        calculatePrice();
    });
}

function initAvailabilityCalendar() {
    const calendarDays = document.querySelectorAll('.calendar-day.available');
    
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            this.classList.toggle('selected');
            updateSelectedDates();
        });
    });
}

function calculatePrice() {
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const quantity = document.getElementById('quantity');
    const priceDisplay = document.querySelector('.price-display');
    const priceNote = document.querySelector('.price-note');
    
    if (!startDate.value || !endDate.value) return;

    const start = new Date(startDate.value);
    const end = new Date(endDate.value);
    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    
    // Get base price from data attribute
    const basePrice = parseInt(priceDisplay.dataset.basePrice || 15000);
    
    // Calculate tier pricing
    let pricePerDay = basePrice;
    if (days >= 3 && days <= 5) {
        pricePerDay = basePrice * 0.9; // 10% discount
    } else if (days >= 6) {
        pricePerDay = basePrice * 0.8; // 20% discount
    }
    
    const qty = parseInt(quantity.value);
    const totalPrice = days * pricePerDay * qty;
    
    // Update display
    priceDisplay.textContent = `LKR ${totalPrice.toLocaleString()}`;
    priceNote.textContent = `${days} days × ${qty} unit(s) • LKR ${pricePerDay.toLocaleString()}/day`;
}

function checkAvailability() {
    const productId = document.querySelector('[data-product-id]').dataset.productId;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const quantity = document.getElementById('quantity').value;
    
    if (!startDate || !endDate) return;

    fetch(`/products/${productId}/check-availability`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            start_date: startDate,
            end_date: endDate,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        updateAvailabilityDisplay(data);
    })
    .catch(error => {
        console.error('Availability check error:', error);
    });
}

function updateAvailabilityDisplay(data) {
    const availabilityStatus = document.querySelector('.availability-status');
    const addToCartBtn = document.querySelector('.btn-add-to-cart');
    
    if (data.available) {
        availabilityStatus.innerHTML = `<i class="fas fa-check-circle"></i> ${data.quantity} Units Available`;
        availabilityStatus.className = 'availability-status in-stock';
        addToCartBtn.disabled = false;
    } else {
        availabilityStatus.innerHTML = `<i class="fas fa-times-circle"></i> Not Available`;
        availabilityStatus.className = 'availability-status out-stock';
        addToCartBtn.disabled = true;
    }
}