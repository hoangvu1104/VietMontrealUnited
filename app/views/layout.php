<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title.' · '.club()) ?></title><meta name="theme-color" content="#073649"><link rel="icon" type="image/jpeg" href="assets/images/club-logo.jpg"><link rel="stylesheet" href="assets/bootstrap.min.css"><link rel="stylesheet" href="assets/app.css?v=club-2022"><link rel="stylesheet" href="assets/club-theme.css?v=4"><script src="assets/app.js" defer></script></head>
<body>
<aside class="sidebar" id="navigation">
<a href="<?= url() ?>" class="brand"><img class="brand-logo" src="assets/images/club-logo.jpg" alt="Logo Viet Montreal United" width="48" height="48"><span><span class="brand-name">Viet Montreal United</span><small>FOOTBALL CLUB</small></span></a>
<div class="nav-caption">CLUB HOUSE</div>
<nav aria-label="Main navigation">
<?php $nav=['home'=>['◫','Overview'],'matches'=>['▦','Matches'],'players'=>['♧','Our squad'],'statistics'=>['▥','Statistics']]; if ($user && !is_admin()) { $nm=next_match(); if($nm) $nav['match']=['⚑','Next match']; $nav['profile']=['○','My profile']; } foreach($nav as $key=>[$icon,$label]): $args=$key==='profile'?['id'=>$user['player_id']]:($key==='match'?['id'=>$nm['id']]:[]); ?>
<a class="nav-item <?= $page===$key?'active':'' ?>" href="<?= url($key,$args) ?>"><span><?= $icon ?></span><?= e($label) ?><?= $key==='matches'?'<i class="nav-dot"></i>':'' ?></a>
<?php endforeach; ?>
<?php if(is_admin()): ?><div class="nav-caption">MANAGEMENT</div><?php foreach(['admin'=>'Dashboard','admin-players'=>'Players','admin-matches'=>'Matches','admin-attendance'=>'Attendance','admin-stats'=>'Match statistics','admin-users'=>'Users','admin-settings'=>'Settings'] as $key=>$label): ?><a class="nav-item <?= $page===$key?'active':'' ?>" href="<?= url($key) ?>"><span>›</span><?= e($label) ?></a><?php endforeach; endif; ?>
</nav>
<div class="sidebar-bottom"><div class="club-note"><span class="live-dot"></span> <?= e(club_slogan()) ?><small>Montréal, Québec · Since 2022</small></div>
<?php if($user): ?><a class="user-link" href="<?= url('account') ?>"><?= avatar(array_replace($user,['full_name'=>$user['full_name'] ?? 'Club Admin'])) ?><span><?= e($user['full_name'] ?? $user['username']) ?><small><?= e($user['role']) ?></small></span></a><form method="post"><?= csrf() ?><input type="hidden" name="action" value="logout"><button class="logout" type="submit">Log out ↗</button></form><?php else: ?><a class="btn btn-dark w-100" href="<?= url('login') ?>">Member login ↗</a><?php endif; ?></div>
</aside>
<div class="workspace"><header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="navigation" aria-expanded="false" aria-label="Toggle menu">☰</button><span class="breadcrumb-label">Club house <span>/</span> <strong><?= e($title) ?></strong></span></div><div class="topbar-right"><span class="season-pill"><span class="live-dot"></span><?= e($settings['season_label']) ?></span><span class="top-location">MONTRÉAL, QC</span></div></header>
<main id="main"><div class="page-heading"><div><div class="eyebrow">VIET MONTREAL UNITED</div><h1><?= e($title) ?><span class="title-dot">.</span></h1><p><?= e(club_slogan()) ?></p></div><div class="heading-action"><?php if(is_admin()): ?><a class="btn btn-dark" href="<?= url('admin-match-edit') ?>">+ Add match</a><?php else: ?><span class="date-stamp"><?= e(date('l, d M Y')) ?></span><?php endif; ?></div></div>
<?php if($error): ?><div role="alert" class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if(isset($_SESSION['flash'])): ?><div role="status" class="alert alert-success"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?= $content ?>
<footer><span>© <?= date('Y') ?> <?= e(club()) ?></span><span><?= e(club_slogan()) ?> <span class="accent">✦</span></span></footer>
</main></div>
</body></html>


