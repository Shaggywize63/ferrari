<?php
/**
 * Big-screen race leaderboard at /leaderboard/ (for a separate display screen).
 * Serves the same live page as /leaderboard.html, which reads /api/leaderboard.php
 * every few seconds; nothing to configure per screen.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
readfile(__DIR__ . '/../leaderboard.html');
