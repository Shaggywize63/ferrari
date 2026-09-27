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
