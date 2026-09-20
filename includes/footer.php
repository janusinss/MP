<?php
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$pos = strpos($scriptName, '/grocery_app');
if ($pos !== false) {
    $rootPath = substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
} else {
    $rootPath = '/';
}
?>
    <footer class="site-footer">
        <div class="container">
            <div class="row g-5">
                
                <div class="col-lg-4 col-md-6">
                    <a href="<?= $rootPath ?: './' ?>" class="text-decoration-none">
                        <span class="footer-brand">FreshCart<span style="color: var(--accent-color)">.</span></span>
                    </a>
                    <p class="text-muted small lh-lg mb-4">
                        Delivering nature's best to your doorstep. We partner directly with local organic farmers to ensure freshness, sustainability, and fair trade practices.
                    </p>
                    <div class="d-flex">
                        <a href="https://www.facebook.com/notagirlgamer69" target="_blank" class="social-icon-link"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.instagram.com/janusinss/" target="_blank" class="social-icon-link"><i class="bi bi-instagram"></i></a>
                        <a href="https://x.com/Syrupynut" target="_blank" class="social-icon-link"><i class="bi bi-twitter"></i></a>
                        <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" class="social-icon-link"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="footer-heading">Shop</h6>
                    <ul class="list-unstyled footer-link-list">
                        <li><a href="<?= $rootPath ?: './' ?>" class="footer-link">All Products</a></li>
                        <li><a href="<?= $rootPath ?: './' ?>#categories" class="footer-link">Fresh Produce</a></li>
                        <li><a href="<?= $rootPath ?: './' ?>#categories" class="footer-link">Dairy & Eggs</a></li>
                        <li><a href="<?= $rootPath ?: './' ?>#categories" class="footer-link">Bakery</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="footer-heading">Company</h6>
                    <ul class="list-unstyled footer-link-list">
                        <li><a href="<?= $rootPath ?>about" class="footer-link">About Us</a></li>
                        <li><a href="<?= $rootPath ?>sustainability" class="footer-link">Sustainability</a></li>
                        <li><a href="<?= $rootPath ?>farmers" class="footer-link">Farmers</a></li>
                        <li><a href="<?= $rootPath ?>contact" class="footer-link">Contact</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-12">
                    <h6 class="footer-heading">Stay Fresh</h6>
                    <p class="small text-muted mb-3">Join our newsletter for exclusive organic deals and recipes.</p>
                    
                    <form action="#">
                        <div class="footer-newsletter-group">
                            <input type="email" class="footer-email-input" placeholder="Your email address">
                            <button class="footer-subscribe-btn" type="button">Join</button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <p class="small text-muted mb-2">Secure Payment</p>
                        <div class="d-flex flex-wrap">
                            <span class="payment-badge">VISA</span>
                            <span class="payment-badge">MasterCard</span>
                            <span class="payment-badge">PayPal</span>
                            <span class="payment-badge">GCash</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="border-top mt-5 pt-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
                <small class="text-muted mb-2 mb-md-0">&copy; 2025 FreshCart Market. Student Project by Janus Dominic.</small>
                <div class="small text-muted">
                    <a href="<?= $rootPath ?>privacy" class="text-decoration-none text-muted fw-bold me-3">Privacy Policy</a>
                    <a href="<?= $rootPath ?>terms" class="text-decoration-none text-muted fw-bold">Terms of Service</a>
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