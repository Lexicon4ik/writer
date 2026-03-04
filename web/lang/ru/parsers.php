<?php
/**
 * Переводы страницы парсеров.
 */
return [
    'title' => 'Парсеры',
    'create' => 'Добавить парсер',
    'edit' => 'Редактировать парсер',

    // Колонки таблицы
    'col_source' => 'Источник',
    'col_list_url' => 'URL списка',
    'col_status' => 'Статус',
    'col_last_run' => 'Последний запуск',
    'col_articles' => 'Статей',
    'col_errors' => 'Ошибки',
    'col_actions' => 'Действия',
    'col_time' => 'Время',
    'col_found' => 'Найдено',
    'col_new' => 'Новых',

    // Страница списка
    'add_parser' => 'Добавить парсер',
    'no_parsers' => 'Парсеры не настроены',
    'no_parsers_hint' => 'Добавьте парсер для одного из существующих источников.',
    'add_source_first' => 'Сначала добавьте источник, затем настройте для него парсер.',
    'status_active' => 'Активен',
    'status_disabled' => 'Отключён',
    'never' => 'Никогда',
    'zero_articles' => 'Пустых запусков',

    // Страница редактирования
    'url_selectors' => 'URL и селекторы',
    'field_list_url' => 'URL списка',
    'field_list_url_help' => 'URL страницы со списком статей',
    'field_article_selector' => 'Селектор статьи',
    'field_article_selector_help' => 'CSS-селектор или XPath для контейнера статьи',
    'field_link_selector' => 'Селектор ссылки',
    'field_link_selector_help' => 'CSS-селектор или XPath для ссылки на статью (относительно контейнера)',
    'field_title_selector' => 'Селектор заголовка',
    'field_title_selector_help' => 'Опционально. Если пусто, используется текст ссылки.',
    'field_date_selector' => 'Селектор даты',
    'field_image_selector' => 'Селектор изображения',
    'field_image_selector_help' => 'Ищет атрибут src или data-src',
    'field_description_selector' => 'Селектор описания',

    // Пагинация
    'pagination' => 'Пагинация',
    'field_pagination_type' => 'Тип пагинации',
    'pagination_none' => 'Нет (одна страница)',
    'pagination_page' => 'Параметр page (?page=N)',
    'pagination_offset' => 'Параметр offset (?offset=N)',
    'pagination_next' => 'Ссылка "Далее"',
    'field_max_pages' => 'Макс. страниц',
    'field_max_pages_help' => 'Максимум страниц за один запуск',
    'field_pagination_param' => 'Имя параметра',
    'field_pagination_start' => 'Начальное значение',
    'field_offset_increment' => 'Шаг offset',
    'field_next_selector' => 'Селектор "Далее"',
    'field_next_selector_help' => 'CSS-селектор для ссылки "Далее"',

    // Фильтрация
    'filtering' => 'Фильтрация',
    'field_min_title_length' => 'Мин. длина заголовка',
    'field_min_title_help' => 'Статьи с короткими заголовками пропускаются',
    'field_date_format' => 'Формат даты',
    'field_date_format_help' => 'PHP формат даты. Если пусто — автоопределение.',
    'field_exclude_patterns' => 'Исключить URL по паттерну',
    'field_exclude_help' => 'Один regex-паттерн на строку. URL, соответствующие паттернам, будут пропущены.',
    'field_link_base_url' => 'Базовый URL для ссылок',
    'field_link_base_help' => 'Базовый URL для относительных ссылок. Если пусто — берётся из URL списка.',

    // Карточка статуса
    'status' => 'Статус',
    'parser_active' => 'Парсер активен',
    'field_request_delay' => 'Задержка запросов (мс)',
    'field_request_delay_help' => 'Задержка между HTTP-запросами',
    'field_fetch_interval' => 'Интервал обновления (мин)',
    'field_fetch_interval_placeholder' => 'каждый раз',
    'field_fetch_interval_help' => 'Минимальный интервал между запусками парсера. Пусто — каждый крон.',
    'error_handling' => 'Обработка ошибок',
    'field_max_errors' => 'Макс. ошибок',
    'field_max_errors_help' => 'Авто-отключение после N ошибок',
    'field_max_zero_runs' => 'Макс. пустых запусков',
    'field_max_zero_help' => 'Предупреждение после N запусков с 0 статьями',
    'field_min_articles' => 'Мин. порог статей',
    'field_min_articles_help' => '0 = отключено. Оповещение, если ниже порога.',

    // Статистика
    'statistics' => 'Статистика',
    'last_run' => 'Последний запуск',
    'last_count' => 'Последний результат',
    'articles' => 'статей',
    'errors' => 'Ошибки',
    'zero_runs' => 'Пустых запусков',
    'last_error' => 'Последняя ошибка',
    'recent_runs' => 'Последние запуски',

    // Действия
    'btn_save' => 'Сохранить',
    'btn_autofill' => 'Авто-заполнение',
    'btn_test' => 'Тест парсера',
    'btn_delete' => 'Удалить парсер',
    'btn_back' => 'Назад',

    // Модальное окно теста
    'test_results' => 'Результаты теста парсера',
    'testing' => 'Тестирование парсера...',
    'testing_current' => 'Тестирование парсера с текущими настройками...',
    'found_articles' => 'Найдено :count статей за :duration мс',
    'col_title' => 'Заголовок',
    'col_date' => 'Дата',
    'col_url' => 'URL',
    'col_image' => 'Изображение',
    'no_title' => '(без заголовка)',
    'has_image_yes' => 'Да',
    'has_image_no' => 'Нет',

    // Модальное окно авто-анализа
    'auto_analyze' => 'Авто-анализ источника',
    'analyzing' => 'Анализируем структуру страницы...',
    'found_selectors' => 'Найдено :count статей. Поля формы заполнены.',
    'detected_selectors' => 'Обнаруженные селекторы:',
    'sample_titles' => 'Примеры найденных заголовков:',
    'analyze_failed' => 'Не удалось проанализировать страницу',

    // Сообщения
    'msg_saved' => 'Парсер успешно сохранён.',
    'msg_deleted' => 'Парсер удалён.',
    'msg_delete_confirm' => 'Удалить этот парсер?',
    'msg_no_parsers' => 'Парсеры не настроены.',
    'msg_reactivated' => 'Парсер реактивирован.',
    'msg_select_source' => 'Выберите источник...',
    'request_error' => 'Ошибка запроса',
];
