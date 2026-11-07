<?php
$id = (int)($_GET['id'] ?? 0);
$m = $id ? match_by_id($id) : [];
$isCompleted = ($m['status'] ?? '') === 'Completed';
if ($error && ($_POST['action'] ?? '') === 'save-match') $m = array_replace($m, $_POST);
?>
<section class="panel"><div class="section-heading"><h2><?= $id?'Edit match':'Create a match' ?></h2><a href="<?= url('admin-matches') ?>">← All matches</a></div><form method="post"><?= csrf() ?><input type="hidden" name="action" value="save-match"><input type="hidden" name="id" value="<?= $id ?>"><div class="form-grid"><?php
field('Ngày thi đấu','match_date',$m['match_date'] ?? date('Y-m-d'),'date'); field('Giờ thi đấu (America/Toronto)','match_time',substr($m['match_time'] ?? '20:00',0,5),'time');
field('Đối thủ','opponent_name',$m['opponent_name'] ?? '','text',true,'maxlength="120"');
select_field('Sân nhà / khách','home_away',['Home'=>'Home','Away'=>'Away'],$m['home_away'] ?? 'Home'); select_field('Trạng thái','status',array_combine(['Upcoming','Completed','Cancelled','Postponed'],['Upcoming','Completed','Cancelled','Postponed']),$m['status'] ?? 'Upcoming');
field('Bàn thắng đội mình (khi Completed)','our_score',$m['our_score'] ?? '','number',false,'min="0" max="999"'); field('Bàn thắng đối thủ (khi Completed)','opponent_score',$m['opponent_score'] ?? '','number',false,'min="0" max="999"');
?><div class="field venue-readonly"><span>Địa điểm thi đấu</span><strong><?= e(MATCH_VENUE_NAME) ?></strong><span><?= e(MATCH_VENUE_ADDRESS) ?></span></div></div><p class="muted mt-3">Khi chuyển sang Completed, cần nhập cả hai tỷ số. Bàn thắng đội mình luôn ở ô đầu, kể cả sân khách.</p><button class="btn btn-dark">Save match</button></form></section>
<?php if ($isCompleted): ?>
<section class="panel mt-4">
    <div class="section-heading">
        <h2>Video highlight</h2>
        <a href="<?= e(url('match', ['id' => $id])) ?>#highlights">Xem trang trận đấu ↗</a>
    </div>
    <p class="muted" id="highlight-help">Tải từng clip MP4, tối đa 50 MB mỗi clip và trong giới hạn máy chủ. Video hiển thị công khai trên trang trận đấu.</p>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('admin-match-edit', ['id' => $id, 'upload' => 'highlight'])) ?>">
        <?= csrf() ?>
        <input type="hidden" name="action" value="upload-highlight">
        <input type="hidden" name="id" value="<?= $id ?>">
        <label class="field" for="highlight-video">Chọn video MP4</label>
        <input class="form-control" type="file" id="highlight-video" name="highlight" accept=".mp4,video/mp4" aria-describedby="highlight-help" required>
        <button class="btn btn-dark mt-3">Tải video lên</button>
    </form>
</section>
<?php endif; ?>
<?php if($id): ?><section class="panel mt-4"><h3>Delete match</h3><p class="muted">Xóa trận sẽ xóa cả xác nhận tham gia, thống kê và video highlight của trận này.</p><form method="post" data-confirm="Xóa vĩnh viễn trận này cùng thống kê, xác nhận tham gia và video highlight?"><?= csrf() ?><input type="hidden" name="action" value="delete-match"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-danger">Delete this match</button></form></section><?php endif; ?>
