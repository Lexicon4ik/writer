-- Migration 009: Add 'image' step to pipeline
-- Extends ENUM values to include image processing step

-- Add 'image' step between 'process' and 'publish'
-- Current ENUM (001 + 004): 'fetch','scrape','process','publish','web_publish','cleanup'
ALTER TABLE pipeline_runs
    MODIFY COLUMN step ENUM('fetch','scrape','process','image','publish','web_publish','cleanup') NOT NULL;

-- Add image operations to AI usage log
-- Current ENUM (001 + 006): 'process','process_chunk','validate','deduplicate'
ALTER TABLE ai_usage_log
    MODIFY COLUMN operation ENUM('process','process_chunk','validate','deduplicate','image_generate','image_tag') NOT NULL;
