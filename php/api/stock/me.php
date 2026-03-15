<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    json_err(400, 'token required');
    return;
}
$dbErr = get_db_error();
if ($dbErr !== null) {
    $msg = $dbErr === 'not_set' ? 'DATABASE_URL not set (check .env)' : ($dbErr === 'parse_error' ? 'DATABASE_URL format invalid' : 'DB connection failed (check host/password)');
    if (getenv('DEBUG_DB') && function_exists('get_db_last_error')) {
        $detail = get_db_last_error();
        if ($detail !== null) {
            $msg .= '. ' . $detail;
        }
    }
    json_err(503, $msg);
    return;
}
$db = get_db();
$payload = verify_game_token($token);
if (!$payload) {
    json_err(401, 'invalid or expired token');
    return;
}
$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$username = $payload->username ?? '';

$user = fetch_one('SELECT username, total_score FROM users WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$wallet = fetch_one('SELECT balance FROM stock_wallet WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$guildRow = fetch_one('SELECT guild_name FROM guilds WHERE guild_id = :g', ['g' => $guildId]);
$totalScore = isset($user['total_score']) ? (int) $user['total_score'] : 0;
$walletBalance = round2(isset($wallet['balance']) ? (float) $wallet['balance'] : 0.0);
json_ok([
    'username' => $user['username'] ?? $username,
    'guild_name' => $guildRow['guild_name'] ?? null,
    'total_score' => $totalScore,
    'wallet_balance' => $walletBalance,
]);
