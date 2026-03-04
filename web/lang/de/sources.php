<?php
/**
 * Sources page translations.
 */
return [
    'title' => 'Quellen',
    'create' => 'Quelle hinzufügen',
    'edit' => 'Quelle bearbeiten',

    // Table columns
    'col_name' => 'Name',
    'col_type' => 'Typ',
    'col_strategy' => 'Strategie',
    'col_channel' => 'Kanal',
    'col_channels' => 'Kanäle',
    'col_feeds' => 'Feeds',
    'col_rank' => 'Rang',
    'col_status' => 'Status',
    'col_last_fetch' => 'Letzter Abruf',

    // Form fields
    'field_name' => 'Name',
    'field_name_help' => 'Anzeigename der Quelle',
    'field_site_url' => 'Webseiten-URL',
    'field_channel' => 'Kanal',
    'field_channel_help' => 'Zielkanal für Artikel',
    'field_type' => 'Typ',
    'field_type_news' => 'Nachrichten',
    'field_type_blog' => 'Blog',
    'field_type_aggregator' => 'Aggregator',
    'field_type_official' => 'Offiziell',
    'field_scrape_strategy' => 'Scraping-Strategie',
    'field_strategy_web' => 'Web (RSS + Scraping)',
    'field_strategy_rss_only' => 'Nur RSS',
    'field_strategy_custom' => 'Eigener Parser',
    'field_authority_rank' => 'Autoritätsrang',
    'field_authority_help' => '1-30: große Medien, 31-60: regional, 61-100: Blogs',
    'field_request_delay' => 'Anfrageverzögerung (ms)',
    'field_proxy_url' => 'Proxy-URL',
    'field_status' => 'Status',
    'field_priority' => 'Priorität',
    'field_priority_help' => 'Quellen mit höherer Priorität werden zuerst verarbeitet',

    // Feeds section
    'feeds' => 'RSS-Feeds',
    'feeds_add' => 'Hinzufügen',
    'feed_url' => 'Feed-URL',
    'feed_name' => 'Name (optional)',
    'feed_interval' => 'Intervall (Min.)',
    'feed_interval_placeholder' => 'jeder Durchlauf',
    'feed_status' => 'Status',
    'feed_last_fetch' => 'Letzter Abruf',
    'feed_errors' => 'Fehler',
    'feed_error' => 'Fehler',
    'feed_reactivate' => 'Reaktivieren',
    'no_feeds' => 'Keine Feeds konfiguriert.',

    // Scrape rules section
    'scrape_rules' => 'Scraping-Regeln',
    'scrape_rules_add' => 'Hinzufügen',
    'scrape_rules_help' => 'CSS-Selektoren zur Extraktion von Artikelinhalten',
    'scrape_content_selector' => 'Inhaltsselektor',
    'scrape_remove_selectors' => 'Entfernungsselektoren',
    'scrape_remove_help' => 'Einer pro Zeile',
    'scrape_priority' => 'Prior.',
    'scrape_field' => 'Feld',
    'scrape_selector' => 'CSS-Selektor',
    'scrape_attribute' => 'Attribut',
    'no_scrape_rules' => 'Keine Scraping-Regeln konfiguriert.',

    // Sidebar
    'info' => 'Information',
    'disabled_feeds' => 'Deaktivierte Feeds',
    'disabled_feeds_help' => 'Feeds wurden wegen Fehlern automatisch deaktiviert',
    'reactivate_all' => 'Alle reaktivieren',
    'custom_parser' => 'Eigener Parser',
    'configure_parser' => 'Parser konfigurieren',
    'general_settings' => 'Allgemeine Einstellungen',

    // Messages
    'msg_saved' => 'Quelle erfolgreich gespeichert.',
    'msg_deleted' => 'Quelle gelöscht.',
    'msg_delete_confirm' => 'Quelle :name löschen? Alle Feeds und Regeln werden gelöscht.',
    'msg_no_sources' => 'Keine Quellen gefunden.',
    'msg_no_sources_link' => 'Erste Quelle hinzufügen',
    'msg_feed_reactivated' => 'Feed reaktiviert.',
    'msg_select_channel' => 'Kanal auswählen...',

    // Actions
    'btn_edit' => 'Bearbeiten',
    'btn_parser' => 'Parser',
    'btn_pause' => 'Pausieren',
    'btn_activate' => 'Aktivieren',
    'btn_delete' => 'Löschen',
];
