<?php
/**
 * HP Matchday · sign-up (Station 01).
 *
 * POST /matchday/api/register.php   { name, phone, email?, gender }
 * →    { success, code, login, key, rec, already }
 *
 * `login` is what the player types at the next stations (first 3 letters of the first
 * name + first 3 digits of the mobile). `code` is the same, or the same plus two hidden
 * characters when another player already holds that login ID.
 */
require_once __DIR__ . '/_md.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mdOut(405, ['success' => false, 'message' => 'POST required.']);
}

$b      = mdBody(16384);
$name   = trim(preg_replace('/\s+/', ' ', (string)($b['name'] ?? '')));
$phone  = mdPhone($b['phone'] ?? '');
$email  = strtolower(trim((string)($b['email'] ?? '')));
$gender = strtolower(trim((string)($b['gender'] ?? '')));

if (mb_strlen($name) < 2 || mb_strlen($name) > 60 || !preg_match('/\p{L}/u', $name)) {
    mdOut(422, ['success' => false, 'field' => 'name', 'message' => 'Please enter your full name.']);
}
if (strlen($phone) !== 10) {
    mdOut(422, ['success' => false, 'field' => 'phone', 'message' => 'Please enter a 10-digit contact number.']);
}
if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    mdOut(422, ['success' => false, 'field' => 'email', 'message' => 'That email address does not look right.']);
}
if (!in_array($gender, ['male', 'female', 'other'], true)) {
    mdOut(422, ['success' => false, 'field' => 'gender', 'message' => 'Please choose an option.']);
}

$pdo   = mdDb();
$login = mdLoginId($name, $phone);

// The same person signing up again (same mobile AND same name) gets their existing player back.
// A mobile number or an email on its own is never treated as identity: families share them.
$norm = fn(string $n): string => mb_strtolower(preg_replace('/\s+/', ' ', trim($n)));
$s = $pdo->prepare('SELECT * FROM md_players WHERE phone = ? ORDER BY id DESC LIMIT 20');
$s->execute([$phone]);
foreach ($s->fetchAll() as $row) {
    if ($norm($row['name']) === $norm($name)) {
        $pdo->prepare('UPDATE md_players SET email = IF(? <> \'\', ?, email), gender = ? WHERE id = ?')
            ->execute([$email, $email, $gender, $row['id']]);
        $row['gender'] = $gender;
        mdOut(200, mdPlayerOut($row) + ['login' => $login, 'already' => true]);
    }
}

$free = function (string $c) use ($pdo): bool {
    $q = $pdo->prepare('SELECT 1 FROM md_players WHERE code = ? LIMIT 1');
    $q->execute([$c]);
    return !$q->fetchColumn();
};
$code  = $login;
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
while (!$free($code)) {
    $code = $login . $chars[random_int(0, 31)] . $chars[random_int(0, 31)];
}

try {
    $pdo->prepare('INSERT INTO md_players (code, name, phone, email, gender) VALUES (?, ?, ?, ?, ?)')
        ->execute([$code, $name, $phone, $email, $gender]);
} catch (PDOException $e) {
    error_log('matchday register: ' . $e->getMessage());
    mdOut(500, ['success' => false, 'message' => 'Sign-up failed. Please try again.']);
}

mdOut(200, mdPlayerOut(['code' => $code, 'name' => $name, 'gender' => $gender, 'rec' => null])
    + ['login' => $login, 'already' => false]);
