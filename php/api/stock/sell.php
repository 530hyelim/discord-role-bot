<?php

require_once __DIR__ . '/../../common.php';

$raw = file_get_contents('php://input');
$body = $raw !== false ? json_decode($raw, true) : [];
$token = $body['token'] ?? null;
$symbol = $body['symbol'] ?? null;
$numShares = isset($body['shares']) ? (int) floor((float) $body['shares']) : 0;

if (!$token || !$symbol || $numShares <= 0) {
    json_err(400, 'invalid request');
    return;
}

ensure_db();
$payload = ensure_game_token($token);

$sym = strtoupper((string) $symbol);
$guildId = $payload->guild_id ?? '';
$userId = $payload->user_id ?? '';

$sql = "SELECT shares, avg_cost FROM stock_portfolio WHERE guild_id = '$guildId' AND user_id = '$userId' AND symbol = '$sym'";
$row = fetch_one($sql);
if (!$row || (int) ($row['shares'] ?? 0) < $numShares) {
    json_err(400, '보유 수량이 부족해요.');
    return;
}

$price = get_price($sym);
if ($price === null) {
    json_err(502, '시세 조회 실패');
    return;
}

$proceeds = round2($price * $numShares);
$newShares = (int) $row['shares'] - $numShares;
$walletBalance = get_wallet_balance($guildId, $userId);
$newBalance = round2($walletBalance + $proceeds);
$now = date('c');

$sql = "INSERT INTO stock_wallet (guild_id, user_id, balance, updated_at)
        VALUES ('$guildId', '$userId', '$newBalance', '$now')
        ON CONFLICT (guild_id, user_id) DO 
        UPDATE SET balance = EXCLUDED.balance, updated_at = EXCLUDED.updated_at";
execute_sql($sql);

$sql = "INSERT INTO stock_portfolio (guild_id, user_id, symbol, shares, avg_cost, updated_at)
        VALUES ('$guildId', '$userId', '$sym', '$newShares', ".(float) $row['avg_cost'].", '$now')
        ON CONFLICT (guild_id, user_id, symbol) DO 
        UPDATE SET shares = EXCLUDED.shares, avg_cost = EXCLUDED.avg_cost, updated_at = EXCLUDED.updated_at";
execute_sql($sql);

$sql = "SELECT total_score FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$userRow = fetch_one($sql);
$totalScore = isset($userRow['total_score']) ? (int) $userRow['total_score'] : 0;

$sql = "SELECT symbol, shares, avg_cost FROM stock_portfolio WHERE guild_id = '$guildId' AND user_id = '$userId'";
$portfolioRows = fetch_all($sql);

$portfolioList = [];
foreach ($portfolioRows as $r) {
    if ((int) ($r['shares'] ?? 0) > 0) {
        $portfolioList[] = ['symbol' => $r['symbol'], 'shares' => (int) $r['shares'], 'avg_cost' => (float) ($r['avg_cost'] ?? 0)];
    }
}
json_ok(['wallet_balance' => $newBalance, 'total_score' => $totalScore, 'portfolio' => $portfolioList, 'sold' => $numShares, 'proceeds' => $proceeds]);
