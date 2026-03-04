<?php
/**
 * Переводы страницы статей.
 */
return [
    'title' => 'Статьи',
    'view' => 'Статья',
    'edit' => 'Редактировать статью',

    // Колонки таблицы
    'col_id' => 'ID',
    'col_title' => 'Заголовок',
    'col_source' => 'Источник',
    'col_channel' => 'Канал',
    'col_status' => 'Статус',
    'col_importance' => 'Важн',
    'col_versions' => 'Версий',
    'col_date' => 'Дата',
    'col_actions' => 'Действия',
    'col_created' => 'Создано',
    'col_published' => 'Опубликовано',

    // Фильтры
    'filter_status' => 'Статус',
    'filter_channel' => 'Канал',
    'filter_source' => 'Источник',
    'filter_date_from' => 'Дата от',
    'filter_date_to' => 'Дата до',
    'filter_all_statuses' => 'Все статусы',
    'filter_all_channels' => 'Все каналы',
    'filter_all_sources' => 'Все источники',
    'btn_filter' => 'Фильтр',

    // Массовые действия
    'select_all' => 'Выбрать все',
    'bulk_action' => '-- Действие --',
    'bulk_reprocess' => 'Переобработать',
    'bulk_cancel' => 'Отменить',
    'bulk_publish' => 'Опубликовать',
    'btn_apply' => 'Применить',
    'total' => 'Всего',
    'bulk_confirm' => 'Применить действие к выбранным статьям?',
    'no_title' => 'Без заголовка',
    'in_cluster' => 'Входит в кластер',
    'btn_view' => 'Просмотр',

    // Страница просмотра статьи
    'article_num' => 'Статья #:id',
    'btn_back' => 'Назад',
    'original' => 'Оригинал',
    'rss_title' => 'Заголовок RSS',
    'scraped_title' => 'Заголовок (парсинг)',
    'rss_description' => 'Описание RSS',
    'scraped_text' => 'Текст (парсинг)',
    'url' => 'URL',
    'source' => 'Источник',
    'importance' => 'Важность',
    'avg_from_versions' => 'среднее из :count версий',
    'created' => 'Создано',
    'versions' => 'Версии',
    'no_versions' => 'Нет версий',
    'version_data' => 'Данные версии',
    'post_preview' => 'Предпросмотр поста',
    'hashtags' => 'Хэштеги',
    'btn_publish' => 'Опубликовать',
    'btn_edit' => 'Редактировать',
    'btn_delete_tg' => 'Удалить из TG',
    'confirm_publish' => 'Опубликовать эту версию?',
    'confirm_delete_tg' => 'Удалить пост из Telegram?',
    'status_history' => 'История статусов',
    'no_history' => 'Нет истории',
    'col_time' => 'Время',
    'col_old_status' => 'Старый статус',
    'col_new_status' => 'Новый статус',
    'col_details' => 'Детали',

    // Карточка действий
    'actions' => 'Действия',
    'confirm_reprocess' => 'Переобработать статью?',
    'confirm_cancel' => 'Отменить статью и все её версии?',
    'btn_cancel' => 'Отменить',
    'status_no_actions' => 'Статья в статусе :status, действия недоступны.',

    // Карточка кластера
    'duplicate_cluster' => 'Кластер дубликатов',
    'cluster_hash' => 'Хэш',
    'cluster_created' => 'Создан',
    'articles_in_cluster' => 'Статьи в кластере',
    'primary' => 'Основная',
    'current' => 'текущая',

    // Карточка информации
    'info' => 'Информация',
    'source_id' => 'ID источника',
    'feed_id' => 'ID фида',
    'cluster_id' => 'ID кластера',
    'url_hash' => 'Хэш URL',
    'updated' => 'Обновлено',

    // Страница редактирования поста
    'edit_post' => 'Редактирование поста',
    'version_for_channel' => 'Версия для канала',
    'field_title' => 'Заголовок',
    'field_body' => 'Текст',
    'tg_html_hint' => 'Поддерживается HTML-разметка Telegram: <b>, <i>, <code>, <a href="">',
    'btn_save_update' => 'Сохранить и обновить в Telegram',
    'btn_save' => 'Сохранить',
    'btn_cancel_edit' => 'Отмена',
    'original_content' => 'Оригинал',
    'original_title' => 'Заголовок',
    'original_text' => 'Текст (фрагмент)',
    'article_id' => 'Статья ID',
    'version_id' => 'Версия ID',
    'channel' => 'Канал',
    'validation_score' => 'Оценка валидации',

    // Сообщения
    'msg_approved' => 'Статья одобрена.',
    'msg_rejected' => 'Статья отклонена.',
    'msg_reprocessed' => 'Статья добавлена в очередь на переобработку.',
    'msg_published' => 'Статья опубликована.',
    'msg_no_articles' => 'Статьи не найдены.',
    'msg_delete_confirm' => 'Удалить эту статью?',
];
