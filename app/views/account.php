<?php if ($user['role']==='PLAYER'): $player=one('SELECT full_name,photo_path FROM players WHERE id=?',[$user['player_id']]); ?>
<section class="panel narrow mb-4">
<h2>Ảnh đại diện</h2>
<div class="account-photo"><?= avatar($player) ?><strong><?= e($player['full_name']) ?></strong></div>
<p class="muted" id="photo-help">Chọn ảnh JPG, PNG hoặc WebP, tối đa 2 MB. Ảnh sẽ hiển thị trên hồ sơ và danh sách cầu thủ của CLB.</p>
<form method="post" enctype="multipart/form-data">
<?= csrf() ?><input type="hidden" name="action" value="player-photo">
<label class="field" for="player-photo">Chọn ảnh của bạn</label>
<input id="player-photo" class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help" required>
<button class="btn btn-dark mt-3" type="submit">Lưu ảnh đại diện</button>
</form>
</section>
<?php endif; ?>
<section class="panel narrow"><h2>Đổi mật khẩu</h2><p class="muted">Tài khoản: <?= e($user['username']) ?></p><form method="post"><?= csrf() ?><input type="hidden" name="action" value="password"><?php field('Mật khẩu hiện tại','current_password','','password',true,'autocomplete="current-password"'); field('Mật khẩu mới (8–72 ký tự)','new_password','','password',true,'minlength="8" maxlength="72" autocomplete="new-password"'); ?><button class="btn btn-dark mt-3">Lưu mật khẩu</button></form></section>

