<?php 
include 'db.php'; 
session_start();
include 'includes/header.php'; 
?>

<section class="legal-hero-modern">
    <div class="container">
        <span class="last-updated-badge">Last Updated: November 28, 2025</span>
        <h1 class="hero-display-text" style="font-size: 3.5rem;">Privacy Policy</h1>
        <p class="text-muted lead" style="max-width: 600px; margin: 0 auto;">
            We value your trust. Here is a clear breakdown of how we protect your data and respect your privacy at FreshCart.
        </p>
    </div>
</section>

<div class="container mb-5">
    <div class="legal-grid-layout">
        
        <aside>
            <div class="legal-toc-wrapper">
                <span class="legal-toc-title">Contents</span>
                <nav>
                    <a href="#intro" class="legal-nav-link"><i class="bi bi-info-circle"></i> Introduction</a>
                    <a href="#collection" class="legal-nav-link"><i class="bi bi-database"></i> Data Collection</a>
                    <a href="#usage" class="legal-nav-link"><i class="bi bi-check2-circle"></i> How We Use It</a>
                    <a href="#security" class="legal-nav-link"><i class="bi bi-shield-lock"></i> Security</a>
                    <a href="#contact" class="legal-nav-link"><i class="bi bi-envelope"></i> Contact Us</a>
                </nav>
            </div>
        </aside>

        <main class="policy-card-enhanced">
            
            <div id="intro" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-stars"></i> Introduction</h2>
                <p class="policy-text">
                    Welcome to FreshCart Market. We are committed to protecting your personal data and your right to privacy. 
                    When you visit our website <strong>freshcart.com</strong> ("Site"), and use our services, you trust us with your personal information. 
                    We take your privacy very seriously. In this privacy notice, we seek to explain to you in the clearest way possible what information we collect, how we use it, and what rights you have in relation to it.
                </p>
            </div>

            <div id="collection" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-person-vcard"></i> Information We Collect</h2>
                <p class="policy-text">We gather specific data to ensure your grocery delivery is accurate and timely. This includes:</p>
                
                <ul class="data-list">
                    <li>
                        <i class="bi bi-person-fill"></i>
                        <div>
                            <strong>Identity Data:</strong> Includes first name, last name, and username.
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-geo-alt-fill"></i>
                        <div>
                            <strong>Delivery Data:</strong> Billing address and shipping address for fulfilling your orders.
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-envelope-at-fill"></i>
                        <div>
                            <strong>Contact Data:</strong> Email address and telephone numbers.
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-pc-display-horizontal"></i>
                        <div>
                            <strong>Technical Data:</strong> Internet protocol (IP) address, browser type and version, time zone setting and location.
                        </div>
                    </li>
                </ul>
            </div>

            <div id="usage" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-graph-up-arrow"></i> How We Use Your Data</h2>
                <p class="policy-text">
                    We process your information for purposes based on legitimate business interests, the fulfillment of our contract with you, and compliance with our legal obligations.
                </p>
                <p class="policy-text mt-3">
                    Specifically, we use it to:
                </p>
                <ul class="policy-text" style="padding-left: 20px; line-height: 2;">
                    <li>Facilitate account creation and logon process.</li>
                    <li>Fulfill and manage your orders, payments, and returns.</li>
                    <li>Send you administrative information (e.g., order updates).</li>
                    <li>Request feedback to improve your FreshCart experience.</li>
                </ul>
            </div>

            <div id="security" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-shield-check"></i> Security Measures</h2>
                <p class="policy-text">
                    We have implemented appropriate technical and organizational security measures designed to protect the security of any personal information we process. 
                    However, please also remember that we cannot guarantee that the internet itself is 100% secure. Although we will do our best to protect your personal information, transmission of personal information to and from our Site is at your own risk.
                </p>
            </div>

            <div id="contact" class="policy-section mb-0">
                <h2 class="policy-heading"><i class="bi bi-chat-square-heart"></i> Contact Us</h2>
                <p class="policy-text">
                    If you have questions or comments about this policy, you may email us or contact us by post:
                </p>
                
                <div class="contact-highlight-box">
                    <h5 class="mb-3">FreshCart Privacy Team</h5>
                    <p class="mb-1"><i class="bi bi-envelope me-2"></i> hello@freshcart.com</p>
                    <p class="mb-0"><i class="bi bi-geo-alt me-2"></i> 123 Market Street, San Francisco, CA 94103</p>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>