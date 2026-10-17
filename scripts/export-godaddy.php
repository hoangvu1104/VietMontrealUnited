<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/jerseys.php';
$destination=__DIR__.'/../var/godaddy-real-roster';
if (!is_dir($destination) && !mkdir($destination,0700,true)) throw new RuntimeException('Cannot create export directory.');
if (is_file($destination.'/install.sql')) throw new RuntimeException('Export already exists; preserve the existing admin credentials.');
$password=bin2hex(random_bytes(16));
$quote=fn(string $s)=>"'".str_replace("'","''",$s)."'";
$sql="-- Import ONCE into a NEW EMPTY database. Never drop existing tables.\nSET NAMES utf8mb4;\n";
$sql.=file_get_contents(__DIR__.'/../database/schema.sql');
$sql.="\nSTART TRANSACTION;\n";
$sql.="INSERT INTO users(username,password_hash,role) VALUES ('admin',".$quote(password_hash($password,PASSWORD_DEFAULT)).",'ADMIN');\n";
foreach (resolve_roster_jerseys(require __DIR__.'/../database/real-players.php') as [$name,$jersey]) {
    $sql.='INSERT INTO players(full_name,jersey_number,is_active) VALUES ('.$quote($name).','.$quote($jersey).",1);\n";
}
$sql.="COMMIT;\nSELECT full_name AS Name,jersey_number AS Jersey,is_active AS Active FROM players ORDER BY full_name;\n";
if (file_put_contents($destination.'/admin-credentials.txt',"Username: admin\nPassword: $password\nChange this password after first login. Keep this file private; do not upload.\n")===false) throw new RuntimeException('Cannot save credentials.');
if (file_put_contents($destination.'/install.sql',$sql)===false) throw new RuntimeException('Cannot save SQL.');
echo "Created $destination/install.sql and private admin-credentials.txt\n";
