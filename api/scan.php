<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

function jsonOut(bool $success, string $message, array $data = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Must be admin or logged-in session
if (!isAdminLoggedIn()) {
    jsonOut(false, 'Unauthorized.');
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$token   = trim($body['token'] ?? '');
$roundId = (int)($body['round_id'] ?? 0);

if (!$token || !$roundId) {
    jsonOut(false, 'Token and round_id are required.');
}

$pdo = db();

// Resolve by access code, mobile, or email
$lookup = strtoupper($token);
$stmt   = $pdo->prepare('SELECT * FROM participants WHERE access_code = ? OR phone = ? OR email = ? LIMIT 1');
$stmt->execute([$lookup, $token, $token]);
$participant = $stmt->fetch();

if (!$participant) {
    jsonOut(false, 'Participant not found. Check the access code, mobile number, or email.');
}

if ($participant['status'] === 'eliminated') {
    jsonOut(false, 'Participant is eliminated and cannot check in.');
}

// Validate round
$round = $pdo->prepare('SELECT * FROM rounds WHERE id = ?');
$round->execute([$roundId]);
$round = $round->fetch();
if (!$round) jsonOut(false, 'Round not found.');

// Upsert participant_round
$existing = $pdo->prepare('SELECT * FROM participant_rounds WHERE participant_id=? AND round_id=?');
$existing->execute([$participant['id'], $roundId]);
$pr = $existing->fetch();

if ($pr) {
    if ($pr['checked_in_at']) {
        jsonOut(false, $participant['name'] . ' is already checked in to this round.', [
            'participant' => $participant,
            'round'       => $round,
        ]);
    }
    $pdo->prepare("UPDATE participant_rounds SET checked_in_at=NOW(), checked_in_by=?, status='checked_in' WHERE id=?")
        ->execute([$_SESSION['admin_id'] ?? null, $pr['id']]);
} else {
    $pdo->prepare("INSERT INTO participant_rounds (participant_id, round_id, checked_in_at, checked_in_by, status) VALUES (?,?,NOW(),?,'checked_in')")
        ->execute([$participant['id'], $roundId, $_SESSION['admin_id'] ?? null]);
}

// Log scan
$pdo->prepare('INSERT INTO qr_scan_logs (participant_id, round_id, scanned_by, scan_type, ip_address, user_agent) VALUES (?,?,?,?,?,?)')
    ->execute([$participant['id'], $roundId, $_SESSION['admin_id'] ?? null, 'check_in', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

jsonOut(true, 'Checked in successfully!', [
    'participant' => [
        'id'    => $participant['id'],
        'name'  => $participant['name'],
        'email' => $participant['email'],
        'city'  => $participant['city'],
    ],
    'round' => [
        'id'   => $round['id'],
        'name' => $round['name'],
    ],
]);
