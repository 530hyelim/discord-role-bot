<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
$symbol = $body['symbol'] ?? null;
$numShares = isset($body['shares']) ? (int) floor((float) $body['shares']) : 0;
if (!$token || !$symbol || $numShares <= 0) {
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
$sym = strtoupper((string) $symbol);
if (!in_array($sym, get_stock_symbols(), true)) {
    json_err(400, '지원하지 않는 종목이에요.');
    return;
}
$price = get_price($sym);
if ($price === null) {
    json_err(502, '시세 조회 실패');
    return;
}
$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';
$cost = round($price * $numShares * 100) / 100;
$walletBalance = get_wallet_balance($guildId, $userId);
if ($walletBalance < $cost) {
    json_err(400, '잔액이 부족해요.');
    return;
}
$row = fetch_one('SELECT shares, avg_cost FROM stock_portfolio WHERE guild_id = :g AND user_id = :u AND symbol = :s', ['g' => $guildId, 'u' => $userId, 's' => $sym]);
$prevShares = isset($row['shares']) ? (int) $row['shares'] : 0;
$prevCost = isset($row['avg_cost']) ? (float) $row['avg_cost'] : 0.0;
$newShares = $prevShares + $numShares;
$newAvgCost = $newShares === 0 ? 0 : ($prevShares * $prevCost + $numShares * $price) / $newShares;
$newBalance = round2($walletBalance - $cost);
$now = date('c');

execute_sql(
    'INSERT INTO stock_wallet (guild_id, user_id, balance, updated_at) VALUES (:g, :u, :bal, :upd)
     ON CONFLICT (guild_id, user_id) DO UPDATE SET balance = EXCLUDED.balance, updated_at = EXCLUDED.updated_at',
    ['g' => $guildId, 'u' => $userId, 'bal' => $newBalance, 'upd' => $now]
);
execute_sql(
    'INSERT INTO stock_portfolio (guild_id, user_id, symbol, shares, avg_cost, updated_at) VALUES (:g, :u, :s, :sh, :ac, :upd)
     ON CONFLICT (guild_id, user_id, symbol) DO UPDATE SET shares = EXCLUDED.shares, avg_cost = EXCLUDED.avg_cost, updated_at = EXCLUDED.updated_at',
    ['g' => $guildId, 'u' => $userId, 's' => $sym, 'sh' => $newShares, 'ac' => $newAvgCost, 'upd' => $now]
);
$userRow = fetch_one('SELECT total_score FROM users WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$totalScore = isset($userRow['total_score']) ? (int) $userRow['total_score'] : 0;
$portfolioRows = fetch_all('SELECT symbol, shares, avg_cost FROM stock_portfolio WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
$portfolioList = [];
foreach ($portfolioRows as $r) {
    if ((int) ($r['shares'] ?? 0) > 0) {
        $portfolioList[] = ['symbol' => $r['symbol'], 'shares' => (int) $r['shares'], 'avg_cost' => (float) ($r['avg_cost'] ?? 0)];
    }
}
json_ok(['wallet_balance' => $newBalance, 'total_score' => $totalScore, 'portfolio' => $portfolioList]);
