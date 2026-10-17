<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$c=require __DIR__.'/../config/database.php';
if(is_file(__DIR__.'/../config/database.local.php')) $c=array_replace($c,require __DIR__.'/../config/database.local.php');
$pdo=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$columns=array_column($pdo->query('SHOW COLUMNS FROM players')->fetchAll(),null,'Field');
if(isset($columns['birth_year']) && !isset($columns['birth_date'])) { echo "Players schema already migrated.\n"; exit; }
if(isset($columns['birth_year']) || !isset($columns['birth_date'])) throw new RuntimeException('Unexpected schema; inspect before migrating.');
$before=[];
foreach(['users','match_availability','player_match_stats'] as $table) $before[$table]=hash('sha256',json_encode($pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll()));
$players=$pdo->query('SELECT * FROM players ORDER BY id')->fetchAll();
$backup=__DIR__.'/../var/players-before-migration-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
if(file_put_contents($backup,json_encode($players,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Cannot write backup.');
$pdo->exec(file_get_contents(__DIR__.'/../database/migrations/001_simplify_players.sql'));
$after=$pdo->query('SELECT * FROM players ORDER BY id')->fetchAll();
if(array_column($players,'id')!==array_column($after,'id')) throw new RuntimeException('Player IDs changed.');
foreach($before as $table=>$hash) if($hash!==hash('sha256',json_encode($pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll()))) throw new RuntimeException("History mismatch: $table");
foreach($after as $i=>$p) {
    if($p['jersey_number']!==(string)$players[$i]['jersey_number']) throw new RuntimeException('Jersey conversion mismatch.');
    $year=$players[$i]['birth_date']?substr($players[$i]['birth_date'],0,4):null;
    if(($p['birth_year']===null?null:(string)$p['birth_year'])!==$year) throw new RuntimeException('Birth year mismatch.');
}
echo 'Migrated '.count($after)." players; IDs, jerseys, birth years, accounts and match history verified.\nBackup: $backup\n";
