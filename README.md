# NewsBot Writer

Система автоматической агрегации новостей с AI-обработкой и публикацией в Telegram-каналы и на сайты через REST API.

## Как это работает

Каждую минуту запускается `cron/master.php`, который прогоняет статьи по пайплайну:

```
RSS/custom → Fetch → Scrape → Process (AI) → Publish (Telegram + REST)
```

1. **Fetch** — загружает RSS-ленты и кастомные парсеры, сохраняет новые статьи
2. **Scrape** — скрапит полный текст и изображения из HTML-страниц
3. **Process** — AI анализирует статью, переписывает под тематику канала, оценивает важность, выявляет дубликаты
4. **Publish** — публикует в Telegram-каналы по расписанию (с учётом часового пояса и лимитов)
5. **Web Publish** — публикует на сайты через REST API с гибким маппингом полей
6. **Cleanup** — (раз в час) удаляет зависшие статьи, чистит старые логи, запускает алерты

## Требования

- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Composer
- PHP-расширения: `pdo`, `pdo_mysql`, `curl`, `simplexml`, `dom`, `mbstring`, `iconv`, `json`, `openssl`

## Установка

### 1. Зависимости

```bash
composer install --no-dev
```

### 2. Конфигурация

```bash
cp .env.example .env
```

Заполнить `.env`:
- Сгенерировать `APP_KEY`: `php bin/generate-key.php`
- Указать данные БД

### 3. База данных

```bash
# Создать базу данных
mysql -u root -p -e "CREATE DATABASE newsbot_writer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "GRANT ALL ON newsbot_writer.* TO 'newsbot'@'localhost' IDENTIFIED BY 'secret';"

# Запустить миграции
php migrations/migrate.php
```

### 4. Cron

```bash
# Скопировать шаблон и настроить
crontab -e
```

Содержимое — см. `docs/crontab.example`.

### 5. Права на папки

```bash
mkdir -p logs locks
chmod 775 logs locks
```

### 6. Веб-панель

Сконфигурировать nginx/apache с document root = `web/`. Пример конфига nginx: `docs/nginx.example.conf` *(если есть)*.

Первый вход: `admin` / `admin` — **сразу сменить пароль**.

## Структура проекта

```
writer/
├── bin/               # CLI-утилиты (generate-key, encrypt-token, etc.)
├── config/            # Конфиг приложения (app.php)
├── cron/              # Точки входа для cron (master, fetch, scrape, process, publish, web_publish, cleanup)
├── docs/              # Документация: schema.sql, crontab.example
├── migrations/        # SQL-миграции (001..005)
├── src/               # Ядро PHP (Models, Services, Pipeline, Core, Helpers)
├── web/               # Веб-панель администратора (PHP, JS, CSS)
├── locks/             # Файловые блокировки (не в git)
├── logs/              # Логи приложения (не в git)
├── vendor/            # Composer (не в git)
├── .env.example       # Шаблон конфигурации
└── composer.json
```

## Переменные окружения

| Переменная                  | Описание                                              |
|-----------------------------|-------------------------------------------------------|
| `APP_KEY`                   | Ключ шифрования (base64, 32 байта). `php bin/generate-key.php` |
| `DB_HOST`                   | Хост MySQL                                            |
| `DB_PORT`                   | Порт MySQL (по умолчанию 3306)                        |
| `DB_NAME`                   | Имя базы данных                                       |
| `DB_USER`                   | Пользователь БД                                       |
| `DB_PASS`                   | Пароль БД                                             |
| `FETCH_DOMAIN_DELAY_MS`     | Задержка между запросами к одному домену (мс)         |

Настройки AI (провайдер, модели, бюджет) хранятся в таблице `settings` и управляются через веб-панель.

## Миграции

```bash
php migrations/migrate.php         # применить все новые
php migrations/migrate.php --status # посмотреть статус
```

Миграции применяются по порядку (001, 002, …) и идемпотентны.

## Логи

Ротация через `logrotate`. Логи пишутся в `logs/app.log`. Уровень логирования настраивается через `LOG_LEVEL` (опционально).

## Лицензия

Proprietary.
