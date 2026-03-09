-- Migration 008: Image Storage Module
-- Adds local image storage tables and image_mode per-channel setting

-- Add image_mode column to channels
ALTER TABLE channels
    ADD COLUMN image_mode ENUM('source','enhanced','generated','disabled') DEFAULT 'source'
    AFTER use_images;

-- Main images table: stores downloaded image files with metadata
CREATE TABLE IF NOT EXISTS images (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    source          ENUM('pexels','ai','scraped','manual') NOT NULL,
    external_id     VARCHAR(100) NULL COMMENT 'ID на внешнем сервисе',

    -- File
    file_path       VARCHAR(500) NOT NULL COMMENT 'Relative path: storage/images/2026/02/abc123.jpg',
    file_hash       CHAR(64) NOT NULL COMMENT 'SHA-256 of file for deduplication',
    width           INT NULL,
    height          INT NULL,
    file_size       INT NULL COMMENT 'Bytes',
    mime_type       VARCHAR(50) NULL,

    -- Metadata
    category        VARCHAR(100) NULL,
    entities        JSON NULL COMMENT '["Bangkok", "protest"]',
    tags            JSON NULL COMMENT '["politics", "outdoor", "crowd"]',
    alt_text        VARCHAR(500) NULL,

    -- License
    license_type    VARCHAR(50) NULL COMMENT 'pexels, cc0, ai-generated',
    license_url     VARCHAR(500) NULL,
    photographer    VARCHAR(200) NULL,
    source_url      VARCHAR(1000) NULL COMMENT 'Link to source page',

    -- Usage
    usage_count     INT DEFAULT 0,
    last_used_at    DATETIME NULL,

    -- Embeddings (for future search)
    embedding       JSON NULL COMMENT 'Vector embedding [float, ...]',

    -- Safety
    has_faces       TINYINT(1) NULL COMMENT 'Contains faces (vision analysis)',
    has_logos       TINYINT(1) NULL COMMENT 'Contains logos',
    safety_score    TINYINT NULL COMMENT '1-10, 10=fully safe',

    downloaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_file_hash (file_hash),
    INDEX idx_source (source),
    INDEX idx_category (category),
    INDEX idx_usage (usage_count, last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link table: article versions <-> images
CREATE TABLE IF NOT EXISTS article_version_images (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    article_version_id BIGINT NOT NULL,
    image_id        BIGINT NOT NULL,
    position        TINYINT DEFAULT 1 COMMENT 'Order: 1=main, 2,3=additional',
    selection_method ENUM('local_match','pexels','ai_generated','scraped','manual') NOT NULL,
    similarity_score FLOAT NULL COMMENT 'For local_match: cosine similarity',

    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (article_version_id) REFERENCES article_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_version_position (article_version_id, position),
    INDEX idx_image (image_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Image search log
CREATE TABLE IF NOT EXISTS image_search_log (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    article_version_id BIGINT NULL,
    query           VARCHAR(500) NOT NULL,
    source          ENUM('local','pexels','ai') NOT NULL,
    results_count   INT DEFAULT 0,
    selected_image_id BIGINT NULL,
    duration_ms     INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_version (article_version_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings for image module
INSERT IGNORE INTO settings (`key`, `value`, description) VALUES
('pexels_api_key',         '',              'Pexels API key for image search (free, 200 req/hr)'),
('image_storage_path',     'storage/images','Relative path for local image storage'),
('image_max_per_article',  '1',             'Max images to attach per article'),
('image_min_width',        '600',           'Minimum acceptable image width in pixels'),
('image_ai_model',         'imagen-3.0-generate-002', 'Google Imagen model for AI image generation'),
('image_vision_enabled',   '0',             'Enable vision analysis for tagging (0=off, 1=on)');
