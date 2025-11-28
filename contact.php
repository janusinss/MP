<?php 
include 'db.php'; 
session_start();
include 'includes/header.php'; 
?>

<div class="contact-section-wrapper">
    <div class="container">
        
        <div class="contact-card-modern">
            
            <div class="col-lg-5 contact-info-panel">
                <h1 class="contact-heading-serif">Get in touch</h1>
                <p class="opacity-75 mb-5">
                    Have a question about your order, organic sourcing, or want to partner with us? We'd love to hear from you.
                </p>

                <ul class="contact-details-list">
                    <li>
                        <div class="contact-icon-circle"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <span class="d-block fw-bold mb-1">Visit Us</span>
                            <span class="opacity-75 small">123 Market Street<br>San Francisco, CA 94103</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon-circle"><i class="bi bi-envelope-fill"></i></div>
                        <div>
                            <span class="d-block fw-bold mb-1">Email Us</span>
                            <span class="opacity-75 small">hello@freshcart.com</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon-circle"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <span class="d-block fw-bold mb-1">Call Us</span>
                            <span class="opacity-75 small">+1 (555) 123-4567</span>
                        </div>
                    </li>
                </ul>

                <div class="mt-auto">
                    <p class="small text-uppercase fw-bold opacity-75 mb-3">Follow our journey</p>
                    <div class="contact-socials">
                        <a href="https://www.facebook.com/notagirlgamer69" target="_blank" class="social-btn-glass"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.instagram.com/janusinss/" target="_blank" class="social-btn-glass"><i class="bi bi-instagram"></i></a>
                        <a href="https://x.com/Syrupynut" target="_blank" class="social-btn-glass"><i class="bi bi-twitter"></i></a>
                        <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" class="social-btn-glass"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 contact-form-panel">
                <form action="#" method="POST"> <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-custom">First Name</label>
                            <input type="text" class="form-control form-control-pill" placeholder="e.g. Janus">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Last Name</label>
                            <input type="text" class="form-control form-control-pill" placeholder="e.g. Dominic">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Email Address</label>
                            <input type="email" class="form-control form-control-pill" placeholder="name@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Subject (Optional)</label>
                            <select class="form-select form-control-pill">
                                <option>Order Inquiry</option>
                                <option>Product Question</option>
                                <option>Partnership</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Message</label>
                            <textarea class="form-control form-control-pill" rows="5" placeholder="How can we help you?"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-send-message">
                                Send Message <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>