<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils/gameAuth.php';
require_once __DIR__ . '/api/helpers.php';

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

/** DB 연결 필수. 실패 시 503 응답 후 종료 */
function ensure_db(): void {
    get_db();
    $err = get_db_error();
    if ($err !== null) {
        $msg = $err === 'not_set' ? 'DATABASE_URL not set (check .env)' : ($err === 'parse_error' ? 'DATABASE_URL format invalid' : 'DB connection failed (check host/password)');
        if (getenv('DEBUG_DB') && function_exists('get_db_last_error')) {
            $detail = get_db_last_error();
            if ($detail !== null) {
                $msg .= '. ' . $detail;
            }
        }
        json_err(503, $msg);
        exit;
    }
}

/**
 * 게임 토큰 검증 필수. 없거나 만료 시 400/401 응답 후 종료.
 * @return object payload (guild_id, user_id, username 등)
 */
function ensure_game_token(?string $token): object {
    if ($token === null || $token === '') {
        json_err(400, 'token required');
        exit;
    }
    $payload = verify_game_token($token);
    if ($payload === null) {
        json_err(401, 'invalid or expired token');
        exit;
    }
    return $payload;
}
