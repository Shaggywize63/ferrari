<?php
/**
 * HP Matchday · leaderboard feed.
 *
 * GET leaderboard.php            all-time
 * GET leaderboard.php?today=1    today only (India time)
 *
 * Public: returns first name + last initial, role, kit, number and scores. Never contact
 * details or photos.
 */
require_once __DIR__ . '/_md.php';

$pdo   = mdDb();
$today = !empty($_GET['today']);
$where = 'score > 0' . ($today ? ' AND played_at >= CURDATE()' : '');
$cols  = 'name, role, kit, num, mode, score, made, shots';

$list = function (string $extra, string $order, int $limit) use ($pdo, $where, $cols): array {
    $rows = $pdo->query("SELECT $cols FROM md_players WHERE $where $extra ORDER BY $order LIMIT $limit")->fetchAll();
    return array_map(fn(array $r): array => [
        'name'  => mdPublicName($r['name']),
        'role'  => $r['role'] ?: 'playmaker',
        'kit'   => $r['kit'] ?: 'white',
        'num'   => $r['num'] !== null ? (int)$r['num'] : null,
        'mode'  => $r['mode'] ?: 'taker',
        'score' => (int)$r['score'],
        'made'  => (int)$r['made'],
        'shots' => (int)$r['shots'],
    ], $rows);
};

$count = fn(string $w): int => (int)$pdo->query("SELECT COUNT(*) FROM md_players WHERE $w")->fetchColumn();

mdOut(200, [
    'success' => true,
    'today'   => $today,
    'top'     => $list('', 'score DESC, played_at ASC, id ASC', 10),
    'scorers' => $list("AND mode = 'taker'", 'made DESC, score DESC, played_at ASC', 5),
    'keepers' => $list("AND mode = 'keeper'", 'made DESC, score DESC, played_at ASC', 5),
    'players' => $count($today ? 'created_at >= CURDATE()' : '1'),
    'played'  => $count($where),
    'ts'      => time(),
]);
