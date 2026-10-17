<?php
// Every mutation is POST-only and passes CSRF and server-side authorization.
function handle_action(): void {
    global $user,$pdo;
    verify_csrf();
    $action=text_input('action',40);
    if ($action==='login') {
        $ip=hash('sha256',$_SERVER['REMOTE_ADDR'] ?? 'local');
        run('INSERT INTO login_attempts(ip_hash,attempts,window_start) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start < ?,1,attempts+1),window_start=IF(window_start < ?,VALUES(window_start),window_start)',[$ip,date('Y-m-d H:i:s'),date('Y-m-d H:i:s',time()-900),date('Y-m-d H:i:s',time()-900)]);
        $attempt=one('SELECT attempts FROM login_attempts WHERE ip_hash=?',[$ip]);
        if ($attempt['attempts']>10) throw new DomainException('Quá nhiều lần đăng nhập. Vui lòng thử lại sau 15 phút.');
        $u=one('SELECT u.*,p.is_active AS player_active FROM users u LEFT JOIN players p ON p.id=u.player_id WHERE username=?',[text_input('username',60)]);
        $password=text_input('password',1024);
        if (!$u || !password_verify($password,$u['password_hash']) || !$u['is_active'] || ($u['role']==='PLAYER' && !$u['player_active'])) throw new DomainException('Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.');
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
        run('DELETE FROM login_attempts WHERE ip_hash=?',[$ip]); redirect($u['role']==='ADMIN'?'admin':'home');
    }
    require_login();
    if ($action==='player-photo') {
        // Derive identity from the session, never from submitted player_id.
        if ($user['role']!=='PLAYER' || !$user['player_id']) abort_page(403,'Chỉ cầu thủ được cập nhật ảnh của mình.');
        $file=$_FILES['photo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) throw new DomainException('Vui lòng chọn ảnh JPG, PNG hoặc WebP.');
        $newPhoto=null;
        $pdo->beginTransaction();
        try {
            $player=one('SELECT id,photo_path,is_active FROM players WHERE id=? FOR UPDATE',[$user['player_id']]);
            if (!$player || !$player['is_active']) throw new DomainException('Tài khoản cầu thủ không còn hoạt động.');
            $newPhoto=upload_image('photo');
            run('UPDATE players SET photo_path=? WHERE id=?',[$newPhoto,$player['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            remove_player_photo($newPhoto);
            throw $e;
        }
        remove_player_photo($player['photo_path']);
        flash('Đã cập nhật ảnh đại diện.'); redirect('account');
    }
    if ($action==='logout') { $_SESSION=[]; session_destroy(); redirect('login'); }
    if ($action==='attendance') {
        $mid=number_input('match_id',2147483647); $m=match_by_id($mid);
        $pid=is_admin()?number_input('player_id',2147483647):(int)$user['player_id'];
        $status=choice('status',['YES','NO','MAYBE','NO_RESPONSE']);
        if (!one('SELECT id FROM players WHERE id=?',[$pid])) throw new DomainException('Không tìm thấy cầu thủ.');
        $pdo->beginTransaction();
        $m=one('SELECT * FROM matches WHERE id=? FOR UPDATE',[$mid]);
        $player=one('SELECT is_active FROM players WHERE id=? FOR UPDATE',[$pid]);
        if (!$player['is_active'] && $m['status']!=='Completed') throw new DomainException('Cầu thủ Inactive không được đăng ký trận mới.');
        if (!is_admin()) {
            $next=next_match();
            if (!$next || (int)$next['id']!==$mid || $m['status']!=='Upcoming' || strtotime($m['match_date'].' '.$m['match_time'])<=time()) throw new DomainException('Chỉ được xác nhận trận tiếp theo trước giờ bóng lăn.');
        }
        run('INSERT INTO match_availability(match_id,player_id,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)',[$mid,$pid,$status]);
        $pdo->commit(); flash('Đã cập nhật xác nhận tham gia.'); redirect(is_admin()?'admin-attendance':'match',['id'=>$mid]);
    }
    if ($action==='stats') {
        $mid=number_input('match_id',2147483647); $pid=is_admin()?number_input('player_id',2147483647):(int)$user['player_id'];
        $goals=number_input('goals'); $assists=number_input('assists');
        $pdo->beginTransaction();
        $m=one('SELECT * FROM matches WHERE id=? FOR UPDATE',[$mid]);
        if (!$m || $m['status']!=='Completed') throw new DomainException('Chỉ nhập thống kê sau khi trận đấu đã hoàn thành.');
        if (!one('SELECT id FROM players WHERE id=?',[$pid])) throw new DomainException('Không tìm thấy cầu thủ.');
        if (!is_admin() && !one("SELECT id FROM match_availability WHERE match_id=? AND player_id=? AND status='YES'",[$mid,$pid])) throw new DomainException('Bạn chưa được xác nhận tham gia trận đấu này.');
        run('INSERT INTO player_match_stats(match_id,player_id,goals,assists,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE goals=VALUES(goals),assists=VALUES(assists),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP',[$mid,$pid,$goals,$assists,$user['id']]);
        $pdo->commit(); flash('Đã lưu bàn thắng và kiến tạo.'); redirect(is_admin()?'admin-stats':'match',['id'=>$mid]);
    }
    if ($action==='password') {
        $password=text_input('new_password',1024);
        if (!password_verify(text_input('current_password',1024),$user['password_hash'])) throw new DomainException('Mật khẩu hiện tại không đúng.');
        if (strlen($password)<8 || strlen($password)>72) throw new DomainException('Mật khẩu mới cần 8–72 ký tự.');
        run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]); session_regenerate_id(true); flash('Đã đổi mật khẩu.'); redirect('account');
    }
    require_admin();
    if ($action==='save-player') {
        $id=number_input('id',2147483647); $old=$id?one('SELECT * FROM players WHERE id=?',[$id]):null;
        if ($id && !$old) throw new DomainException('Không tìm thấy cầu thủ.');
        $name=text_input('full_name',120);
        // Preserve string formatting; compare normalized jerseys in the database.
        $jersey=$_POST['jersey_number'] ?? null;
        if (!is_string($jersey) || trim($jersey)==='' || mb_strlen($jersey)>20) throw new DomainException('Số áo cần từ 1 đến 20 ký tự.');
        assert_unique_jersey($pdo,$jersey,$id);
        $year=text_input('birth_year',4,false);
        if ($year!=='' && (!preg_match('/^[0-9]{4}$/D',$year) || (int)$year<1900 || (int)$year>(int)date('Y'))) throw new DomainException('Năm sinh phải từ 1900 đến năm hiện tại.');
        $active=isset($_POST['is_active'])?(int)choice('is_active',['0','1']):0;
        $values=[$name,$jersey,$year===''?null:(int)$year,$active];
        try {
            if ($id) run('UPDATE players SET full_name=?,jersey_number=?,birth_year=?,is_active=? WHERE id=?',[...$values,$id]);
            else run('INSERT INTO players(full_name,jersey_number,birth_year,is_active) VALUES (?,?,?,?)',$values);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                assert_unique_jersey($pdo,$jersey,$id);
                throw new DomainException('Jersey number '.trim($jersey).' is already assigned. Please choose another number.');
            }
            throw $e;
        }
        flash('Đã lưu cầu thủ.'); redirect('admin-players');
    }
    if ($action==='save-match') {
        $id=number_input('id',2147483647); $old=$id?match_by_id($id):null;
        $date=date_input('match_date'); $time=text_input('match_time',5);
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)) throw new DomainException('Giờ thi đấu không hợp lệ.');
        $opponent=text_input('opponent_name',120); $location=$old['location'] ?? MATCH_VENUE_NAME; $side=choice('home_away',['Home','Away']);
        $status=choice('status',['Upcoming','Completed','Cancelled','Postponed']);
        $ours=$status==='Completed'?number_input('our_score'):null; $theirs=$status==='Completed'?number_input('opponent_score'):null;
        $logo=$old['opponent_logo'] ?? null; // Preserve retired logo data.
        $values=[$date,$time,$opponent,$logo,$location,$side,$ours,$theirs,$status];
        if ($id) run('UPDATE matches SET match_date=?,match_time=?,opponent_name=?,opponent_logo=?,location=?,home_away=?,our_score=?,opponent_score=?,status=? WHERE id=?',[...$values,$id]);
        else { run('INSERT INTO matches(match_date,match_time,opponent_name,opponent_logo,location,home_away,our_score,opponent_score,status) VALUES (?,?,?,?,?,?,?,?,?)',$values); $id=(int)$pdo->lastInsertId(); }
        flash('Đã lưu trận đấu.'); redirect('admin-matches');
    }
    if ($action==='delete-match') { $id=number_input('id',2147483647); match_by_id($id); run('DELETE FROM matches WHERE id=?',[$id]); flash('Đã xóa trận đấu cùng dữ liệu tham gia và thống kê của trận.'); redirect('admin-matches'); }
    if ($action==='create-user') {
        $pid=number_input('player_id',2147483647);
        if (!one('SELECT id FROM players WHERE id=? AND is_active=1',[$pid])) throw new DomainException('Chỉ tạo tài khoản cho cầu thủ Active.');
        $username=text_input('username',60); $password=text_input('password',1024);
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/D',$username)) throw new DomainException('Username cần 3–60 chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.');
        if (strlen($password)<8 || strlen($password)>72) throw new DomainException('Mật khẩu cần 8–72 ký tự.');
        if (one('SELECT id FROM users WHERE username=? OR player_id=?',[$username,$pid])) throw new DomainException('Username hoặc tài khoản cầu thủ đã tồn tại.');
        run("INSERT INTO users(username,password_hash,role,player_id) VALUES (?,?,'PLAYER',?)",[$username,password_hash($password,PASSWORD_DEFAULT),$pid]);
        flash('Đã tạo tài khoản cầu thủ.'); redirect('admin-users');
    }
    if ($action==='save-user') {
        $id=number_input('id',2147483647); $u=one("SELECT * FROM users WHERE id=? AND role='PLAYER'",[$id]);
        if (!$u) throw new DomainException('Chỉ quản lý tài khoản cầu thủ tại đây.');
        $active=(int)choice('is_active',['0','1']); $password=text_input('password',1024,false);
        if ($password!=='' && (strlen($password)<8 || strlen($password)>72)) throw new DomainException('Mật khẩu cần 8–72 ký tự.');
        run('UPDATE users SET is_active=? WHERE id=?',[$active,$id]);
        if ($password!=='') run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
        flash('Đã cập nhật tài khoản.'); redirect('admin-users');
    }
    if ($action==='settings') {
        $values=['club_name'=>text_input('club_name',120),'club_short'=>text_input('club_short',8),'season_label'=>text_input('season_label',60)];
        $pdo->beginTransaction(); foreach($values as $k=>$v) run('UPDATE settings SET setting_value=? WHERE setting_key=?',[$v,$k]); $pdo->commit();
        flash('Đã lưu thiết lập CLB.'); redirect('admin-settings');
    }
    throw new DomainException('Thao tác không hợp lệ.');
}
