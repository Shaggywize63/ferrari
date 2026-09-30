<?php

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

function generateAccessCode(): string {
    // Excludes confusable chars: 0/O, 1/I/L
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function isParticipantLoggedIn(): bool {
    return !empty($_SESSION['participant_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        redirect(APP_URL . '/admin/login.php');
    }
    // an admin still on the default password must set their own before anything else
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'account.php') {
        redirect(APP_URL . '/admin/account.php');
    }
}

/** Documented default admin password (sql/schema.sql); it only ever works until changed. */
const DEFAULT_ADMIN_PASSWORD = 'Admin@123';
/** Hash the original schema seeded for the default admin; it never matched the documented password. */
const BROKEN_SEED_ADMIN_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

/** Password check for admin login, accepting the documented default on the broken seeded account. */
function adminPasswordOk(string $password, string $hash): bool {
    if (password_verify($password, $hash)) return true;
    return hash_equals(BROKEN_SEED_ADMIN_HASH, $hash) && hash_equals(DEFAULT_ADMIN_PASSWORD, $password);
}

function requireParticipant(): void {
    if (!isParticipantLoggedIn()) {
        redirect(APP_URL . '/participant-login.php');
    }
}

function currentAdmin(): ?array {
    if (!isAdminLoggedIn()) return null;
    static $admin = null;
    if ($admin === null) {
        $stmt = db()->prepare('SELECT id, username, name, email, role FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
    }
    return $admin;
}

function currentParticipant(): ?array {
    if (!isParticipantLoggedIn()) return null;
    static $participant = null;
    if ($participant === null) {
        $stmt = db()->prepare('SELECT * FROM participants WHERE id = ?');
        $stmt->execute([$_SESSION['participant_id']]);
        $participant = $stmt->fetch() ?: null;
    }
    return $participant;
}

function formatScore(float $score): string {
    return number_format($score, 1);
}

function getRankBadge(int $rank): string {
    return match($rank) {
        1 => '<span class="rank-badge rank-1">🥇 1st</span>',
        2 => '<span class="rank-badge rank-2">🥈 2nd</span>',
        3 => '<span class="rank-badge rank-3">🥉 3rd</span>',
        default => '<span class="rank-badge">#' . $rank . '</span>',
    };
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return $diff . 's ago';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

/**
 * Self-upgrading schema for kiosk data, so shared hosting needs no manual SQL:
 * widens participants.access_code for the kiosk login ID (e.g. ARJ987)
 * and creates race_scores (live leaderboard) and kiosk_records (cross-kiosk login,
 * journey stage). Each step is independent and never takes the page down: a host
 * that refuses foreign keys gets the tables without them.
 */
function ensureKioskSchema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $step = static function (callable $fn, string $what): void {
        try {
            $fn();
        } catch (Throwable $e) {
            error_log("ensureKioskSchema ($what): " . $e->getMessage());
        }
    };
    $create = static function (string $ddl, string $fk) use ($pdo): void {
        try {
            $pdo->exec(str_replace('{FK}', ",\n        $fk", $ddl));
        } catch (PDOException $e) {
            $pdo->exec(str_replace('{FK}', '', $ddl));
        }
    };

    $step(function () use ($pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM participants LIKE 'access_code'")->fetch();
        if ($col && stripos($col['Type'], 'char(6)') === 0) {
            $pdo->exec('ALTER TABLE participants MODIFY access_code VARCHAR(16) NOT NULL');
        }
    }, 'access_code');

    $step(fn() => $create("CREATE TABLE IF NOT EXISTS race_scores (
        id             INT          NOT NULL AUTO_INCREMENT,
        code           VARCHAR(16)  NOT NULL,
        participant_id INT          NULL,
        name           VARCHAR(100) NOT NULL,
        race_num       VARCHAR(8)   NULL,
        score          INT          NOT NULL,
        created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_rs_code (code),
        KEY idx_rs_created (created_at){FK}
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CONSTRAINT fk_rs_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL'), 'race_scores');

    // Each driver's kiosk journey record (avatar, car, results) so any station can log them in,
    // plus per-station flags so the admin can see where every driver is in the journey
    $step(fn() => $create("CREATE TABLE IF NOT EXISTS kiosk_records (
        code           VARCHAR(16)  NOT NULL,
        participant_id INT          NULL,
        rec            MEDIUMTEXT   NOT NULL,
        rec_ts         BIGINT       NOT NULL DEFAULT 0,
        driver_done    TINYINT(1)   NOT NULL DEFAULT 0,
        car_done       TINYINT(1)   NOT NULL DEFAULT 0,
        race_done      TINYINT(1)   NOT NULL DEFAULT 0,
        pit_done       TINYINT(1)   NOT NULL DEFAULT 0,
        tag            VARCHAR(40)  NULL,
        race_score     INT          NULL,
        pit_score      INT          NULL,
        updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (code){FK}
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CONSTRAINT fk_kr_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE'), 'kiosk_records');
}

/**
 * Key a kiosk receives with a driver's record, letting it save that record back
 * even though (unlike the registering kiosk) it never saw the driver's mobile.
 */
function kioskSyncKey(string $code): string {
    return substr(hash_hmac('sha256', 'kiosk-sync|' . $code, SESSION_SECRET . '|' . DB_PASS), 0, 32);
}

/** Kiosk stations after registration, in journey order: key => label. */
const JOURNEY_STATIONS = [
    'driver' => 'Avatar',
    'car'    => 'Car Design',
    'pit'    => 'Pit Stop',
    'race'   => 'Race',
];

/** Which stations a kiosk record shows as finished. */
function journeyFlags(array $rec): array {
    return [
        'driver' => !empty($rec['avatar']) || !empty($rec['tag']),
        'car'    => !empty($rec['car']),
        'race'   => isset($rec['race']['score']),
        'pit'    => isset($rec['pit']['score']),
    ];
}

/** Where a driver is: ['label' => ..., 'done' => stations finished, 'total' => 4]. */
function journeyStage(array $flags): array {
    $done = count(array_filter($flags));
    if ($done === count(JOURNEY_STATIONS)) {
        return ['label' => 'Journey complete', 'done' => $done, 'total' => count(JOURNEY_STATIONS)];
    }
    foreach (JOURNEY_STATIONS as $k => $label) {
        if (empty($flags[$k])) {
            return ['label' => 'Next: ' . $label, 'done' => $done, 'total' => count(JOURNEY_STATIONS)];
        }
    }
    return ['label' => 'Registered', 'done' => $done, 'total' => count(JOURNEY_STATIONS)];
}

/**
 * Best race per driver (earliest wins a tie), optionally only races since a unix time.
 * Plain GROUP BY rather than window functions so it runs on any MySQL/MariaDB.
 */
function bestRaceScores(PDO $pdo, int $since = 0, int $limit = 50): array {
    $w  = $since > 0 ? 'WHERE created_at >= FROM_UNIXTIME(?)' : '';
    $wr = $since > 0 ? 'WHERE r.created_at >= FROM_UNIXTIME(?)' : '';
    $st = $pdo->prepare("
        SELECT r.code, MAX(r.name) AS name, MAX(r.race_num) AS race_num, r.score,
               MIN(r.created_at) AS first_at, UNIX_TIMESTAMP(MIN(r.created_at)) AS ts
        FROM race_scores r
        JOIN (SELECT code, MAX(score) AS best FROM race_scores $w GROUP BY code) b
          ON b.code = r.code AND b.best = r.score
        $wr
        GROUP BY r.code, r.score
        ORDER BY r.score DESC, first_at ASC
        LIMIT " . max(1, min($limit, 500)));
    $st->execute($since > 0 ? [$since, $since] : []);
    return $st->fetchAll();
}

/**
 * Participants joined with their journey progress. Columns added per row:
 * driver_done, car_done, race_done, pit_done, tag, race_score, pit_score,
 * last_seen, races, best (NULL where the driver has no kiosk record / races yet).
 */
const PARTICIPANT_JOURNEY_SQL = "
    SELECT p.id, p.name, p.email, p.phone, p.city, p.access_code, p.registered_at,
           k.driver_done, k.car_done, k.race_done, k.pit_done, k.tag, k.race_score, k.pit_score,
           k.updated_at AS last_seen, s.races, s.best, s.last_race
    FROM participants p
    LEFT JOIN kiosk_records k ON k.code = p.access_code
    LEFT JOIN (SELECT code, COUNT(*) AS races, MAX(score) AS best, MAX(created_at) AS last_race
               FROM race_scores GROUP BY code) s ON s.code = p.access_code";

/** Station flags for a PARTICIPANT_JOURNEY_SQL row (a logged race counts even without a kiosk record). */
function participantFlags(array $row): array {
    return [
        'driver' => !empty($row['driver_done']),
        'car'    => !empty($row['car_done']),
        'race'   => !empty($row['race_done']) || !empty($row['races']),
        'pit'    => !empty($row['pit_done']),
    ];
}

/** Drivers who have finished each station: ['registered' => n, 'driver' => n, ..., 'complete' => n]. */
function journeyCounts(PDO $pdo): array {
    $r = $pdo->query("
        SELECT COUNT(*) AS registered,
               COALESCE(SUM(k.driver_done), 0) AS driver,
               COALESCE(SUM(k.car_done), 0) AS car,
               COALESCE(SUM(CASE WHEN k.race_done = 1 OR s.code IS NOT NULL THEN 1 ELSE 0 END), 0) AS race,
               COALESCE(SUM(k.pit_done), 0) AS pit,
               COALESCE(SUM(CASE WHEN k.driver_done = 1 AND k.car_done = 1 AND k.pit_done = 1
                                  AND (k.race_done = 1 OR s.code IS NOT NULL) THEN 1 ELSE 0 END), 0) AS complete
        FROM participants p
        LEFT JOIN kiosk_records k ON k.code = p.access_code
        LEFT JOIN (SELECT DISTINCT code FROM race_scores) s ON s.code = p.access_code
    ")->fetch();
    return array_map('intval', $r);
}

/** Small coloured badge for a driver's journey stage. */
function stageBadge(array $flags): string {
    $st  = journeyStage($flags);
    $clr = $st['done'] === $st['total'] ? '#1E8E3E' : ($st['done'] === 0 ? '#5B6B7F' : '#0096D6');
    $pct = (int)round($st['done'] / $st['total'] * 100);
    return '<div style="min-width:140px"><span style="font-size:.8rem;font-weight:600;color:' . $clr . '">'
         . htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') . '</span>'
         . '<div style="height:5px;background:rgba(13,27,62,.1);border-radius:3px;margin-top:4px">'
         . '<div style="height:5px;width:' . $pct . '%;background:' . $clr . ';border-radius:3px"></div></div>'
         . '<small style="color:var(--text-muted)">' . $st['done'] . ' / ' . $st['total'] . ' stations</small></div>';
}

/** Plain-language cause for database set-up errors (connection, credentials, missing tables), else null. */
function dbErrorHint(PDOException $e): ?string {
    $msg = $e->getMessage();
    $driver = preg_match('/\[(\d{4})\]/', $msg, $m) ? (int)$m[1] : 0;
    return match (true) {
        $driver === 1045 => 'The database rejected the username or password. Check DB_USER and DB_PASS in the server\'s .htaccess (SetEnv lines).',
        in_array($driver, [1044, 1049], true) => 'The database named in DB_NAME was not found or this user cannot open it. Check DB_NAME in the server\'s .htaccess.',
        in_array($driver, [2002, 2003, 2005, 2006], true) => 'Could not reach the database server. Check DB_HOST in the server\'s .htaccess.',
        str_contains($msg, '42S02') => 'A database table is missing, so the database has not been set up yet. Import sql/schema.sql in phpMyAdmin.',
        default => null,
    };
}
