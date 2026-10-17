<?php
require_once __DIR__ . "/../config/match-venue.php";
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function query(string $sql, array $args=[]): array { $s=$GLOBALS['pdo']->prepare($sql); $s->execute($args); return $s->fetchAll(); }
function one(string $sql, array $args=[]): ?array { return query($sql,$args)[0] ?? null; }
function run(string $sql, array $args=[]): void { $s=$GLOBALS['pdo']->prepare($sql); $s->execute($args); }
function url(string $page='home', array $args=[]): string { return 'index.php?'.http_build_query(['page'=>$page]+$args); }
function redirect(string $page, array $args=[]): never { header('Location: '.url($page,$args)); exit; }
function abort_page(int $code,string $message): never { http_response_code($code); echo '<!doctype html><meta charset="utf-8"><h1>'.e($code).'</h1><p>'.e($message).'</p><a href="index.php">Về trang chủ</a>'; exit; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function text_input(string $key,int $max=190,bool $required=true): string {
    $v=trim(is_string($_POST[$key] ?? null)?$_POST[$key]:'');
    if (($required && $v==='') || mb_strlen($v)>$max) throw new DomainException('Thông tin không hợp lệ: '.$key);
    return $v;
}
function number_input(string $key,int $max=999): int {
    $v=$_POST[$key] ?? ''; if (!is_string($v) || !preg_match('/^\d+$/D',$v) || (int)$v>$max) throw new DomainException($key.': hãy nhập số nguyên từ 0 đến '.$max.'.'); return (int)$v;
}
function choice(string $key,array $choices): string { $v=text_input($key); if (!in_array($v,$choices,true)) throw new DomainException('Lựa chọn không hợp lệ: '.$key); return $v; }
function date_input(string $key,bool $required=true): ?string {
    $v=text_input($key,10,$required); if (!$required && $v==='') return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); if (!$d || $d->format('Y-m-d')!==$v) throw new DomainException('Ngày không hợp lệ: '.$key); return $v;
}
function next_match(): ?array { return one("SELECT * FROM matches WHERE status='Upcoming' AND TIMESTAMP(match_date,match_time)>? ORDER BY match_date,match_time,id LIMIT 1",[date('Y-m-d H:i:s')]); }
function last_match(): ?array { return one("SELECT * FROM matches WHERE status='Completed' ORDER BY match_date DESC,match_time DESC,id DESC LIMIT 1"); }
function match_by_id(int $id): array { return one('SELECT * FROM matches WHERE id=?',[$id]) ?? throw new DomainException('Không tìm thấy trận đấu.'); }
function attendance(int $match): array {
    return query("SELECT p.id,p.full_name,p.jersey_number,p.is_active,COALESCE(a.status,'NO_RESPONSE') AS response FROM players p LEFT JOIN match_availability a ON a.player_id=p.id AND a.match_id=? WHERE p.is_active=1 OR (a.id IS NOT NULL AND EXISTS (SELECT 1 FROM matches WHERE id=? AND status='Completed')) ORDER BY p.jersey_number,p.full_name",[$match,$match]);
}
function attendance_counts(int $match): array {
    $c=['YES'=>0,'NO'=>0,'MAYBE'=>0,'NO_RESPONSE'=>0]; foreach(attendance($match) as $p) { if ($p['is_active']) $c[$p['response']]++; } return $c;
}
function team_stats(): array {
    return one("SELECT COUNT(*) AS matches,COALESCE(SUM(our_score>opponent_score),0) AS wins,COALESCE(SUM(our_score=opponent_score),0) AS draws,COALESCE(SUM(our_score<opponent_score),0) AS losses,COALESCE(SUM(our_score),0) AS gf,COALESCE(SUM(opponent_score),0) AS ga,COALESCE(SUM(CAST(our_score AS SIGNED)-CAST(opponent_score AS SIGNED)),0) AS gd FROM matches WHERE status='Completed'");
}
function rankings(string $sort='goals'): array {
    $sort=in_array($sort,['goals','assists','played','contributions'],true)?$sort:'goals';
    return query("SELECT p.*,COALESCE(s.goals,0) AS goals,COALESCE(s.assists,0) AS assists,COALESCE(s.goals,0)+COALESCE(s.assists,0) AS contributions,COALESCE(a.played,0) AS played FROM players p LEFT JOIN (SELECT s.player_id,SUM(s.goals) AS goals,SUM(s.assists) AS assists FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.status='Completed' GROUP BY s.player_id) s ON s.player_id=p.id LEFT JOIN (SELECT a.player_id,COUNT(*) AS played FROM match_availability a JOIN matches m ON m.id=a.match_id WHERE m.status='Completed' AND a.status='YES' GROUP BY a.player_id) a ON a.player_id=p.id ORDER BY $sort DESC,p.full_name,p.id");
}
function match_stats(int $id): array { return query('SELECT s.*,p.full_name,p.jersey_number,u.username AS editor FROM player_match_stats s JOIN players p ON p.id=s.player_id JOIN users u ON u.id=s.updated_by WHERE s.match_id=? ORDER BY s.goals DESC,s.assists DESC,p.full_name',[$id]); }
function mismatches(): array { return query("SELECT m.*,COALESCE(SUM(s.goals),0) AS declared_goals FROM matches m LEFT JOIN player_match_stats s ON s.match_id=m.id WHERE m.status='Completed' GROUP BY m.id,m.match_date,m.match_time,m.opponent_name,m.opponent_logo,m.location,m.home_away,m.our_score,m.opponent_score,m.status,m.created_at,m.updated_at HAVING COALESCE(SUM(s.goals),0)<>m.our_score ORDER BY m.match_date DESC"); }
function upload_image(string $key,?string $old=null): ?string {
    $f=$_FILES[$key] ?? null; if (!$f || ($f['error'] ?? null)===UPLOAD_ERR_NO_FILE) return $old;
    if (!is_array($f) || !is_int($f['error'] ?? null) || !is_string($f['name'] ?? null) || !is_string($f['tmp_name'] ?? null) || !is_int($f['size'] ?? null)) throw new DomainException('Tệp ảnh không hợp lệ.');
    if ($f['error']!==UPLOAD_ERR_OK || $f['size']<=0 || $f['size']>2*1024*1024 || !is_uploaded_file($f['tmp_name'])) throw new DomainException('Ảnh tối đa 2 MB; vui lòng thử lại.');
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
    if (!isset($types[$ext]) || $types[$ext]!==$mime || !@getimagesize($f['tmp_name'])) throw new DomainException('Chỉ chấp nhận ảnh JPG, PNG hoặc WebP hợp lệ.');
    $name=bin2hex(random_bytes(16)).'.'.$ext; $dir=__DIR__.'/../public/uploads/';
    if (!move_uploaded_file($f['tmp_name'],$dir.$name)) throw new DomainException('Không lưu được ảnh. Kiểm tra quyền thư mục uploads.');
    return 'uploads/'.$name;
}
function initials(string $name): string { $a=preg_split('/\s+/u',trim($name)); return mb_strtoupper(mb_substr($a[0],0,1).mb_substr(end($a),0,1)); }
function remove_player_photo(?string $path): void {
    if ($path && preg_match('~^uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~D',$path)) {
        $file=__DIR__.'/../public/'.$path;
        if (is_file($file) && !@unlink($file)) error_log('Could not remove replaced player photo.');
    }
}
function avatar(array $p): string {
    $photo=$p['photo_path'] ?? null;
    if (is_string($photo) && preg_match('~^uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~D',$photo)) return '<img class="avatar" src="'.e($photo).'" alt="Ảnh của '.e($p['full_name']).'" loading="lazy">';
    return '<span class="avatar">'.e(initials($p['full_name'])).'</span>';
}
function club(): string { return $GLOBALS['settings']['club_name'] ?? 'Viet Montreal United'; }
function club_slogan(): string { return 'Chung một sân – Chung một tinh thần – Chung một cộng đồng.'; }
function badge(string $text): string { return '<span class="badge status-'.e(strtolower($text)).'">'.e($text).'</span>'; }
function match_title(array $m): string { $a=club(); $b=$m['opponent_name']; if ($m['home_away']==='Away') [$a,$b]=[$b,$a]; return $a.' · '.$b; }
function score(array $m): string { return $m['status']==='Completed' ? ($m['home_away']==='Home'?$m['our_score'].' – '.$m['opponent_score']:$m['opponent_score'].' – '.$m['our_score']) : 'VS'; }
function field(string $label,string $name,mixed $value='',string $type='text',bool $required=true,string $extra=''): void { echo '<label class="field">'.e($label).'<input class="form-control" type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" '.($required?'required ':'').$extra.'></label>'; }
function select_field(string $label,string $name,array $options,mixed $value): void { echo '<label class="field">'.e($label).'<select class="form-select" name="'.e($name).'">'; foreach($options as $k=>$v) echo '<option value="'.e($k).'" '.((string)$value===(string)$k?'selected':'').'>'.e($v).'</option>'; echo '</select></label>'; }
