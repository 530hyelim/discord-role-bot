<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
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

$walletBalance = get_wallet_balance($guildId, $userId);
$maxWithdrawInt = (int) floor($walletBalance);
$withdrawAmount = isset($body['amount'])
    ? min((int) floor((float) $body['amount']), $maxWithdrawInt)
    : $maxWithdrawInt;
if ($withdrawAmount <= 0) {
    json_err(400, '출금할 잔액이 없어요.');
    return;
}
$user = fetch_one('SELECT total_score, username FROM users WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$points = isset($user['total_score']) ? (int) $user['total_score'] : 0;
$newWallet = round2(max(0, $walletBalance - $withdrawAmount));
$newPoints = $points + $withdrawAmount;
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
