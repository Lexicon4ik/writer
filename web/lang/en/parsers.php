<?php
/**
 * Parsers page translations.
 */
return [
    'title' => 'Parsers',
    'create' => 'Add Parser',
    'edit' => 'Edit Parser',

    // Table columns
    'col_source' => 'Source',
    'col_list_url' => 'List URL',
    'col_status' => 'Status',
    'col_last_run' => 'Last Run',
    'col_articles' => 'Articles',
    'col_errors' => 'Errors',
    'col_actions' => 'Actions',
    'col_time' => 'Time',
    'col_found' => 'Found',
    'col_new' => 'New',

    // Index page
    'add_parser' => 'Add Parser',
    'no_parsers' => 'No parsers configured',
    'no_parsers_hint' => 'Add a parser for one of the existing sources.',
    'add_source_first' => 'First add a source, then configure a parser for it.',
    'status_active' => 'Active',
    'status_disabled' => 'Disabled',
    'never' => 'Never',
    'zero_articles' => 'Zero articles runs',

    // Edit page
    'url_selectors' => 'URL and Selectors',
    'field_list_url' => 'List URL',
    'field_list_url_help' => 'URL of the page containing the list of articles',
    'field_article_selector' => 'Article Selector',
    'field_article_selector_help' => 'CSS selector or XPath for article container element',
    'field_link_selector' => 'Link Selector',
    'field_link_selector_help' => 'CSS selector or XPath for the link to article (relative to article container)',
    'field_title_selector' => 'Title Selector',
    'field_title_selector_help' => 'Optional. Falls back to link text if empty.',
    'field_date_selector' => 'Date Selector',
    'field_image_selector' => 'Image Selector',
    'field_image_selector_help' => 'Looks for src or data-src attribute',
    'field_description_selector' => 'Description Selector',

    // Pagination
    'pagination' => 'Pagination',
    'field_pagination_type' => 'Pagination Type',
    'pagination_none' => 'None (single page)',
    'pagination_page' => 'Page parameter (?page=N)',
    'pagination_offset' => 'Offset parameter (?offset=N)',
    'pagination_next' => 'Next link (follow link to next page)',
    'field_max_pages' => 'Max Pages',
    'field_max_pages_help' => 'Maximum pages to fetch per run',
    'field_pagination_param' => 'Parameter Name',
    'field_pagination_start' => 'Start Value',
    'field_offset_increment' => 'Offset Increment',
    'field_next_selector' => 'Next Link Selector',
    'field_next_selector_help' => 'CSS selector for the "Next" link',

    // Filtering
    'filtering' => 'Filtering',
    'field_min_title_length' => 'Min Title Length',
    'field_min_title_help' => 'Articles with shorter titles are skipped',
    'field_date_format' => 'Date Format',
    'field_date_format_help' => 'PHP date format. Auto-detect if empty.',
    'field_exclude_patterns' => 'Exclude URL Patterns',
    'field_exclude_help' => 'One regex pattern per line. URLs matching these patterns will be skipped.',
    'field_link_base_url' => 'Link Base URL',
    'field_link_base_help' => 'Base URL for resolving relative links. Auto-detect from list URL if empty.',

    // Status card
    'status' => 'Status',
    'parser_active' => 'Parser is active',
    'field_request_delay' => 'Request Delay (ms)',
    'field_request_delay_help' => 'Delay between HTTP requests',
    'field_fetch_interval' => 'Fetch Interval (min)',
    'field_fetch_interval_placeholder' => 'every run',
    'field_fetch_interval_help' => 'Minimum minutes between parser runs. Empty = every cron.',
    'error_handling' => 'Error Handling',
    'field_max_errors' => 'Max Errors',
    'field_max_errors_help' => 'Auto-disable after N errors',
    'field_max_zero_runs' => 'Max Zero Runs',
    'field_max_zero_help' => 'Warning after N runs with 0 articles',
    'field_min_articles' => 'Min Articles Threshold',
    'field_min_articles_help' => '0 = disabled. Alert if below threshold.',

    // Statistics
    'statistics' => 'Statistics',
    'last_run' => 'Last Run',
    'last_count' => 'Last Count',
    'articles' => 'articles',
    'errors' => 'Errors',
    'zero_runs' => 'Zero Runs',
    'last_error' => 'Last Error',
    'recent_runs' => 'Recent Runs',

    // Actions
    'btn_save' => 'Save',
    'btn_autofill' => 'Auto-fill',
    'btn_test' => 'Test Parser',
    'btn_delete' => 'Delete Parser',
    'btn_back' => 'Back',

    // Test modal
    'test_results' => 'Parser Test Results',
    'testing' => 'Testing parser...',
    'testing_current' => 'Testing parser with current settings...',
    'found_articles' => 'Found :count articles in :duration ms',
    'col_title' => 'Title',
    'col_date' => 'Date',
    'col_url' => 'URL',
    'col_image' => 'Image',
    'no_title' => '(no title)',
    'has_image_yes' => 'Yes',
    'has_image_no' => 'No',

    // Auto-analyze modal
    'auto_analyze' => 'Auto-analyze Source',
    'analyzing' => 'Analyzing page structure...',
    'found_selectors' => 'Found :count articles. Form fields filled.',
    'detected_selectors' => 'Detected Selectors:',
    'sample_titles' => 'Sample titles found:',
    'analyze_failed' => 'Failed to analyze the page',

    // Messages
    'msg_saved' => 'Parser saved successfully.',
    'msg_deleted' => 'Parser deleted.',
    'msg_delete_confirm' => 'Delete this parser?',
    'msg_no_parsers' => 'No parsers configured.',
    'msg_reactivated' => 'Parser reactivated.',
    'msg_select_source' => 'Select a source...',
    'request_error' => 'Request failed',
];
