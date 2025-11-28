<?php 
include 'db.php';
session_start();
include 'includes/header.php'; 
?>

<section class="farmers-hero">
    <div class="container">
        <span class="text-uppercase text-muted fw-bold small tracking-wider mb-2 d-block">Our Partners</span>
        <h1 class="page-title animate-fade-in">Meet the Hands That Feed Us</h1>
        <p class="page-subtitle">
            Real people, real farms, real passion. We partner with over 50 local growers who share our commitment to organic, sustainable agriculture.
        </p>
    </div>
</section>

<div class="container mb-5 pb-5">
    <div class="row g-4 farmers-grid">
        
        <div class="col-md-4">
            <div class="farmer-profile-card">
                <div class="farmer-img-container">
                    <img src="./assets/images/Elena-Rodriguez-1080x1080-thumbn.jpg" alt="Elena Rodriguez">
                    <div class="farmer-img-overlay"></div>
                </div>
                <div class="farmer-content">
                    <span class="farmer-badge">Vegetables</span>
                    <h3 class="farmer-name">Elena Rodriguez</h3>
                    <div class="farmer-location">
                        <i class="bi bi-geo-alt-fill text-warning"></i> Green Valley Farms, CA
                    </div>
                    <p class="farmer-quote">
                        "We've been growing organic kale and spinach for three generations. The soil tells you what it needs if you listen."
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="farmer-profile-card">
                <div class="farmer-img-container">
                    <img src="./assets/images/chen.jpg" alt="David Chen">
                    <div class="farmer-img-overlay"></div>
                </div>
                <div class="farmer-content">
                    <span class="farmer-badge">Orchard Fruits</span>
                    <h3 class="farmer-name">David Chen</h3>
                    <div class="farmer-location">
                        <i class="bi bi-geo-alt-fill text-warning"></i> Sunrise Orchards, WA
                    </div>
                    <p class="farmer-quote">
                        "The secret to the perfect crisp apple isn't chemicals—it's patience, sunlight, and pruning at the exact right time."
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="farmer-profile-card">
                <div class="farmer-img-container">
                    <img src="./assets/images/sarah-miller.jpg" alt="Sarah Miller">
                    <div class="farmer-img-overlay"></div>
                </div>
                <div class="farmer-content">
                    <span class="farmer-badge">Dairy</span>
                    <h3 class="farmer-name">Sarah Miller</h3>
                    <div class="farmer-location">
                        <i class="bi bi-geo-alt-fill text-warning"></i> Happy Cows Dairy, WI
                    </div>
                    <p class="farmer-quote">
                        "Ethical farming means treating every animal like family. Happy cows genuinely do make better milk."
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>