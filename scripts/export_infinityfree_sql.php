<?php
// scripts/export_infinityfree_sql.php
// Generates a clean, fully-compatible SQL dump for InfinityFree MySQL hosting.
require_once __DIR__ . '/../config/db.php';

$outputFile = __DIR__ . '/../database/grocery_db_cloud.sql';

$header = <<<SQL
-- FreshCart Cloud Database Schema & Initial Catalog
-- Target Environment: InfinityFree MySQL Hosting
-- Generated on: %TIMESTAMP%

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

SQL;

$header = str_replace('%TIMESTAMP%', date('Y-m-d H:i:s'), $header);

$sql = $header;

$tables = [
    'users' => false,
    'products' => true,
    'coupons' => true,
    'orders' => false,
    'order_items' => false,
    'reviews' => false,
    'api_cart' => false
];

foreach ($tables as $table => $includeData) {
    $sql .= "\n-- --------------------------------------------------------\n";
    $sql .= "-- Table structure for table `{$table}`\n";
    $sql .= "-- --------------------------------------------------------\n\n";
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

    // Get CREATE TABLE statement
    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $createTable = $row['Create Table'];

    // Normalize collation for InfinityFree MySQL compatibility
    $createTable = preg_replace('/COLLATE=utf8mb4_[a-zA-Z0-9_]+/', 'COLLATE=utf8mb4_general_ci', $createTable);
    $createTable = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $createTable);

    $sql .= $createTable . ";\n\n";

    // Include data if specified
    if ($includeData) {
        $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $sql .= "LOCK TABLES `{$table}` WRITE;\n";
            $sql .= "/*!40000 ALTER TABLE `{$table}` DISABLE KEYS */;\n";

            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $columns) . '`';

            $valRows = [];
            foreach ($rows as $r) {
                $escapedVals = [];
                foreach ($columns as $c) {
                    $v = $r[$c];
                    if ($v === null) {
                        $escapedVals[] = 'NULL';
                    } elseif (is_numeric($v)) {
                        $escapedVals[] = $v;
                    } else {
                        $escapedVals[] = $pdo->quote($v);
                    }
                }
                $valRows[] = "(" . implode(',', $escapedVals) . ")";
            }

            // Chunk inserts by 50 rows for InfinityFree phpMyAdmin query size limits
            $chunks = array_chunk($valRows, 50);
            foreach ($chunks as $chunk) {
                $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $chunk) . ";\n";
            }

            $sql .= "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;\n";
            $sql .= "UNLOCK TABLES;\n\n";
        }
    }
}

$footer = <<<SQL
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

SQL;

$sql .= $footer;

file_put_contents($outputFile, $sql);
echo "Successfully generated: $outputFile (" . number_format(filesize($outputFile)) . " bytes)\n";
