<?php
/**
 * Переводы страницы каналов.
 */
return [
    'title' => 'Каналы',
    'create' => 'Добавить канал',
    'edit' => 'Редактировать канал',

    // Колонки таблицы
    'col_name' => 'Название',
    'col_chat_id' => 'Chat ID',
    'col_bot' => 'Бот',
    'col_sources' => 'Источников',
    'col_status' => 'Статус',
    'col_today_limit' => 'Сегодня / Лимит',

    // Поля формы
    'field_name' => 'Название канала',
    'field_name_help' => 'Отображаемое название канала',
    'field_chat_id' => 'Telegram Chat ID',
    'field_chat_id_help' => '@channel_name или -100123456789',
    'field_bot' => 'Telegram бот',
    'field_bot_help' => 'Бот для публикации в этот канал',
    'field_language' => 'Язык',
    'field_language_help' => 'Язык контента для AI обработки',
    'field_topic' => 'Тема',
    'field_prompt' => 'AI промпт',
    'field_prompt_help' => 'Инструкции для AI по обработке статей.',
    'field_validation_prompt' => 'Промпт валидации',
    'field_status' => 'Статус',
    'field_post_format' => 'Формат поста',
    'field_post_interval' => 'Интервал постов (мин)',

    // Настройки публикации
    'field_max_per_run' => 'Макс за запуск',
    'field_max_per_day' => 'Макс в день',
    'field_min_importance' => 'Мин. важность',
    'field_active_hours_start' => 'Активные часы: начало',
    'field_active_hours_end' => 'Активные часы: конец',
    'field_use_images' => 'Использовать изображения',

    // Настройки валидации
    'validation' => 'Валидация',
    'field_min_score' => 'Мин. оценка',
    'field_validation_mode' => 'Режим',
    'validation_never' => 'Никогда',
    'validation_sample' => 'Выборка (%)',
    'validation_importance' => 'По важности',
    'validation_always' => 'Всегда',
    'field_sample_pct' => 'Выборка %',
    'field_importance_min' => 'Мин. важность',

    // Сайдбар
    'info' => 'Информация',

    // Переобработка
    'reprocess_days' => ':count день|:count дней',
    'day_1' => '1 день',
    'day_3' => '3 дня',
    'day_7' => '7 дней',
    'day_14' => '14 дней',

    // Сообщения
    'msg_saved' => 'Канал успешно сохранён.',
    'msg_deleted' => 'Канал удалён.',
    'msg_delete_confirm' => 'Удалить этот канал?',
    'msg_no_channels' => 'Каналы не настроены.',
    'msg_prompt_changed' => 'Промпт изменён. :count неопубликованных статей добавлено в очередь на переобработку.',
    'msg_select_bot' => 'Выберите бота...',

    // Тестирование
    'test_post' => 'Отправить тестовое сообщение',
    'test_success' => 'Тестовое сообщение отправлено!',
    'test_failed' => 'Не удалось отправить тестовое сообщение',
];
