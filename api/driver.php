<?php
/**
 * Driver record sync — lets a driver log in at any kiosk station with their unique code.
 *
 * GET   /api/driver.php?code=ARJ9876
 *       The driver's latest kiosk record (avatar, car, results; no mobile/email),
 *       plus a sync key so that kiosk can save the record back.
 * POST  /api/driver.php
 *       Body (JSON): { code, rec, mobile? | key? }
 *       Saves the record. The registering kiosk proves ownership with the driver's
 *       mobile; any other kiosk with the key it received from GET.
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

function cleanCode(mixed $v): string {
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$v));
    return strlen($c) >= 3 && strlen($c) <= 16 ? $c : '';
}

$pdo = db();
ensureKioskSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 6 * 1024 * 1024) {
        respond(413, ['success' => false, 'message' => 'Record too large.']);
    }
    $b    = json_decode($raw, true) ?? [];
    $code = cleanCode($b['code'] ?? '');
    $rec  = $b['rec'] ?? null;
    if ($code === '' || !is_array($rec) || cleanCode($rec['id'] ?? '') !== $code) {
        respond(422, ['success' => false, 'message' => 'Invalid record.']);
    }

    $s = $pdo->prepare('SELECT id, phone FROM participants WHERE access_code = ? LIMIT 1');
    $s->execute([$code]);
    $p = $s->fetch();
    if (!$p) {
        respond(404, ['success' => false, 'message' => 'Unknown driver code.']);
    }

    $mobile = substr(preg_replace('/\D/', '', (string)($b['mobile'] ?? '')), -10);
    $key    = (string)($b['key'] ?? '');
    $owner  = ($mobile !== '' && $mobile === substr((string)$p['phone'], -10))
           || ($key !== '' && hash_equals(kioskSyncKey($code), $key));
    if (!$owner) {
        respond(403, ['success' => false, 'message' => 'Not allowed to update this driver.']);
    }

    // contact details stay in participants; they never travel between kiosks
    unset($rec['mobile'], $rec['email'], $rec['_k']);
    $ts = max(0, (int)($rec['_ts'] ?? 0));

    $flags = journeyFlags($rec);

    // keep whichever copy is newest (a delayed save from one kiosk must not undo another's)
    $pdo->prepare(
        'INSERT INTO kiosk_records
            (code, participant_id, rec, rec_ts, driver_done, car_done, race_done, pit_done, tag, race_score, pit_score)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            rec         = IF(VALUES(rec_ts) >= rec_ts, VALUES(rec), rec),
            driver_done = IF(VALUES(rec_ts) >= rec_ts, VALUES(driver_done), driver_done),
            car_done    = IF(VALUES(rec_ts) >= rec_ts, VALUES(car_done), car_done),
            race_done   = IF(VALUES(rec_ts) >= rec_ts, VALUES(race_done), race_done),
            pit_done    = IF(VALUES(rec_ts) >= rec_ts, VALUES(pit_done), pit_done),
            tag         = IF(VALUES(rec_ts) >= rec_ts, VALUES(tag), tag),
            race_score  = IF(VALUES(rec_ts) >= rec_ts, VALUES(race_score), race_score),
            pit_score   = IF(VALUES(rec_ts) >= rec_ts, VALUES(pit_score), pit_score),
            participant_id = VALUES(participant_id),
            rec_ts      = GREATEST(rec_ts, VALUES(rec_ts))'
    )->execute([
        $code, $p['id'], json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $ts,
        (int)$flags['driver'], (int)$flags['car'], (int)$flags['race'], (int)$flags['pit'],
        isset($rec['tag']) ? substr((string)$rec['tag'], 0, 40) : null,
        $flags['race'] ? (int)$rec['race']['score'] : null,
        $flags['pit'] ? (int)$rec['pit']['score'] : null,
    ]);

    respond(200, ['success' => true]);
}

$code = cleanCode($_GET['code'] ?? '');
if ($code === '') {
    respond(422, ['success' => false, 'message' => 'Enter a unique code.']);
}
$s = $pdo->prepare(
    'SELECT k.rec, k.rec_ts FROM kiosk_records k
     JOIN participants p ON p.id = k.participant_id AND p.access_code = k.code
     WHERE k.code = ? LIMIT 1'
);
$s->execute([$code]);
$row = $s->fetch();
$rec = $row ? json_decode($row['rec'], true) : null;
if (!is_array($rec)) {
    respond(404, ['success' => false, 'message' => 'Unknown driver code.']);
}

respond(200, [
    'success' => true,
    'rec'     => $rec,
    'ts'      => (int)$row['rec_ts'],
    'key'     => kioskSyncKey($code),
]);
