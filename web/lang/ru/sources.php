<?php
/**
 * Переводы страницы источников.
 */
return [
    'title' => 'Источники',
    'create' => 'Добавить источник',
    'edit' => 'Редактирование источника',

    // Колонки таблицы
    'col_name' => 'Название',
    'col_type' => 'Тип',
    'col_strategy' => 'Стратегия',
    'col_channel' => 'Канал',
    'col_channels' => 'Каналов',
    'col_feeds' => 'Фиды',
    'col_rank' => 'Ранг',
    'col_status' => 'Статус',
    'col_last_fetch' => 'Последняя загрузка',

    // Поля формы
    'field_name' => 'Название',
    'field_name_help' => 'Отображаемое название источника',
    'field_site_url' => 'URL сайта',
    'field_channel' => 'Канал',
    'field_channel_help' => 'Целевой канал для статей',
    'field_type' => 'Тип',
    'field_type_news' => 'Новости',
    'field_type_blog' => 'Блог',
    'field_type_aggregator' => 'Агрегатор',
    'field_type_official' => 'Официальный',
    'field_scrape_strategy' => 'Стратегия скрапинга',
    'field_strategy_web' => 'Web (RSS + scrape)',
    'field_strategy_rss_only' => 'Только RSS',
    'field_strategy_custom' => 'Custom Parser',
    'field_authority_rank' => 'Ранг авторитетности',
    'field_authority_help' => '1-30: крупные СМИ, 31-60: региональные, 61-100: блоги',
    'field_request_delay' => 'Задержка запросов (мс)',
    'field_proxy_url' => 'Proxy URL',
    'field_status' => 'Статус',
    'field_priority' => 'Приоритет',
    'field_priority_help' => 'Источники с высшим приоритетом обрабатываются первыми',

    // Секция фидов
    'feeds' => 'RSS-фиды',
    'feeds_add' => 'Добавить',
    'feed_url' => 'URL ленты',
    'feed_name' => 'Название (опционально)',
    'feed_interval' => 'Интервал (мин)',
    'feed_interval_placeholder' => 'каждый раз',
    'feed_status' => 'Статус',
    'feed_last_fetch' => 'Последняя загрузка',
    'feed_errors' => 'Ошибок',
    'feed_error' => 'Ошибка',
    'feed_reactivate' => 'Реактивировать',
    'no_feeds' => 'Ленты не настроены.',

    // Секция правил парсинга
    'scrape_rules' => 'Правила скрапинга',
    'scrape_rules_add' => 'Добавить',
    'scrape_rules_help' => 'CSS-селекторы для извлечения контента статей',
    'scrape_content_selector' => 'Content selector',
    'scrape_remove_selectors' => 'Remove selectors',
    'scrape_remove_help' => 'по строке',
    'scrape_priority' => 'Prior',
    'scrape_field' => 'Поле',
    'scrape_selector' => 'CSS селектор',
    'scrape_attribute' => 'Атрибут',
    'no_scrape_rules' => 'Правила парсинга не настроены.',

    // Сайдбар
    'info' => 'Информация',
    'disabled_feeds' => 'Отключённые фиды',
    'disabled_feeds_help' => 'Фиды автоматически отключены из-за ошибок',
    'reactivate_all' => 'Реактивировать все',
    'custom_parser' => 'Custom Parser',
    'configure_parser' => 'Настроить парсер',
    'general_settings' => 'Основные настройки',

    // Сообщения
    'msg_saved' => 'Источник успешно сохранён.',
    'msg_deleted' => 'Источник удалён.',
    'msg_delete_confirm' => 'Удалить источник :name? Все фиды и правила будут удалены.',
    'msg_no_sources' => 'Источники не найдены.',
    'msg_no_sources_link' => 'Добавьте первый источник',
    'msg_feed_reactivated' => 'Лента реактивирована.',
    'msg_select_channel' => 'Выберите канал...',

    // Действия
    'btn_edit' => 'Редактировать',
    'btn_parser' => 'Парсер',
    'btn_pause' => 'Приостановить',
    'btn_activate' => 'Активировать',
    'btn_delete' => 'Удалить',
];
