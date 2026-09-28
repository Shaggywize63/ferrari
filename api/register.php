<?php
/**
 * Public registration endpoint — called by the standalone Supercraft app (index.html)
 * and optionally by any other client.
 *
 * POST /api/register.php
 * Body (JSON): { name, phone, email, fp26_id? }
 * Response:    { success, access_code, participant_id, message }
 */

// Allow cross-origin calls from the same domain (app runs at the same origin)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

function out(bool $ok, string $msg, array $extra = []): never {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$name   = trim($body['name']   ?? '');
$phone  = trim($body['phone']  ?? $body['mobile'] ?? '');
$email  = strtolower(trim($body['email'] ?? ''));
$fp26   = trim($body['fp26_id'] ?? '');   // internal Supercraft ID, stored for reference

if (!$name) out(false, 'Name is required.');
if (!$phone && !$email) out(false, 'Phone or email is required.');

// Basic phone normalisation: strip +91 prefix, keep digits only
$phone = preg_replace('/\D/', '', $phone);
if (str_starts_with($phone, '91') && strlen($phone) === 12) {
    $phone = substr($phone, 2);
}

$pdo = db();

// If already registered (by email or phone) return their existing code
$existing = null;
if ($email) {
    $s = $pdo->prepare('SELECT id, access_code FROM participants WHERE email = ? LIMIT 1');
    $s->execute([$email]);
    $existing = $s->fetch();
}
if (!$existing && $phone) {
    $s = $pdo->prepare('SELECT id, access_code FROM participants WHERE phone = ? LIMIT 1');
    $s->execute([$phone]);
    $existing = $s->fetch();
}

if ($existing) {
    out(true, 'Already registered.', [
        'access_code'    => $existing['access_code'],
        'participant_id' => $existing['id'],
        'already_exists' => true,
    ]);
}

// Generate unique access code
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
do {
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $ck = $pdo->prepare('SELECT id FROM participants WHERE access_code = ?');
    $ck->execute([$code]);
} while ($ck->fetch());

try {
    $ins = $pdo->prepare(
        'INSERT INTO participants (name, email, phone, access_code) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$name, $email ?: '', $phone ?: '', $code]);
    $pid = (int) $pdo->lastInsertId();

    // Auto-register in round 1 (qualification) if it exists and is active/upcoming
    $r1 = $pdo->query(
        "SELECT id FROM rounds WHERE round_number = 1 AND status IN ('active','upcoming') LIMIT 1"
    )->fetch();
    if ($r1) {
        $pdo->prepare(
            "INSERT IGNORE INTO participant_rounds (participant_id, round_id) VALUES (?, ?)"
        )->execute([$pid, $r1['id']]);
    }

    out(true, 'Registered successfully!', [
        'access_code'    => $code,
        'participant_id' => $pid,
        'already_exists' => false,
    ]);
} catch (PDOException $e) {
    // Duplicate entry (race condition) — retry lookup
    if ($email) {
        $s = $pdo->prepare('SELECT id, access_code FROM participants WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        $row = $s->fetch();
        if ($row) out(true, 'Already registered.', ['access_code' => $row['access_code'], 'participant_id' => $row['id'], 'already_exists' => true]);
    }
    out(false, 'Registration failed. Please try again.');
}
