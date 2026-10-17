<?php
function section_heading(string $title,?string $link=null,string $label='View all'): void { echo '<div class="section-heading"><h2>'.e($title).'</h2>'.($link?'<a href="'.e($link).'">'.e($label).' ↗</a>':'').'</div>'; }
function metric_cards(): void { $s=team_stats(); $labels=['matches'=>['▦','Matches played'],'wins'=>['♜','Wins'],'draws'=>['＝','Draws'],'losses'=>['↘','Losses'],'gf'=>['⚽','Goals for'],'ga'=>['◉','Goals against'],'gd'=>['↗','Goal difference']]; echo '<div class="metrics">'; foreach($labels as $k=>[$icon,$label]) echo '<div class="metric"><span class="metric-icon">'.$icon.'</span><span class="metric-value">'.($k==='gd' && $s[$k]>0?'+':'').e($s[$k]).'</span><span class="metric-label">'.$label.'</span></div>'; echo '</div>'; }
function match_card(array $m,bool $next=false,bool $detail=false): void {
    $home=$m['home_away']==='Home'; $left=$home?club():$m['opponent_name']; $right=$home?$m['opponent_name']:club();
    $heading=$detail?($m['status']==='Completed'?'MATCH REPORT':strtoupper($m['status'])):($next?'UP NEXT':'LAST MATCH');
    echo '<div class="match-card '.($next?'next-card':'last-card').'"><div class="card-top"><span class="eyebrow">'.e($heading).'</span>'.badge($next?$m['home_away']:'Full time').'</div>';
    echo '<div class="match-date">'.e(date('l, d F',strtotime($m['match_date']))).' <span>· '.e(substr($m['match_time'],0,5)).'</span></div><div class="scoreboard">';
    foreach([[$left,$home],[$right,!$home]] as $i=>[$name,$ours]) {
        if ($i===1) echo '<div class="score">'.e(score($m)).'<small>'.($next?'KICK-OFF '.e(substr($m['match_time'],0,5)):'FINAL SCORE').'</small></div>';
        echo '<div class="team">'.($ours?'<img class="team-logo" src="assets/images/club-logo.jpg" alt="Logo Viet Montreal United" width="72" height="72">':'').'<strong>'.e($name).'</strong><small>'.($ours?'OUR CLUB':'OPPONENT').'</small></div>';
    }
    echo '</div><div class="match-location">⌖ '.'<a href="'.e(MATCH_VENUE_MAP_URL).'" target="_blank" rel="noopener noreferrer">'.e(MATCH_VENUE_NAME).'</a><small>'.e(MATCH_VENUE_ADDRESS).'</small><a class="venue-directions" href="'.e(MATCH_VENUE_MAP_URL).'" target="_blank" rel="noopener noreferrer">Get Directions ↗</a>'.'</div>';
    if ($next) { $c=attendance_counts((int)$m['id']); echo '<div class="attendance-strip"><span><i class="live-dot"></i><strong>'.$c['YES'].'</strong> confirmed</span><span>'.$c['NO'].' unavailable</span><span>'.($c['NO_RESPONSE']+$c['MAYBE']).' pending</span></div>'; }
    else { $stats=match_stats((int)$m['id']); echo '<div class="scoring-summary">'; foreach(['goals'=>'⚽ Goals','assists'=>'↗ Assists'] as $key=>$label) { echo '<div><small>'.$label.'</small>'; $found=false; foreach($stats as $s) if($s[$key]>0) { $found=true; echo '<span>'.e($s['full_name']).' <b>'.e($s[$key]).'</b></span>'; } if(!$found) echo '<span>Chưa có thống kê</span>'; echo '</div>'; } echo '</div>'; }
    echo '<a class="card-link" href="'.url('match',['id'=>$m['id']]).'">'.($next?'View match & attendance':'View match report').' <span>↗</span></a></div>';
}
function leaderboard(string $sort,string $title,string $subtitle): void { $rows=array_slice(rankings($sort),0,5); echo '<section class="panel">'; section_heading($title,url('statistics',['sort'=>$sort])); echo '<p class="panel-subtitle">'.e($subtitle).'</p><div class="leaderboard">'; foreach($rows as $i=>$p) echo '<a class="leader-row" href="'.url('profile',['id'=>$p['id']]).'"><span class="rank">'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span>'.avatar($p).'<span class="player-name">'.e($p['full_name']).'<small>#'.e($p['jersey_number']).'</small></span><strong class="leader-total">'.e($p[$sort]).'</strong></a>'; if(!$rows) echo '<p class="empty">Chưa có cầu thủ.</p>'; echo '</div></section>'; }
function attendance_form(array $m): void {
    global $user;
    if (!$user || !$user['player_id'] || is_admin()) return;
    $next=next_match(); if(!$next || $next['id']!=$m['id']) return;
    $a=one('SELECT status FROM match_availability WHERE match_id=? AND player_id=?',[$m['id'],$user['player_id']]);
    echo '<div class="rsvp"><div><span class="eyebrow">MATCH AVAILABILITY</span><h3>Bạn có tham gia trận đấu này không?</h3><p>'.($a && $a['status']!=='NO_RESPONSE'?'Lựa chọn hiện tại: '.e($a['status']).'. Có thể đổi trước giờ bóng lăn.':'Bạn chưa xác nhận tham gia. Hãy cho đồng đội biết nhé!').'</p></div><form method="post" class="rsvp-buttons">'.csrf().'<input type="hidden" name="action" value="attendance"><input type="hidden" name="match_id" value="'.e($m['id']).'"><button class="btn btn-club" name="status" value="YES">✓ Yes, I will play</button><button class="btn btn-outline-dark" name="status" value="NO">✕ I can’t make it</button><button class="btn btn-light" name="status" value="MAYBE">Maybe</button></form></div>';
}

function venue_card(): void { ?>
<section class="panel venue-card mb-4"><div><span class="eyebrow">ĐỊA ĐIỂM THI ĐẤU</span><h2>Match Venue</h2><strong><?= e(MATCH_VENUE_NAME) ?></strong><p class="muted"><?= e(MATCH_VENUE_STREET) ?><br><?= e(MATCH_VENUE_CITY) ?></p></div><a class="btn btn-outline-dark" href="<?= e(MATCH_VENUE_MAP_URL) ?>" target="_blank" rel="noopener noreferrer">Xem bản đồ / Get Directions ↗</a></section>
<?php }
