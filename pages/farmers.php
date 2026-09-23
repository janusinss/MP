<?php 
// pages/farmers.php - FreshCart Market
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include __DIR__ . '/../includes/header.php'; 
?>

<section class="content-page-hero">
    <div class="container">
        <span class="content-kicker">Grower Cooperative</span>
        <h1 class="content-hero-title">Meet the growers behind<br>your daily family meals.</h1>
        <p class="content-hero-lead">
            We partner with regional family growers who practice regenerative soil care, ethical livestock stewardship, and chemical-free cultivation. Here are the people who nurture your food.
        </p>
        <div class="content-hero-actions">
            <a href="<?= $rootPath ?>index.php" class="btn-hero-primary">
                <i class="bi bi-basket3-fill"></i> Shop Local Produce
            </a>
            <a href="<?= $rootPath ?>contact" class="btn-hero-secondary">
                <i class="bi bi-person-plus-fill"></i> Become a Partner Farm
            </a>
        </div>
    </div>
</section>

<div class="container py-5 my-2">
    <!-- Farmer Showcase Cards Grid -->
    <div class="row g-4 mb-5">
        <!-- Farmer 1: Elena Rodriguez -->
        <div class="col-lg-4 col-md-6">
            <div class="farmer-portrait-card">
                <div class="farmer-portrait-media">
                    <img src="<?= $rootPath ?>assets/images/Elena-Rodriguez-1080x1080-thumbn.jpg" alt="Elena Rodriguez, Organic Greens Farmer" class="farmer-portrait-img" loading="lazy">
                    <div class="farmer-portrait-overlay"></div>
                    <span class="farmer-portrait-badge">Greens & Brassicas</span>
                </div>
                <div class="farmer-portrait-body">
                    <h3 class="farmer-portrait-name">Elena Rodriguez</h3>
                    <div class="farmer-portrait-location">
                        <i class="bi bi-geo-alt-fill"></i> Green Valley Farms, San Ramon
                    </div>
                    <div class="farmer-quote-box">
                        "The secret to crisp kale and sweet carrots is living compost and soil microorganisms. If you feed the soil, the soil feeds you."
                    </div>
                    <div class="farmer-specs-list">
                        <span><strong>28 Acres</strong> organic</span>
                        <span>Partner since <strong>2021</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Farmer 2: David Chen -->
        <div class="col-lg-4 col-md-6">
            <div class="farmer-portrait-card">
                <div class="farmer-portrait-media">
                    <img src="<?= $rootPath ?>assets/images/chen.jpg" alt="David Chen, Orchard Fruit Specialist" class="farmer-portrait-img" loading="lazy">
                    <div class="farmer-portrait-overlay"></div>
                    <span class="farmer-portrait-badge">Highland Orchards</span>
                </div>
                <div class="farmer-portrait-body">
                    <h3 class="farmer-portrait-name">David Chen</h3>
                    <div class="farmer-portrait-location">
                        <i class="bi bi-geo-alt-fill"></i> Sunrise Orchards, Bukidnon
                    </div>
                    <div class="farmer-quote-box">
                        "Natural altitude and volcanic soils provide ideal fruit sweetness. We prune by hand and harvest each branch only when sugars peak."
                    </div>
                    <div class="farmer-specs-list">
                        <span><strong>45 Hectares</strong> orchard</span>
                        <span>Partner since <strong>2022</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Farmer 3: Sarah Miller -->
        <div class="col-lg-4 col-md-12">
            <div class="farmer-portrait-card">
                <div class="farmer-portrait-media">
                    <img src="<?= $rootPath ?>assets/images/sarah-miller.jpg" alt="Sarah Miller, Pasture Dairy Steward" class="farmer-portrait-img" loading="lazy">
                    <div class="farmer-portrait-overlay"></div>
                    <span class="farmer-portrait-badge">Grass-Fed Dairy</span>
                </div>
                <div class="farmer-portrait-body">
                    <h3 class="farmer-portrait-name">Sarah Miller</h3>
                    <div class="farmer-portrait-location">
                        <i class="bi bi-geo-alt-fill"></i> Highland Pastures Cooperative
                    </div>
                    <div class="farmer-quote-box">
                        "Our herd grazes open clover pastures daily. Humane, low-stress environments make healthier cows and rich, clean milk."
                    </div>
                    <div class="farmer-specs-list">
                        <span><strong>100%</strong> pasture-raised</span>
                        <span>Partner since <strong>2023</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cooperative Standards Banner -->
    <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm mb-5" style="border: 1px solid #E5E0D5;">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="content-kicker">Guaranteed Fair Exchange</span>
                <h2 class="content-card-title fs-2 mb-3">Our Three Cooperative Standards</h2>
                <p class="text-secondary leading-relaxed mb-4">
                    Most supermarkets impose retroactive price cuts on farmers for unseasonal surpluses or delayed transit spoilage. FreshCart guarantees fixed floor prices before planting starts.
                </p>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 36px; height: 36px; flex-shrink: 0;">
                            <i class="bi bi-check2"></i>
                        </div>
                        <div>
                            <span class="fw-bold text-dark d-block">Direct Payment within 24 Hours</span>
                            <span class="text-muted small">Electronic settlements transfer on the afternoon of delivery, eliminating 90-day wholesale payment lags.</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 36px; height: 36px; flex-shrink: 0;">
                            <i class="bi bi-check2"></i>
                        </div>
                        <div>
                            <span class="fw-bold text-dark d-block">Zero Broker Markup</span>
                            <span class="text-muted small">We operate our own refrigerated transit fleet, removing third-party broker cuts from crop revenues.</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 36px; height: 36px; flex-shrink: 0;">
                            <i class="bi bi-check2"></i>
                        </div>
                        <div>
                            <span class="fw-bold text-dark d-block">Organic Transition Grants</span>
                            <span class="text-muted small">We support conventional growers through their 3-year transition into certified organic cultivation.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="p-4 rounded-4" style="background: #F7F6F2; border: 1px solid #E5E0D5;">
                    <div class="mb-3 text-success fs-1">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">Are you a regional grower?</h3>
                    <p class="text-muted small mb-4">
                        We are continually expanding our farm network in Zamboanga and regional Mindanao. If you practice sustainable, chemical-free farming, we would love to connect.
                    </p>
                    <a href="<?= $rootPath ?>contact" class="btn-hero-primary w-100 justify-content-center">
                        Inquire About Farm Partnership <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>