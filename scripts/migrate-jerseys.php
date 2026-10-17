<?php
require __DIR__.'/database-connection.php';
$columns = array_column($pdo->query('SHOW COLUMNS FROM players')->fetchAll(), 'Field');
if (in_array('jersey_key', $columns, true)) {
    $indexes = array_column($pdo->query('SHOW INDEX FROM players WHERE Non_unique=0')->fetchAll(), 'Key_name');
    if (!in_array('players_jersey_unique', $indexes, true)) throw new RuntimeException('Missing jersey unique index; inspect schema.');
    echo "Jersey migration already applied.\n";
} else {
    $pdo->exec(file_get_contents(__DIR__.'/../database/migrations/002_unique_jerseys.sql'));
    echo "Jersey migration applied.\n";
}
