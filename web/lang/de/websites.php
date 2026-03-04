<?php
/**
 * Website endpoints page translations.
 */
return [
    'title'  => 'Webseiten',
    'create' => 'Webseite hinzufügen',
    'edit'   => 'Webseite bearbeiten',
    'new'    => 'Neue Webseite',

    // Table columns
    'col_site'     => 'Webseite',
    'col_channel'  => 'Quellkanal',
    'col_auth'     => 'Authentifizierung',
    'col_schedule' => 'Zeitplan',
    'col_published'=> 'Veröffentlicht',
    'col_errors'   => 'Fehler',
    'col_status'   => 'Status',

    // Auth types
    'auth_none'          => 'Keine Auth.',
    'auth_bearer'        => 'Bearer-Token',
    'auth_api_key'       => 'API-Schlüssel (eigener Header)',
    'auth_basic'         => 'HTTP Basic (Benutzer:Passwort)',
    'auth_custom_header' => 'Eigener Header',

    // Schedule
    'schedule_max_day' => 'max :n/Tag',

    // Status badges
    'status_active' => 'Aktiv',
    'status_paused' => 'Pausiert',

    // Form sections
    'section_basic'    => 'Grundlagen',
    'section_auth'     => 'Authentifizierung',
    'section_mapping'  => 'Feldzuordnung',
    'section_extras'   => 'Statische Payload-Felder',
    'section_response' => 'Antwort & Fehlerbehandlung',
    'section_schedule' => 'Veröffentlichungszeitplan',
    'section_stats'    => 'Statistiken',

    // Form fields – basic
    'field_name'              => 'Name',
    'field_name_placeholder'  => 'Meine Nachrichtenseite',
    'field_site_url'          => 'Webseiten-URL',
    'field_site_url_note'     => '(zur Anzeige)',
    'field_api_url'           => 'API-URL',
    'field_api_url_help'      => 'REST-Endpunkt-URL, an die Artikel gesendet werden.',
    'field_channel'           => 'Quell-Inhaltskanal',
    'field_channel_help'      => 'KI-verarbeiteter Inhalt wird aus article_versions dieses Kanals entnommen.',
    'field_channel_select'    => '— Kanal auswählen —',
    'field_http_method'       => 'HTTP-Methode',
    'field_status'            => 'Status',
    'field_content_type'      => 'Content-Type',
    'content_type_json'       => 'application/json (empfohlen)',

    // Form fields – auth
    'field_auth_type'             => 'Typ',
    'field_auth_header_name'      => 'Header-Name',
    'field_auth_header_name_help' => 'Z.B.: X-API-Key, X-Auth-Token',
    'field_auth_credential'       => 'Zugangsdaten',
    'field_auth_credential_keep'  => '(leer lassen, um bestehende zu behalten)',
    'field_auth_credential_placeholder' => 'Token / Benutzer:Passwort',
    'field_auth_credential_help'  => 'Für <b>Basic</b>: <code>Benutzer:Passwort</code> &nbsp;|&nbsp; Für <b>Bearer / API Key / Custom</b>: vollständiger Token. Wird verschlüsselt gespeichert.',

    // Form fields – mapping
    'mapping_help_btn'    => 'Hilfe',
    'mapping_help_fields' => '<b>Verfügbare interne Felder:</b>',
    'mapping_help_syntax' => '<b>Syntax:</b>',
    'mapping_help_direct' => '<code>"field": "target_field"</code> — direkte Zuordnung',
    'mapping_help_transform' => '<code>"field": {"to": "target_field", "transform": "..."}}</code> — mit Transformation',
    'mapping_help_transforms_label' => '<b>Transformationen:</b>',
    'mapping_help_nesting'  => '<b>Verschachtelung:</b>',
    'field_mapping_json'    => 'Mapping-JSON',
    'mapping_example'       => 'WordPress-Beispiel:',

    // Form fields – extras
    'extras_note' => 'Statische Werte, die jeder Anfrage hinzugefügt werden, nicht aus dem Artikel entnommen. Z.B.: <code>{"status": "publish", "author": 1}</code>',

    // Form fields – response/error
    'field_success_codes'  => 'HTTP-Erfolgscodes',
    'field_success_codes_help' => 'Kommagetrennt.',
    'field_external_id_path'   => 'JSON-Pfad zur Artikel-ID',
    'field_external_id_help'   => 'Pfad in der JSON-Antwort.',
    'field_external_url_path'  => 'JSON-Pfad zur Artikel-URL',
    'field_retry_codes'    => 'HTTP-Wiederholungscodes',
    'field_max_retries'    => 'Max. Wiederholungen',
    'field_retry_delay'    => 'Wiederholungsverzögerung (Sek.)',

    // Form fields – schedule
    'field_interval'      => 'Intervall zwischen Beiträgen (Min.)',
    'field_hours_start'   => 'Beginn (UTC)',
    'field_hours_end'     => 'Ende (UTC)',
    'field_max_per_run'   => 'Max pro Durchlauf',
    'field_max_per_day'   => 'Max pro Tag',

    // Stats
    'stats_total'       => 'Versuche gesamt:',
    'stats_published'   => 'Veröffentlicht:',
    'stats_failed'      => 'Fehler:',
    'stats_cancelled'   => 'Abgebrochen:',
    'stats_last'        => 'Zuletzt veröffentlicht:',
    'stats_created'     => 'Erstellt:',

    // Test
    'btn_test'       => 'Verbindung testen',
    'btn_checking'   => 'Prüfe...',
    'test_error'     => 'Fehler: ',

    // Messages
    'msg_no_endpoints'   => 'Keine konfigurierten Webseiten zur Veröffentlichung.',
    'msg_add_first'      => 'Erste Webseite hinzufügen',
    'msg_saved'          => 'Webseite erfolgreich gespeichert.',
    'msg_deleted'        => 'Webseite gelöscht.',
    'msg_delete_confirm' => 'Webseite ":name" löschen?',
    'msg_toggle_pause'   => 'Pausieren',
    'msg_toggle_resume'  => 'Aktivieren',

    // Buttons
    'btn_back'   => 'Zurück',
    'btn_save'   => 'Speichern',
    'btn_cancel' => 'Abbrechen',
];
