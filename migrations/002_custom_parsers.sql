-- Migration 002: Custom Parsers
-- Добавляет поддержку парсинга источников без RSS

-- Таблица конфигурации парсеров
CREATE TABLE IF NOT EXISTS source_parsers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    source_id           INT NOT NULL UNIQUE,
    
    -- URL и основные селекторы
    list_url            VARCHAR(500) NOT NULL COMMENT 'URL страницы со списком новостей',
    article_selector    VARCHAR(200) NOT NULL COMMENT 'CSS/XPath селектор контейнера одной статьи в списке',
    link_selector       VARCHAR(200) NOT NULL COMMENT 'CSS/XPath селектор ссылки на статью (относительно article_selector)',
    title_selector      VARCHAR(200) NULL COMMENT 'Селектор заголовка (опционально, для предпросмотра)',
    date_selector       VARCHAR(200) NULL COMMENT 'Селектор даты публикации',
    image_selector      VARCHAR(200) NULL COMMENT 'Селектор изображения-превью',
    description_selector VARCHAR(200) NULL COMMENT 'Селектор краткого описания (для rss_description)',
    
    -- Пагинация
    pagination_type     ENUM('none','page_param','next_link','offset') DEFAULT 'none',
    pagination_param    VARCHAR(50) NULL COMMENT 'Имя параметра: page, p, offset и т.д.',
    pagination_selector VARCHAR(200) NULL COMMENT 'Селектор ссылки "следующая страница" (для next_link)',
    pagination_start    INT DEFAULT 1 COMMENT 'Начальное значение параметра пагинации',
    max_pages           TINYINT DEFAULT 3 COMMENT 'Макс. страниц для парсинга за один запуск',
    
    -- Rate limiting
    request_delay_ms    INT DEFAULT 2000 COMMENT 'Минимальная задержка между запросами (мс)',
    offset_increment    INT DEFAULT 20 COMMENT 'Размер инкремента для offset-пагинации',
    
    -- Форматы данных
    date_format         VARCHAR(100) NULL COMMENT 'PHP date format или regex для извлечения даты',
    link_base_url       VARCHAR(255) NULL COMMENT 'Базовый URL для относительных ссылок (если отличается от list_url)',
    
    -- Фильтрация
    exclude_patterns    JSON NULL COMMENT 'JSON-массив regex для исключения URL (реклама, категории)',
    min_title_length    INT DEFAULT 10 COMMENT 'Минимальная длина заголовка',
    
    -- Статус и мониторинг
    is_active           TINYINT(1) DEFAULT 1,
    last_parsed_at      DATETIME NULL,
    last_articles_count INT DEFAULT 0 COMMENT 'Кол-во статей при последнем парсинге',
    consecutive_errors  INT DEFAULT 0,
    consecutive_zero_articles INT DEFAULT 0 COMMENT 'Кол-во запусков подряд с 0 статьями',
    min_articles_threshold INT DEFAULT 5 COMMENT 'Мин. ожидаемых статей (0 = отключено)',
    max_errors          INT DEFAULT 5 COMMENT 'После N ошибок — автоотключение',
    max_zero_runs       INT DEFAULT 3 COMMENT 'После N запусков с 0 статьями — warning',
    last_error          TEXT NULL,
    
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_active (is_active, last_parsed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Логирование парсинга для отладки
CREATE TABLE IF NOT EXISTS parser_runs (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_parser_id INT NOT NULL,
    started_at      DATETIME NOT NULL,
    finished_at     DATETIME NULL,
    duration_ms     INT NULL,
    pages_parsed    INT DEFAULT 0,
    articles_found  INT DEFAULT 0,
    articles_new    INT DEFAULT 0,
    articles_skipped INT DEFAULT 0,
    error_message   TEXT NULL,
    FOREIGN KEY (source_parser_id) REFERENCES source_parsers(id) ON DELETE CASCADE,
    INDEX idx_parser_date (source_parser_id, started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
