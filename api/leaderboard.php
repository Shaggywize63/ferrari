<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$roundId = (int)($_GET['round'] ?? 0);
$limit   = min((int)($_GET['limit'] ?? 50), 200);

$pdo = db();

if ($roundId) {
    $stmt = $pdo->prepare('SELECT * FROM v_round_leaderboard WHERE round_id=? ORDER BY round_rank LIMIT ?');
    $stmt->execute([$roundId, $limit]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM v_leaderboard ORDER BY overall_rank LIMIT ?');
    $stmt->execute([$limit]);
}

echo json_encode([
    'success' => true,
    'round_id' => $roundId ?: null,
    'data'    => $stmt->fetchAll(),
    'timestamp' => time(),
]);
