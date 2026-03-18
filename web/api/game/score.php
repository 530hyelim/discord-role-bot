<?php

require_once __DIR__ . '/../../common.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
$score = isset($body['score']) ? (int) $body['score'] : -1;

if ($score < 0 || !$token) {
    json_err(400, 'invalid request');
    return;
}

ensure_db();
$payload = ensure_game_token($token);

$pointsToAdd = min((int) floor($score / 10), 100);
if ($pointsToAdd <= 0) {
    json_ok(['added' => 0, 'total_score' => 0]);
    return;
}

$guildId = (string) ($payload->guild_id ?? '');
$userId = (string) ($payload->user_id ?? '');
$username = $payload->username ?? '';

$sql = "SELECT total_score, username FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$row = fetch_one($sql);

$current = isset($row['total_score']) ? (int) $row['total_score'] : 0;
$newTotal = $current + $pointsToAdd;

$sql = "INSERT INTO users (guild_id, user_id, username, total_score)
        VALUES ('$guildId', '$userId', '$username', '$newTotal')
        ON CONFLICT (guild_id, user_id) DO 
        UPDATE SET username = EXCLUDED.username, total_score = EXCLUDED.total_score";

execute_sql($sql);
json_ok(['added' => $pointsToAdd, 'total_score' => $newTotal]);
