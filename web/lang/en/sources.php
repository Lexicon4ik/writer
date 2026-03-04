<?php
/**
 * Sources page translations.
 */
return [
    'title' => 'Sources',
    'create' => 'Add Source',
    'edit' => 'Edit Source',

    // Table columns
    'col_name' => 'Name',
    'col_type' => 'Type',
    'col_strategy' => 'Strategy',
    'col_channel' => 'Channel',
    'col_channels' => 'Channels',
    'col_feeds' => 'Feeds',
    'col_rank' => 'Rank',
    'col_status' => 'Status',
    'col_last_fetch' => 'Last Fetch',

    // Form fields
    'field_name' => 'Name',
    'field_name_help' => 'Display name for the source',
    'field_site_url' => 'Site URL',
    'field_channel' => 'Channel',
    'field_channel_help' => 'Target channel for articles',
    'field_type' => 'Type',
    'field_type_news' => 'News',
    'field_type_blog' => 'Blog',
    'field_type_aggregator' => 'Aggregator',
    'field_type_official' => 'Official',
    'field_scrape_strategy' => 'Scrape Strategy',
    'field_strategy_web' => 'Web (RSS + scrape)',
    'field_strategy_rss_only' => 'RSS only',
    'field_strategy_custom' => 'Custom Parser',
    'field_authority_rank' => 'Authority Rank',
    'field_authority_help' => '1-30: major media, 31-60: regional, 61-100: blogs',
    'field_request_delay' => 'Request Delay (ms)',
    'field_proxy_url' => 'Proxy URL',
    'field_status' => 'Status',
    'field_priority' => 'Priority',
    'field_priority_help' => 'Higher priority sources processed first',

    // Feeds section
    'feeds' => 'RSS Feeds',
    'feeds_add' => 'Add',
    'feed_url' => 'Feed URL',
    'feed_name' => 'Name (optional)',
    'feed_interval' => 'Interval (min)',
    'feed_interval_placeholder' => 'every run',
    'feed_status' => 'Status',
    'feed_last_fetch' => 'Last Fetch',
    'feed_errors' => 'Errors',
    'feed_error' => 'Error',
    'feed_reactivate' => 'Reactivate',
    'no_feeds' => 'No feeds configured.',

    // Scrape rules section
    'scrape_rules' => 'Scrape Rules',
    'scrape_rules_add' => 'Add',
    'scrape_rules_help' => 'CSS selectors for extracting article content',
    'scrape_content_selector' => 'Content selector',
    'scrape_remove_selectors' => 'Remove selectors',
    'scrape_remove_help' => 'One per line',
    'scrape_priority' => 'Prior',
    'scrape_field' => 'Field',
    'scrape_selector' => 'CSS Selector',
    'scrape_attribute' => 'Attribute',
    'no_scrape_rules' => 'No scrape rules configured.',

    // Sidebar
    'info' => 'Information',
    'disabled_feeds' => 'Disabled Feeds',
    'disabled_feeds_help' => 'Feeds were auto-disabled due to errors',
    'reactivate_all' => 'Reactivate All',
    'custom_parser' => 'Custom Parser',
    'configure_parser' => 'Configure Parser',
    'general_settings' => 'General Settings',

    // Messages
    'msg_saved' => 'Source saved successfully.',
    'msg_deleted' => 'Source deleted.',
    'msg_delete_confirm' => 'Delete source :name? All feeds and rules will be deleted.',
    'msg_no_sources' => 'No sources found.',
    'msg_no_sources_link' => 'Add your first source',
    'msg_feed_reactivated' => 'Feed reactivated.',
    'msg_select_channel' => 'Select a channel...',

    // Actions
    'btn_edit' => 'Edit',
    'btn_parser' => 'Parser',
    'btn_pause' => 'Pause',
    'btn_activate' => 'Activate',
    'btn_delete' => 'Delete',
];
