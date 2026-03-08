-- Migration 006: Add Gemini AI Provider
-- Adds support for Google's Gemini models natively

-- Update AI usage log enum to include gemini
ALTER TABLE ai_usage_log MODIFY COLUMN provider ENUM('anthropic', 'openrouter', 'gemini') DEFAULT 'openrouter' COMMENT 'AI provider used';

-- Update AI errors enum to include gemini
ALTER TABLE ai_errors MODIFY COLUMN provider ENUM('openrouter', 'anthropic', 'gemini') NOT NULL;

-- Initialize Circuit Breaker state for gemini
-- INSERT IGNORE is used so we don't error out if it somehow exists
INSERT IGNORE INTO circuit_breaker_state (service, state, failure_count) VALUES ('gemini', 'closed', 0);

-- Insert default setting for the gemini API key (empty by default)
INSERT IGNORE INTO settings (`key`, `value`, description) VALUES
('gemini_api_key', '', 'Google Gemini API key'),
('ai_provider', 'gemini', 'AI provider: openrouter, anthropic, or gemini'); -- optionally make gemini default, but let's just add the value options in PHP logic
