<?php
$id=(int)($_GET['id'] ?? 0);
$p=$id?one('SELECT * FROM players WHERE id=?',[$id]):['is_active'=>1];
if($id && !$p) throw new DomainException('Không tìm thấy cầu thủ.');
if($error) $p=array_replace($p,$_POST,['is_active'=>$_POST['is_active'] ?? 0]);
?>
<section class="panel narrow">
<div class="section-heading"><h2><?= $id?'Cập nhật cầu thủ':'Thêm cầu thủ' ?></h2><a href="<?= url('admin-players') ?>">← Danh sách</a></div>
<form method="post">
<?= csrf() ?><input type="hidden" name="action" value="save-player"><input type="hidden" name="id" value="<?= $id ?>">
<?php
field('Họ và Tên','full_name',$p['full_name'] ?? '','text',true,'maxlength="120"');
field('Số áo','jersey_number',$p['jersey_number'] ?? '','text',true,'maxlength="20"');
field('Năm sinh','birth_year',$p['birth_year'] ?? '','number',false,'min="1900" max="'.date('Y').'" step="1"');
?>
<label class="form-check my-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= !empty($p['is_active'])?'checked':'' ?>><span class="form-check-label">Active</span></label>
<button class="btn btn-dark">Lưu</button>
</form></section>
