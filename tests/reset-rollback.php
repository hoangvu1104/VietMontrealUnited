<?php
if (PHP_SAPI!=='cli') exit;
$db=new PDO('mysql:host=127.0.0.1;port='.(getenv('TEST_DB_PORT') ?: '3311').';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE roster_rollback_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec("GRANT ALL ON roster_rollback_test.* TO 'roster_test'@'127.0.0.1'");
$db->exec('USE roster_rollback_test');
$db->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
$db->exec(file_get_contents(__DIR__.'/fixtures/demo.sql'));
$db->exec("CREATE TRIGGER fail_import BEFORE INSERT ON players FOR EACH ROW BEGIN IF NEW.full_name='Steven Ha' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Intentional rollback test'; END IF; END");
function snapshot(PDO $db): string {
 $rows=[]; foreach (['players','users','matches','match_availability','player_match_stats','settings','login_attempts'] as $t) $rows[$t]=$db->query("SELECT * FROM $t")->fetchAll(PDO::FETCH_ASSOC);
 return hash('sha256',json_encode($rows));
}
$before=snapshot($db);
putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT='.(getenv('TEST_DB_PORT') ?: '3311')); putenv('DB_NAME=roster_rollback_test'); putenv('DB_USER=roster_test'); putenv('DB_PASSWORD=IsolatedRosterTest123!');
$cmd=[PHP_BINARY,__DIR__.'/../scripts/reset-sample-and-import-real-players.php','--development-reset'];
$p=proc_open($cmd,[1=>['file',__DIR__.'/../var/rollback-test-output.txt','w'],2=>['file',__DIR__.'/../var/rollback-test-errors.txt','w']],$pipes);
$exit=proc_close($p);
if ($exit===0 || $before!==snapshot($db)) throw new RuntimeException('Rollback failed');
echo "PASS: failure on 21st player restores all seven tables, accounts, settings and statistics.\n";
putenv('DB_NAME=roster_reset_test'); $db->exec('USE roster_reset_test'); $before=snapshot($db);
$p=proc_open($cmd,[1=>['file',__DIR__.'/../var/rerun-test-output.txt','w'],2=>['file',__DIR__.'/../var/rerun-test-errors.txt','w']],$pipes);
$exit=proc_close($p);
if ($exit===0 || $before!==snapshot($db)) throw new RuntimeException('Rerun protection failed');
echo "PASS: repeat reset refuses real roster and changes no records.\n";

