#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Migration runner for NewsBot
 * 
 * Applies SQL migrations in order. Each migration file is named NNN_name.sql
 * where NNN is a 3-digit version number.
 * 
 * Usage: php migrate.php
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\Database;

echo "NewsBot Migration Runner\n";
echo "========================\n\n";

/**
 * Разбить SQL-файл на отдельные statements.
 * Корректно обрабатывает:
 * - Точки с запятой внутри строковых литералов ('...' и "...")
 * - Экранированные кавычки (\' и \")
 * - Однострочные комментарии (-- ...)
 * - Многострочные комментарии (/* ... * /)
 * 
 * @return string[] Массив SQL-statements без завершающих ';'
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        // Однострочный комментарий: -- до конца строки
        if ($char === '-' && $next === '-') {
            $end = strpos($sql, "\n", $i);
            if ($end === false) {
                // Комментарий до конца файла — пропустить
                break;
            }
            // Добавить комментарий как есть (некоторые движки их принимают)
            $current .= substr($sql, $i, $end - $i + 1);
            $i = $end + 1;
            continue;
        }

        // Многострочный комментарий: /* ... */
        if ($char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) {
                // Незакрытый комментарий — добавить остаток
                $current .= substr($sql, $i);
                break;
            }
            $current .= substr($sql, $i, $end - $i + 2);
            $i = $end + 2;
            continue;
        }

        // Строковый литерал: '...' или "..."
        if ($char === "'" || $char === '"') {
            $quote = $char;
            $current .= $char;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                $current .= $c;
                if ($c === '\\') {
                    // Экранированный символ — добавить следующий без проверки
                    $i++;
                    if ($i < $len) {
                        $current .= $sql[$i];
                    }
                    $i++;
                    continue;
                }
                if ($c === $quote) {
                    // Двойное экранирование: '' или ""
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $current .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    // Конец строки
                    $i++;
                    break;
                }
                $i++;
            }
            continue;
        }

        // Разделитель statement
        if ($char === ';') {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                // Убрать чисто комментарийные блоки
                $clean = trim(preg_replace('/--[^\n]*/', '', $trimmed));
                $clean = trim(preg_replace('/\/\*.*?\*\//s', '', $clean));
                if ($clean !== '') {
                    $statements[] = $trimmed;
                }
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $char;
        $i++;
    }

    // Последний statement без ';'
    $trimmed = trim($current);
    if ($trimmed !== '') {
        $clean = trim(preg_replace('/--[^\n]*/', '', $trimmed));
        $clean = trim(preg_replace('/\/\*.*?\*\//s', '', $clean));
        if ($clean !== '') {
            $statements[] = $trimmed;
        }
    }

    return $statements;
}

try {
    $pdo = Database::getInstance();
    
    // 1. Create migrations table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            version INT NOT NULL PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 2. Get list of applied migrations
    $applied = [];
    $stmt = $pdo->query("SELECT version FROM migrations ORDER BY version");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $applied[] = (int)$row['version'];
    }
    
    // 3. Scan migration files
    $files = glob(__DIR__ . '/*.sql');
    $migrations = [];
    
    foreach ($files as $file) {
        $basename = basename($file);
        // Match pattern: NNN_name.sql (e.g., 001_initial.sql)
        if (preg_match('/^(\d{3})_(.+)\.sql$/', $basename, $matches)) {
            $version = (int)$matches[1];
            $migrations[$version] = [
                'file' => $file,
                'filename' => $basename,
            ];
        }
    }
    
    // Sort by version
    ksort($migrations);
    
    // 4. Apply pending migrations
    $appliedCount = 0;
    
    foreach ($migrations as $version => $migration) {
        if (in_array($version, $applied)) {
            echo "✓ Migration {$migration['filename']} already applied\n";
            continue;
        }
        
        echo "→ Applying {$migration['filename']}... ";
        
        $sql = file_get_contents($migration['file']);
        if ($sql === false) {
            throw new RuntimeException("Failed to read migration file: {$migration['file']}");
        }
        
        // Robust SQL splitting that handles semicolons inside string literals
        $statements = splitSqlStatements($sql);

        // Note: DDL statements (CREATE TABLE, ALTER TABLE) in MySQL implicitly commit
        // transactions, so we don't use transactions for migrations.
        // Execute each statement separately.
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        // Record migration
        $stmt = $pdo->prepare("INSERT INTO migrations (version, filename, applied_at) VALUES (?, ?, NOW())");
        $stmt->execute([$version, $migration['filename']]);
        
        echo "OK\n";
        $appliedCount++;
    }
    
    echo "\n";
    if ($appliedCount > 0) {
        echo "Applied {$appliedCount} migration(s).\n";
    } else {
        echo "All migrations are up to date.\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR\n";
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "ERROR\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
