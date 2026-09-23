<?php
// pages/about.php - FreshCart Market
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include __DIR__ . '/../includes/header.php';
?>

<section class="content-page-hero">
    <div class="container">
        <span class="content-kicker">Our Story</span>
        <h1 class="content-hero-title">Groceries harvested at dawn,<br>delivered to your table by noon.</h1>
        <p class="content-hero-lead">
            FreshCart connects regional family farms directly with households. No centralized warehouse delays, no chemical ripening chambers, and no wholesale brokers.
        </p>
        <div class="content-hero-actions">
            <a href="<?= $rootPath ?>farmers" class="btn-hero-primary">
                <i class="bi bi-people-fill"></i> Meet Our Farmers
            </a>
            <a href="<?= $rootPath ?>index.php" class="btn-hero-secondary">
                <i class="bi bi-basket-fill"></i> Browse Fresh Harvest
            </a>
        </div>
    </div>
</section>

<div class="container py-5 my-2">
    <!-- Harvest Logistics Bento -->
    <div class="text-center mb-4">
        <span class="content-kicker">The Freshness Timeline</span>
        <h2 class="content-card-title fs-2">From Soil to Kitchen in Under 12 Hours</h2>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-4 col-md-6">
            <div class="content-bento-card">
                <span class="content-card-tag">04:30 AM</span>
                <h3 class="content-card-title">Dawn Field Harvesting</h3>
                <p class="content-card-desc">
                    Greens, herbs, and fruits are harvested at sunrise when cell hydration is highest and natural sugars peak. Nothing sits in field bins under midday sun.
                </p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="content-bento-card">
                <span class="content-card-tag">07:00 AM</span>
                <h3 class="content-card-title">Direct Cold-Chain Transit</h3>
                <p class="content-card-desc">
                    Produce transfers directly into regional temperature-controlled vans. We bypass intermediate regional wholesale terminals to preserve natural shelf life.
                </p>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="content-bento-card">
                <span class="content-card-tag">11:00 AM</span>
                <h3 class="content-card-title">Neighborhood Distribution</h3>
                <p class="content-card-desc">
                    Orders are packed into reusable crates and routed directly to residential doorsteps. Zero cold-storage holding for days or weeks.
                </p>
            </div>
        </div>
    </div>

    <!-- Editorial Split Section -->
    <div class="row align-items-center g-4 g-lg-5 py-4">
        <div class="col-lg-6">
            <div class="position-relative rounded-4 overflow-hidden shadow-sm" style="border: 1px solid #E5E0D5;">
                <img src="<?= $rootPath ?>assets/images/gulay1.jpg" alt="Fresh harvest intake" class="img-fluid w-100" style="height: 380px; object-fit: cover;">
                <div class="p-3 bg-white border-top text-muted small d-flex align-items-center gap-2">
                    <i class="bi bi-geo-alt-fill text-success"></i> Regional intake and sorting hub, Zamboanga City
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <span class="content-kicker">Fair Food Economics</span>
            <h2 class="content-card-title fs-2 mb-3">Rebuilding the Supply Chain for Growers</h2>
            <p class="text-secondary leading-relaxed mb-3">
                In traditional retail chains, produce passes through field brokers, central auctions, regional consolidation warehouses, and supermarket distribution depots. By the time leafy greens reach a display shelf, they have lost over 40% of their vitamin content, and the farmer takes home less than 18% of the checkout price.
            </p>
            <p class="text-secondary leading-relaxed mb-4">
                FreshCart reverses this model. Over 70% of every retail peso goes directly to our partner farms within 24 hours of fulfillment. By keeping distribution local and direct, growers earn stable livelihoods while families receive vibrant produce at fair prices.
            </p>
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 48px; height: 48px; flex-shrink: 0;">
                    <i class="bi bi-shield-check fs-4"></i>
                </div>
                <div>
                    <h4 class="h6 mb-1 fw-bold text-dark">100% Traceable Origin</h4>
                    <p class="text-muted small mb-0">Every product batch lists the exact grower and harvest timestamp on its crate label.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 3 Core Principles -->
    <div class="row g-4 mt-4 pt-3">
        <div class="col-md-4">
            <div class="content-bento-card">
                <div class="mb-3 text-success fs-3">
                    <i class="bi bi-flower1"></i>
                </div>
                <h3 class="content-card-title fs-4">Soil Stewardship</h3>
                <p class="content-card-desc">
                    We only partner with growers who practice crop rotation, compost enrichment, and zero synthetic pesticide applications to protect ground biodiversity.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-bento-card">
                <div class="mb-3 text-success fs-3">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <h3 class="content-card-title fs-4">Guaranteed Fair Income</h3>
                <p class="content-card-desc">
                    Contracted growers set baseline prices prior to seed planting, shielding family farming operations from speculative commodity price spikes.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-bento-card">
                <div class="mb-3 text-success fs-3">
                    <i class="bi bi-recycle"></i>
                </div>
                <h3 class="content-card-title fs-4">Circular Fulfillment</h3>
                <p class="content-card-desc">
                    Every order is packed in compostable plant-based bags or returnable totes. We sanitize and reuse crates, keeping single-use plastics out of landfills.
                </p>
            </div>
        </div>
    </div>

    <!-- Founder Note Banner -->
    <div class="mt-5 p-4 p-md-5 rounded-4" style="background: #FAF9F6; border: 1px solid #E5E0D5;">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="content-kicker">Founder's Commitment</span>
                <blockquote class="fs-5 text-dark fw-medium mb-3" style="font-family: var(--font-serif, serif); font-style: italic; line-height: 1.5;">
                    "Wholesome food shouldn't be an expensive luxury or an environmental liability. When we pay our farmers fair wages and shorten delivery time to hours instead of days, families eat better and regional agriculture thrives."
                </blockquote>
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <div class="fw-bold text-dark">Janus Dominic</div>
                        <div class="text-muted small">Founder & Operations Lead, FreshCart</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="<?= $rootPath ?>sustainability" class="btn-hero-primary d-inline-flex">
                    <i class="bi bi-globe-americas"></i> Our Sustainability Metrics
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>