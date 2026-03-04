<?php
/**
 * Переводы страницы настроек.
 */
return [
    'title' => 'Настройки',

    // Группы
    'group_general' => 'Общие',
    'group_ai' => 'Настройки AI',
    'group_telegram' => 'Telegram',
    'group_scraping' => 'Парсинг',
    'group_publishing' => 'Публикация',

    // Общие настройки
    'site_name' => 'Название сайта',
    'timezone' => 'Часовой пояс',
    'language' => 'Язык админки',
    'debug_mode' => 'Режим отладки',

    // Настройки AI
    'ai_provider' => 'Провайдер AI',
    'ai_model' => 'Модель AI',
    'ai_api_key' => 'API ключ',
    'ai_api_key_help' => 'Оставьте пустым, чтобы сохранить текущий ключ.',
    'ai_daily_budget' => 'Дневной бюджет ($)',
    'ai_max_tokens' => 'Макс. токенов',
    'ai_temperature' => 'Temperature',
    'ai_test' => 'Проверить подключение к AI',
    'ai_test_success' => 'Подключение к AI успешно!',
    'ai_test_failed' => 'Ошибка подключения к AI',

    // Настройки Telegram
    'tg_api_id' => 'API ID',
    'tg_api_hash' => 'API Hash',
    'tg_test' => 'Проверить Telegram',
    'tg_test_success' => 'Подключение к Telegram успешно!',
    'tg_test_failed' => 'Ошибка подключения к Telegram',

    // Настройки парсинга
    'scrape_timeout' => 'Таймаут парсинга (сек)',
    'scrape_delay' => 'Задержка между запросами (сек)',
    'scrape_user_agent' => 'User Agent',
    'scrape_max_retries' => 'Макс. повторов',

    // Настройки публикации
    'publish_interval' => 'Мин. интервал между постами (мин)',
    'publish_max_per_hour' => 'Макс. постов в час',
    'publish_quiet_hours' => 'Тихие часы (без публикации)',

    // UI страницы настроек
    'not_selected' => '-- Не выбран --',
    'current_value' => 'Текущее',
    'enabled' => 'Включено',
    'btn_save' => 'Сохранить',

    // Карточки сайдбара
    'ai_usage_today' => 'AI расходы сегодня',
    'used' => 'Использовано',
    'calls' => 'Вызовов',
    'input_tokens' => 'Input tokens',
    'output_tokens' => 'Output tokens',
    'test_ai' => 'Тест AI',
    'test_ai_hint' => 'Отправить тестовый запрос к AI API',
    'btn_test_ai' => 'Тест AI',
    'test_telegram' => 'Тест Telegram',
    'test_telegram_hint' => 'Отправить тестовое сообщение в alert chat',
    'btn_test_telegram' => 'Тест Telegram',

    // Результаты тестов
    'test_success' => 'Успешно!',
    'test_provider' => 'Провайдер',
    'test_model' => 'Модель',
    'test_response' => 'Ответ',
    'test_tokens' => 'Токены',
    'test_duration' => 'Время',
    'msg_sent' => 'Сообщение отправлено!',
    'test_bot' => 'Бот',
    'test_chat' => 'Чат',
    'test_message_id' => 'ID сообщения',

    // Сообщения
    'msg_saved' => 'Настройки успешно сохранены.',
    'msg_key_hidden' => 'Ключ скрыт в целях безопасности',
    'msg_value_encrypted' => 'Значение зашифровано',

    // Действия
    'save_settings' => 'Сохранить настройки',
    'reset_defaults' => 'Сбросить по умолчанию',
    'clear_cache' => 'Очистить кэш',
    'cache_cleared' => 'Кэш успешно очищен.',
];
