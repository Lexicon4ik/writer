<?php
/**
 * Переводы страницы сайтов (website endpoints).
 */
return [
    'title'  => 'Сайты',
    'create' => 'Добавить сайт',
    'edit'   => 'Редактировать сайт',
    'new'    => 'Новый сайт',

    // Колонки таблицы
    'col_site'      => 'Сайт',
    'col_channel'   => 'Канал-источник',
    'col_auth'      => 'Аутентификация',
    'col_schedule'  => 'Расписание',
    'col_published' => 'Опубликовано',
    'col_errors'    => 'Ошибок',
    'col_status'    => 'Статус',

    // Типы аутентификации
    'auth_none'          => 'Без авторизации',
    'auth_bearer'        => 'Bearer Token',
    'auth_api_key'       => 'API Key (произвольный заголовок)',
    'auth_basic'         => 'HTTP Basic (user:password)',
    'auth_custom_header' => 'Custom Header',

    // Расписание
    'schedule_max_day' => 'макс :n/день',

    // Бейджи статуса
    'status_active' => 'Активен',
    'status_paused' => 'Пауза',

    // Разделы формы
    'section_basic'    => 'Основное',
    'section_auth'     => 'Аутентификация',
    'section_mapping'  => 'Маппинг полей',
    'section_extras'   => 'Статические поля payload',
    'section_response' => 'Разбор ответа и обработка ошибок',
    'section_schedule' => 'Расписание публикации',
    'section_stats'    => 'Статистика',

    // Поля формы – основное
    'field_name'              => 'Название',
    'field_name_placeholder'  => 'Мой новостной сайт',
    'field_site_url'          => 'URL сайта',
    'field_site_url_note'     => '(для отображения)',
    'field_api_url'           => 'API URL',
    'field_api_url_help'      => 'URL REST-эндпоинта, на который будут отправляться статьи.',
    'field_channel'           => 'Канал-источник контента',
    'field_channel_help'      => 'Из article_versions этого канала берётся обработанный AI-контент.',
    'field_channel_select'    => '— выберите канал —',
    'field_http_method'       => 'HTTP метод',
    'field_status'            => 'Статус',
    'field_content_type'      => 'Content-Type',
    'content_type_json'       => 'application/json (рекомендуется)',

    // Поля формы – аутентификация
    'field_auth_type'             => 'Тип',
    'field_auth_header_name'      => 'Имя заголовка',
    'field_auth_header_name_help' => 'Например: X-API-Key, X-Auth-Token',
    'field_auth_credential'       => 'Учётные данные',
    'field_auth_credential_keep'  => '(оставьте пустым чтобы не менять)',
    'field_auth_credential_placeholder' => 'токен / user:password',
    'field_auth_credential_help'  => 'Для <b>Basic</b>: <code>username:password</code> &nbsp;|&nbsp; Для <b>Bearer / API Key / Custom</b>: токен целиком. Хранится в зашифрованном виде.',

    // Поля формы – маппинг
    'mapping_help_btn'    => 'Справка',
    'mapping_help_fields' => '<b>Доступные внутренние поля:</b>',
    'mapping_help_syntax' => '<b>Синтаксис:</b>',
    'mapping_help_direct' => '<code>"поле": "target_field"</code> — прямой маппинг',
    'mapping_help_transform' => '<code>"поле": {"to": "target_field", "transform": "..."}}</code> — с трансформацией',
    'mapping_help_transforms_label' => '<b>Трансформации:</b>',
    'mapping_help_nesting'  => '<b>Вложенность:</b>',
    'field_mapping_json'    => 'JSON маппинга',
    'mapping_example'       => 'Пример WordPress:',

    // Поля формы – extras
    'extras_note' => 'Статические значения, которые добавляются в каждый запрос и не берутся из статьи. Например: <code>{"status": "publish", "author": 1}</code>',

    // Поля формы – ответ/ошибки
    'field_success_codes'      => 'HTTP-коды успеха',
    'field_success_codes_help' => 'Через запятую.',
    'field_external_id_path'   => 'JSON-путь к ID статьи',
    'field_external_id_help'   => 'Путь в JSON-ответе.',
    'field_external_url_path'  => 'JSON-путь к URL статьи',
    'field_retry_codes'        => 'HTTP-коды для retry',
    'field_max_retries'        => 'Макс. повторных попыток',
    'field_retry_delay'        => 'Задержка retry (сек.)',

    // Поля формы – расписание
    'field_interval'    => 'Интервал между постами (мин.)',
    'field_hours_start' => 'Начало (UTC)',
    'field_hours_end'   => 'Конец (UTC)',
    'field_max_per_run' => 'Макс. за запуск',
    'field_max_per_day' => 'Макс. в день',

    // Статистика
    'stats_total'     => 'Всего попыток:',
    'stats_published' => 'Опубликовано:',
    'stats_failed'    => 'Ошибок:',
    'stats_cancelled' => 'Отменено:',
    'stats_last'      => 'Последняя публикация:',
    'stats_created'   => 'Создан:',

    // Тест
    'btn_test'     => 'Тест подключения',
    'btn_checking' => 'Проверка...',
    'test_error'   => 'Ошибка: ',

    // Сообщения
    'msg_no_endpoints'   => 'Нет настроенных сайтов для публикации.',
    'msg_add_first'      => 'Добавить первый сайт',
    'msg_saved'          => 'Сайт успешно сохранён.',
    'msg_deleted'        => 'Сайт удалён.',
    'msg_delete_confirm' => 'Удалить сайт «:name»?',
    'msg_toggle_pause'   => 'Приостановить',
    'msg_toggle_resume'  => 'Активировать',

    // Кнопки
    'btn_back'   => 'Назад',
    'btn_save'   => 'Сохранить',
    'btn_cancel' => 'Отмена',
];
