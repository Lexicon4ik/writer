<?php
/**
 * Settings page translations.
 */
return [
    'title' => 'Settings',

    // Groups
    'group_general' => 'General',
    'group_ai' => 'AI Configuration',
    'group_telegram' => 'Telegram',
    'group_scraping' => 'Scraping',
    'group_publishing' => 'Publishing',

    // General settings
    'site_name' => 'Site Name',
    'timezone' => 'Timezone',
    'language' => 'Admin Language',
    'debug_mode' => 'Debug Mode',

    // AI settings
    'ai_provider' => 'AI Provider',
    'ai_model' => 'AI Model',
    'ai_api_key' => 'API Key',
    'ai_api_key_help' => 'Leave empty to keep existing key.',
    'ai_daily_budget' => 'Daily Budget ($)',
    'ai_max_tokens' => 'Max Tokens',
    'ai_temperature' => 'Temperature',
    'ai_test' => 'Test AI Connection',
    'ai_test_success' => 'AI connection successful!',
    'ai_test_failed' => 'AI connection failed',

    // Telegram settings
    'tg_api_id' => 'API ID',
    'tg_api_hash' => 'API Hash',
    'tg_test' => 'Test Telegram',
    'tg_test_success' => 'Telegram connection successful!',
    'tg_test_failed' => 'Telegram connection failed',

    // Scraping settings
    'scrape_timeout' => 'Scrape Timeout (sec)',
    'scrape_delay' => 'Delay Between Requests (sec)',
    'scrape_user_agent' => 'User Agent',
    'scrape_max_retries' => 'Max Retries',

    // Publishing settings
    'publish_interval' => 'Min Interval Between Posts (min)',
    'publish_max_per_hour' => 'Max Posts Per Hour',
    'publish_quiet_hours' => 'Quiet Hours (no posting)',

    // Settings page UI
    'not_selected' => '-- Not selected --',
    'current_value' => 'Current',
    'enabled' => 'Enabled',
    'btn_save' => 'Save',

    // Sidebar cards
    'ai_usage_today' => 'AI Usage Today',
    'used' => 'Used',
    'calls' => 'Calls',
    'input_tokens' => 'Input tokens',
    'output_tokens' => 'Output tokens',
    'test_ai' => 'Test AI',
    'test_ai_hint' => 'Send a test request to AI API',
    'btn_test_ai' => 'Test AI',
    'test_telegram' => 'Test Telegram',
    'test_telegram_hint' => 'Send a test message to alert chat',
    'btn_test_telegram' => 'Test Telegram',

    // Test results
    'test_success' => 'Success!',
    'test_provider' => 'Provider',
    'test_model' => 'Model',
    'test_response' => 'Response',
    'test_tokens' => 'Tokens',
    'test_duration' => 'Duration',
    'msg_sent' => 'Message sent!',
    'test_bot' => 'Bot',
    'test_chat' => 'Chat',
    'test_message_id' => 'Message ID',

    // Messages
    'msg_saved' => 'Settings saved successfully.',
    'msg_key_hidden' => 'Key is hidden for security',
    'msg_value_encrypted' => 'Value is encrypted',

    // Actions
    'save_settings' => 'Save Settings',
    'reset_defaults' => 'Reset to Defaults',
    'clear_cache' => 'Clear Cache',
    'cache_cleared' => 'Cache cleared successfully.',
];
