<?php

require_once __DIR__ . '/../../common.php';

$token = $_GET['token'] ?? null;
ensure_db();
$payload = ensure_game_token($token);

// JWT에서 숫자로 넘어올 수 있으므로 문자열로 통일 (DB 컬럼과 매칭)
$guildId = (string) ($payload->guild_id ?? '');
$userId = (string) ($payload->user_id ?? '');
$username = $payload->username ?? '';

$sql = "SELECT username, total_score FROM users WHERE guild_id = '$guildId' AND user_id = '$userId'";
$user = fetch_one($sql);

$sql = "SELECT balance FROM stock_wallet WHERE guild_id = '$guildId' AND user_id = '$userId'";
$wallet = fetch_one($sql);

$sql = "SELECT guild_name FROM guilds WHERE guild_id = '$guildId'";
$guildRow = fetch_one($sql);

$totalScore = isset($user['total_score']) ? (int) $user['total_score'] : 0;
$walletBalance = round2(isset($wallet['balance']) ? (float) $wallet['balance'] : 0.0);

$out = [
    'username' => $user['username'] ?? $username,
    'guild_name' => $guildRow['guild_name'] ?? null,
    'total_score' => $totalScore,
    'wallet_balance' => $walletBalance,
];
// 배포 환경 디버깅: ?debug=1 이면 조회에 사용한 id와 row 존재 여부 포함
if (!empty($_GET['debug'])) {
    $out['_debug'] = [
        'guild_id' => $guildId,
        'user_id' => $userId,
        'user_row_found' => $user !== null,
        'wallet_row_found' => $wallet !== null,
    ];
}
json_ok($out);
