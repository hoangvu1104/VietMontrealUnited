<div class="section-heading"><h2>Danh sách cầu thủ</h2><a class="btn btn-dark" href="<?= url('admin-player-edit') ?>">+ Add player</a></div>
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Họ tên</th><th>Số áo</th><th>Năm sinh</th><th>Active</th><th>Action</th></tr></thead><tbody>
<?php foreach(query('SELECT * FROM players ORDER BY is_active DESC,full_name,id') as $p): ?>
<tr><td><a href="<?= url('profile',['id'=>$p['id']]) ?>"><?= e($p['full_name']) ?></a></td><td class="jersey-text"><?= e($p['jersey_number']) ?></td><td><?= e($p['birth_year'] ?? '—') ?></td><td><?= $p['is_active']?'✓ Active':'Inactive' ?></td><td><a href="<?= url('admin-player-edit',['id'=>$p['id']]) ?>">Edit ↗</a></td></tr>
<?php endforeach; ?></tbody></table></div></section>
