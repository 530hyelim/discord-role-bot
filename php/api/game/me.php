<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    json_err(400, 'token required');
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
$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$username = $payload->username ?? '';

$userRow = fetch_one('SELECT username, total_score FROM users WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$guildRow = fetch_one('SELECT guild_name FROM guilds WHERE guild_id = :g', ['g' => $guildId]);
$totalScore = isset($userRow['total_score']) ? (int) $userRow['total_score'] : 0;
json_ok([
    'username' => $userRow['username'] ?? $username,
    'guild_name' => $guildRow['guild_name'] ?? null,
    'total_score' => $totalScore,
]);
