<?php
declare(strict_types=1);
$config = require __DIR__ . '/../config/database.php';
if (is_file(__DIR__ . '/../config/database.local.php')) $config = array_replace($config, require __DIR__ . '/../config/database.local.php');
date_default_timezone_set($config['timezone']);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header('Cache-Control: no-store');
require __DIR__ . '/functions.php';
require __DIR__ . '/jerseys.php';
require __DIR__ . '/auth.php';
try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $settings = array_column(query('SELECT * FROM settings'), 'setting_value', 'setting_key');
} catch (PDOException $e) {
    error_log($e->getMessage()); http_response_code(503);
    exit('Database is not configured. Configure config/database.local.php and follow README.md.');
}
$user = current_user();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
