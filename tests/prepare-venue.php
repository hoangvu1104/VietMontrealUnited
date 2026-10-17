<?php
// Local-only: creates a NEW isolated database on the preview MariaDB instance.
if (PHP_SAPI !== "cli") exit;
$db=new PDO('mysql:host=127.0.0.1;port=3312;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE vmu_venue_20260930_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE vmu_venue_20260930_test');
$db->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
$db->exec(file_get_contents(__DIR__.'/fixtures/demo.sql'));
$db->exec("CREATE USER IF NOT EXISTS 'venue_test'@'127.0.0.1' IDENTIFIED BY 'VenueRegression2026!'");
$db->exec("GRANT ALL ON vmu_venue_20260930_test.* TO 'venue_test'@'127.0.0.1'");
echo "Isolated test database prepared.\n";
