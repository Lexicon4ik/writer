-- Migration 011: Add pexels_ai image mode
-- Adds 'pexels_ai' mode to channels.image_mode ENUM
-- pexels_ai: Pexels → AI генерация (без library и source)

ALTER TABLE channels
    MODIFY COLUMN image_mode
    ENUM('source','enhanced','generated','ai_only','library','pexels_ai','disabled')
    DEFAULT 'source';
