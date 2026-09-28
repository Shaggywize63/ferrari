<?php
/**
 * Leaderboard API
 *
 * POST  /api/leaderboard.php            Save a race result from a kiosk.
 *       Body (JSON): { fp26_id|code, score, num? }  — code must be a registered driver.
 * GET   /api/leaderboard.php?board=race[&since=<unix seconds>][&limit=N]
 *       Best race score per driver (since the given time, e.g. local midnight for "Today").
 * GET   /api/leaderboard.php[?round=N]  Original round-based leaderboard (admin rounds).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$pdo = db();
ensureKioskSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b     = json_decode(file_get_contents('php://input'), true) ?? [];
    $code  = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($b['fp26_id'] ?? $b['code'] ?? '')));
    $score = filter_var($b['score'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    $num   = substr(preg_replace('/\D/', '', (string)($b['num'] ?? '')), 0, 4);

    if ($code === '' || strlen($code) > 16 || $score === false) {
        respond(422, ['success' => false, 'message' => 'Invalid race result.']);
    }
    $s = $pdo->prepare('SELECT id, name FROM participants WHERE access_code = ? LIMIT 1');
    $s->execute([$code]);
    $p = $s->fetch();
    if (!$p) {
        respond(404, ['success' => false, 'message' => 'Unknown driver code.']);
    }
    $pdo->prepare('INSERT INTO race_scores (code, participant_id, name, race_num, score) VALUES (?, ?, ?, ?, ?)')
        ->execute([$code, $p['id'], $p['name'], $num !== '' ? $num : null, $score]);
    respond(200, ['success' => true]);
}

if (($_GET['board'] ?? '') === 'race') {
    $limit = max(1, min((int)($_GET['limit'] ?? 50), 200));
    $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
    $where = $since > 0 ? 'WHERE created_at >= FROM_UNIXTIME(?)' : '';
    $args  = $since > 0 ? [$since] : [];

    $rows = array_map(static fn(array $r) => [
        'id'    => $r['code'],
        'name'  => $r['name'],
        'num'   => $r['race_num'] ?? '',
        'score' => (int)$r['score'],
        'ts'    => (int)$r['ts'] * 1000,
    ], bestRaceScores($pdo, $since, $limit));

    $ct = $pdo->prepare("SELECT COUNT(DISTINCT code) FROM race_scores $where");
    $ct->execute($args);

    respond(200, [
        'success'   => true,
        'board'     => 'race',
        'total'     => (int)$ct->fetchColumn(),
        'data'      => $rows,
        'timestamp' => time(),
    ]);
}

// ---- original round-based leaderboard (admin-entered round scores) ----
$roundId = (int)($_GET['round'] ?? 0);
$limit   = min((int)($_GET['limit'] ?? 50), 200);

if ($roundId) {
    $stmt = $pdo->prepare('SELECT * FROM v_round_leaderboard WHERE round_id=? ORDER BY round_rank LIMIT ?');
    $stmt->execute([$roundId, $limit]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM v_leaderboard ORDER BY overall_rank LIMIT ?');
    $stmt->execute([$limit]);
}

respond(200, [
    'success'   => true,
    'round_id'  => $roundId ?: null,
    'data'      => $stmt->fetchAll(),
    'timestamp' => time(),
]);
