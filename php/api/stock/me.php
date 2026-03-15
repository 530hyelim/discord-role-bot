<?php

require_once __DIR__ . '/../../common.php';

$token = $_GET['token'] ?? null;
ensure_db();
$payload = ensure_game_token($token);

$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$username = $payload->username ?? '';

$sql = "SELECT username, total_score FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$user = fetch_one($sql);

$sql = "SELECT balance FROM stock_wallet WHERE guild_id = '$guildId' AND user_id = '$userId'";
$wallet = fetch_one($sql);

$sql = "SELECT guild_name FROM guilds WHERE guild_id = '$guildId'";
$guildRow = fetch_one($sql);

$totalScore = isset($user['total_score']) ? (int) $user['total_score'] : 0;
$walletBalance = round2(isset($wallet['balance']) ? (float) $wallet['balance'] : 0.0);

json_ok([
    'username' => $user['username'] ?? $username,
    'guild_name' => $guildRow['guild_name'] ?? null,
    'total_score' => $totalScore,
    'wallet_balance' => $walletBalance,
]);
