<?php
/**
 * Settings page translations.
 */
return [
    'title' => 'Einstellungen',

    // Groups
    'group_general' => 'Allgemein',
    'group_ai' => 'KI-Konfiguration',
    'group_telegram' => 'Telegram',
    'group_scraping' => 'Scraping',
    'group_publishing' => 'Veröffentlichung',

    // General settings
    'site_name' => 'Seitenname',
    'timezone' => 'Zeitzone',
    'language' => 'Admin-Sprache',
    'debug_mode' => 'Debug-Modus',

    // AI settings
    'ai_provider' => 'KI-Anbieter',
    'ai_model' => 'KI-Modell',
    'ai_api_key' => 'API-Schlüssel',
    'ai_api_key_help' => 'Leer lassen, um bestehenden Schlüssel zu behalten.',
    'ai_daily_budget' => 'Tagesbudget ($)',
    'ai_max_tokens' => 'Max. Token',
    'ai_temperature' => 'Temperatur',
    'ai_test' => 'KI-Verbindung testen',
    'ai_test_success' => 'KI-Verbindung erfolgreich!',
    'ai_test_failed' => 'KI-Verbindung fehlgeschlagen',

    // Telegram settings
    'tg_api_id' => 'API-ID',
    'tg_api_hash' => 'API-Hash',
    'tg_test' => 'Telegram testen',
    'tg_test_success' => 'Telegram-Verbindung erfolgreich!',
    'tg_test_failed' => 'Telegram-Verbindung fehlgeschlagen',

    // Scraping settings
    'scrape_timeout' => 'Scraping-Timeout (Sek.)',
    'scrape_delay' => 'Verzögerung zwischen Anfragen (Sek.)',
    'scrape_user_agent' => 'User Agent',
    'scrape_max_retries' => 'Max. Wiederholungen',

    // Publishing settings
    'publish_interval' => 'Min. Intervall zwischen Beiträgen (Min.)',
    'publish_max_per_hour' => 'Max. Beiträge pro Stunde',
    'publish_quiet_hours' => 'Ruhestunden (kein Posten)',

    // Settings page UI
    'not_selected' => '-- Nicht ausgewählt --',
    'current_value' => 'Aktuell',
    'enabled' => 'Aktiviert',
    'btn_save' => 'Speichern',

    // Sidebar cards
    'ai_usage_today' => 'KI-Nutzung heute',
    'used' => 'Verbraucht',
    'calls' => 'Aufrufe',
    'input_tokens' => 'Eingabe-Token',
    'output_tokens' => 'Ausgabe-Token',
    'test_ai' => 'KI testen',
    'test_ai_hint' => 'Testanfrage an KI-API senden',
    'btn_test_ai' => 'KI testen',
    'test_telegram' => 'Telegram testen',
    'test_telegram_hint' => 'Testnachricht an Alert-Chat senden',
    'btn_test_telegram' => 'Telegram testen',

    // Test results
    'test_success' => 'Erfolgreich!',
    'test_provider' => 'Anbieter',
    'test_model' => 'Modell',
    'test_response' => 'Antwort',
    'test_tokens' => 'Token',
    'test_duration' => 'Dauer',
    'msg_sent' => 'Nachricht gesendet!',
    'test_bot' => 'Bot',
    'test_chat' => 'Chat',
    'test_message_id' => 'Nachrichten-ID',

    // Messages
    'msg_saved' => 'Einstellungen erfolgreich gespeichert.',
    'msg_key_hidden' => 'Schlüssel aus Sicherheitsgründen verborgen',
    'msg_value_encrypted' => 'Wert ist verschlüsselt',

    // Actions
    'save_settings' => 'Einstellungen speichern',
    'reset_defaults' => 'Auf Standard zurücksetzen',
    'clear_cache' => 'Cache leeren',
    'cache_cleared' => 'Cache erfolgreich geleert.',
];
