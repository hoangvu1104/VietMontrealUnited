<?php
if (PHP_SAPI!=='cli') exit;
$db=new PDO('mysql:host=127.0.0.1;port='.(getenv('TEST_DB_PORT') ?: '3311').';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE roster_install_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec("GRANT ALL ON roster_install_test.* TO 'roster_test'@'127.0.0.1'");
putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT='.(getenv('TEST_DB_PORT') ?: '3311')); putenv('DB_NAME=roster_install_test'); putenv('DB_USER=roster_test'); putenv('DB_PASSWORD=IsolatedRosterTest123!'); putenv('ADMIN_PASSWORD=IsolatedAdminTest123!');
$p=proc_open([PHP_BINARY,__DIR__.'/../scripts/install.php'],[1=>STDOUT,2=>STDERR],$pipes);
if (proc_close($p)!==0) throw new RuntimeException('Installer failed');
$db->exec('USE roster_install_test');
if ((int)$db->query('SELECT COUNT(*) FROM players WHERE is_active=1')->fetchColumn()!==21 || (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==1) throw new RuntimeException('Installer verification failed');
echo "PASS: CLI installer creates 21 active players and one Admin.\n";

