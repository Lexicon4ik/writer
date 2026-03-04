-- Migration 001: Initial Schema
-- Основная схема базы данных NewsBot

CREATE TABLE IF NOT EXISTS migrations (
    version INT NOT NULL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT,
    description VARCHAR(500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bots (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    token           VARCHAR(100) NOT NULL,
    type            ENUM('publishing', 'service') DEFAULT 'publishing',
    status          ENUM('active', 'paused') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channels (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    bot_id              INT NOT NULL,
    name                VARCHAR(100) NOT NULL,
    chat_id             VARCHAR(50) NOT NULL COMMENT 'Telegram chat/channel ID',
    topic               VARCHAR(200) NULL,
    language            VARCHAR(10) DEFAULT 'ru',
    timezone            VARCHAR(50) DEFAULT 'UTC' COMMENT 'IANA timezone: Asia/Bangkok, Europe/Moscow',
    ai_prompt           TEXT NOT NULL,
    ai_model            VARCHAR(100) NULL COMMENT 'Override model for this channel (e.g. anthropic/claude-sonnet-4-20250514)',
    ai_temperature      DECIMAL(3,2) NULL COMMENT 'Override temperature for AI processing, 0.00-2.00 (NULL = use global setting)',
    validation_prompt   TEXT NULL,
    validation_mode     ENUM('never', 'sample', 'importance_threshold', 'always') DEFAULT 'sample',
    validation_sample_pct INT DEFAULT 20,
    validation_importance_min INT DEFAULT 7,
    min_validation_score INT DEFAULT 5,
    post_template       TEXT NOT NULL,
    publish_interval_min INT DEFAULT 30,
    active_hours_start  TIME DEFAULT '08:00:00',
    active_hours_end    TIME DEFAULT '22:00:00',
    max_per_run         INT DEFAULT 5,
    max_per_day         INT DEFAULT 50,
    use_images          TINYINT(1) DEFAULT 1,
    manual_review_enabled TINYINT(1) DEFAULT 0 COMMENT 'If 1, all articles go to manual_review instead of auto-publishing',
    status              ENUM('active', 'paused') DEFAULT 'active',
    prompt_updated_at   DATETIME NULL COMMENT 'Время последнего изменения ai_prompt',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sources (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,
    site_url            VARCHAR(255) NOT NULL,
    type                ENUM('news', 'blog', 'aggregator', 'government', 'other') DEFAULT 'news',
    scrape_strategy     ENUM('web', 'rss_only', 'api', 'custom_parser') DEFAULT 'web' COMMENT 'web=scrape HTML, rss_only=use RSS content, api=reserved (not implemented), custom_parser=WebParser',
    authority_rank      INT DEFAULT 50 COMMENT '1-100, lower = more authoritative (1=Reuters, 100=blogs). Used in ORDER BY ASC for primary article selection.',
    request_delay_ms    INT DEFAULT 2000,
    proxy_url           VARCHAR(255) NULL,
    status              ENUM('active', 'paused') DEFAULT 'active',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_sources (
    channel_id          INT NOT NULL,
    source_id           INT NOT NULL,
    priority            INT DEFAULT 0 COMMENT 'Higher = process first',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, source_id),
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feeds (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    source_id           INT NOT NULL,
    url                 VARCHAR(500) NOT NULL,
    type                ENUM('rss', 'atom', 'json') DEFAULT 'rss',
    date_filter         ENUM('none', 'today', 'hours') DEFAULT 'none',
    date_filter_hours   INT NULL,
    status              ENUM('active', 'paused', 'auto_disabled') DEFAULT 'active',
    consecutive_errors  INT DEFAULT 0,
    max_errors          INT DEFAULT 5,
    last_fetched_at     DATETIME NULL,
    last_error          TEXT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_status (status, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scrape_rules (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    source_id           INT NULL COMMENT 'NULL = universal rule',
    content_selector    VARCHAR(500) NOT NULL COMMENT 'XPath selector for content',
    remove_selectors    JSON NULL COMMENT 'JSON array of XPath selectors to remove',
    title_selector      VARCHAR(200) NULL,
    image_selector      VARCHAR(200) NULL,
    priority            INT DEFAULT 0 COMMENT 'Higher = try first',
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_source_priority (source_id, priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agents (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_agent          VARCHAR(500) NOT NULL,
    is_active           TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_clusters (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    primary_article_id  BIGINT NULL COMMENT 'Best article in cluster, set after comparison',
    article_count       INT DEFAULT 1,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_primary (primary_article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS articles (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    feed_id             INT NULL COMMENT 'NULL for custom parser sources',
    source_id           INT NOT NULL,
    cluster_id          INT NULL COMMENT 'FK to article_clusters if duplicate',
    url                 VARCHAR(2000) NOT NULL,
    url_hash            CHAR(64) NOT NULL COMMENT 'SHA-256 of canonical URL',
    rss_guid            VARCHAR(500) NULL,
    rss_title           VARCHAR(500) NULL,
    rss_description     TEXT NULL,
    rss_content         MEDIUMTEXT NULL,
    rss_pub_date        DATETIME NULL,
    rss_image_url       VARCHAR(1000) NULL,
    scraped_title       VARCHAR(500) NULL,
    scraped_text        MEDIUMTEXT NULL,
    scraped_image_url   VARCHAR(1000) NULL,
    original_language   VARCHAR(10) NULL COMMENT 'ISO 639-1 detected language',
    dedup_title         VARCHAR(200) NULL COMMENT 'Title for AI deduplication',
    dedup_summary       VARCHAR(500) NULL COMMENT 'Summary for dedup context',
    status              ENUM('fetched','scraping','scraped','scrape_failed','duplicate','processing','processed','skipped','cancelled','manual_review','process_failed','publishing','published','publish_failed','expired') DEFAULT 'fetched' COMMENT 'NB: publishing/published/publish_failed are UNUSED — publication status is managed via article_versions.status only. Kept in ENUM for potential future use.',
    scrape_attempts     TINYINT DEFAULT 0,
    importance_score    TINYINT NULL COMMENT 'Общая оценка важности из AI (0-10), до привязки к каналам',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE SET NULL,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    FOREIGN KEY (cluster_id) REFERENCES article_clusters(id) ON DELETE SET NULL,
    UNIQUE INDEX idx_url_hash (url_hash),
    INDEX idx_status_created (status, created_at),
    INDEX idx_dedup (created_at, status, dedup_title(50)),
    INDEX idx_source (source_id, created_at DESC),
    INDEX idx_cluster (cluster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_versions (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    article_id          BIGINT NOT NULL,
    channel_id          INT NOT NULL,
    title               VARCHAR(200) NOT NULL,
    short_title         VARCHAR(80) NULL,
    description         VARCHAR(300) NULL,
    body                MEDIUMTEXT NOT NULL,
    hashtags            JSON NULL,
    filter_tags         JSON NULL,
    importance_score    TINYINT NULL COMMENT 'Оценка важности для конкретного канала (из AI-обработки для этого канала)',
    validation_score    TINYINT NULL,
    validation_notes    TEXT NULL,
    status              ENUM('pending', 'validated', 'publishing', 'published', 'failed', 'skipped', 'cancelled', 'manual_review', 'edited', 'deleted') DEFAULT 'pending',
    telegram_message_id BIGINT NULL,
    prompt_version      CHAR(32) NULL COMMENT 'MD5 хеш ai_prompt канала на момент обработки (для отслеживания переобработки)',
    retry_count         TINYINT DEFAULT 0 COMMENT 'Количество попыток повторной публикации',
    published_at        DATETIME NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_article_channel (article_id, channel_id),
    INDEX idx_channel_status (channel_id, status, published_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_fingerprints (
    article_id          BIGINT NOT NULL PRIMARY KEY,
    signature           VARBINARY(512) NOT NULL COMMENT 'MinHash signature',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_cluster_members (
    cluster_id          INT NOT NULL,
    article_id          BIGINT NOT NULL,
    similarity          FLOAT NULL COMMENT 'Similarity to primary article',
    added_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cluster_id, article_id),
    FOREIGN KEY (cluster_id) REFERENCES article_clusters(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_status_log (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    article_id          BIGINT NOT NULL,
    old_status          VARCHAR(50) NULL,
    new_status          VARCHAR(50) NOT NULL,
    details             JSON NULL COMMENT 'Additional context: error message, reason, etc.',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_article_time (article_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage_log (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    channel_id          INT NULL,
    article_id          BIGINT NULL,
    operation           ENUM('process', 'process_chunk', 'validate', 'deduplicate') NOT NULL,
    provider            ENUM('anthropic', 'openrouter') DEFAULT 'openrouter' COMMENT 'AI provider used',
    model               VARCHAR(100) NOT NULL,
    input_tokens        INT NOT NULL,
    output_tokens       INT NOT NULL,
    estimated_cost      DECIMAL(10,6) NOT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (created_at),
    INDEX idx_channel_date (channel_id, created_at),
    INDEX idx_provider (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pipeline_runs (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    step            ENUM('fetch','scrape','process','publish','cleanup') NOT NULL,
    channel_ids     VARCHAR(255) NULL COMMENT 'NULL = all channels',
    started_at      DATETIME NOT NULL,
    finished_at     DATETIME NULL,
    duration_ms     INT NULL,
    articles_total  INT DEFAULT 0,
    articles_ok     INT DEFAULT 0,
    articles_failed INT DEFAULT 0,
    error_message   TEXT NULL,
    INDEX idx_step_date (step, started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50) NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    last_login_at       DATETIME NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    ip_address          VARCHAR(45) NOT NULL,
    username            VARCHAR(50) NULL,
    success             TINYINT(1) NOT NULL,
    attempted_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domain rate limits: предотвращает слишком частые запросы к одному домену при скрапинге.
CREATE TABLE IF NOT EXISTS domain_rate_limits (
    domain              VARCHAR(255) NOT NULL PRIMARY KEY,
    last_request_at     DATETIME(3) NOT NULL COMMENT 'Время последнего запроса (с миллисекундами)',
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Circuit Breaker: отдельная таблица для атомарных операций (не settings!)
-- Это защищает от race condition при параллельных cron-процессах.
CREATE TABLE IF NOT EXISTS circuit_breaker_state (
    service         VARCHAR(50) PRIMARY KEY,
    state           ENUM('closed', 'open', 'half_open') DEFAULT 'closed',
    failure_count   INT DEFAULT 0,
    last_failure_at DATETIME NULL,
    last_success_at DATETIME NULL,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- INITIAL DATA
-- =============================================================================

-- Default settings (AI via OpenRouter)
INSERT INTO settings (`key`, `value`, description) VALUES
('ai_provider', 'openrouter', 'AI provider: openrouter or anthropic'),
('openrouter_api_key', '', 'OpenRouter API key (sk-or-...)'),
('anthropic_api_key', '', 'Anthropic API key (for direct access)'),
('model_process', 'anthropic/claude-sonnet-4-20250514', 'Model for article processing'),
('model_process_fallback', 'anthropic/claude-3-5-sonnet-20241022', 'Fallback model for processing'),
('model_validate', 'anthropic/claude-haiku-4-5-20251001', 'Model for validation'),
('model_validate_fallback', 'google/gemini-2.0-flash-001', 'Fallback model for validation'),
('model_deduplicate', 'anthropic/claude-haiku-4-5-20251001', 'Model for deduplication'),
('model_deduplicate_fallback', 'openai/gpt-4o-mini', 'Fallback model for deduplication'),
('model_fallback_enabled', '1', 'Enable automatic fallback to alternative models'),
('ai_daily_budget', '10.00', 'Daily AI spending limit (USD)'),
('max_article_age_hours', '24', 'Max article age before expiration'),
('alert_chat_id', '', 'Telegram chat ID for admin alerts'),
('alert_bot_id', '', 'Bot ID for admin alerts'),
('dedup_max_batches', '3', 'Max AI batches for deduplication (150 articles each)'),
('dedup_max_articles', '450', 'Max existing articles to compare for deduplication (per check, must be <= dedup_max_batches * 150)'),
('temperature_process', '0.4', 'AI temperature for article processing (0.0-1.0, lower = more precise)'),
('temperature_validate', '0.3', 'AI temperature for validation'),
('temperature_deduplicate', '0.1', 'AI temperature for deduplication'),
('dedup_minhash_enabled', '1', 'Enable MinHash pre-filter for deduplication (0=AI-only, 1=MinHash+AI)');

-- Default User-Agents (updated 2025)
INSERT INTO user_agents (user_agent) VALUES
('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'),
('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'),
('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'),
('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'),
('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15');

-- Default admin (password: admin — CHANGE AFTER FIRST LOGIN)
-- Password hash for 'admin': $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO admin_users (username, password_hash, created_at) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW());

-- FK для article_clusters.primary_article_id (добавляется после создания обеих таблиц,
-- т.к. circular reference: articles.cluster_id → article_clusters, article_clusters.primary_article_id → articles)
ALTER TABLE article_clusters
    ADD CONSTRAINT fk_primary_article
    FOREIGN KEY (primary_article_id) REFERENCES articles(id) ON DELETE SET NULL;

-- Universal scrape rules (source_id = NULL)
INSERT INTO scrape_rules (source_id, content_selector, remove_selectors, priority) VALUES
(NULL, '//article | //main | //*[contains(@class,"post-content")] | //*[contains(@class,"article-body")] | //*[contains(@class,"entry-content")]',
 '["//nav","//header","//footer","//aside","//*[contains(@class,\"social\")]","//*[contains(@class,\"share\")]","//*[contains(@class,\"comment\")]","//*[contains(@class,\"related\")]","//*[contains(@class,\"sidebar\")]","//*[contains(@class,\"ad\")]","//*[contains(@class,\"advertisement\")]","//script","//style","//iframe","//form"]',
 0);
