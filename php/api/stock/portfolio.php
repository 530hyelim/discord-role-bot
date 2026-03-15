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

$rows = fetch_all('SELECT symbol, shares, avg_cost FROM stock_portfolio WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$list = [];
foreach ($rows as $row) {
    if ((int) ($row['shares'] ?? 0) > 0) {
        $list[] = ['symbol' => $row['symbol'], 'shares' => (int) $row['shares'], 'avg_cost' => (float) ($row['avg_cost'] ?? 0)];
    }
}
json_ok(['portfolio' => $list]);
