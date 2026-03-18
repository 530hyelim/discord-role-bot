<?php

require_once __DIR__ . '/../../common.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;

ensure_db();
$payload = ensure_game_token($token);

$guildId = (string) ($payload->guild_id ?? '');
$userId = (string) ($payload->user_id ?? '');
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

$sql = "SELECT total_score, username FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$user = fetch_one($sql);

$points = isset($user['total_score']) ? (int) $user['total_score'] : 0;
$newWallet = round2(max(0, $walletBalance - $withdrawAmount));
$newPoints = $points + $withdrawAmount;
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
