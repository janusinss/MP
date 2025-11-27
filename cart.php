<?php
include 'db.php';
session_start();

// 1. Handle Coupon Submission
$coupon_msg = '';
$coupon_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_coupon'])) {
    $code = $_POST['coupon_code'];
    
    // Check DB
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'Active' AND expiry_date >= CURDATE()");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coupon) {
        $_SESSION['discount'] = [
            'code' => $coupon['code'],
            'percent' => $coupon['discount_percent']
        ];
        $coupon_msg = "Coupon applied! " . $coupon['discount_percent'] . "% Off.";
    } else {
        $coupon_error = "Invalid or expired coupon.";
        unset($_SESSION['discount']); // Clear invalid codes
    }
}

// Handle Remove Coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['discount']);
    header("Location: cart.php");
    exit;
}

// Fetch Cart Items (Existing Logic)
$cartItems = [];
$subTotal = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $qty = $_SESSION['cart'][$product['id']];
        $lineTotal = $product['price'] * $qty;
        
        $cartItems[] = [
            'id' => $product['id'],
            'name' => $product['name'], 
            'price' => $product['price'],
            'qty' => $qty,
            'subtotal' => $lineTotal
        ];
        $subTotal += $lineTotal;
    }
}

// Calculate Final Total
$discountAmount = 0;
$finalTotal = $subTotal;

if (isset($_SESSION['discount'])) {
    $discountAmount = ($subTotal * $_SESSION['discount']['percent']) / 100;
    $finalTotal = $subTotal - $discountAmount;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">
    <h2><i class="bi bi-cart-check"></i> Your Shopping Cart</h2>
    <a href="index.php" class="btn btn-secondary mb-3">&larr; Continue Shopping</a>

    <?php if (empty($cartItems)): ?>
                                <button type="submit" name="apply_coupon" class="btn btn-outline-dark">Apply</button>
                            </div>
                            <?php if ($coupon_msg): ?>
                                <div class="text-success small mt-1"><?= $coupon_msg ?></div>
                            <?php endif; ?>
                            <?php if ($coupon_error): ?>
                                <div class="text-danger small mt-1"><?= $coupon_error ?></div>
                            <?php endif; ?>
                        </form>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?= number_format($subTotal, 2) ?></span>
                        </div>

                        <?php if (isset($_SESSION['discount'])): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Discount (<?= $_SESSION['discount']['code'] ?>):</span>
                                <span>-$<?= number_format($discountAmount, 2) ?></span>
                            </div>
                            <div class="text-end mb-2">
                                <a href="cart.php?remove_coupon=true" class="text-danger small text-decoration-none">[Remove Coupon]</a>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between fs-4 fw-bold border-top pt-2">
                            <span>Total:</span>
                            <span>$<?= number_format($finalTotal, 2) ?></span>
                        </div>

                        <a href="checkout.php" class="btn btn-success w-100 btn-lg mt-3">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>