<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_game_jwt_secret(): string {
    $v = env('GAME_JWT_SECRET') ?? env('TOKEN') ?? 'game-secret-change-in-production';
    return (string) $v;
}

function verify_game_token(?string $token): ?object {
    if ($token === null || $token === '') {
        return null;
    }
    try {
        $secret = get_game_jwt_secret();
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return $decoded;
    } catch (Throwable $e) {
        return null;
    }
}
