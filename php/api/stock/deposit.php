<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
$amount = isset($body['amount']) ? (int) floor((float) $body['amount']) : 0;
if (!$token || $amount <= 0) {
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
$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$username = $payload->username ?? '';

$user = fetch_one('SELECT total_score, username FROM users WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$points = isset($user['total_score']) ? (int) $user['total_score'] : 0;
if ($points < $amount) {
    json_err(400, '포인트가 부족해요.');
    return;
}
$walletBalance = get_wallet_balance($guildId, $userId);
$newPoints = $points - $amount;
$newWallet = round2($walletBalance + $amount);
$now = date('c');

execute_sql(
    'INSERT INTO users (guild_id, user_id, username, total_score) VALUES (:g, :u, :name, :score)
     ON CONFLICT (guild_id, user_id) DO UPDATE SET username = EXCLUDED.username, total_score = EXCLUDED.total_score',
    ['g' => $guildId, 'u' => $userId, 'name' => $username, 'score' => $newPoints]
);
execute_sql(
    'INSERT INTO stock_wallet (guild_id, user_id, balance, updated_at) VALUES (:g, :u, :bal, :upd)
     ON CONFLICT (guild_id, user_id) DO UPDATE SET balance = EXCLUDED.balance, updated_at = EXCLUDED.updated_at',
    ['g' => $guildId, 'u' => $userId, 'bal' => $newWallet, 'upd' => $now]
);
json_ok(['total_score' => $newPoints, 'wallet_balance' => $newWallet]);
