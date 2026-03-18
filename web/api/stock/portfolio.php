<?php

require_once __DIR__ . '/../../common.php';

$token = $_GET['token'] ?? null;
ensure_db();
$payload = ensure_game_token($token);

$guildId = (string) ($payload->guild_id ?? '');
$userId = (string) ($payload->user_id ?? '');

$sql = "SELECT symbol, shares, avg_cost FROM stock_portfolio WHERE guild_id = '$guildId' AND user_id = '$userId'";
$rows = fetch_all($sql);

$list = [];
foreach ($rows as $row) {
    if ((int) ($row['shares'] ?? 0) > 0) {
        $list[] = ['symbol' => $row['symbol'], 'shares' => (int) $row['shares'], 'avg_cost' => (float) ($row['avg_cost'] ?? 0)];
    }
}
json_ok(['portfolio' => $list]);
