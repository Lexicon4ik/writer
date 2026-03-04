#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Generate APP_KEY for .env
 *
 * Usage: php bin/generate-key.php
 */

$key = base64_encode(random_bytes(32));

echo "APP_KEY=base64:{$key}\n";
echo "\nCopy the line above into your .env file\n";
