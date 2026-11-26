<?php
/**
 * HP Matchday · player record (station login and saving progress).
 *
 * GET  player.php?code=ANA981        Station login with the login ID.
 *                                    409 { ambiguous: true } when two players share it.
 * POST player.php { login, mobile }  The same, confirmed with the mobile number.
 * POST player.php { code, key, rec } Save the player's journey (avatar, jersey, game result).
 *                                    Returns the player's best score and leaderboard position.
 */
require_once __DIR__ . '/_md.php';

/** Players a login ID can belong to: that exact code, or the code plus a two-character suffix. */
function mdCandidates(PDO $pdo, string $login): array {
    $s = $pdo->prepare('SELECT * FROM md_players WHERE code = ? OR (code LIKE ? AND CHAR_LENGTH(code) = ?) ORDER BY id DESC LIMIT 50');
    $s->execute([$login, $login . '__', strlen($login) + 2]);   // mdCode() leaves only A-Z and 0-9
    return $s->fetchAll();
}

$pdo = mdDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $login = mdCode($_GET['code'] ?? '');
    if ($login === '') {
        mdOut(422, ['success' => false, 'message' => 'Enter your login ID.']);
    }
    $rows = mdCandidates($pdo, $login);
    if (!$rows) {
        mdOut(404, ['success' => false, 'message' => 'We couldn’t find that login ID.']);
    }
    if (count($rows) > 1) {
        mdOut(409, ['success' => false, 'ambiguous' => true, 'message' => 'More than one player has this login ID.']);
    }
    mdOut(200, mdPlayerOut($rows[0]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mdOut(405, ['success' => false, 'message' => 'Not allowed.']);
}
$b = mdBody();

// station login confirmed with the mobile number
if (isset($b['login'])) {
    $login  = mdCode($b['login']);
    $mobile = mdPhone($b['mobile'] ?? '');
    if ($login === '' || strlen($mobile) !== 10) {
        mdOut(422, ['success' => false, 'message' => 'Enter your login ID and 10-digit contact number.']);
    }
    foreach (mdCandidates($pdo, $login) as $row) {
        if ($row['phone'] === $mobile) mdOut(200, mdPlayerOut($row));
    }
    mdOut(404, ['success' => false, 'message' => 'No player matches that login ID and contact number.']);
}

// save
$code = mdCode($b['code'] ?? '');
$rec  = $b['rec'] ?? null;
$key  = (string)($b['key'] ?? '');
if ($code === '' || !is_array($rec)) {
    mdOut(422, ['success' => false, 'message' => 'Invalid record.']);
}
if ($key === '' || !hash_equals(mdKey($code), $key)) {
    mdOut(403, ['success' => false, 'message' => 'Not allowed to update this player.']);
}
$s = $pdo->prepare('SELECT * FROM md_players WHERE code = ? LIMIT 1');
$s->execute([$code]);
$row = $s->fetch();
if (!$row) {
    mdOut(404, ['success' => false, 'message' => 'Unknown player.']);
}

unset($rec['mobile'], $rec['phone'], $rec['email'], $rec['key']);
// the photo is the only large field: keep it only if it really is a small JPEG
if (isset($rec['photo']) && (!is_string($rec['photo']) || strlen($rec['photo']) > 300000
    || !preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/=]+$#', $rec['photo']))) {
    $rec['photo'] = '';
}
$rec['id'] = $code;
$ts = max(0, (int)($rec['_ts'] ?? 0));

$role = in_array($rec['role'] ?? null, MD_ROLES, true) ? $rec['role'] : null;
$kit  = in_array($rec['kit'] ?? null, MD_KITS, true) ? $rec['kit'] : null;
$num  = isset($rec['num']) && (int)$rec['num'] >= 1 && (int)$rec['num'] <= 99 ? (int)$rec['num'] : null;

// The leaderboard keeps each player's best game, whichever copy of the record is newest.
$best = [(int)$row['score'], (int)$row['made'], (int)$row['shots'], $row['mode'], $row['played_at']];
foreach ([$rec['best'] ?? null, $rec['game'] ?? null] as $g) {
    if (!is_array($g) || !isset($g['score'])) continue;
    $sc = max(0, min(MD_MAX_SCORE, (int)$g['score']));
    if ($sc > $best[0]) {
        $best = [$sc, max(0, min(99, (int)($g['made'] ?? 0))), max(0, min(99, (int)($g['shots'] ?? 0))),
                 ($g['mode'] ?? '') === 'keeper' ? 'keeper' : 'taker', date('Y-m-d H:i:s')];
    }
}

$json = json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($ts >= (int)$row['rec_ts']) {   // a delayed save from one station must not undo a newer one
    $pdo->prepare('UPDATE md_players SET rec = ?, rec_ts = ?, role = COALESCE(?, role), kit = COALESCE(?, kit), num = COALESCE(?, num) WHERE id = ?')
        ->execute([$json, $ts, $role, $kit, $num, $row['id']]);
}
$pdo->prepare('UPDATE md_players SET score = ?, made = ?, shots = ?, mode = ?, played_at = ? WHERE id = ?')
    ->execute([$best[0], $best[1], $best[2], $best[3], $best[4], $row['id']]);

mdOut(200, ['success' => true, 'best' => $best[0], 'rank' => mdRank($pdo, $best[0])]);
