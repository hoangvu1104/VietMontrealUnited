<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
foreach ($_GET as $value) if (!is_string($value)) abort_page(400,'Tham số không hợp lệ.');
$page=is_string($_GET['page'] ?? null)?$_GET['page']:'home';
$routes=['home'=>'home','matches'=>'matches','match'=>'match','players'=>'players','profile'=>'profile','statistics'=>'statistics','login'=>'login','account'=>'account','admin'=>'home','admin-players'=>'admin-players','admin-player-edit'=>'admin-player-edit','admin-matches'=>'admin-matches','admin-match-edit'=>'admin-match-edit','admin-attendance'=>'admin-match-data','admin-stats'=>'admin-match-data','admin-users'=>'admin-users','admin-settings'=>'admin-settings'];
if (!isset($routes[$page])) abort_page(404,'Không tìm thấy trang.');
if (str_starts_with($page,'admin')) require_admin();
if ($page==='account') require_login();
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require __DIR__.'/../app/actions.php';
    try { handle_action(); }
    catch (DomainException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); http_response_code(422); }
    catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage()); $error='Không lưu được dữ liệu. Kiểm tra thông tin trùng lặp và thử lại.'; http_response_code(422); }
}
$titles=['home'=>'Club overview','matches'=>'Match centre','match'=>'Match detail','players'=>'Our squad','profile'=>'Player profile','statistics'=>'Club statistics','login'=>'Welcome back','account'=>'My account','admin'=>'Club dashboard','admin-players'=>'Manage players','admin-player-edit'=>'Player details','admin-matches'=>'Manage matches','admin-match-edit'=>'Match details','admin-attendance'=>'Match attendance','admin-stats'=>'Match statistics','admin-users'=>'Player accounts','admin-settings'=>'Club settings'];
$title=$titles[$page];
ob_start();
try { require __DIR__.'/../app/views/'.$routes[$page].'.php'; $content=ob_get_clean(); }
catch (DomainException $e) { ob_end_clean(); abort_page(404,$e->getMessage()); }
require __DIR__.'/../app/views/layout.php';
