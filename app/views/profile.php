<?php
$id=(int)($_GET['id'] ?? ($user['player_id'] ?? 0));
$p=null; foreach(rankings() as $row) if((int)$row['id']===$id) $p=$row;
if(!$p) throw new DomainException('Không tìm thấy cầu thủ.');
$private=is_admin() || ($user && (int)$user['player_id']===$id);
$recent=query("SELECT m.*,COALESCE(s.goals,0) AS goals,COALESCE(s.assists,0) AS assists FROM matches m JOIN match_availability a ON a.match_id=m.id AND a.player_id=? AND a.status='YES' LEFT JOIN player_match_stats s ON s.match_id=m.id AND s.player_id=a.player_id WHERE m.status='Completed' ORDER BY m.match_date DESC,m.match_time DESC",[$id]);
?>
<section class="profile-banner"><?= avatar($p) ?><div><span class="eyebrow">#<?= e($p['jersey_number']) ?></span><h2><?= e($p['full_name']) ?></h2><p><?= $p['is_active']?'Active':'Inactive' ?></p></div><?php if(is_admin()): ?><a class="btn btn-green" href="<?= url('admin-player-edit',['id'=>$id]) ?>">Edit player ↗</a><?php endif; ?></section>
<?php if ($user && $user['role']==='PLAYER' && (int)$user['player_id']===$id): ?><p><a class="btn btn-outline-dark" href="<?= url('account') ?>">Cập nhật ảnh đại diện</a></p><?php endif; ?>
<div class="metrics profile-metrics"><?php foreach(['played'=>'Matches played','goals'=>'Goals','assists'=>'Assists','contributions'=>'Goals + Assists'] as $key=>$label): ?><div class="metric"><span class="metric-value"><?= e($p[$key]) ?></span><span class="metric-label"><?= $label ?></span></div><?php endforeach; ?></div>
<?php if($private): ?><section class="panel my-4"><h2>Thông tin cá nhân</h2><div class="personal-details"><span>Năm sinh <strong><?= e($p['birth_year'] ?? '—') ?></strong></span></div></section><?php endif; ?>
<section class="panel mt-4"><h2>Recent matches</h2><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Opponent</th><th>Result</th><th>Goals</th><th>Assists</th><th></th></tr></thead><tbody><?php foreach($recent as $m): ?><tr><td><?= e($m['match_date']) ?></td><td><?= e($m['opponent_name']) ?></td><td><?= e(score($m)) ?></td><td><?= e($m['goals']) ?></td><td><?= e($m['assists']) ?></td><td><a href="<?= url('match',['id'=>$m['id']]) ?>"><?= $private?'View / enter stats':'View match' ?> ↗</a></td></tr><?php endforeach; ?></tbody></table><?php if(!$recent): ?><p class="empty">Chưa có trận đã tham gia.</p><?php endif; ?></div></section>

