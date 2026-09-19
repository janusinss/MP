<?php
// scripts/package_dist.php
// Packages the exact web files needed for InfinityFree into dist_infinityfree.zip

$zipFile = __DIR__ . '/../dist_infinityfree.zip';
if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot create zip file: $zipFile\n");
}

$foldersToInclude = [
    'account',
    'admin',
    'assets',
    'auth',
    'cart',
    'config',
    'includes',
    'orders',
    'pages',
    'products',
    'api'
];

$filesToInclude = [
    'index.php',
    '.htaccess'
];

$root = realpath(__DIR__ . '/..');

foreach ($foldersToInclude as $folder) {
    $folderPath = $root . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($folderPath)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $filePath = $item->getRealPath();
        $relPath = substr($filePath, strlen($root) + 1);
        $relPath = str_replace('\\', '/', $relPath);

        if ($item->isDir()) {
            $zip->addEmptyDir($relPath);
        } else {
            $zip->addFile($filePath, $relPath);
        }
    }
}

foreach ($filesToInclude as $file) {
    $filePath = $root . DIRECTORY_SEPARATOR . $file;
    if (file_exists($filePath)) {
        $zip->addFile($filePath, $file);
    }
}

// Add the pre-configured .env directly into the root of zip as .env
$envSource = $root . DIRECTORY_SEPARATOR . '.env.infinityfree';
if (file_exists($envSource)) {
    $zip->addFile($envSource, '.env');
}

$zip->close();
echo "Successfully created deployment archive: dist_infinityfree.zip (" . number_format(filesize($zipFile)) . " bytes)\n";
