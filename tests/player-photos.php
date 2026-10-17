<?php
require __DIR__.'/../scripts/database-connection.php';
if ($c['database']!=='roster_export_test' || $c['port']!==(getenv('TEST_DB_PORT') ?: '3311')) throw new RuntimeException('Isolated test database required.');
$checks=0;
function check(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); echo "PASS: $label\n"; $checks++; }
class PhotoClient {
 private CurlHandle $ch; private string $csrf='';
 function __construct() { $this->ch=curl_init(); curl_setopt_array($this->ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]); }
 function request(string $page,array $data=[],array $args=[]): array {
  curl_setopt($this->ch,CURLOPT_URL,'http://127.0.0.1:8769/index.php?'.http_build_query(['page'=>$page]+$args));
  curl_setopt($this->ch,CURLOPT_POST,(bool)$data);
  if ($data) curl_setopt($this->ch,CURLOPT_POSTFIELDS,$data+['csrf'=>$this->csrf]);
  $body=curl_exec($this->ch); if ($body===false) throw new RuntimeException(curl_error($this->ch));
  if (preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m)) $this->csrf=$m[1];
  if (preg_match('/Fatal error|Warning:|Uncaught/',$body)) throw new RuntimeException('PHP errors in response');
  return [(int)curl_getinfo($this->ch,CURLINFO_HTTP_CODE),$body];
 }
 function login(string $name,string $password): void { $this->request('login'); [$code]=$this->request('login',['action'=>'login','username'=>$name,'password'=>$password]); check($code===302,'Login '.$name); $this->request('account'); }
}
$ids=$pdo->query('SELECT id FROM players ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$photo=fn()=> $pdo->query('SELECT photo_path FROM players WHERE id='.(int)$ids[0])->fetchColumn();
$filesBefore=glob(__DIR__.'/../public/uploads/*');
$q=$pdo->prepare("INSERT INTO users(username,password_hash,role,player_id) VALUES ('photo_test',?,'PLAYER',?)");
$q->execute([password_hash('PhotoTest123!',PASSWORD_DEFAULT),$ids[0]]);
$uid=(int)$pdo->lastInsertId();
$fixture=__DIR__.'/../var/photo-test.png';
file_put_contents($fixture,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aC1sAAAAASUVORK5CYII='));
file_put_contents(__DIR__.'/../var/photo-fake.png','<?php echo "bad";');
file_put_contents(__DIR__.'/../var/photo-large.png',str_repeat('x',2*1024*1024+1));
$upload=fn()=>new CURLFile($fixture,'image/png','portrait.png');
try {
 $guest=new PhotoClient(); $guest->request('login');
 [$code]=$guest->request('account',['action'=>'player-photo','photo'=>$upload()]); check($code===302 && !$photo(),'Guest cannot upload');
 $client=new PhotoClient(); $client->login('photo_test','PhotoTest123!');
 [$code,$body]=$client->request('account'); check($code===200 && str_contains($body,'multipart/form-data') && str_contains($body,'Ảnh đại diện'),'Upload form in own account');
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>$upload(),'csrf'=>'bad']); check($code===403 && !$photo(),'CSRF rejection');
 [$code]=$client->request('account',['action'=>'player-photo']); check($code===422,'Missing file rejection');
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>new CURLFile(__DIR__.'/../var/photo-fake.png','image/png','fake.png')]); check($code===422 && !$photo(),'Fake image rejected');
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>new CURLFile($fixture,'image/png','payload.php')]); check($code===422,'Executable extension rejected');
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>new CURLFile(__DIR__.'/../var/photo-large.png','image/png','large.png')]); check($code===422,'Over 2 MB rejected');
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>$upload(),'player_id'=>$ids[1]]);
 $first=$photo(); check($code===302 && is_string($first) && is_file(__DIR__.'/../public/'.$first),'Own photo saved');
 check($pdo->query('SELECT photo_path FROM players WHERE id='.(int)$ids[1])->fetchColumn()===null,'Forged player_id cannot change another player');
 foreach (['account','profile','players'] as $page) { [$code,$body]=$client->request($page,[],['id'=>$ids[0]]); check($code===200 && str_contains($body,$first),'Photo displays on '.$page); }
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>$upload()]); $second=$photo();
 check($code===302 && $second!==$first && !is_file(__DIR__.'/../public/'.$first) && is_file(__DIR__.'/../public/'.$second),'Replacement removes old photo');
 $files=glob(__DIR__.'/../public/uploads/*');
 $pdo->exec("CREATE TRIGGER reject_photo BEFORE UPDATE ON players FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test write failure'");
 try { [$code]=$client->request('account',['action'=>'player-photo','photo'=>$upload()]); check($code===422 && $photo()===$second && glob(__DIR__.'/../public/uploads/*')===$files,'Database failure preserves old photo and removes new orphan'); }
 finally { $pdo->exec('DROP TRIGGER reject_photo'); }
 $pdo->exec('UPDATE users SET is_active=0 WHERE id='.$uid);
 [$code]=$client->request('account',['action'=>'player-photo','photo'=>$upload()]); check($code===302 && $photo()===$second,'Disabled account cannot upload');
 $admin=new PhotoClient(); preg_match('/Password: (.+)/',file_get_contents(__DIR__.'/../var/godaddy-real-roster/admin-credentials.txt'),$m); $admin->login('admin',trim($m[1]));
 [$code]=$admin->request('account',['action'=>'player-photo','photo'=>$upload(),'player_id'=>$ids[0]]); check($code===403 && $photo()===$second,'Admin cannot impersonate photo owner through endpoint');
} finally {
 $path=$photo(); if ($path && is_file(__DIR__.'/../public/'.$path)) unlink(__DIR__.'/../public/'.$path);
 $pdo->exec('UPDATE players SET photo_path=NULL WHERE id='.(int)$ids[0]);
 $pdo->exec('DELETE FROM users WHERE id='.$uid);
}
check(glob(__DIR__.'/../public/uploads/*')===$filesBefore,'Upload files cleaned after tests');
check((int)$pdo->query('SELECT COUNT(*) FROM players WHERE is_active=1')->fetchColumn()===21,'Roster remains 21 active players');
echo "$checks photo checks passed.\n";

