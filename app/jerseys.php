<?php
declare(strict_types=1);

// Use the same expression/collation as the generated database key.
function jersey_owner(PDO $db, string $jersey, int $exceptId = 0): ?array {
    $q = $db->prepare('SELECT id,full_name FROM players WHERE jersey_key = LOWER(TRIM(CONVERT(? USING utf8mb4))) COLLATE utf8mb4_bin AND id <> ? LIMIT 1');
    $q->execute([$jersey, $exceptId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function assert_unique_jersey(PDO $db, string $jersey, int $exceptId = 0): void {
    if ($owner = jersey_owner($db, $jersey, $exceptId)) {
        throw new DomainException('Jersey number '.trim($jersey).' is already assigned to '.$owner['full_name'].'.');
    }
}

// The caller must still handle a unique-key conflict if another writer wins.
function find_available_jersey_number(PDO $db): string {
    for ($number = 1; $number < PHP_INT_MAX; $number++) {
        if (!jersey_owner($db, (string)$number)) return (string)$number;
    }
    throw new RuntimeException('No available jersey number.');
}

function resolve_roster_jerseys(array $roster): array {
    $used=[];
    foreach ($roster as &$player) {
        $key=mb_strtolower(trim($player[1]));
        if (isset($used[$key])) {
            $number=1;
            while (isset($used[(string)$number])) $number++;
            $player[1]=(string)$number;
            $key=$player[1];
        }
        $used[$key]=true;
    }
    unset($player);
    return $roster;
}
