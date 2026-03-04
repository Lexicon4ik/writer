<?php
/**
 * Parsers page translations.
 */
return [
    'title' => 'Parser',
    'create' => 'Parser hinzufügen',
    'edit' => 'Parser bearbeiten',

    // Table columns
    'col_source' => 'Quelle',
    'col_list_url' => 'Listen-URL',
    'col_status' => 'Status',
    'col_last_run' => 'Letzter Lauf',
    'col_articles' => 'Artikel',
    'col_errors' => 'Fehler',
    'col_actions' => 'Aktionen',
    'col_time' => 'Zeit',
    'col_found' => 'Gefunden',
    'col_new' => 'Neu',

    // Index page
    'add_parser' => 'Parser hinzufügen',
    'no_parsers' => 'Keine Parser konfiguriert',
    'no_parsers_hint' => 'Fügen Sie einen Parser für eine der vorhandenen Quellen hinzu.',
    'add_source_first' => 'Fügen Sie zuerst eine Quelle hinzu, dann konfigurieren Sie einen Parser dafür.',
    'status_active' => 'Aktiv',
    'status_disabled' => 'Deaktiviert',
    'never' => 'Nie',
    'zero_articles' => 'Durchläufe ohne Artikel',

    // Edit page
    'url_selectors' => 'URL und Selektoren',
    'field_list_url' => 'Listen-URL',
    'field_list_url_help' => 'URL der Seite mit der Artikelliste',
    'field_article_selector' => 'Artikel-Selektor',
    'field_article_selector_help' => 'CSS-Selektor oder XPath für den Artikel-Container',
    'field_link_selector' => 'Link-Selektor',
    'field_link_selector_help' => 'CSS-Selektor oder XPath für den Artikellink (relativ zum Artikel-Container)',
    'field_title_selector' => 'Titel-Selektor',
    'field_title_selector_help' => 'Optional. Verwendet Linktext, wenn leer.',
    'field_date_selector' => 'Datum-Selektor',
    'field_image_selector' => 'Bild-Selektor',
    'field_image_selector_help' => 'Sucht nach src- oder data-src-Attribut',
    'field_description_selector' => 'Beschreibung-Selektor',

    // Pagination
    'pagination' => 'Seitenumbruch',
    'field_pagination_type' => 'Seitenumbruch-Typ',
    'pagination_none' => 'Keiner (einzelne Seite)',
    'pagination_page' => 'Seitenparameter (?page=N)',
    'pagination_offset' => 'Offset-Parameter (?offset=N)',
    'pagination_next' => 'Nächster Link (Link zur nächsten Seite folgen)',
    'field_max_pages' => 'Max. Seiten',
    'field_max_pages_help' => 'Maximale Seiten pro Durchlauf',
    'field_pagination_param' => 'Parametername',
    'field_pagination_start' => 'Startwert',
    'field_offset_increment' => 'Offset-Schrittweite',
    'field_next_selector' => 'Nächster-Link-Selektor',
    'field_next_selector_help' => 'CSS-Selektor für den „Weiter"-Link',

    // Filtering
    'filtering' => 'Filterung',
    'field_min_title_length' => 'Min. Titellänge',
    'field_min_title_help' => 'Artikel mit kürzeren Titeln werden übersprungen',
    'field_date_format' => 'Datumsformat',
    'field_date_format_help' => 'PHP-Datumsformat. Automatische Erkennung, wenn leer.',
    'field_exclude_patterns' => 'URL-Ausschlussmuster',
    'field_exclude_help' => 'Ein Regex-Muster pro Zeile. URLs, die diesen Mustern entsprechen, werden übersprungen.',
    'field_link_base_url' => 'Link-Basis-URL',
    'field_link_base_help' => 'Basis-URL für relative Links. Automatische Erkennung aus Listen-URL, wenn leer.',

    // Status card
    'status' => 'Status',
    'parser_active' => 'Parser ist aktiv',
    'field_request_delay' => 'Anfrageverzögerung (ms)',
    'field_request_delay_help' => 'Verzögerung zwischen HTTP-Anfragen',
    'field_fetch_interval' => 'Abrufintervall (Min.)',
    'field_fetch_interval_placeholder' => 'jeder Durchlauf',
    'field_fetch_interval_help' => 'Mindestminuten zwischen Parser-Läufen. Leer = jeder Cron.',
    'error_handling' => 'Fehlerbehandlung',
    'field_max_errors' => 'Max. Fehler',
    'field_max_errors_help' => 'Automatisch deaktivieren nach N Fehlern',
    'field_max_zero_runs' => 'Max. Nullläufe',
    'field_max_zero_help' => 'Warnung nach N Durchläufen mit 0 Artikeln',
    'field_min_articles' => 'Min. Artikelschwelle',
    'field_min_articles_help' => '0 = deaktiviert. Warnung bei Unterschreitung.',

    // Statistics
    'statistics' => 'Statistiken',
    'last_run' => 'Letzter Lauf',
    'last_count' => 'Letzte Anzahl',
    'articles' => 'Artikel',
    'errors' => 'Fehler',
    'zero_runs' => 'Nullläufe',
    'last_error' => 'Letzter Fehler',
    'recent_runs' => 'Letzte Läufe',

    // Actions
    'btn_save' => 'Speichern',
    'btn_autofill' => 'Automatisch ausfüllen',
    'btn_test' => 'Parser testen',
    'btn_delete' => 'Parser löschen',
    'btn_back' => 'Zurück',

    // Test modal
    'test_results' => 'Parser-Testergebnisse',
    'testing' => 'Parser wird getestet...',
    'testing_current' => 'Parser mit aktuellen Einstellungen testen...',
    'found_articles' => ':count Artikel in :duration ms gefunden',
    'col_title' => 'Titel',
    'col_date' => 'Datum',
    'col_url' => 'URL',
    'col_image' => 'Bild',
    'no_title' => '(kein Titel)',
    'has_image_yes' => 'Ja',
    'has_image_no' => 'Nein',

    // Auto-analyze modal
    'auto_analyze' => 'Quelle automatisch analysieren',
    'analyzing' => 'Seitenstruktur wird analysiert...',
    'found_selectors' => ':count Artikel gefunden. Formularfelder ausgefüllt.',
    'detected_selectors' => 'Erkannte Selektoren:',
    'sample_titles' => 'Gefundene Beispieltitel:',
    'analyze_failed' => 'Seitenanalyse fehlgeschlagen',

    // Messages
    'msg_saved' => 'Parser erfolgreich gespeichert.',
    'msg_deleted' => 'Parser gelöscht.',
    'msg_delete_confirm' => 'Diesen Parser löschen?',
    'msg_no_parsers' => 'Keine Parser konfiguriert.',
    'msg_reactivated' => 'Parser reaktiviert.',
    'msg_select_source' => 'Quelle auswählen...',
    'request_error' => 'Anfrage fehlgeschlagen',
];
