-- Migration 003: Security and Reliability Improvements
-- Добавляет поля для retry-логики, AI-ошибок и Circuit Breaker

-- 1. Retry-логика: поле retry_count уже создано в 001_initial.sql
-- Индекс для retry-логики
ALTER TABLE article_versions 
    ADD INDEX idx_failed_retry (status, updated_at, retry_count);

-- 2. Таблица AI-ошибок для мониторинга
CREATE TABLE IF NOT EXISTS ai_errors (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    article_id      BIGINT NULL,
    channel_id      INT NULL,
    operation       ENUM('process','process_chunk','validate','deduplicate') NOT NULL,
    provider        ENUM('openrouter','anthropic') NOT NULL,
    model           VARCHAR(100) NOT NULL,
    error_type      ENUM('rate_limit','auth','bad_request','timeout','server_error','parse_error') NOT NULL,
    http_code       INT NULL,
    error_message   TEXT NOT NULL,
    request_tokens  INT NULL COMMENT 'Сколько токенов было в запросе (если известно)',
    retry_attempt   TINYINT DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (created_at),
    INDEX idx_type (error_type, created_at),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Circuit Breaker: инициализация записей в выделенной таблице circuit_breaker_state
-- Таблица создана в 001_initial.sql. Здесь добавляем начальные записи для всех сервисов.
INSERT IGNORE INTO circuit_breaker_state (service, state, failure_count) VALUES
    ('openrouter', 'closed', 0),
    ('anthropic', 'closed', 0),
    ('telegram', 'closed', 0),
    ('scraper', 'closed', 0);

-- ВАЖНО: Circuit Breaker использует атомарный инкремент через INSERT ... ON DUPLICATE KEY UPDATE
-- Правильная формула для избежания race condition (см. src/Core/CircuitBreaker.php):
-- 
-- INSERT INTO circuit_breaker_state (service, failure_count, last_failure_at, state)
-- VALUES (?, 1, NOW(), 'closed')
-- ON DUPLICATE KEY UPDATE
--     failure_count = failure_count + 1,  -- атомарный инкремент
--     last_failure_at = NOW(),
--     state = IF(failure_count + 1 >= ?, 'open', state)
-- 
-- Используем failure_count + 1 в IF() потому что UPDATE ещё не применён в момент проверки.

-- 4. Поле для шифрования токенов (encrypted_ prefix означает зашифрованные данные)
-- При миграции на шифрование: перенести данные из token в encrypted_token
-- TODO: В будущем удалить legacy поле `token` после полного перехода на encrypted_token
ALTER TABLE bots 
    ADD COLUMN encrypted_token TEXT NULL COMMENT 'Зашифрованный токен бота (AES-256-GCM)' AFTER token;

-- ВАЖНО: После применения этой миграции запустить одноразовый скрипт миграции токенов:
-- php migrations/encrypt_tokens.php
-- Скрипт: для каждого бота с непустым token и пустым encrypted_token:
--   1. Crypto::encrypt(token) → encrypted_token
--   2. Замаскировать token: substr(token, 0, 10) . '***'
-- См. tasks/01-foundation.md для реализации encrypt_tokens.php

-- 5. (idx_failed_retry уже добавлен выше)

-- 6. Добавить APP_KEY в settings как напоминание
INSERT IGNORE INTO settings (`key`, `value`, description) VALUES
    ('app_key_reminder', 'SET_IN_ENV', 'APP_KEY должен быть установлен в .env, не в БД!');
