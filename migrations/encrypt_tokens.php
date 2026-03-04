#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * One-time migration script to encrypt existing plain-text bot tokens.
 *
 * Run AFTER applying migration 003 and setting APP_KEY in .env:
 *   php migrations/encrypt_tokens.php
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Database, Crypto, Logger};

echo "Encrypting bot tokens...\n";

try {
    $bots = Database::fetchAll(
        "SELECT id, token, encrypted_token FROM bots
         WHERE token != '' AND token NOT LIKE '%***%'
           AND (encrypted_token IS NULL OR encrypted_token = '')"
    );

    if (empty($bots)) {
        echo "No plain-text tokens found. All tokens are already encrypted.\n";
        exit(0);
    }

    $count = 0;
    foreach ($bots as $bot) {
        try {
            $encrypted = Crypto::encrypt($bot['token']);
            $masked = substr($bot['token'], 0, 10) . '***';

            Database::update('bots', [
                'encrypted_token' => $encrypted,
                'token' => $masked,
            ], 'id = ?', [$bot['id']]);

            echo "Bot #{$bot['id']}: token encrypted\n";
            $count++;
        } catch (\Exception $e) {
            echo "Bot #{$bot['id']}: ERROR - {$e->getMessage()}\n";
            Logger::error('Failed to encrypt bot token', [
                'bot_id' => $bot['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    echo "\nDone. Encrypted {$count} token(s).\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
