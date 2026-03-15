<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function round2(float $x): float {
    return round($x * 100) / 100;
}

function json_err(int $code, string $message): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['error' => $message]);
}

function json_ok($data): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode($data);
}

function get_wallet_balance(string $guildId, string $userId): float {
    $row = fetch_one('SELECT balance FROM stock_wallet WHERE guild_id = :g AND user_id = :u', ['g' => $guildId, 'u' => $userId]);
    return round2(isset($row['balance']) ? (float) $row['balance'] : 0.0);
}
