<?php
/**
 * HP Matchday · shared API helpers.
 *
 * Matchday is a separate site from the Ferrari kiosk: it only shares the database
 * connection and server settings. All of its data lives in its own table (md_players),
 * which is created automatically on first use.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const MD_ROLES = ['striker', 'playmaker', 'defender', 'goalkeeper'];
const MD_KITS  = ['white', 'black', 'blue', 'navy', 'green'];
const MD_MAX_SCORE = 9999;

function mdOut(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function mdBody(int $maxBytes = 1048576): array {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > $maxBytes) {
        mdOut(413, ['success' => false, 'message' => 'Too much data.']);
    }
    $b = json_decode($raw, true);
    return is_array($b) ? $b : [];
}

function mdDb(): PDO {
    static $ready = false;
    $pdo = db();
    if (!$ready) {
        $ready = true;
        $pdo->exec("CREATE TABLE IF NOT EXISTS md_players (
            id         INT           NOT NULL AUTO_INCREMENT,
            code       VARCHAR(16)   NOT NULL,
            name       VARCHAR(100)  NOT NULL,
            phone      VARCHAR(20)   NOT NULL,
            email      VARCHAR(190)  NOT NULL DEFAULT '',
            gender     VARCHAR(12)   NOT NULL DEFAULT '',
            rec        MEDIUMTEXT    NULL,
            rec_ts     BIGINT        NOT NULL DEFAULT 0,
            role       VARCHAR(16)   NULL,
            kit        VARCHAR(16)   NULL,
            num        TINYINT UNSIGNED NULL,
            mode       VARCHAR(8)    NULL,
            score      INT           NOT NULL DEFAULT 0,
            made       INT           NOT NULL DEFAULT 0,
            shots      INT           NOT NULL DEFAULT 0,
            played_at  DATETIME      NULL,
            created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_md_code (code),
            KEY idx_md_phone (phone),
            KEY idx_md_score (score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    return $pdo;
}

/** Login IDs and codes: letters and digits only, upper case. */
function mdCode(mixed $v): string {
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$v));
    return strlen($c) >= 3 && strlen($c) <= 16 ? $c : '';
}

/** Ten-digit mobile number (drops +91 / leading 0). */
function mdPhone(mixed $v): string {
    $d = preg_replace('/\D/', '', (string)$v);
    if (strlen($d) === 12 && str_starts_with($d, '91')) $d = substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0') $d = substr($d, 1);
    return $d;
}

/** Login ID: first 3 letters of the first name + first 3 digits of the mobile, e.g. ANA981. */
function mdLoginId(string $name, string $phone): string {
    $first   = preg_split('/\s+/', trim($name))[0] ?? '';
    $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $first));
    if ($letters === '') $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
    return substr(str_pad($letters, 3, 'X'), 0, 3) . substr($phone, 0, 3);
}

/** Key a station receives with a player's record, so it can save that record back. */
function mdKey(string $code): string {
    return substr(hash_hmac('sha256', 'matchday-sync|' . $code, SESSION_SECRET . '|' . DB_PASS), 0, 32);
}

/** What a station receives at login. Contact details never leave the server. */
function mdPlayerOut(array $row): array {
    $rec = json_decode((string)($row['rec'] ?? ''), true);
    if (!is_array($rec)) $rec = [];
    $rec['id']     = $row['code'];
    $rec['name']   = $row['name'];
    $rec['gender'] = $row['gender'];
    return ['success' => true, 'code' => $row['code'], 'key' => mdKey($row['code']), 'rec' => $rec];
}

/** Leaderboard position for a score (1 = top). Ties share a position. */
function mdRank(PDO $pdo, int $score): ?int {
    if ($score <= 0) return null;
    $s = $pdo->prepare('SELECT COUNT(*) FROM md_players WHERE score > ?');
    $s->execute([$score]);
    return (int)$s->fetchColumn() + 1;
}

/** Name as shown on the public leaderboard: first name and the initial of the last name. */
function mdPublicName(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? '';
    $first = mb_strtoupper(mb_substr($first, 0, 1)) . mb_substr($first, 1);
    if (count($parts) > 1) {
        $first .= ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
    }
    return mb_substr($first, 0, 24);
}
