{{-- resources/views/layouts/partials/footer.blade.php --}}
<footer class="site-footer">
    <!-- Footer Top -->
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">
                <!-- Company Info -->
                <div class="col-lg-4">
                    <div class="footer-widget">
                        <h3 class="footer-brand">KL Mobile Events</h3>
                        <p class="footer-desc">
                            Your trusted partner for professional event equipment rental and services in Kuala Lumpur. 
                            Making your events memorable with quality equipment and exceptional service.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link" title="WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6">
                    <div class="footer-widget">
                        <h4 class="widget-title">Quick Links</h4>
                        <ul class="footer-links">
                            <li><a href="{{ route('home') }}">Home</a></li>
                            <li><a href="{{ route('about') }}">About Us</a></li>
                            <li><a href="{{ route('categories.index') }}">Equipment</a></li>
                            <li><a href="{{ route('packages.index') }}">Packages</a></li>
                            <li><a href="{{ route('contact') }}">Contact</a></li>
                            <li><a href="{{ route('booking.create') }}">Book Now</a></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Popular Categories -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-widget">
                        <h4 class="widget-title">Popular Categories</h4>
                        <ul class="footer-links">
                            @php
                                $popularCategories = \App\Models\Category::where('status', 'active')
                                    ->withCount('products')
                                    ->orderBy('products_count', 'desc')
                                    ->limit(6)
                                    ->get();
                            @endphp
                            @foreach($popularCategories as $category)
                                <li>
                                    <a href="{{ route('categories.show', $category->slug) }}">
                                        {{ $category->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-widget">
                        <h4 class="widget-title">Contact Info</h4>
                        <div class="contact-info">
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>{{ config('settings.office_address', 'Level 15, Menara KL, Jalan Sultan Ismail, 50250 Kuala Lumpur') }}</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <span>{{ config('settings.contact_phone', '+94 11 234 5678') }}</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <span>{{ config('settings.contact_email', 'info@klmobileevents.com') }}</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span>Mon - Sat: 9:00 AM - 6:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="copyright">
                        &copy; {{ date('Y') }} KL Mobile Events. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <ul class="footer-bottom-links">
                        <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}">Terms & Conditions</a></li>
                        <li><a href="{{ route('sitemap') }}">Sitemap</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top" title="Back to Top">
        <i class="fas fa-chevron-up"></i>
    </button>
</footer>

<style>
/* Footer Styles */
.site-footer {
    background-color: var(--bg-darker);
    color: var(--text-gray);
    margin-top: 80px;
}

.footer-top {
    padding: 60px 0 40px;
    border-bottom: 1px solid var(--border-dark);
}

.footer-brand {
    font-size: 28px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 20px;
}

.footer-desc {
    color: var(--text-gray);
    line-height: 1.8;
    margin-bottom: 25px;
}

.widget-title {
    color: var(--off-white);
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    position: relative;
    padding-bottom: 10px;
}

.widget-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 40px;
    height: 2px;
    background: var(--primary-purple);
}

/* Social Links */
.social-links {
    display: flex;
    gap: 10px;
}

.social-link {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--bg-card);
    color: var(--text-gray);
    border-radius: 50%;
    transition: all 0.3s;
}

.social-link:hover {
    background-color: var(--primary-purple);
    color: white;
    transform: translateY(-3px);
}

/* Footer Links */
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 10px;
}

.footer-links a {
    color: var(--text-gray);
    text-decoration: none;
    transition: all 0.3s;
    display: inline-block;
}

.footer-links a:hover {
    color: var(--secondary-purple);
    transform: translateX(5px);
}

/* Contact Info */
.info-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 15px;
}

.info-item i {
    color: var(--primary-purple);
    margin-top: 3px;
    width: 20px;
}

/* Footer Bottom */
.footer-bottom {
    background-color: var(--bg-dark);
    padding: 20px 0;
}

.copyright {
    margin: 0;
    color: var(--text-gray);
}

.footer-bottom-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    gap: 20px;
    justify-content: flex-end;
}

.footer-bottom-links a {
    color: var(--text-gray);
    text-decoration: none;
    transition: color 0.3s;
}

.footer-bottom-links a:hover {
    color: var(--secondary-purple);
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary-purple) 0%, var(--accent-violet) 100%);
    color: white;
    border: none;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 1000;
}

.back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(147, 51, 234, 0.4);
}

.back-to-top.show {
    display: flex;
}

/* Responsive */
@media (max-width: 768px) {
    .footer-top {
        padding: 40px 0 30px;
    }
    
    .footer-bottom-links {
        justify-content: center;
        margin-top: 15px;
    }
    
    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
    }
}
</style>

<script>
// Back to Top Button
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.getElementById('backToTop');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
</script>