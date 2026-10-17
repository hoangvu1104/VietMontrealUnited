<?php
// Local isolated verification only; never reads the application's connection configuration.
if (PHP_SAPI!=='cli') exit;
$db=new PDO('mysql:host=127.0.0.1;port='.(getenv('TEST_DB_PORT') ?: '3311').';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE USER IF NOT EXISTS 'roster_test'@'127.0.0.1' IDENTIFIED BY 'IsolatedRosterTest123!'");
foreach (['roster_export_test','roster_reset_test','roster_workflows_test'] as $name) {
 $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 $db->exec("GRANT ALL ON `$name`.* TO 'roster_test'@'127.0.0.1'");
 $db->exec("USE `$name`");
 if ($name==='roster_export_test') $db->exec(file_get_contents(__DIR__.'/../var/godaddy-real-roster/install.sql'));
 else {
  $schema=file_get_contents(__DIR__.'/../database/schema.sql');
  if ($name==='roster_reset_test') $schema=preg_replace('/^ (?:jersey_key|CONSTRAINT players_jersey_nonempty|UNIQUE KEY players_jersey_unique).*\R/m','',$schema);
  $db->exec($schema);
  $db->exec(file_get_contents(__DIR__.'/fixtures/demo.sql'));
 }
}
echo "Prepared isolated export, migration/reset and legacy workflow databases.\n";

