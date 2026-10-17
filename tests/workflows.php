<?php
declare(strict_types=1);
// Run against a NEW dedicated test database/server; see README.
if (PHP_SAPI!=='cli') exit;
$base=$argv[1] ?? 'http://127.0.0.1:8766/index.php';
$c=require __DIR__.'/../config/database.php';
if(is_file(__DIR__.'/../config/database.local.php')) $c=array_replace($c,require __DIR__.'/../config/database.local.php');
if(!str_ends_with($c['database'],'_test')) exit("Safety: DB_NAME must end with _test.\n");
$pdo=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
date_default_timezone_set('America/Toronto');
require __DIR__.'/../app/functions.php';
$settings=['club_name'=>'Viet Montreal United'];
$checks=0;
function check(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; echo "PASS: $label\n"; }
class Client {
    private CurlHandle $ch;
    public string $token='';
    public function __construct() { $this->ch=curl_init(); curl_setopt_array($this->ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]); }
    public function request(string $page,array $data=[],array $args=[]): array {
        global $base;
        curl_setopt($this->ch,CURLOPT_URL,$base.'?'.http_build_query(['page'=>$page]+$args));
        curl_setopt($this->ch,CURLOPT_POST,(bool)$data);
        if($data) { $values=$data+['csrf'=>$this->token]; curl_setopt($this->ch,CURLOPT_POSTFIELDS,array_filter($data,fn($v)=>$v instanceof CURLFile)?$values:http_build_query($values)); }
        $body=curl_exec($this->ch); if($body===false) throw new RuntimeException(curl_error($this->ch));
        preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$matches); if(isset($matches[1])) $this->token=$matches[1];
        check(!preg_match('/(?:Fatal error|Warning:|Deprecated:|Uncaught)/',$body),'No PHP errors: '.$page);
        return [(int)curl_getinfo($this->ch,CURLINFO_HTTP_CODE),$body];
    }
    public function login(string $username,string $password): void { $this->request('login'); [$code]=$this->request('login',['action'=>'login','username'=>$username,'password'=>$password]); check($code===302,'Login '.$username); $this->request('home'); }
}
$public=new Client();
foreach(['home','matches','players','statistics','profile','match','login'] as $page) { [$code,$body]=$public->request($page,[],['id'=>$page==='match'?8:1]); check($code===200,'Public page '.$page); }
[$code,$body]=$public->request('profile',[],['id'=>1]); check(!str_contains($body,'Ngày sinh') && !str_contains($body,'Điện thoại'),'Removed fields absent from profile');
[$code]=$public->request('admin'); check($code===302,'Anonymous admin URL blocked');
[$code]=$public->request('login',['action'=>'login','username'=>'admin','password'=>'Admin123!','csrf'=>'wrong']); check($code===403,'CSRF enforcement');
$admin=new Client(); $admin->login('admin','Admin123!');
foreach(['admin','admin-players','admin-player-edit','admin-matches','admin-match-edit','admin-attendance','admin-stats','admin-users','admin-settings','account'] as $page) { [$code]=$admin->request($page); check($code===200,'Admin page '.$page); }
$name='Workflow <script>alert(1)</script>';
$player=['action'=>'save-player','id'=>0,'full_name'=>$name,'jersey_number'=>'Y-07','birth_year'=>'1998','is_active'=>'1'];
[$code]=$admin->request('admin-player-edit',$player); check($code===302,'Admin adds player without account fields');
$pid=(int)one('SELECT id FROM players WHERE full_name=?',[$name])['id'];
check(!one('SELECT id FROM users WHERE player_id=?',[$pid]),'Player creation does not create an implicit account');
$account=['action'=>'create-user','player_id'=>$pid,'username'=>'workflow_test','password'=>'Workflow123!'];
[$code]=$admin->request('admin-users',$account); check($code===302,'Separate account creation');
$uid=(int)one('SELECT id FROM users WHERE player_id=?',[$pid])['id'];
check(password_verify('Workflow123!',one('SELECT password_hash FROM users WHERE id=?',[$uid])['password_hash']),'New password hashed');
[$code,$body]=$public->request('players'); check(str_contains($body,'&lt;script&gt;') && !str_contains($body,$name),'Stored HTML escaped');
[$code]=$admin->request('admin-users',$account); check($code===422,'Duplicate account rejected');
foreach(['Y-07','2003','007','A-10','GK-01',' 007 ','ABCDEFGHIJKLMNOPQRST'] as $jersey) {
    [$code]=$admin->request('admin-player-edit',array_replace($player,['id'=>$pid,'jersey_number'=>$jersey]),['id'=>$pid]);
    check($code===302 && one('SELECT jersey_number FROM players WHERE id=?',[$pid])['jersey_number']===$jersey,'Exact jersey roundtrip: '.$jersey);
    foreach(['admin-players','players','profile','statistics','admin-player-edit'] as $view) {
        [$code,$body]=$admin->request($view,[],['id'=>$pid]);
        check($code===200 && str_contains($body,e($jersey)),'Exact jersey display '.$view.': '.$jersey);
    }
}
foreach(['','   ',str_repeat('A',21)] as $jersey) { [$code]=$admin->request('admin-player-edit',array_replace($player,['id'=>$pid,'jersey_number'=>$jersey]),['id'=>$pid]); check($code===422,'Invalid jersey length rejected'); }
foreach(['1899','2027','1990-01-01','abcd','1990.5'] as $year) { [$code]=$admin->request('admin-player-edit',array_replace($player,['id'=>$pid,'birth_year'=>$year]),['id'=>$pid]); check($code===422,'Invalid birth year rejected: '.$year); }
[$code]=$admin->request('admin-player-edit',array_replace($player,['id'=>$pid,'full_name'=>' ']),['id'=>$pid]); check($code===422,'Full name is required');
$admin->request('admin-player-edit',array_replace($player,['id'=>$pid]),['id'=>$pid]);
[$code,$body]=$admin->request('admin-player-edit',[],['id'=>$pid]);
preg_match_all('/<(?:input|select)\b[^>]*name="([^"]+)"/',$body,$formNames);
check(!array_diff($formNames[1],['csrf','action','id','full_name','jersey_number','birth_year','is_active']),'Only four player fields in edit form');
$pclient=new Client(); $pclient->login('workflow_test','Workflow123!');
[$code]=$pclient->request('admin-players'); check($code===403,'Player direct admin route forbidden');
[$code]=$pclient->request('home',$player); check($code===403,'Player forged admin action forbidden');
$match=['action'=>'save-match','id'=>0,'match_date'=>date('Y-m-d',time()+86400),'match_time'=>'18:30','opponent_name'=>'Workflow FC','home_away'=>'Away','status'=>'Upcoming','our_score'=>'','opponent_score'=>''];
[$code]=$admin->request('admin-match-edit',$match); check($code===302,'Admin creates match');
$mid=(int)one("SELECT id FROM matches WHERE opponent_name='Workflow FC'")['id'];
check((int)next_match()['id']===$mid,'Next match chooses earliest future Upcoming');
check(one('SELECT location FROM matches WHERE id=?',[$mid])['location']===MATCH_VENUE_NAME,'New match uses official venue without location input');
foreach(['home','match','admin-match-edit'] as $route) {
 [$code,$body]=$admin->request($route,[],['id'=>$mid]);
 check(str_contains($body,MATCH_VENUE_NAME) && str_contains($body,MATCH_VENUE_ADDRESS),'Official venue on '.$route);
 check(!str_contains($body,'name="opponent_logo"') && !str_contains($body,'name="location"'),'No logo or location input on '.$route);
 if($route!=='admin-match-edit') check(str_contains($body,e(MATCH_VENUE_MAP_URL)) && str_contains($body,'rel="noopener noreferrer"'),'Safe directions link on '.$route);
}
run('UPDATE matches SET location=?,opponent_logo=? WHERE id=?',['Legacy ground','uploads/legacy-logo.png',$mid]);
[$code]=$admin->request('admin-match-edit',array_replace($match,['id'=>$mid,'location'=>'Forged venue']),['id'=>$mid]);
$legacy=one('SELECT location,opponent_logo FROM matches WHERE id=?',[$mid]);
check($code===302 && $legacy['location']==='Legacy ground' && $legacy['opponent_logo']==='uploads/legacy-logo.png','Edit preserves legacy venue and logo; forged location ignored');
[$code,$body]=$public->request('match',[],['id'=>$mid]);
check(!str_contains($body,'uploads/legacy-logo.png') && !str_contains($body,'opponent-crest'),'Legacy opponent logo and placeholder not rendered');

