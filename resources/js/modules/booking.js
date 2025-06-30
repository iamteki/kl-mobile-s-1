// ===================================
// resources/js/modules/booking.js
// Booking process functionality
// ===================================

export function initBooking() {
    const bookingForm = document.getElementById('bookingForm');
    
    if (!bookingForm) return;
    
    // Multi-step form
    initMultiStepForm();
    
    // Form validation
    initFormValidation();
    
    // Date/time pickers
    initBookingDatePickers();
    
    // Payment handling
    initPayment();
}

function initMultiStepForm() {
    const steps = document.querySelectorAll('.booking-step');
    const stepIndicators = document.querySelectorAll('.step-indicator');
    const nextButtons = document.querySelectorAll('.btn-next-step');
    const prevButtons = document.querySelectorAll('.btn-prev-step');
    
    let currentStep = 0;
    
    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            step.classList.toggle('active', index === stepIndex);
        });
        
        stepIndicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === stepIndex);
            indicator.classList.toggle('completed', index < stepIndex);
        });
        
        currentStep = stepIndex;
        
        // Update progress bar
        const progress = ((stepIndex + 1) / steps.length) * 100;
        document.querySelector('.progress-bar').style.width = `${progress}%`;
    }
    
    nextButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                showStep(currentStep + 1);
            }
        });
    });
    
    prevButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            showStep(currentStep - 1);
        });
    });
    
    // Initialize first step
    showStep(0);
}

function validateStep(stepIndex) {
    const currentStepElement = document.querySelectorAll('.booking-step')[stepIndex];
    const requiredFields = currentStepElement.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        showNotification('Please fill in all required fields', 'error');
    }
    
    return isValid;
}

function initFormValidation() {
    // Real-time validation
    const inputs = document.querySelectorAll('.form-control');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
}

function validateField(field) {
    // Remove previous error
    field.classList.remove('is-invalid');
    
    // Required validation
    if (field.hasAttribute('required') && !field.value.trim()) {
        field.classList.add('is-invalid');
        return false;
    }
    
    // Email validation
    if (field.type === 'email' && field.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(field.value)) {
            field.classList.add('is-invalid');
            return false;
        }
    }
    
    // Phone validation
    if (field.type === 'tel' && field.value) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        if (!phoneRegex.test(field.value)) {
            field.classList.add('is-invalid');
            return false;
        }
    }
    
    return true;
}

function initBookingDatePickers() {
    const eventDate = document.getElementById('eventDate');
    const installationTime = document.getElementById('installationTime');
    const eventStartTime = document.getElementById('eventStartTime');
    const dismantleTime = document.getElementById('dismantleTime');
    
    // Set minimum date
    if (eventDate) {
        const minDate = new Date();
        minDate.setDate(minDate.getDate() + 3); // Minimum 3 days advance booking
        eventDate.min = minDate.toISOString().split('T')[0];
        
        eventDate.addEventListener('change', function() {
            // Update installation date options based on event date
            updateTimeOptions();
        });
    }
}

function initPayment() {
    const paymentForm = document.getElementById('paymentForm');
    
    if (!paymentForm) return;
    
    paymentForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = paymentForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        try {
            // Create payment intent
            const response = await fetch('/checkout/create-payment-intent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    amount: document.querySelector('[data-total-amount]').dataset.totalAmount
                })
            });
            
            const { clientSecret } = await response.json();
            
            // Initialize Stripe
            const stripe = Stripe(process.env.MIX_STRIPE_KEY);
            
            // Confirm payment
            const { error } = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: document.getElementById('cardholderName').value,
                        email: document.getElementById('email').value
                    }
                }
            });
            
            if (error) {
                showNotification(error.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Complete Booking';
            } else {
                // Payment successful
                paymentForm.submit();
            }
        } catch (error) {
            console.error('Payment error:', error);
            showNotification('Payment failed. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Complete Booking';
        }
    });
}

// Initialize all modules
document.addEventListener('DOMContentLoaded', function() {
    initFilters();
    initProductPage();
    initCart();
    initBooking();
});