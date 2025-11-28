<?php 
include 'db.php'; 
session_start();
include 'includes/header.php'; 
?>

<section class="legal-hero-modern">
    <div class="container">
        <span class="last-updated-badge">Effective Date: November 28, 2025</span>
        <h1 class="hero-display-text" style="font-size: 3.5rem;">Terms of Service</h1>
        <p class="text-muted lead" style="max-width: 600px; margin: 0 auto;">
            Please read these terms carefully before using our service. They define the rules and regulations for the use of FreshCart Market.
        </p>
    </div>
</section>

<div class="container mb-5">
    <div class="legal-grid-layout">
        
        <aside>
            <div class="legal-toc-wrapper">
                <span class="legal-toc-title">Table of Contents</span>
                <nav>
                    <a href="#acceptance" class="legal-nav-link"><i class="bi bi-file-earmark-check"></i> Acceptance</a>
                    <a href="#accounts" class="legal-nav-link"><i class="bi bi-person-badge"></i> User Accounts</a>
                    <a href="#products" class="legal-nav-link"><i class="bi bi-basket"></i> Products & Pricing</a>
                    <a href="#orders" class="legal-nav-link"><i class="bi bi-credit-card"></i> Orders & Payment</a>
                    <a href="#returns" class="legal-nav-link"><i class="bi bi-arrow-counterclockwise"></i> Returns Policy</a>
                    <a href="#liability" class="legal-nav-link"><i class="bi bi-shield-exclamation"></i> Liability</a>
                </nav>
            </div>
        </aside>

        <main class="policy-card-enhanced">
            
            <div id="acceptance" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-check2-square"></i> 1. Acceptance of Terms</h2>
                <p class="policy-text">
                    By accessing and using the <strong>FreshCart Market</strong> website, you agree to comply with and be bound by these Terms of Service. 
                    These Terms apply to all visitors, users, and others who access or use the Service. If you disagree with any part of the terms, then you may not access the Service.
                </p>
            </div>

            <div id="accounts" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-person-lock"></i> 2. User Accounts</h2>
                <p class="policy-text">To access specific features like ordering and order tracking, you must create an account. You agree to:</p>
                
                <ul class="data-list">
                    <li>
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>Security:</strong> Maintain the confidentiality of your password and accept responsibility for all activities that occur under your account.
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-pencil-square"></i>
                        <div>
                            <strong>Accuracy:</strong> Provide accurate, current, and complete information during the registration process.
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-exclamation-circle"></i>
                        <div>
                            <strong>Notification:</strong> Notify us immediately of any unauthorized use of your account or any other breach of security.
                        </div>
                    </li>
                </ul>
            </div>

            <div id="products" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-tag"></i> 3. Products and Pricing</h2>
                <p class="policy-text">
                    We strive to ensure that all details, descriptions, and prices of products appearing on FreshCart are accurate. However, errors may occur.
                </p>
                <ul class="policy-text mt-3" style="padding-left: 20px; line-height: 2;">
                    <li><strong>Freshness Guarantee:</strong> Due to the nature of organic produce, items may vary slightly in size, color, and weight from images.</li>
                    <li><strong>Availability:</strong> All products are subject to availability. We reserve the right to limit quantities.</li>
                    <li><strong>Pricing:</strong> Prices are subject to change without notice. If we discover an error in the price of goods you have ordered, we will inform you ASAP.</li>
                </ul>
            </div>

            <div id="orders" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-wallet2"></i> 4. Orders and Payments</h2>
                <p class="policy-text">
                    By placing an order, you are offering to purchase a product subject to these terms. All orders are subject to availability and confirmation of the order price.
                </p>
                <div class="p-3 bg-light rounded-3 mt-3 border">
                    <strong>Payment Methods:</strong> We currently accept Cash on Delivery (COD). Online payment gateways (Credit Card/GCash) will be implemented in future updates.
                </div>
            </div>

            <div id="returns" class="policy-section">
                <h2 class="policy-heading"><i class="bi bi-box-seam"></i> 5. Returns and Refunds</h2>
                <p class="policy-text">
                    Due to the perishable nature of our goods (fruits, vegetables, dairy), we have a specific policy:
                </p>
                <ul class="data-list">
                    <li>
                        <i class="bi bi-flower1"></i>
                        <div>
                            <strong>Perishables:</strong> If you receive damaged or spoiled items, please contact us within 24 hours of delivery with photos for a refund.
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-box"></i>
                        <div>
                            <strong>Non-Perishables:</strong> Unopened pantry items may be returned within 7 days of purchase in their original packaging.
                        </div>
                    </li>
                </ul>
            </div>

            <div id="liability" class="policy-section mb-0">
                <h2 class="policy-heading"><i class="bi bi-shield-exclamation"></i> 6. Limitation of Liability</h2>
                <p class="policy-text">
                    In no event shall FreshCart Market, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your access to or use of or inability to access or use the Service.
                </p>
            </div>

        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>