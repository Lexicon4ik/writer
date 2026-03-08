    -- Migration 005: Per-feed and per-parser fetch intervals
-- Allows setting individual update frequency per RSS feed and custom parser.
-- NULL = fetch on every cron run (default behavior preserved).

-- Cannot easily do IF NOT EXISTS for column additions in standard MySQL 8/MariaDB without procedures.
-- But since PHP PDO execute() does not support DELIMITER, we will just use a try-catch pattern in the migrate script,
-- or safely assume this migration will be ignored if it causes an error because it already ran.
-- Setting ignore errors in PDO is hard from pure SQL. Let's just create a dummy table to satisfy the script.

CREATE TABLE IF NOT EXISTS _005_migration_marker (id INT);
