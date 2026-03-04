<?php
/**
 * Website endpoints page translations.
 */
return [
    'title'  => 'Sites',
    'create' => 'Add Site',
    'edit'   => 'Edit Site',
    'new'    => 'New Site',

    // Table columns
    'col_site'     => 'Site',
    'col_channel'  => 'Source Channel',
    'col_auth'     => 'Authentication',
    'col_schedule' => 'Schedule',
    'col_published'=> 'Published',
    'col_errors'   => 'Errors',
    'col_status'   => 'Status',

    // Auth types
    'auth_none'          => 'No Auth',
    'auth_bearer'        => 'Bearer Token',
    'auth_api_key'       => 'API Key (custom header)',
    'auth_basic'         => 'HTTP Basic (user:password)',
    'auth_custom_header' => 'Custom Header',

    // Schedule
    'schedule_max_day' => 'max :n/day',

    // Status badges
    'status_active' => 'Active',
    'status_paused' => 'Paused',

    // Form sections
    'section_basic'    => 'Basic',
    'section_auth'     => 'Authentication',
    'section_mapping'  => 'Field Mapping',
    'section_extras'   => 'Static Payload Fields',
    'section_response' => 'Response & Error Handling',
    'section_schedule' => 'Publish Schedule',
    'section_stats'    => 'Statistics',

    // Form fields – basic
    'field_name'              => 'Name',
    'field_name_placeholder'  => 'My News Site',
    'field_site_url'          => 'Site URL',
    'field_site_url_note'     => '(for display)',
    'field_api_url'           => 'API URL',
    'field_api_url_help'      => 'REST endpoint URL where articles will be sent.',
    'field_channel'           => 'Source Content Channel',
    'field_channel_help'      => 'AI-processed content is taken from article_versions of this channel.',
    'field_channel_select'    => '— select channel —',
    'field_http_method'       => 'HTTP Method',
    'field_status'            => 'Status',
    'field_content_type'      => 'Content-Type',
    'content_type_json'       => 'application/json (recommended)',

    // Form fields – auth
    'field_auth_type'             => 'Type',
    'field_auth_header_name'      => 'Header Name',
    'field_auth_header_name_help' => 'E.g.: X-API-Key, X-Auth-Token',
    'field_auth_credential'       => 'Credentials',
    'field_auth_credential_keep'  => '(leave empty to keep unchanged)',
    'field_auth_credential_placeholder' => 'token / user:password',
    'field_auth_credential_help'  => 'For <b>Basic</b>: <code>username:password</code> &nbsp;|&nbsp; For <b>Bearer / API Key / Custom</b>: full token. Stored encrypted.',

    // Form fields – mapping
    'mapping_help_btn'    => 'Help',
    'mapping_help_fields' => '<b>Available internal fields:</b>',
    'mapping_help_syntax' => '<b>Syntax:</b>',
    'mapping_help_direct' => '<code>"field": "target_field"</code> — direct mapping',
    'mapping_help_transform' => '<code>"field": {"to": "target_field", "transform": "..."}}</code> — with transform',
    'mapping_help_transforms_label' => '<b>Transforms:</b>',
    'mapping_help_nesting'  => '<b>Nesting:</b>',
    'field_mapping_json'    => 'Mapping JSON',
    'mapping_example'       => 'WordPress example:',

    // Form fields – extras
    'extras_note' => 'Static values added to every request, not taken from the article. E.g.: <code>{"status": "publish", "author": 1}</code>',

    // Form fields – response/error
    'field_success_codes'  => 'HTTP Success Codes',
    'field_success_codes_help' => 'Comma-separated.',
    'field_external_id_path'   => 'JSON path to article ID',
    'field_external_id_help'   => 'Path in JSON response.',
    'field_external_url_path'  => 'JSON path to article URL',
    'field_retry_codes'    => 'HTTP Retry Codes',
    'field_max_retries'    => 'Max Retries',
    'field_retry_delay'    => 'Retry Delay (sec.)',

    // Form fields – schedule
    'field_interval'      => 'Interval between posts (min.)',
    'field_hours_start'   => 'Start (UTC)',
    'field_hours_end'     => 'End (UTC)',
    'field_max_per_run'   => 'Max per run',
    'field_max_per_day'   => 'Max per day',

    // Stats
    'stats_total'       => 'Total attempts:',
    'stats_published'   => 'Published:',
    'stats_failed'      => 'Errors:',
    'stats_cancelled'   => 'Cancelled:',
    'stats_last'        => 'Last published:',
    'stats_created'     => 'Created:',

    // Test
    'btn_test'       => 'Test Connection',
    'btn_checking'   => 'Checking...',
    'test_error'     => 'Error: ',

    // Messages
    'msg_no_endpoints'   => 'No configured websites for publishing.',
    'msg_add_first'      => 'Add first site',
    'msg_saved'          => 'Website saved successfully.',
    'msg_deleted'        => 'Website deleted.',
    'msg_delete_confirm' => 'Delete site ":name"?',
    'msg_toggle_pause'   => 'Pause',
    'msg_toggle_resume'  => 'Activate',

    // Buttons
    'btn_back'   => 'Back',
    'btn_save'   => 'Save',
    'btn_cancel' => 'Cancel',
];
