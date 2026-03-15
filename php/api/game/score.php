<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
$score = isset($body['score']) ? (int) $body['score'] : -1;
if ($score < 0 || !$token) {
    json_err(400, 'invalid request');
    return;
}
$db = get_db();
if (!$db) {
    json_err(503, 'DATABASE_URL not set or DB connection failed');
    return;
}
$payload = verify_game_token($token);
if (!$payload) {
    json_err(401, 'invalid or expired token');
    return;
}
$pointsToAdd = min((int) floor($score / 10), 100);
if ($pointsToAdd <= 0) {
    json_ok(['added' => 0, 'total_score' => 0]);
    return;
}
$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$username = $payload->username ?? '';

$row = fetch_one('SELECT total_score, username FROM users WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$current = isset($row['total_score']) ? (int) $row['total_score'] : 0;
$newTotal = $current + $pointsToAdd;

execute_sql(
    'INSERT INTO users (guild_id, user_id, username, total_score) VALUES (:g, :u, :name, :score)
     ON CONFLICT (guild_id, user_id) DO UPDATE SET username = EXCLUDED.username, total_score = EXCLUDED.total_score',
    ['g' => $guildId, 'u' => $userId, 'name' => $username, 'score' => $newTotal]
);
json_ok(['added' => $pointsToAdd, 'total_score' => $newTotal]);
