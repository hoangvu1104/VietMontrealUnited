<?php
require __DIR__.'/../scripts/database-connection.php';
require __DIR__.'/../app/jerseys.php';
if ($c['database']!=='roster_export_test' || $c['port']!==(getenv('TEST_DB_PORT') ?: '3311')) throw new RuntimeException('Isolated export database required.');
$checks=0;
function check(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); echo 'PASS: '.$label."\n"; $checks++; }
$expected=resolve_roster_jerseys(require __DIR__.'/../database/real-players.php');
$rows=$pdo->query('SELECT full_name,jersey_number FROM players ORDER BY id')->fetchAll(PDO::FETCH_NUM);
check($rows===$expected,'Exact 21-player exported roster and assigned jerseys');
check((int)$pdo->query('SELECT COUNT(*) FROM players WHERE is_active=1')->fetchColumn()===21,'21 active players');
check((int)$pdo->query('SELECT COUNT(DISTINCT jersey_key) FROM players')->fetchColumn()===21,'21 normalized unique jerseys');
foreach (['matches','match_availability','player_match_stats'] as $t) check((int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()===0,"Empty $t");
check((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='PLAYER'")->fetchColumn()===0,'No demo player accounts');
$base=$argv[1] ?? 'http://127.0.0.1:8769/index.php';
$ch=curl_init(); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>10]);
$token='';
function request(string $page,array $data=[],array $args=[]): array {
 global $ch,$base,$token;
 curl_setopt($ch,CURLOPT_URL,$base.'?'.http_build_query(['page'=>$page]+$args));
 curl_setopt($ch,CURLOPT_POST,(bool)$data);
 if ($data) curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data+['csrf'=>$token]));
 $body=curl_exec($ch); if ($body===false) throw new RuntimeException(curl_error($ch));
 if (preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m)) $token=$m[1];
 return [(int)curl_getinfo($ch,CURLINFO_HTTP_CODE),$body];
}
request('login');
preg_match('/Password: (.+)/',file_get_contents(__DIR__.'/../var/godaddy-real-roster/admin-credentials.txt'),$m);
[$code]=request('login',['action'=>'login','username'=>'admin','password'=>trim($m[1])]);
check($code===302,'Generated Admin login');
[$code,$body]=request('admin-players');
check($code===200,'Admin Players renders');
foreach ($expected as [$name,$jersey]) check(str_contains($body,$name),'Admin list contains '.$name);
$input=['action'=>'save-player','id'=>0,'full_name'=>'Jersey test','jersey_number'=>' 99 ','birth_year'=>'','is_active'=>'1'];
[$code,$body]=request('admin-player-edit',$input);
check($code===422 && str_contains($body,'Jersey number 99 is already assigned to Tuan Nguyen.'),'Add rejects duplicate with named owner');
$input['jersey_number']='Y-07';
[$code]=request('admin-player-edit',$input); check($code===302,'Add string jersey');
$id=(int)$pdo->lastInsertId();
$id=(int)$pdo->query("SELECT id FROM players WHERE full_name='Jersey test'")->fetchColumn();
try {
 $input['id']=$id;
 [$code]=request('admin-player-edit',$input,['id'=>$id]); check($code===302,'Edit permits own jersey');
 $input['jersey_number']='2003';
 [$code]=request('admin-player-edit',$input,['id'=>$id]); check($code===302,'Edit string jersey 2003');
 $input['jersey_number']='99';
 [$code,$body]=request('admin-player-edit',$input,['id'=>$id]); check($code===422 && str_contains($body,'Tuan Nguyen'),'Edit rejects occupied jersey');
 $input['jersey_number']='Y-07'; request('admin-player-edit',$input,['id'=>$id]);
 $input['id']=0; $input['jersey_number']=' y-07 ';
 [$code,$body]=request('admin-player-edit',$input); check($code===422 && str_contains($body,'Jersey test'),'Case/space insensitive Add validation');
 foreach (['99',' 99 ','y-07',' Y-07 '] as $jersey) {
  try { $q=$pdo->prepare("INSERT INTO players(full_name,jersey_number) VALUES ('Must fail',?)"); $q->execute([$jersey]); throw new RuntimeException('Database accepted duplicate'); }
  catch (PDOException $e) { check(($e->errorInfo[1] ?? 0)===1062,'Database unique rejects '.$jersey); }
 }
 try { $pdo->exec("INSERT INTO players(full_name,jersey_number) VALUES ('Must fail','   ')"); throw new RuntimeException('Accepted empty'); }
 catch (PDOException $e) { check(true,'Database rejects blank jersey'); }
 check(find_available_jersey_number($pdo)==='3','Reusable next available number accounts for occupied 1 and 2');
} finally { $q=$pdo->prepare('DELETE FROM players WHERE id=?'); $q->execute([$id]); }
check($pdo->query('SELECT full_name,jersey_number FROM players ORDER BY id')->fetchAll(PDO::FETCH_NUM)===$expected,'Roster unchanged after test cleanup');
echo "$checks checks passed.\n";

