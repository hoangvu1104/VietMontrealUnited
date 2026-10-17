<?php
require __DIR__.'/database-connection.php';
$columns=array_column($pdo->query('SHOW COLUMNS FROM players')->fetchAll(),'Field');
if (in_array('photo_path',$columns,true)) {
    echo "Player photos migration already applied.\n";
} else {
    $pdo->exec(file_get_contents(__DIR__.'/../database/migrations/003_player_photos.sql'));
    echo "Player photos migration applied.\n";
}
