-- Migration 005: Per-feed and per-parser fetch intervals
-- Allows setting individual update frequency per RSS feed and custom parser.
-- NULL = fetch on every cron run (default behavior preserved).

ALTER TABLE feeds
    ADD COLUMN fetch_interval_min INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Minutes between fetches. NULL = every cron run.'
    AFTER max_errors;

ALTER TABLE source_parsers
    ADD COLUMN fetch_interval_min INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Minutes between parses. NULL = every cron run.'
    AFTER max_errors;
