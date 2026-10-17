<?php
// Smoke-test an extracted website.zip, including runtime config dependencies.
if (PHP_SAPI!=='cli') exit;
$root=$argv[1] ?? '';
if (!is_dir($root.'/app') || !is_file($root.'/config/match-venue.php')) throw new RuntimeException('Incomplete package: missing app or config/match-venue.php');
require $root.'/app/functions.php';
if (!defined('MATCH_VENUE_NAME') || !defined('MATCH_VENUE_MAP_URL') || !function_exists('avatar')) throw new RuntimeException('Runtime dependencies did not load');
if (is_file($root.'/config/database.local.php')) throw new RuntimeException('Private database config included in package');
echo "PASS: packaged functions load with venue configuration; private database config excluded.\n";
