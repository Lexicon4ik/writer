-- Migration 010: Image Library Mode
-- Adds 'library' and 'ai_only' modes to channels.image_mode ENUM
-- Adds 'library' to article_version_images.selection_method ENUM
-- Images are now stored in category-based folders: storage/images/{category}/

-- Expand image_mode ENUM on channels
ALTER TABLE channels
    MODIFY COLUMN image_mode
    ENUM('source','enhanced','generated','ai_only','library','disabled')
    DEFAULT 'source';

-- Expand selection_method ENUM on article_version_images
ALTER TABLE article_version_images
    MODIFY COLUMN selection_method
    ENUM('local_match','pexels','ai_generated','scraped','manual','library') NOT NULL;

-- Add category index to support library mode lookups (already exists in 008, no-op if present)
-- ALTER TABLE images ADD INDEX IF NOT EXISTS idx_category (category);
