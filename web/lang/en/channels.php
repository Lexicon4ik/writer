<?php
/**
 * Channels page translations.
 */
return [
    'title' => 'Channels',
    'create' => 'Add Channel',
    'edit' => 'Edit Channel',

    // Table columns
    'col_name' => 'Name',
    'col_chat_id' => 'Chat ID',
    'col_bot' => 'Bot',
    'col_sources' => 'Sources',
    'col_status' => 'Status',
    'col_today_limit' => 'Today / Limit',

    // Form fields
    'field_name' => 'Channel Name',
    'field_name_help' => 'Display name for the channel',
    'field_chat_id' => 'Telegram Chat ID',
    'field_chat_id_help' => '@channel_name or -100123456789',
    'field_bot' => 'Telegram Bot',
    'field_bot_help' => 'Bot used to post to this channel',
    'field_language' => 'Language',
    'field_language_help' => 'Content language for AI processing',
    'field_topic' => 'Topic',
    'field_prompt' => 'AI Prompt',
    'field_prompt_help' => 'Instructions for AI to process articles.',
    'field_validation_prompt' => 'Validation Prompt',
    'field_status' => 'Status',
    'field_post_format' => 'Post Format',
    'field_post_interval' => 'Post Interval (min)',

    // Publishing settings
    'field_max_per_run' => 'Max per run',
    'field_max_per_day' => 'Max per day',
    'field_min_importance' => 'Min Importance',
    'field_active_hours_start' => 'Active hours: start',
    'field_active_hours_end' => 'Active hours: end',
    'field_use_images' => 'Use images',

    // Validation settings
    'validation' => 'Validation',
    'field_min_score' => 'Min score',
    'field_validation_mode' => 'Mode',
    'validation_never' => 'Never',
    'validation_sample' => 'Sample (%)',
    'validation_importance' => 'By importance',
    'validation_always' => 'Always',
    'field_sample_pct' => 'Sample %',
    'field_importance_min' => 'Min importance',

    // Sidebar
    'info' => 'Info',

    // Reprocess
    'reprocess_days' => ':count day|:count days',
    'day_1' => '1 day',
    'day_3' => '3 days',
    'day_7' => '7 days',
    'day_14' => '14 days',

    // Messages
    'msg_saved' => 'Channel saved successfully.',
    'msg_deleted' => 'Channel deleted.',
    'msg_delete_confirm' => 'Delete this channel?',
    'msg_no_channels' => 'No channels configured.',
    'msg_prompt_changed' => 'Prompt changed. :count unpublished articles queued for reprocessing.',
    'msg_select_bot' => 'Select a bot...',

    // Test
    'test_post' => 'Send Test Message',
    'test_success' => 'Test message sent!',
    'test_failed' => 'Failed to send test message',
];
