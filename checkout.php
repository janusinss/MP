<?php
session_start();
include 'db.php';

// If cart is empty, kick them back to the shop
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// AUTO-FILL LOGIC
$pre_name = "";
$pre_address = "";

if (isset($_SESSION['user_id'])) {
    // Fetch user details if logged in
    $stmtUser = $pdo->prepare("SELECT full_name, address FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $pre_name = $user['full_name'];
        $pre_address = $user['address'];
    }
}

// Calculate Total Again
$ids = implode(',', array_keys($_SESSION['cart']));
$stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subTotal = 0;
foreach ($products as $p) {
    $subTotal += $p['price'] * $_SESSION['cart'][$p['id']];
}

// Apply Discount if exists
$finalTotal = $subTotal;
if (isset($_SESSION['discount'])) {
    $discountAmount = ($subTotal * $_SESSION['discount']['percent']) / 100;
    $finalTotal = $subTotal - $discountAmount;
}
?>

<!DOCTYPE html>
<html>
<head>
                            <label>Full Name</label>
                            <input type="text" name="customer_name" class="form-control" required 
                                   value="<?= htmlspecialchars($pre_name) ?>" placeholder="John Doe">
                        </div>
                        <div class="mb-3">
                            <label>Delivery Address</label>
                            <textarea name="address" class="form-control" rows="3" required 
                                      placeholder="123 Zamboanga Street..."><?= htmlspecialchars($pre_address) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Payment Method</label>
                            <select class="form-select">
                                <option>Cash on Delivery (COD)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 btn-lg">Confirm Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>