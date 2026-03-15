<?php

require_once __DIR__ . '/../../common.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
$amount = isset($body['amount']) ? (int) floor((float) $body['amount']) : 0;

if (!$token || $amount <= 0) {
    json_err(400, 'invalid request');
    return;
}

ensure_db();
$payload = ensure_game_token($token);

$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$username = $payload->username ?? '';

$sql = "SELECT total_score, username FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$user = fetch_one($sql);

$points = isset($user['total_score']) ? (int) $user['total_score'] : 0;
if ($points < $amount) {
    json_err(400, '포인트가 부족해요.');
    return;
}

$walletBalance = get_wallet_balance($guildId, $userId);
$newPoints = $points - $amount;
$newWallet = round2($walletBalance + $amount);
$now = date('c');

$sql = "INSERT INTO users (guild_id, user_id, username, total_score)
        VALUES ('$guildId', '$userId', '$username', '$newPoints')
        ON CONFLICT (guild_id, user_id) DO 
        UPDATE SET username = EXCLUDED.username, total_score = EXCLUDED.total_score";
execute_sql($sql);

$sql = "INSERT INTO stock_wallet (guild_id, user_id, balance, updated_at)
        VALUES ('$guildId', '$userId', '$newWallet', '$now')
        ON CONFLICT (guild_id, user_id) DO 
        UPDATE SET balance = EXCLUDED.balance, updated_at = EXCLUDED.updated_at";
execute_sql($sql);

json_ok(['total_score' => $newPoints, 'wallet_balance' => $newWallet]);
