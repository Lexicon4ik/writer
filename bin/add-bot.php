#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Add a Telegram bot
 *
 * Usage: php bin/add-bot.php "BotName" "123456:ABC-DEF" [publishing|service]
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Database, Crypto};

$name = $argv[1] ?? null;
$token = $argv[2] ?? null;
$type = $argv[3] ?? 'publishing';

if (!$name || !$token) {
    echo "Usage: php bin/add-bot.php <name> <token> [type]\n";
    echo "\nArguments:\n";
    echo "  name   — Bot name (e.g., 'MyNewsBot')\n";
    echo "  token  — Telegram bot token from @BotFather\n";
    echo "  type   — 'publishing' (default) or 'service' (for alerts)\n";
    exit(1);
}

if (!in_array($type, ['publishing', 'service'])) {
    echo "Error: type must be 'publishing' or 'service'\n";
    exit(1);
}

try {
    $encryptedToken = Crypto::encrypt($token);
    $maskedToken = substr($token, 0, 10) . '***';

    $id = Database::insert('bots', [
        'name' => $name,
        'token' => $maskedToken,
        'encrypted_token' => $encryptedToken,
        'type' => $type,
        'status' => 'active',
    ]);

    echo "Bot #{$id} '{$name}' added successfully (token encrypted).\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
