#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Set or view settings
 *
 * Usage:
 *   php bin/set-setting.php openrouter_api_key sk-or-xxx  — save (encrypts API keys automatically)
 *   php bin/set-setting.php --show openrouter_api_key      — show decrypted value
 *   php bin/set-setting.php --list                          — show all settings
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Database, Crypto, Settings};

// Keys that are automatically encrypted when saving
const ENCRYPTED_KEYS = ['openrouter_api_key', 'anthropic_api_key'];

$action = $argv[1] ?? '--help';

if ($action === '--help' || $action === '-h') {
    echo "Usage:\n";
    echo "  php bin/set-setting.php <key> <value>      — Set a setting\n";
    echo "  php bin/set-setting.php --show <key>       — Show a setting (decrypts if needed)\n";
    echo "  php bin/set-setting.php --list             — List all settings\n";
    exit(0);
}

if ($action === '--list') {
    $settings = Database::fetchAll("SELECT `key`, `value`, description FROM settings ORDER BY `key`");

    if (empty($settings)) {
        echo "No settings found. Run migrations first.\n";
        exit(0);
    }

    echo "Settings:\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($settings as $s) {
        $display = in_array($s['key'], ENCRYPTED_KEYS) && $s['value'] ? '***encrypted***' : $s['value'];
        $display = mb_substr($display ?? '', 0, 40);
        printf("%-30s = %s\n", $s['key'], $display);
    }
    exit(0);
}

if ($action === '--show') {
    $key = $argv[2] ?? null;
    if (!$key) {
        echo "Usage: php bin/set-setting.php --show <key>\n";
        exit(1);
    }

    $value = Settings::get($key);
    if ($value === null) {
        echo "Key '{$key}' not found\n";
        exit(1);
    }

    if (in_array($key, ENCRYPTED_KEYS)) {
        $value = Crypto::decryptSafe($value);
    }

    echo "{$key} = {$value}\n";
    exit(0);
}

// Set: php bin/set-setting.php <key> <value>
$key = $action;
$value = $argv[2] ?? null;

if ($value === null) {
    echo "Usage: php bin/set-setting.php <key> <value>\n";
    exit(1);
}

try {
    if (in_array($key, ENCRYPTED_KEYS)) {
        $value = Crypto::encrypt($value);
        echo "Value encrypted before saving.\n";
    }

    Settings::set($key, $value);
    echo "Setting '{$key}' saved.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
