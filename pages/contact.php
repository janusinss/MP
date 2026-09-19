<?php 
// pages/contact.php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include __DIR__ . '/../includes/header.php'; 
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
                            <span class="opacity-75 small">Maasin Z.C<br>Zamboanga City, 7000</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon-circle"><i class="bi bi-envelope-fill"></i></div>
                        <div>
                            <span class="d-block fw-bold mb-1">Email Us</span>
                            <span class="opacity-75 small">janusdominic0@gmail.com</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon-circle"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <span class="d-block fw-bold mb-1">Call Us</span>
                            <span class="opacity-75 small">+63 994 873 9200</span>
                        </div>
                    </li>
                </ul>

                <div class="contact-social-pills">
                    <a href="https://www.facebook.com/notagirlgamer69" target="_blank" class="contact-social-btn"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/janusinss/" target="_blank" class="contact-social-btn"><i class="bi bi-instagram"></i></a>
                    <a href="https://x.com/Syrupynut" target="_blank" class="contact-social-btn"><i class="bi bi-twitter"></i></a>
                    <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" class="contact-social-btn"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>

            <div class="col-lg-7 contact-form-panel">
                <h3 class="mb-4" style="font-family: var(--font-serif); font-weight: 700;">Send a Message</h3>
                
                <form action="#" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6 form-group-modern">
                            <label class="label-modern">Your Name</label>
                            <input type="text" class="input-modern" placeholder="John Doe" required>
                        </div>
                        <div class="col-md-6 form-group-modern">
                            <label class="label-modern">Email Address</label>
                            <input type="email" class="input-modern" placeholder="john@example.com" required>
                        </div>
                        <div class="col-12 form-group-modern">
                            <label class="label-modern">Subject</label>
                            <input type="text" class="input-modern" placeholder="How can we help?" required>
                        </div>
                        <div class="col-12 form-group-modern">
                            <label class="label-modern">Message</label>
                            <textarea class="input-modern" rows="5" placeholder="Write your message here..." required></textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="button" class="btn btn-primary rounded-pill px-5 py-3 shadow-sm fw-bold" onclick="alert('Thank you for reaching out! We will reply to your inquiry shortly.');">
                                Send Message <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>