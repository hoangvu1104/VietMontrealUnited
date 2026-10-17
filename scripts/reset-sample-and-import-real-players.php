<?php
require __DIR__.'/database-connection.php';
require __DIR__.'/../app/jerseys.php';
if (!in_array('--development-reset', $argv, true)) throw new RuntimeException('Usage: php scripts/reset-sample-and-import-real-players.php --development-reset (pause application writes first).');
$demoNames=['Minh Nguyen','John Nguyen','Peter Tran','Alex Pham','David Le','Kevin Vo','Thomas Bui','Hoang Nguyen','Duc Tran','Bao Pham','Quang Le','Huy Nguyen','Tony Tran','Andy Do','Long Vu','Tuan Ho','Nam Dang','Son Pham','Khoa Le','Daniel Nguyen','Anthony Phan','William Tran','Vincent Le','Eric Nguyen'];
$opponents=['Ottawa Athletic','Laval FC','Toronto United','Longueuil SC','Brossard FC','Montreal FC','Quebec City FC','Laval FC','Montreal FC','Toronto United','Ottawa Athletic'];
$tables=['player_match_stats','match_availability','users','matches','players','settings','login_attempts'];
$actual=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (array_diff($actual,$tables)) throw new RuntimeException('Unknown tables found; inspect dependencies before reset.');
$indexes=array_column($pdo->query('SHOW INDEX FROM players WHERE Non_unique=0')->fetchAll(),'Key_name');
if (!in_array('players_jersey_unique',$indexes,true)) throw new RuntimeException('Run scripts/migrate-jerseys.php first.');
$pdo->beginTransaction();
try {
    $before=[];
    foreach ($tables as $table) $before[$table]=$pdo->query("SELECT * FROM `$table` FOR UPDATE")->fetchAll();
    foreach ($before['players'] as $p) {
        if (($demoNames[$p['id']-1] ?? null)!==$p['full_name']) throw new RuntimeException('Unrecognized player or roster already imported; nothing deleted.');
    }
    foreach ($before['matches'] as $m) {
        if (($opponents[$m['id']-1] ?? null)!==$m['opponent_name']) throw new RuntimeException('Unrecognized match; nothing deleted.');
    }
    foreach ($before['users'] as $u) {
        if ($u['role']==='ADMIN' && $u['player_id']!==null) throw new RuntimeException('Admin linked to player; manual review needed.');
        if ($u['role']!=='ADMIN' && ($u['role']!=='PLAYER' || $u['username']!=='player'.$u['player_id'])) throw new RuntimeException('Unrecognized account; nothing deleted.');
    }
    $backup=__DIR__.'/../var/before-real-roster-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
    if (file_put_contents($backup,json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Backup failed.');
    $removed=[];
    foreach (['player_match_stats','match_availability'] as $table) $removed[$table]=$pdo->exec("DELETE FROM `$table`");
    $removed['users']=$pdo->exec("DELETE FROM users WHERE role='PLAYER'");
    foreach (['matches','players'] as $table) $removed[$table]=$pdo->exec("DELETE FROM `$table`");
    $insert=$pdo->prepare('INSERT INTO players(full_name,jersey_number,is_active) VALUES (?,?,1)');
    $roster=require __DIR__.'/../database/real-players.php';
    foreach ($roster as [$name,$jersey]) {
        if (jersey_owner($pdo,$jersey)) $jersey=find_available_jersey_number($pdo);
        $insert->execute([$name,$jersey]);
    }
    $players=$pdo->query('SELECT full_name,jersey_number,is_active FROM players ORDER BY id')->fetchAll();
    if (count($players)!==21 || count(array_unique(array_column($players,'jersey_number')))!==21 || count(array_unique(array_column($players,'full_name')))!==21 || array_sum(array_column($players,'is_active'))!==21) throw new RuntimeException('Roster verification failed.');
    foreach (['settings','login_attempts'] as $table) if ($before[$table]!==$pdo->query("SELECT * FROM `$table`")->fetchAll()) throw new RuntimeException('System data changed.');
    $admins=array_values(array_filter($before['users'],fn($u)=>$u['role']==='ADMIN'));
    if ($admins!==$pdo->query('SELECT * FROM users')->fetchAll()) throw new RuntimeException('Admin data changed.');
    $report=['database'=>$c['database'],'removed'=>$removed,'players'=>$players,'backup'=>$backup];
    $reportPath=__DIR__.'/../var/real-roster-report-'.date('Ymd-His').'.json';
    if (file_put_contents($reportPath,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Report write failed.');
    $pdo->commit();
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\nCommitted. Report: $reportPath\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
