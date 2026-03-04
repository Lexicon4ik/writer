-- Migration 004: Website REST Publishing
-- Добавляет таблицы для публикации статей на сайты через REST API

-- Таблица конфигурации сайтов-получателей
CREATE TABLE IF NOT EXISTS website_endpoints (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL                   COMMENT 'Название сайта для отображения',
    site_url            VARCHAR(255) NOT NULL                   COMMENT 'Отображаемый URL (например https://example.com)',
    api_url             VARCHAR(500) NOT NULL                   COMMENT 'URL REST-эндпоинта для публикации',

    -- Источник контента: чьи article_versions использовать для публикации
    source_channel_id   INT NOT NULL                           COMMENT 'FK → channels: берём AI-обработку из article_versions этого канала',

    -- Аутентификация
    auth_type           ENUM('none','bearer','api_key','basic','custom_header') DEFAULT 'bearer',
    auth_credential     TEXT NULL                              COMMENT 'Зашифровано: токен, "user:pass" и т.д.',
    auth_header_name    VARCHAR(100) NULL                      COMMENT 'Имя заголовка для типов api_key и custom_header',

    -- Формат запроса
    http_method         ENUM('POST','PUT','PATCH') DEFAULT 'POST',
    content_type        ENUM('application/json','application/x-www-form-urlencoded') DEFAULT 'application/json',

    -- Маппинг полей статьи → полей API (JSON).
    -- Простой формат: {"title": "post_title", "body": "content"}
    -- С трансформацией: {"body": {"to": "content", "transform": "strip_html"}, "hashtags": {"to": "tags", "transform": "array"}}
    -- Доступные внутренние поля: title, short_title, description, body, body_plain,
    --   hashtags, url, image_url, date, date_iso, source_name, importance_score
    -- Доступные трансформации: strip_html, array, csv, iso8601, plain_date
    field_mapping       JSON NOT NULL                          COMMENT 'Маппинг внутренних полей → полей API сайта',

    -- Статические поля, добавляемые в каждый запрос (не перезаписывают маппинг)
    -- Например: {"status": "publish", "author": 1, "categories": [5]}
    payload_extras      JSON NULL                              COMMENT 'Статические поля payload (не из статьи)',

    -- Разбор ответа
    success_http_codes  VARCHAR(50)  DEFAULT '200,201'         COMMENT 'HTTP-коды, считающиеся успехом (через запятую)',
    external_id_path    VARCHAR(200) NULL                      COMMENT 'JSON-путь к ID статьи в ответе: "id" или "data.post_id"',
    external_url_path   VARCHAR(200) NULL                      COMMENT 'JSON-путь к URL статьи в ответе',

    -- Обработка ошибок
    retry_http_codes    VARCHAR(100) DEFAULT '429,500,502,503,504' COMMENT 'HTTP-коды для повторной попытки',
    max_retries         TINYINT  DEFAULT 3                     COMMENT 'Максимальное число повторных попыток',
    retry_delay_sec     SMALLINT DEFAULT 300                   COMMENT 'Задержка между повторными попытками (секунды)',

    -- Расписание публикации (аналогично channels)
    publish_interval_min INT  DEFAULT 30                       COMMENT 'Минимальный интервал между публикациями (минуты)',
    active_hours_start   TIME DEFAULT '08:00:00',
    active_hours_end     TIME DEFAULT '22:00:00',
    max_per_run          INT  DEFAULT 5                        COMMENT 'Максимум публикаций за один запуск cron',
    max_per_day          INT  DEFAULT 50                       COMMENT 'Максимум публикаций в день',

    status              ENUM('active','paused') DEFAULT 'active',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (source_channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица трекинга публикаций статей на сайты
CREATE TABLE IF NOT EXISTS website_article_versions (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    article_id      BIGINT NOT NULL,
    endpoint_id     INT    NOT NULL,

    -- Статус публикации
    status          ENUM('pending','publishing','published','failed','cancelled','skipped')
                    DEFAULT 'pending',

    -- Результат публикации
    external_id     VARCHAR(200) NULL   COMMENT 'ID статьи, присвоенный сайтом',
    external_url    VARCHAR(500) NULL   COMMENT 'URL опубликованной статьи на сайте',

    -- Информация об ошибках
    last_error      TEXT     NULL,
    last_http_code  SMALLINT NULL,
    retry_count     TINYINT  DEFAULT 0,

    published_at    DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (article_id)  REFERENCES articles(id)           ON DELETE CASCADE,
    FOREIGN KEY (endpoint_id) REFERENCES website_endpoints(id)  ON DELETE CASCADE,
    UNIQUE INDEX idx_article_endpoint (article_id, endpoint_id),
    INDEX idx_endpoint_status (endpoint_id, status, published_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавить web_publish в ENUM pipeline_runs.step
ALTER TABLE pipeline_runs
    MODIFY COLUMN step ENUM('fetch','scrape','process','publish','web_publish','cleanup') NOT NULL;
