<?php
// scripts/generate_cloud_sql.php
// Creates 100% clean UTF-8 SQL dump without MariaDB sandbox comments or DEFINER restrictions

$destFile = __DIR__ . '/../database/grocery_db_cloud.sql';
$dumpCmd = '"C:\\xampp\\mysql\\bin\\mysqldump.exe" --user=root --skip-comments --default-character-set=utf8mb4 --skip-triggers --skip-routines --ignore-table=grocery_db.view_daily_sales grocery_db';

exec($dumpCmd, $outputLines, $returnCode);

if ($returnCode !== 0) {
    die("mysqldump failed with code $returnCode\n");
}

$content = implode("\n", $outputLines);

// 1. Remove MariaDB sandbox and specific version comments
$content = preg_replace('/\/\*M!999999\\\-.*?\*\//s', '', $content);
$content = preg_replace('/\/\*M!\d+.*?\*\/\s*;/s', '', $content);
$content = preg_replace('/^\s*;\s*$/m', '', $content);

// 2. Replace MariaDB 11 collation utf8mb4_uca1400_ai_ci with universal utf8mb4_general_ci
$content = str_replace('utf8mb4_uca1400_ai_ci', 'utf8mb4_general_ci', $content);

// 3. Remove DEFINER clauses from procedures, functions, triggers, and views
$content = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/i', '', $content);
$content = preg_replace('/DEFINER=[^\s]+/i', '', $content);

// 4. Ensure UTF-8 without BOM
file_put_contents($destFile, $content);
echo "Generated database/grocery_db_cloud.sql successfully (" . number_format(strlen($content)) . " bytes)\n";
