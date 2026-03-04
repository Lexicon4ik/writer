<?php
/**
 * Channels page translations.
 */
return [
    'title' => 'Kanäle',
    'create' => 'Kanal hinzufügen',
    'edit' => 'Kanal bearbeiten',

    // Table columns
    'col_name' => 'Name',
    'col_chat_id' => 'Chat-ID',
    'col_bot' => 'Bot',
    'col_sources' => 'Quellen',
    'col_status' => 'Status',
    'col_today_limit' => 'Heute / Limit',

    // Form fields
    'field_name' => 'Kanalname',
    'field_name_help' => 'Anzeigename des Kanals',
    'field_chat_id' => 'Telegram Chat-ID',
    'field_chat_id_help' => '@kanalname oder -100123456789',
    'field_bot' => 'Telegram-Bot',
    'field_bot_help' => 'Bot zum Posten in diesem Kanal',
    'field_language' => 'Sprache',
    'field_language_help' => 'Inhaltssprache für KI-Verarbeitung',
    'field_topic' => 'Thema',
    'field_prompt' => 'KI-Prompt',
    'field_prompt_help' => 'Anweisungen für die KI zur Artikelverarbeitung.',
    'field_validation_prompt' => 'Validierungs-Prompt',
    'field_status' => 'Status',
    'field_post_format' => 'Beitragsformat',
    'field_post_interval' => 'Beitragsintervall (Min.)',

    // Publishing settings
    'field_max_per_run' => 'Max pro Durchlauf',
    'field_max_per_day' => 'Max pro Tag',
    'field_min_importance' => 'Min. Wichtigkeit',
    'field_active_hours_start' => 'Aktive Stunden: Beginn',
    'field_active_hours_end' => 'Aktive Stunden: Ende',
    'field_use_images' => 'Bilder verwenden',

    // Validation settings
    'validation' => 'Validierung',
    'field_min_score' => 'Min. Punktzahl',
    'field_validation_mode' => 'Modus',
    'validation_never' => 'Nie',
    'validation_sample' => 'Stichprobe (%)',
    'validation_importance' => 'Nach Wichtigkeit',
    'validation_always' => 'Immer',
    'field_sample_pct' => 'Stichprobe %',
    'field_importance_min' => 'Min. Wichtigkeit',

    // Sidebar
    'info' => 'Info',

    // Reprocess
    'reprocess_days' => ':count Tag|:count Tage',
    'day_1' => '1 Tag',
    'day_3' => '3 Tage',
    'day_7' => '7 Tage',
    'day_14' => '14 Tage',

    // Messages
    'msg_saved' => 'Kanal erfolgreich gespeichert.',
    'msg_deleted' => 'Kanal gelöscht.',
    'msg_delete_confirm' => 'Diesen Kanal löschen?',
    'msg_no_channels' => 'Keine Kanäle konfiguriert.',
    'msg_prompt_changed' => 'Prompt geändert. :count unveröffentlichte Artikel zur Neuverarbeitung eingereiht.',
    'msg_select_bot' => 'Bot auswählen...',

    // Test
    'test_post' => 'Testnachricht senden',
    'test_success' => 'Testnachricht gesendet!',
    'test_failed' => 'Testnachricht konnte nicht gesendet werden',
];
