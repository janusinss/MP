<?php 
include 'db.php'; 
session_start();
// Include Header
include 'includes/header.php'; 
?>

<section class="sus-hero">
    <div class="container">
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 mb-3">Our Mission</span>
        <h1 class="sus-title animate-fade-in">Good for you.<br>Better for the planet.</h1>
        <p class="sus-lead">
            We believe that the food system shouldn't come at a cost to the earth. 
            From soil to doorstep, we are redefining what it means to be fresh.
        </p>
    </div>
</section>

<div class="container">
    
    <div class="row g-4 impact-grid">
        <div class="col-md-4">
            <div class="impact-card">
                <div class="impact-icon-bg">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="impact-number">100%</div>
                <div class="impact-label">Plastic-Free Packaging</div>
                <p class="text-muted mt-3 small px-3">
                    Every box, bag, and container is compostable or recyclable. No single-use plastics, ever.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="impact-card">
                <div class="impact-icon-bg">
                    <i class="bi bi-shop"></i>
                </div>
                <div class="impact-number">50+</div>
                <div class="impact-label">Local Farm Partners</div>
                <p class="text-muted mt-3 small px-3">
                    We source within 100 miles of your city, reducing travel time and supporting family farms.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="impact-card">
                <div class="impact-icon-bg">
                    <i class="bi bi-recycle"></i>
                </div>
                <div class="impact-number">0%</div>
                <div class="impact-label">Food Waste in Transit</div>
                <p class="text-muted mt-3 small px-3">
                    Our AI-driven inventory means we only order what we sell. Nothing goes to the landfill.
                </p>
            </div>
        </div>
    </div>

    <div class="forest-section">
        <div class="row g-0 align-items-center">
            <div class="col-lg-6">
                <div class="forest-content">
                    <div class="d-flex align-items-center gap-2 mb-3 text-warning">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <span class="text-uppercase small fw-bold tracking-wide">Vision 2026</span>
                    </div>
                    <h2 class="forest-title">Our Carbon Footprint Promise</h2>
                    <p class="forest-text">
                        We use electric vans for 90% of our city deliveries. We aren't just offsetting carbon; we are actively reducing it. 
                        By 2026, FreshCart aims to be the first grocery delivery service to be completely carbon neutral.
                    </p>
                    <button class="btn btn-light rounded-pill px-4 mt-4 text-success fw-bold">Read our 2025 Report</button>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="forest-img-side"></div>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>