$a=['action'=>'attendance','match_id'=>$mid,'status'=>'YES'];
[$code]=$pclient->request('match',$a,['id'=>$mid]); check($code===302,'Player confirms YES');
[$code]=$pclient->request('match',array_replace($a,['status'=>'NO']),['id'=>$mid]); check($code===302 && one('SELECT status FROM match_availability WHERE player_id=? AND match_id=?',[$pid,$mid])['status']==='NO','Player changes to NO');
$pclient->request('match',$a,['id'=>$mid]);
$stats=['action'=>'stats','match_id'=>$mid,'goals'=>'2','assists'=>'1'];
[$code]=$pclient->request('match',$stats,['id'=>$mid]); check($code===422,'Stats blocked before Completed');
$later=(int)one("SELECT id FROM matches WHERE id<>? AND status='Upcoming' ORDER BY match_date LIMIT 1",[$mid])['id'];
[$code]=$pclient->request('match',array_replace($a,['match_id'=>$later]),['id'=>$later]); check($code===422,'Attendance restricted to next match');
run('UPDATE matches SET match_date=?,match_time=? WHERE id=?',[date('Y-m-d',time()-86400),'18:30',$mid]);
[$code]=$pclient->request('match',$a,['id'=>$mid]); check($code===422,'Attendance blocked after kickoff');
$match=array_replace($match,['id'=>$mid,'match_date'=>date('Y-m-d',time()-86400),'status'=>'Completed','our_score'=>'3','opponent_score'=>'1']);
[$code]=$admin->request('admin-match-edit',$match,['id'=>$mid]); check($code===302,'Admin completes match and score');
[$code]=$pclient->request('match',array_replace($stats,['goals'=>'-1']),['id'=>$mid]); check($code===422,'Negative goals rejected');
[$code]=$pclient->request('match',array_replace($stats,['assists'=>'1.5']),['id'=>$mid]); check($code===422,'Fractional assists rejected');
[$code]=$pclient->request('match',$stats,['id'=>$mid]); check($code===302,'Participating player enters own stats');
$victim=one('SELECT * FROM player_match_stats WHERE match_id=? AND player_id=1',[$mid]);
$pclient->request('match',$stats+['player_id'=>1],['id'=>$mid]);
check(one('SELECT * FROM player_match_stats WHERE match_id=? AND player_id=1',[$mid])===$victim,'Forged player_id cannot edit another player');
$rank=array_column(rankings(),null,'id')[$pid]; check((int)$rank['played']===1 && (int)$rank['goals']===2 && (int)$rank['assists']===1,'Personal statistics update');
[$code,$body]=$admin->request('admin'); check(str_contains($body,'Workflow FC') && str_contains($body,'Kiểm tra thống kê'),'Goal mismatch warns admin');
[$code]=$admin->request('admin-stats',array_replace($stats,['player_id'=>$pid,'goals'=>'3']),['id'=>$mid]); check($code===302,'Admin corrects player statistics');
check((int)one('SELECT updated_by FROM player_match_stats WHERE match_id=? AND player_id=?',[$mid,$pid])['updated_by']===1,'Stats record editor');
check(!array_filter(mismatches(),fn($m)=>(int)$m['id']===$mid),'Mismatch clears after correction');
$nonparticipant=new Client(); $nonparticipant->login('player24','Player123!');
[$code]=$nonparticipant->request('match',$stats,['id'=>$mid]); check($code===422,'Nonparticipant cannot enter stats');
$before=team_stats(); check((int)$before['matches']===9 && (int)$before['wins']===6 && (int)$before['draws']===2 && (int)$before['losses']===1,'Team W/D/L aggregates correct');
check(score($match)==='1 – 3','Away score shown in home-away order');
$cancel=array_replace($match,['status'=>'Cancelled']); $admin->request('admin-match-edit',$cancel,['id'=>$mid]);
$rank=array_column(rankings(),null,'id')[$pid]; check((int)$rank['played']===0 && (int)$rank['goals']===0,'Cancelled match excluded from player totals');
check((int)team_stats()['matches']===8,'Cancelled match excluded from team totals');
$admin->request('admin-match-edit',$match,['id'=>$mid]);
$admin->request('admin-users',['action'=>'save-user','id'=>$uid,'is_active'=>'0','password'=>'']);
[$code]=$pclient->request('account'); check($code===302,'Locked account loses existing access');
$admin->request('admin-users',['action'=>'save-user','id'=>$uid,'is_active'=>'1','password'=>'Reset123!']);
$pclient->login('workflow_test','Reset123!');
$nextId=(int)next_match()['id'];
$admin->request('admin-attendance',['action'=>'attendance','player_id'=>$pid,'match_id'=>$nextId,'status'=>'YES'],['id'=>$nextId]);
$history=one('SELECT * FROM player_match_stats WHERE player_id=? AND match_id=?',[$pid,$mid]);
$inactive=array_replace($player,['id'=>$pid]); unset($inactive['is_active']);
$admin->request('admin-player-edit',$inactive,['id'=>$pid]);
check((int)one('SELECT is_active FROM players WHERE id=?',[$pid])['is_active']===0,'Unchecked checkbox becomes Inactive');
check(one('SELECT * FROM player_match_stats WHERE player_id=? AND match_id=?',[$pid,$mid])===$history,'Inactive preserves historical goals and assists');
check(in_array($pid,array_column(attendance($mid),'id')) && !in_array($pid,array_column(attendance($nextId),'id')),'Inactive retained in history, absent from next registration');
foreach(['players','admin-attendance','match'] as $view) { [$code,$body]=$admin->request($view,[],['id'=>$nextId]); check(!str_contains($body,e($name)),'Inactive excluded: '.$view); }
[$code]=$admin->request('admin-attendance',['action'=>'attendance','player_id'=>$pid,'match_id'=>$nextId,'status'=>'YES'],['id'=>$nextId]); check($code===422,'Admin cannot register Inactive in a new match');
$r=array_column(rankings(),null,'id')[$pid]; check((int)$r['played']===1 && (int)$r['goals']===3 && (int)$r['assists']===1,'Inactive retains all personal totals');
[$code]=$pclient->request('account'); check($code===302,'Inactive player loses access');
$admin->request('admin-player-edit',array_replace($player,['id'=>$pid,'is_active'=>'1','password'=>'']),['id'=>$pid]);
$pclient->login('workflow_test','Reset123!');
[$code]=$pclient->request('account',['action'=>'password','current_password'=>'Reset123!','new_password'=>'Changed123!']); check($code===302,'Own password change');
$admin->request('admin-matches',['action'=>'delete-match','id'=>$mid]);
check(!one('SELECT id FROM matches WHERE id=?',[$mid]) && !one('SELECT id FROM player_match_stats WHERE match_id=?',[$mid]) && !one('SELECT id FROM match_availability WHERE match_id=?',[$mid]),'Delete match cascades stats and attendance');
$admin->request('admin-settings',['action'=>'settings','club_name'=>'Test Club','club_short'=>'TC','season_label'=>'Test season']);
[$code,$body]=$public->request('home'); check(str_contains($body,'Test Club') && str_contains($body,'Test season'),'Settings saved and rendered');
$admin->request('admin-match-edit',array_replace($match,['id'=>0,'our_score'=>'']),[]);
check(!one("SELECT id FROM matches WHERE opponent_name='Workflow FC'"),'Completed match requires both scores');
run("UPDATE matches SET status='Cancelled'");
check(next_match()===null && last_match()===null,'Empty next/last selection');
[$code,$body]=$public->request('home'); check($code===200 && str_contains($body,'Chưa có trận sắp tới'),'Empty dashboard renders gracefully');
[$code]=$public->request('home',[],['q'=>['bad']]); check($code===400,'Malformed query rejected');
echo "\n$checks checks passed.\n";
