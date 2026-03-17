<?php

require_once __DIR__ . '/../../common.php';

$token = $_GET['token'] ?? null;
ensure_db();
$payload = ensure_game_token($token);

$guildId = (string) ($payload->guild_id ?? '');
$userId = (string) ($payload->user_id ?? '');
$username = $payload->username ?? '';

$sql = "SELECT username, total_score FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$userRow = fetch_one($sql);

$sql = "SELECT guild_name FROM guilds WHERE guild_id = '$guildId'";
$guildRow = fetch_one($sql);

$totalScore = isset($userRow['total_score']) ? (int) $userRow['total_score'] : 0;

json_ok([
    'username' => $userRow['username'] ?? $username,
    'guild_name' => $guildRow['guild_name'] ?? null,
    'total_score' => $totalScore,
]);
