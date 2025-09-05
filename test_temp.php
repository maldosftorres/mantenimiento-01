<?php
// test_temp.php - quick check de permisos de carpeta temp/
$dir = __DIR__ . '/temp';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true)) {
        die("❌ No se pudo crear la carpeta temp en: " . htmlspecialchars($dir));
    }
}
$testFile = $dir . '/write_test.txt';
$res = @file_put_contents($testFile, "ok ".date('c'));
if ($res === false) {
    echo "❌ No se pudo escribir en ".htmlspecialchars($dir).". Revisá permisos NTFS (Propiedades → Seguridad).";
} else {
    echo "✅ temp es escribible. Archivo: ".htmlspecialchars($testFile);
}
