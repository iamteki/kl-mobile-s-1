// Navigation functionality
export function initNavigation() {
    const navbar = document.querySelector('.navbar');
    
    if (!navbar) return;
    
    // Sticky navbar on scroll
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', initNavigation);