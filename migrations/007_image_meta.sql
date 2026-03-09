-- Migration 007: Image Metadata for Article Versions
-- Adds image_meta JSON column to article_versions for AI-extracted image search data

ALTER TABLE article_versions
    ADD COLUMN image_meta JSON NULL COMMENT 'AI-extracted image metadata: event_title, entity_type, category, image_queries, etc.'
    AFTER filter_tags;
