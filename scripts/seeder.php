<?php
// scripts/seeder.php
// Generates realistic mock users, products, orders, and reviews.
require_once __DIR__ . '/../config/db.php';

set_time_limit(300);

echo "<h1>Starting Data Seeding...</h1>";

$fakerFirstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'Chris', 'Jessica', 'Daniel', 'Ashley', 'James', 'Linda', 'Robert', 'Patricia', 'William', 'Elizabeth', 'Joseph', 'Susan', 'Thomas', 'Jennifer'];
$fakerLastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
$fakerStreets = ['Main St', 'Park Ave', 'Broadway', 'Oak Ln', 'Maple Dr', 'Cedar Rd', 'Elm St', 'Pine Ct', 'Washington Blvd', 'Lakeview Dr'];
$fakerCities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];
$productPrefixes = ['Organic', 'Fresh', 'Premium', 'Local', 'Imported', 'Homemade', 'Artisanal', 'Classic', 'Spicy', 'Sweet'];
$productTypes = ['Apple', 'Banana', 'Orange', 'Bread', 'Milk', 'Cheese', 'Tomato', 'Potato', 'Carrot', 'Beef', 'Chicken', 'Salmon', 'Pasta', 'Rice', 'Coffee', 'Tea', 'Chocolate', 'Cookie', 'Juice', 'Water'];

function getRandomName()
{
    global $fakerFirstNames, $fakerLastNames;
    return $fakerFirstNames[array_rand($fakerFirstNames)] . ' ' . $fakerLastNames[array_rand($fakerLastNames)];
}

function getRandomAddress()
{
    global $fakerStreets, $fakerCities;
    return rand(100, 9999) . ' ' . $fakerStreets[array_rand($fakerStreets)] . ', ' . $fakerCities[array_rand($fakerCities)];
}

function getRandomProduct()
{
    global $productPrefixes, $productTypes;
    return $productPrefixes[array_rand($productPrefixes)] . ' ' . $productTypes[array_rand($productTypes)];
}

try {
    $pdo->beginTransaction();

    // 1. Seed 10 Users
    echo "Seeding Users...<br>";
    $userIds = [];
    $stmtUser = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
    for ($i = 0; $i < 10; $i++) {
        $name = getRandomName();
        $email = strtolower(str_replace(' ', '.', $name)) . rand(10, 99) . "@example.com";
        $password = password_hash('password123', PASSWORD_DEFAULT);
        try {
            $stmtUser->execute([$name, $email, $password]);
            $userIds[] = $pdo->lastInsertId();
        } catch (PDOException $e) {
            // Duplicate email ignore
        }
    }

    // Always ensure we have user IDs
    $existingUsers = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $userIds = array_merge($userIds, $existingUsers);

    // 2. Seed 15 Products
    echo "Seeding Products...<br>";
    $productIds = [];
    $categories = ['Fruits', 'Vegetables', 'Dairy', 'Bakery', 'Meat', 'Beverages', 'Snacks'];
    $stmtProduct = $pdo->prepare("INSERT INTO products (name, category, price, stock_qty, description, image) VALUES (?, ?, ?, ?, ?, ?)");
    for ($i = 0; $i < 15; $i++) {
        $pName = getRandomProduct();
        $cat = $categories[array_rand($categories)];
        $price = rand(100, 2000) / 100;
        $stock = rand(5, 50);
        $desc = "Freshly harvested and delivered. High quality and organically produced $pName.";
        $image = "assets/images/sample.jpg"; 

        $stmtProduct->execute([$pName, $cat, $price, $stock, $desc, $image]);
        $productIds[] = $pdo->lastInsertId();
    }

    $existingProducts = $pdo->query("SELECT id, price FROM products")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Seed 15 Orders
    echo "Seeding Orders & Order Items...<br>";
    $statuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, customer_name, address, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");

    for ($i = 0; $i < 15; $i++) {
        $uid = $userIds[array_rand($userIds)];
        $cName = getRandomName();
        $addr = getRandomAddress();
        $status = $statuses[array_rand($statuses)];
        $daysAgo = rand(0, 30);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));

        // Temp total
        $stmtOrder->execute([$uid, $cName, $addr, 0, $status, $createdAt]);
        $orderId = $pdo->lastInsertId();

        $itemCount = rand(1, 4);
        $orderTotal = 0;

        for ($j = 0; $j < $itemCount; $j++) {
            $prod = $existingProducts[array_rand($existingProducts)];
            $qty = rand(1, 3);
            $uPrice = $prod['price'];
            $orderTotal += ($qty * $uPrice);

            $stmtItem->execute([$orderId, $prod['id'], $qty, $uPrice]);
        }

        // Update Total
        $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?")->execute([$orderTotal, $orderId]);
    }

    $pdo->commit();
    echo "<h2 style='color:green'>Seeding Successfully Completed!</h2>";
    echo "<p><a href='../index.php'>Go to Home</a> | <a href='../admin/'>Go to Admin</a></p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2 style='color:red'>Seeding Failed: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>
