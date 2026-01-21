<?php
include 'db.php';

set_time_limit(300); // Allow 5 minutes for seeding

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

    // 1. Seed Users (Target: ~200 total)
    echo "<p>Seeding Users...</p>";
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, address) VALUES (?, ?, ?, ?)");
    $password = password_hash('password123', PASSWORD_DEFAULT);

    for ($i = 0; $i < 150; $i++) {
        $name = getRandomName();
        $email = strtolower(str_replace(' ', '.', $name)) . rand(100, 9999) . '@example.com';
        $address = getRandomAddress();
        $stmt->execute([$name, $email, $password, $address]);
    }
    echo "<p class='text-success'>✅ Added 150 Users.</p>";

    // 2. Fetch Existing Products (Do NOT create new ones)
    echo "<p>Fetching existing products...</p>";

    // Cleanup previous dummy products if they exist
    // Must delete dependent records first to avoid Foreign Key Constraint Fails!
    echo "<p>Cleaning up old data...</p>";

    // 1. Delete Reviews for dummy products
    $pdo->exec("DELETE FROM reviews WHERE product_id IN (SELECT id FROM products WHERE image = 'default.jpg')");

    // 2. Delete Order Items for dummy products
    $pdo->exec("DELETE FROM order_items WHERE product_id IN (SELECT id FROM products WHERE image = 'default.jpg')");

    // 3. Now safe to delete the dummy products
    $cleanStmt = $pdo->exec("DELETE FROM products WHERE image = 'default.jpg'");

    // 4. Clean up any Orders that are now empty (have no items)
    $pdo->exec("DELETE FROM orders WHERE id NOT IN (SELECT DISTINCT order_id FROM order_items)");

    if ($cleanStmt > 0) {
        echo "<p class='text-warning'>🧹 Removed $cleanStmt dummy products (and their history) from previous run.</p>";
    }

    $productIds = $pdo->query("SELECT id, price FROM products")->fetchAll(PDO::FETCH_ASSOC);

    if (count($productIds) < 1) {
        throw new Exception("No products found! Please add at least a few real products to the database manually.");
    }
    echo "<p class='text-success'>✅ Using " . count($productIds) . " existing products for orders.</p>";

    // Get IDs for relation
    $userIds = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Seed Orders & Items (Target: ~1000 orders)
    echo "<p>Seeding Orders & Items...</p>";
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, customer_name, address, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");

    $statuses = ['Pending', 'Usage', 'Shipped', 'Delivered', 'Cancelled'];

    for ($i = 0; $i < 1200; $i++) {
        $uid = $userIds[array_rand($userIds)];
        $orderDate = date('Y-m-d H:i:s', strtotime('-' . rand(0, 365) . ' days'));
        $status = $statuses[array_rand($statuses)];

        // Pick random items
        $numItems = rand(1, 5);
        $totalAmount = 0;
        $orderItems = [];

        for ($j = 0; $j < $numItems; $j++) {
            $p = $productIds[array_rand($productIds)];
            $qty = rand(1, 3);
            $totalAmount += $p['price'] * $qty;
            $orderItems[] = ['pid' => $p['id'], 'qty' => $qty];
        }

        // Insert Order
        // Note: We use a placeholder name/address, in real app it comes from user or form
        $stmtOrder->execute([$uid, 'Customer ' . $uid, '123 Fake St', $totalAmount, $status, $orderDate]);
        $orderId = $pdo->lastInsertId();

        // Insert Items
        foreach ($orderItems as $item) {
            $stmtItem->execute([$orderId, $item['pid'], $item['qty']]);
            // Note: The trigger we just created will auto-reduce stock here!
        }
    }
    echo "<p class='text-success'>✅ Added 1200 Orders.</p>";

    $pdo->commit();
    echo "<h1>Seeding Complete! Database is compliant.</h1>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>Seeding Failed: " . $e->getMessage() . "</h2>";
}
?>