<?php
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $u = one('SELECT u.*, p.full_name, p.photo_path, p.is_active AS player_active FROM users u LEFT JOIN players p ON p.id=u.player_id WHERE u.id=?', [$_SESSION['user_id']]);
    if (!$u || !$u['is_active'] || ($u['role']==='PLAYER' && (!$u['player_id'] || !$u['player_active']))) { unset($_SESSION['user_id']); return null; }
    return $u;
}
function require_login(): void { if (!$GLOBALS['user']) redirect('login'); }
function require_admin(): void { require_login(); if ($GLOBALS['user']['role'] !== 'ADMIN') abort_page(403, 'Bạn không có quyền quản trị.'); }
function is_admin(): bool { return ($GLOBALS['user']['role'] ?? '') === 'ADMIN'; }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function verify_csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) abort_page(403, 'Phiên biểu mẫu hết hạn. Vui lòng tải lại trang.');
}
