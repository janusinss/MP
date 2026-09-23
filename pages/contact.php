<?php 
// pages/contact.php - FreshCart Market
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include __DIR__ . '/../includes/header.php'; 
?>

<section class="content-page-hero">
    <div class="container">
        <span class="content-kicker">Support & Partnerships</span>
        <h1 class="content-hero-title">We are here to assist<br>with every order and inquiry.</h1>
        <p class="content-hero-lead">
            Have a question about delivery times, farm sourcing, or wholesale supply? Our local team in Zamboanga City is ready to help you.
        </p>
    </div>
</section>

<div class="contact-section-container">
    <div class="container">
        <!-- Main Bento Contact Box -->
        <div class="contact-bento-shell mb-5">
            <div class="row g-0">
                <!-- Left Details Column (Dark Forest) -->
                <div class="col-lg-5">
                    <div class="contact-info-panel-modern">
                        <div>
                            <span class="contact-info-kicker">Direct Reach</span>
                            <h2 class="contact-info-title">Let's talk food.</h2>
                            <p class="contact-info-desc">
                                We operate directly in our fulfillment zone. Whether you are a household shopper, a restaurant chef, or a local farm grower, send us a note.
                            </p>
                        </div>

                        <ul class="contact-channels-list">
                            <li class="contact-channel-item">
                                <div class="contact-channel-icon">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div class="contact-channel-text">
                                    <span class="contact-channel-label">Fulfillment Center</span>
                                    <span class="contact-channel-val d-block">
                                        Maasin Z.C., Zamboanga City, 7000
                                    </span>
                                </div>
                            </li>
                            <li class="contact-channel-item">
                                <div class="contact-channel-icon">
                                    <i class="bi bi-envelope-fill"></i>
                                </div>
                                <div class="contact-channel-text">
                                    <span class="contact-channel-label">Email Support</span>
                                    <a href="mailto:janusdominic0@gmail.com" class="contact-channel-val">
                                        janusdominic0@gmail.com
                                    </a>
                                </div>
                            </li>
                            <li class="contact-channel-item">
                                <div class="contact-channel-icon">
                                    <i class="bi bi-telephone-fill"></i>
                                </div>
                                <div class="contact-channel-text">
                                    <span class="contact-channel-label">Phone & SMS</span>
                                    <a href="tel:+639948739200" class="contact-channel-val">
                                        +63 994 873 9200
                                    </a>
                                </div>
                            </li>
                            <li class="contact-channel-item">
                                <div class="contact-channel-icon">
                                    <i class="bi bi-clock-fill"></i>
                                </div>
                                <div class="contact-channel-text">
                                    <span class="contact-channel-label">Support Hours</span>
                                    <span class="contact-channel-val d-block">
                                        Mon – Sun: 6:00 AM – 8:00 PM PHT
                                    </span>
                                </div>
                            </li>
                        </ul>

                        <div class="contact-social-row">
                            <a href="https://www.facebook.com/notagirlgamer69" target="_blank" rel="noopener noreferrer" class="contact-social-link" aria-label="Facebook">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="https://www.instagram.com/janusinss/" target="_blank" rel="noopener noreferrer" class="contact-social-link" aria-label="Instagram">
                                <i class="bi bi-instagram"></i>
                            </a>
                            <a href="https://x.com/Syrupynut" target="_blank" rel="noopener noreferrer" class="contact-social-link" aria-label="Twitter">
                                <i class="bi bi-twitter-x"></i>
                            </a>
                            <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" rel="noopener noreferrer" class="contact-social-link" aria-label="LinkedIn">
                                <i class="bi bi-linkedin"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Form Column (Clean White) -->
                <div class="col-lg-7">
                    <div class="contact-form-panel-modern">
                        <h2 class="contact-form-title">Send Us a Message</h2>
                        <p class="contact-form-desc">
                            Select an inquiry topic below to direct your message to the appropriate department.
                        </p>

                        <!-- Topic Select Chips -->
                        <div class="mb-3">
                            <span class="contact-field-label">Inquiry Topic</span>
                            <div class="contact-topics-wrap" role="group" aria-label="Inquiry Topic Selection">
                                <button type="button" class="contact-topic-chip active" data-topic="Order Status">Order Status</button>
                                <button type="button" class="contact-topic-chip" data-topic="Produce Quality">Produce Quality</button>
                                <button type="button" class="contact-topic-chip" data-topic="Farmer Partnership">Farmer Partnership</button>
                                <button type="button" class="contact-topic-chip" data-topic="Wholesale & Bulk">Wholesale & Bulk</button>
                                <button type="button" class="contact-topic-chip" data-topic="General Inquiry">General Inquiry</button>
                            </div>
                        </div>

                        <form id="contactForm" onsubmit="handleContactSubmit(event)">
                            <input type="hidden" id="inquiry_topic" name="inquiry_topic" value="Order Status">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="contact-field-group">
                                        <label for="contact_name" class="contact-field-label">Your Name <span class="text-danger">*</span></label>
                                        <input type="text" id="contact_name" name="name" class="contact-input-modern" placeholder="e.g. Maria Santos" required autocomplete="name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="contact-field-group">
                                        <label for="contact_email" class="contact-field-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" id="contact_email" name="email" class="contact-input-modern" placeholder="maria@example.com" required autocomplete="email">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="contact-field-group">
                                        <label for="contact_phone" class="contact-field-label">Mobile Number <span class="text-muted small fw-normal">(Optional)</span></label>
                                        <input type="tel" id="contact_phone" name="phone" class="contact-input-modern" placeholder="+63 900 000 0000" autocomplete="tel">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="contact-field-group">
                                        <label for="contact_message" class="contact-field-label">Message Details <span class="text-danger">*</span></label>
                                        <textarea id="contact_message" name="message" class="contact-input-modern" rows="5" placeholder="Please describe how we can assist you with your order, product questions, or partnership..." required></textarea>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <button type="submit" id="submitBtn" class="btn-contact-submit">
                                        <span>Send Message</span>
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Help FAQ Bento -->
        <div class="pt-3">
            <div class="text-center mb-4">
                <span class="content-kicker">Helpful Answers</span>
                <h2 class="content-card-title fs-3">Frequently Asked Questions</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="contact-faq-card">
                        <div class="contact-faq-q">
                            <i class="bi bi-clock-history text-success"></i>
                            <span>When is same-day cutoff?</span>
                        </div>
                        <p class="contact-faq-a">
                            Orders confirmed before 10:00 AM PHT are harvested and delivered the same afternoon between 2:00 PM and 6:30 PM.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-faq-card">
                        <div class="contact-faq-q">
                            <i class="bi bi-shield-check text-success"></i>
                            <span>What if produce is damaged?</span>
                        </div>
                        <p class="contact-faq-a">
                            If any vegetable or fruit arrives bruised, text or email a photo within 24 hours. We issue an instant replacement or store credit.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-faq-card">
                        <div class="contact-faq-q">
                            <i class="bi bi-box2-heart text-success"></i>
                            <span>How do I return crates?</span>
                        </div>
                        <p class="contact-faq-a">
                            Simply place your previous insulated totes, ice gel packs, or crates outside on delivery day. Your driver will collect and log them.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Topic Chip Selector
    const topicChips = document.querySelectorAll('.contact-topic-chip');
    const topicInput = document.getElementById('inquiry_topic');
    
    topicChips.forEach(chip => {
        chip.addEventListener('click', function() {
            topicChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            if (topicInput) {
                topicInput.value = this.getAttribute('data-topic');
            }
        });
    });
});

function handleContactSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const form = document.getElementById('contactForm');
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Transmitting...';
    }

    setTimeout(function() {
        if (window.FreshToast) {
            FreshToast.success('Thank you for reaching out! Our team in Zamboanga City will follow up within 2 hours.', 'Inquiry Transmitted');
        } else {
            alert('Thank you for reaching out! Our team will follow up within 2 hours.');
        }

        form.reset();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>Message Sent</span> <i class="bi bi-check-circle-fill ms-1"></i>';
            setTimeout(() => {
                btn.innerHTML = '<span>Send Message</span> <i class="bi bi-send-fill ms-1"></i>';
            }, 3000);
        }
    }, 600);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>