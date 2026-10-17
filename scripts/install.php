<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$c=require __DIR__.'/../config/database.php';
if(is_file(__DIR__.'/../config/database.local.php')) $c=array_replace($c,require __DIR__.'/../config/database.local.php');
if(!preg_match('/^[a-zA-Z0-9_]+$/D',$c['database'])) exit("Invalid database name.\n");
$password=getenv('ADMIN_PASSWORD');
if (!$password || strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Set ADMIN_PASSWORD to 12–72 characters before installation.');
$pdo=new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `'.$c['database'].'`');
if($pdo->query('SHOW TABLES')->fetch()) exit("Database is not empty; no data changed. Use a new empty database.\n");
$pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
require __DIR__.'/../app/jerseys.php';
$pdo->beginTransaction();
try {
    $q=$pdo->prepare("INSERT INTO users(username,password_hash,role) VALUES (?,?, 'ADMIN')");
    $q->execute(['admin',password_hash($password,PASSWORD_DEFAULT)]);
    $q=$pdo->prepare('INSERT INTO players(full_name,jersey_number,is_active) VALUES (?,?,1)');
    foreach (resolve_roster_jerseys(require __DIR__.'/../database/real-players.php') as $player) $q->execute($player);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }
echo "Installed {$c['database']}: 21 active players; admin account created. No demo data.\n";
