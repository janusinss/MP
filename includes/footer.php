<?php
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$pos = strpos($scriptName, '/grocery_app');
if ($pos !== false) {
    $rootPath = substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
} else {
    $rootPath = '/';
}
?>
    <footer class="site-footer" id="siteFooter">
        <!-- Top Agricultural Sourcing Trust Strip -->
        <div class="footer-trust-banner">
            <div class="container">
                <div class="footer-trust-grid">
                    <div class="footer-trust-item">
                        <span class="footer-trust-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
                        <div class="footer-trust-text">
                            <strong>Direct Farm Traceability</strong>
                            <span>Every item linked to parcel origin</span>
                        </div>
                    </div>
                    <div class="footer-trust-divider" aria-hidden="true"></div>
                    <div class="footer-trust-item">
                        <span class="footer-trust-icon" aria-hidden="true"><i class="bi bi-snow2"></i></span>
                        <div class="footer-trust-text">
                            <strong>4&deg;C Cold-Chain Transit</strong>
                            <span>Insulated zero-heat degraded packing</span>
                        </div>
                    </div>
                    <div class="footer-trust-divider" aria-hidden="true"></div>
                    <div class="footer-trust-item">
                        <span class="footer-trust-icon" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span>
                        <div class="footer-trust-text">
                            <strong>78% Direct Grower Revenue</strong>
                            <span>Fair price to independent family farms</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row g-4 g-lg-5 footer-main-row">
                
                <!-- Col 1: Brand & Sourcing Statement -->
                <div class="col-lg-4 col-md-6 col-12 footer-brand-col">
                    <a href="<?= $rootPath ?: './' ?>" class="footer-brand-anchor" aria-label="FreshCart Home">
                        <span class="footer-brand">FreshCart<span class="footer-brand-dot">.</span></span>
                    </a>
                    <p class="footer-brand-desc">
                        Direct agricultural sourcing from independent family farms practicing certified regenerative agriculture. No multi-week cold storage or speculative broker markups.
                    </p>
                    <div class="footer-social-wrap">
                        <a href="https://www.facebook.com/notagirlgamer69" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on Facebook">
                            <i class="bi bi-facebook" aria-hidden="true"></i>
                        </a>
                        <a href="https://www.instagram.com/janusinss/" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on Instagram">
                            <i class="bi bi-instagram" aria-hidden="true"></i>
                        </a>
                        <a href="https://x.com/Syrupynut" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on Twitter">
                            <i class="bi bi-twitter-x" aria-hidden="true"></i>
                        </a>
                        <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on LinkedIn">
                            <i class="bi bi-linkedin" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <!-- Col 2: Shop Catalog -->
                <div class="col-lg-2 col-md-3 col-6 footer-nav-col">
                    <h6 class="footer-heading">Shop Aisles</h6>
                    <ul class="footer-link-list">
                        <li><a href="<?= $rootPath ?: './' ?>#shop" class="footer-link">All Harvests</a></li>
                        <li><a href="<?= $rootPath ?: './' ?>#categories" class="footer-link">Fresh Produce</a></li>
                        <li><a href="<?= $rootPath ?: './' ?>#categories" class="footer-link">Dairy &amp; Eggs</a></li>
                        <li><a href="<?= $rootPath ?: './' ?>#categories" class="footer-link">Artisan Bakery</a></li>
                    </ul>
                </div>

                <!-- Col 3: Company & Integrity -->
                <div class="col-lg-2 col-md-3 col-6 footer-nav-col">
                    <h6 class="footer-heading">Standards</h6>
                    <ul class="footer-link-list">
                        <li><a href="<?= $rootPath ?>about" class="footer-link">About Us</a></li>
                        <li><a href="<?= $rootPath ?>sustainability" class="footer-link">Sustainability</a></li>
                        <li><a href="<?= $rootPath ?>farmers" class="footer-link">Our Farmers</a></li>
                        <li><a href="<?= $rootPath ?>contact" class="footer-link">Contact</a></li>
                    </ul>
                </div>

                <!-- Col 4: Newsletter & Verification -->
                <div class="col-lg-4 col-md-12 col-12 footer-newsletter-col">
                    <h6 class="footer-heading">Stay Fresh</h6>
                    <p class="footer-newsletter-desc">Morning harvest alerts and seasonal heirloom arrivals. Zero spam.</p>
                    
                    <form id="footerNewsletterForm" class="footer-newsletter-form" onsubmit="handleFooterNewsletter(event)">
                        <label for="footerEmailInput" class="visually-hidden">Email address</label>
                        <div class="footer-newsletter-group">
                            <input type="email" id="footerEmailInput" class="footer-email-input" placeholder="Enter your email" required autocomplete="email">
                            <button class="footer-subscribe-btn" type="submit" id="footerSubscribeBtn">
                                <span>Join</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="footerNewsletterMsg" class="footer-newsletter-msg" role="status" aria-live="polite"></div>
                    </form>

                    <div class="footer-payment-section">
                        <span class="footer-payment-title">Guaranteed Secure Checkout</span>
                        <div class="footer-payment-badges">
                            <span class="payment-badge"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i> VISA</span>
                            <span class="payment-badge"><i class="bi bi-credit-card" aria-hidden="true"></i> Mastercard</span>
                            <span class="payment-badge"><i class="bi bi-paypal" aria-hidden="true"></i> PayPal</span>
                            <span class="payment-badge"><i class="bi bi-phone" aria-hidden="true"></i> GCash</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer Bottom Legal & Attribution Bar -->
            <div class="footer-bottom-bar">
                <div class="footer-bottom-left">
                    <span class="footer-copyright">&copy; <?= date('Y') ?> FreshCart Market. Student Project by <strong class="footer-author-name">Janus Dominic</strong>.</span>
                </div>
                <div class="footer-bottom-right">
                    <span class="footer-ssl-badge"><i class="bi bi-shield-check text-brand me-1" aria-hidden="true"></i>256-Bit SSL Encrypted</span>
                    <a href="<?= $rootPath ?>privacy" class="footer-legal-link">Privacy Policy</a>
                    <span class="footer-legal-sep" aria-hidden="true">&bull;</span>
                    <a href="<?= $rootPath ?>terms" class="footer-legal-link">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-bg-dark border-0 rounded-4 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex p-2">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-bag-check-fill text-success fs-5"></i>
                    <span>Item added to cart!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function handleFooterNewsletter(e) {
            e.preventDefault();
            const input = document.getElementById('footerEmailInput');
            const msg = document.getElementById('footerNewsletterMsg');
            const btn = document.getElementById('footerSubscribeBtn');
            if (input && input.value.trim()) {
                btn.disabled = true;
                btn.innerHTML = '<span>Joined</span> <i class="bi bi-check2" aria-hidden="true"></i>';
                if (msg) {
                    msg.textContent = 'Subscribed! Welcome to morning harvest drops.';
                    msg.className = 'footer-newsletter-msg is-success';
                }
                setTimeout(function() {
                    input.value = '';
                    btn.disabled = false;
                    btn.innerHTML = '<span>Join</span> <i class="bi bi-arrow-right" aria-hidden="true"></i>';
                }, 3500);
            }
        }

        // Global AJAX Add to Cart (Works on every page)
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.classList.contains('add-cart-form')) {
                e.preventDefault();
                const formData = new FormData(e.target);
                fetch('<?= $rootPath ?>cart/add.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const badge = document.getElementById('cart-badge');
                        if (badge) badge.innerText = data.cart_count;
                        else location.reload();
                        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                        toast.show();
                    } else if (data.status === 'login_required') {
                        window.location.href = '<?= $rootPath ?>auth/login.php';
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    </script>
</body>
</html>