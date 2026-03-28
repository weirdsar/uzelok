# УЗЕЛОК64 — uzelok64.ru

Мультибрендовый интернет-магазин текстильных изделий + инженерно-швейная мастерская.

## Бренды

- **БАТЯ** — хозтовары
- **БУЙ** — автоаксессуары
- **ВОЛНА** — охота, рыбалка, туризм

## Стек

- PHP 8.4 (чистый PHP, без фреймворков)
- SQLite
- Tailwind CSS
- Ozon Seller API
- Telegram Bot API

## Локальная разработка

### Docker

```bash
docker compose up -d
```

→ http://localhost:8080

### PHP built-in server

```bash
php -S localhost:8000 -t public_html
```

### Инициализация БД

```bash
php database/init.php
```

### Наполнение каталога (seed из мока / синхронизации)

```bash
php database/seed.php
```

При заглушках Ozon API подтягиваются реальные названия и ссылки из `OzonService::getMockData()`.

### Синхронизация с Ozon (CLI)

```bash
php cron/sync.php
```

### Фото товаров (локальные файлы)

1. Скопировать `scripts/ozon-image-urls.example.php` → `scripts/ozon-image-urls.local.php` (файл в `.gitignore`).
2. Вставить в массив прямые URL главных фото с Ozon (`og:image` или `cdn1.ozon.ru` / `ir.ozon.ru` из кода страницы карточки). Серверный `curl` к HTML Ozon часто получает Antibot — URL удобнее копировать из браузера.
3. Запустить:

```bash
php scripts/download-images.php
```

Для проверки пайплайна без Ozon:

```bash
php scripts/download-images.php --demo
```

(заглушки через picsum.photos; затем замените URL в `ozon-image-urls.local.php` на реальные.)

Файлы сохраняются в `public_html/assets/images/products/{sku}.jpg`, в БД обновляется `image_local_path`.

## Настройка

1. Скопировать `config/config.example.php` → `config/config.php`
2. Заполнить API-ключи Ozon, токен Telegram, email
3. Запустить `php database/init.php`

## Деплой

Push в `main` → GitHub Actions → sFTP на Beget.

Необходимые секреты: `BEGET_FTP_HOST`, `BEGET_FTP_USER`, `BEGET_FTP_PASSWORD`.

### Первый push (репозиторий создан на GitHub)

```bash
cd /path/to/uzelok
git remote add origin https://github.com/USER/REPO.git
git push -u origin main
```

После первого деплоя на сервере: `config/config.php`, при необходимости `.ozon.env`, затем `php database/init.php`, `php scripts/fill-seo-articles.php`, `php scripts/sync-user-infographics.php`, `php cron/sync.php`.

## Cron (Beget)

Ежедневная синхронизация с Ozon (подставьте свой путь к домашней директории на сервере):

```text
0 3 * * * /usr/bin/php /home/u/username/uzelok64.ru/cron/sync.php >> /home/u/username/uzelok64.ru/logs/cron.log 2>&1
```
