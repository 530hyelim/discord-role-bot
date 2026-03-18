<?php

function round2(float $x): float {
    return round($x * 100) / 100;
}

function get_wallet_balance(string $guildId, string $userId): float {
    $sql = "SELECT balance FROM stock_wallet WHERE guild_id = '$guildId' AND user_id = '$userId'";
    $row = fetch_one($sql);
    return round2(isset($row['balance']) ? (float) $row['balance'] : 0.0);
}
