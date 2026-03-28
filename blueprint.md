# УЗЕЛОК64 — Полная проектная документация
## uzelok64.ru | БАТЯ • БУЙ • ВОЛНА

---

# ЭТАП 1: ТЕХНИЧЕСКАЯ СПЕЦИФИКАЦИЯ (BLUEPRINT)

---

## 1.1 Структура папок для Beget

```
uzelok64.ru/
├── public_html/                    # DocumentRoot (Beget)
│   ├── index.php                   # Точка входа (фронт-контроллер)
│   ├── submit-form.php             # Обработчик форм (AJAX)
│   ├── .htaccess                   # Маршрутизация, защита, PHP 8.4
│   ├── robots.txt
│   ├── sitemap.xml
│   ├── favicon.ico
│   ├── assets/
│   │   ├── css/
│   │   │   └── app.css             # Минимальные кастомные стили (Tailwind CDN)
│   │   ├── js/
│   │   │   └── app.js              # Модалки, фильтрация, формы
│   │   └── images/
│   │       ├── brands/
│   │       │   ├── batya-logo.png
│   │       │   ├── buy-logo.png
│   │       │   └── volna-logo.png
│   │       ├── products/           # Локальные копии фото товаров
│   │       │   ├── product-1.jpg
│   │       │   └── ...
│   │       ├── workshop/           # Фото мастерской и образцов
│   │       │   └── ...
│   │       └── hero-bg.jpg
│   └── admin/
│       ├── index.php               # Админка (HTTP Auth)
│       └── sync.php                # Запуск синхронизации
│
├── core/                           # Бизнес-логика (вне DocumentRoot)
│   ├── Database.php                # PDO-обёртка для SQLite
│   ├── Product.php                 # Модель продукта (CRUD)
│   ├── Brand.php                   # Enum брендов
│   ├── OzonService.php             # Интеграция с Ozon Seller API
│   ├── TelegramService.php         # Отправка сообщений в Telegram
│   ├── SyncController.php          # Логика синхронизации
│   ├── FormHandler.php             # Обработка форм обратной связи
│   └── helpers.php                 # Утилиты (sanitize, format, etc.)
│
├── config/
│   └── config.php                  # Все настройки проекта
│
├── database/
│   ├── uzelok.db                   # SQLite база данных
│   └── migrations/
│       └── 001_create_products.sql # SQL-миграция
│
├── templates/
│   ├── layout/
│   │   ├── header.php              # Шапка сайта
│   │   └── footer.php              # Подвал сайта
│   ├── components/
│   │   ├── product-card.php        # Карточка товара
│   │   ├── brand-card.php          # Карточка бренда
│   │   ├── order-modal.php         # Модальное окно заявки
│   │   └── contact-form.php        # Форма обратной связи
│   ├── sections/
│   │   ├── hero.php                # Hero-секция
│   │   ├── brands.php              # Блок брендов
│   │   ├── catalog.php             # Каталог товаров
│   │   ├── workshop.php            # Блок мастерской
│   │   └── contacts.php            # Блок контактов
│   └── pages/
│       └── admin.php               # Шаблон админки
│
├── logs/
│   ├── sync.log                    # Лог синхронизации
│   └── error.log                   # Лог ошибок
│
├── vendor/                         # Composer autoload
│   └── autoload.php
│
├── composer.json                   # PSR-4 автозагрузка
├── .github/
│   └── workflows/
│       └── deploy.yml              # GitHub Actions для деплоя
├── .gitignore
└── README.md
```

## 1.2 Схема базы данных SQLite

```sql
-- database/migrations/001_create_products.sql

-- Таблица товаров
CREATE TABLE IF NOT EXISTS products (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    brand_type      TEXT NOT NULL CHECK(brand_type IN ('batya', 'buy', 'volna')),
    sku             TEXT UNIQUE,                    -- SKU для Ozon API
    offer_id        TEXT,                           -- offer_id для Ozon API
    title           TEXT NOT NULL,
    price_ozon      INTEGER NOT NULL DEFAULT 0,     -- Цена в копейках (или рублях целых)
    ozon_url        TEXT NOT NULL DEFAULT '',
    image_local_path TEXT DEFAULT '',               -- Путь к локальному фото
    image_ozon_url  TEXT DEFAULT '',                -- URL фото на Ozon (для фолбэка)
    description     TEXT DEFAULT '',
    category        TEXT DEFAULT '',
    is_active       INTEGER NOT NULL DEFAULT 1,     -- 0 = скрыт, 1 = активен
    sort_order      INTEGER NOT NULL DEFAULT 0,     -- Порядок сортировки
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Индексы
CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand_type);
CREATE INDEX IF NOT EXISTS idx_products_active ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);

-- Таблица заявок (для истории)
CREATE TABLE IF NOT EXISTS orders (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id      INTEGER,                        -- NULL если общая заявка
    customer_name   TEXT NOT NULL,
    customer_phone  TEXT NOT NULL,
    customer_email  TEXT NOT NULL DEFAULT '',
    message         TEXT DEFAULT '',
    source          TEXT DEFAULT 'website',          -- 'website', 'workshop'
    status          TEXT DEFAULT 'new',              -- 'new', 'processed', 'closed'
    telegram_sent   INTEGER DEFAULT 0,              -- 1 = уведомление отправлено
    email_sent      INTEGER DEFAULT 0,              -- 1 = email отправлено
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);

-- Таблица логов синхронизации
CREATE TABLE IF NOT EXISTS sync_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    finished_at     DATETIME,
    products_updated INTEGER DEFAULT 0,
    products_added  INTEGER DEFAULT 0,
    products_deactivated INTEGER DEFAULT 0,
    status          TEXT DEFAULT 'running',          -- 'running', 'success', 'error'
    error_message   TEXT DEFAULT '',
    trigger_type    TEXT DEFAULT 'cron'              -- 'cron', 'manual'
);
```

## 1.3 Файл конфигурации

```php
<?php
// config/config.php
declare(strict_types=1);

return [
    // === Основные ===
    'app_name'    => 'УЗЕЛОК64',
    'app_url'     => 'https://uzelok64.ru',
    'debug'       => false,          // true только на dev

    // === Пути ===
    'paths' => [
        'root'      => dirname(__DIR__),
        'public'    => dirname(__DIR__) . '/public_html',
        'database'  => dirname(__DIR__) . '/database/uzelok.db',
        'logs'      => dirname(__DIR__) . '/logs',
        'templates' => dirname(__DIR__) . '/templates',
        'images'    => dirname(__DIR__) . '/public_html/assets/images',
    ],

    // === Ozon Seller API ===
    'ozon' => [
        'client_id' => 'YOUR_OZON_CLIENT_ID',       // ← Заменить
        'api_key'   => 'YOUR_OZON_API_KEY',          // ← Заменить
        'base_url'  => 'https://api-seller.ozon.ru',
        // SKU товаров для отслеживания (заполнить реальными SKU)
        'tracked_skus' => [
            // БАТЯ
            'BATYA-001',
            'BATYA-002',
            'BATYA-003',
            'BATYA-004',
            // БУЙ
            'BUY-001',
            'BUY-002',
            'BUY-003',
            'BUY-004',
            'BUY-005',
            'BUY-006',
            // ВОЛНА
            'VOLNA-001',
            'VOLNA-002',
            'VOLNA-003',
            'VOLNA-004',
            'VOLNA-005',
            'VOLNA-006',
        ],
        // Маппинг SKU → бренд (для автоопределения)
        'sku_brand_map' => [
            'BATYA' => 'batya',
            'BUY'   => 'buy',
            'VOLNA' => 'volna',
        ],
    ],

    // === Telegram Bot API ===
    'telegram' => [
        'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN',   // ← Заменить (получить через @BotFather)
        'chat_id'   => 'YOUR_TELEGRAM_CHAT_ID',     // ← Заменить (ID чата/канала владельца)
        'base_url'  => 'https://api.telegram.org',
    ],

    // === Email ===
    'email' => [
        'to'       => 'owner@uzelok64.ru',          // ← Заменить
        'from'     => 'noreply@uzelok64.ru',
        'from_name'=> 'УЗЕЛОК64 — Заявка с сайта',
    ],

    // === Админка ===
    'admin' => [
        'username' => 'admin',
        'password' => 'CHANGE_ME_SECURE_PASSWORD',   // ← Заменить (будет хеширован)
    ],

    // === Магазины Ozon (для ссылок) ===
    'ozon_stores' => [
        'batya' => 'https://www.ozon.ru/seller/batya-2103460/',
        'buy'   => 'https://www.ozon.ru/seller/buy/',
        'volna' => 'https://www.ozon.ru/seller/volna-2250971/',
    ],
];
```

## 1.4 Архитектура классов

### Brand.php — Enum брендов

```php
<?php
declare(strict_types=1);
namespace Uzelok\Core;

enum Brand: string
{
    case BATYA = 'batya';
    case BUY   = 'buy';
    case VOLNA = 'volna';

    public function label(): string
    {
        return match($this) {
            self::BATYA => 'БАТЯ',
            self::BUY   => 'БУЙ',
            self::VOLNA => 'ВОЛНА',
        };
    }

    public function subtitle(): string
    {
        return match($this) {
            self::BATYA => 'Хозтовары',
            self::BUY   => 'Авто / Мото / Водный транспорт',
            self::VOLNA => 'Охота / Рыбалка / Туризм',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::BATYA => '#d97706',
            self::BUY   => '#2563eb',
            self::VOLNA => '#059669',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::BATYA => '🔧',
            self::BUY   => '🚗',
            self::VOLNA => '🎣',
        };
    }
}
```

### Database.php — PDO-обёртка

```php
<?php
declare(strict_types=1);
namespace Uzelok\Core;

final class Database
{
    private static ?self $instance = null;
    private readonly \PDO $pdo;

    private function __construct(string $dbPath)
    {
        $this->pdo = new \PDO("sqlite:{$dbPath}", options: [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
    }

    public static function getInstance(string $dbPath): self
    {
        return self::$instance ??= new self($dbPath);
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
```

### Product.php — Модель

```php
<?php
declare(strict_types=1);
namespace Uzelok\Core;

final readonly class ProductDTO
{
    public function __construct(
        public int    $id,
        public Brand  $brand_type,
        public string $title,
        public int    $price_ozon,
        public string $ozon_url,
        public string $image_local_path,
        public string $description,
        public bool   $is_active,
        public string $updated_at,
    ) {}
}

final class Product
{
    public function __construct(
        private readonly Database $db,
    ) {}

    /** @return ProductDTO[] */
    public function getAll(?Brand $brand = null, bool $activeOnly = true): array { /* ... */ }
    public function getById(int $id): ?ProductDTO { /* ... */ }
    public function upsertFromOzon(array $data): void { /* ... */ }
    public function deactivateExcept(array $activeIds): void { /* ... */ }
}
```

### OzonService.php

```php
<?php
declare(strict_types=1);
namespace Uzelok\Core;

final class OzonService
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api-seller.ozon.ru',
    ) {}

    /**
     * POST /v3/product/info/list
     * @param string[] $skus
     * @return array Массив данных товаров
     */
    public function fetchProductData(array $skus): array { /* ... */ }

    /**
     * Обновляет БД по полученным данным
     */
    public function updateDatabase(Product $productModel, array $productsData): int { /* ... */ }

    /**
     * Скачивает изображение товара локально
     */
    public function downloadImage(string $url, string $savePath): bool { /* ... */ }
}
```

### TelegramService.php

```php
<?php
declare(strict_types=1);
namespace Uzelok\Core;

final class TelegramService
{
    public function __construct(
        private readonly string $botToken,
        private readonly string $chatId,
    ) {}

    public function sendMessage(string $text, string $parseMode = 'HTML'): bool
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $payload = [
            'chat_id'    => $this->chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }
}
```

### SyncController.php

```php
<?php
declare(strict_types=1);
namespace Uzelok\Core;

final class SyncController
{
    public function __construct(
        private readonly OzonService $ozon,
        private readonly Product $product,
        private readonly Database $db,
        private readonly string $logPath,
    ) {}

    /**
     * Запуск синхронизации
     * @param string $trigger 'cron' или 'manual'
     */
    public function run(string $trigger = 'cron'): array { /* ... */ }

    private function log(string $message): void { /* ... */ }
}
```

## 1.5 План синхронизации

1. **Cron (ежесуточно)**: `0 3 * * * php /home/user/uzelok64.ru/core/cron-sync.php`
2. **Ручной запуск**: кнопка в `admin/sync.php` (HTTP Auth)
3. **Алгоритм**:
   - Загрузить массив SKU из `config.php`
   - Вызвать `OzonService::fetchProductData($skus)`
   - Для каждого товара: обновить или вставить запись в `products`
   - Скачать новые/обновлённые изображения
   - Деактивировать товары, пропавшие из Ozon
   - Записать результат в `sync_log`
   - Записать лог в `logs/sync.log`

---

# ЭТАП 2: КОНЦЕПЦИЯ УПАКОВКИ УСЛУГ (БЛОК МАСТЕРСКОЙ)

---

## 2.1 Позиционирование

**Заголовок секции**: «От идеи — до эталона»

**Подзаголовок**: «Инженерно-швейная мастерская | п. Солнечный, Саратов»

**Ключевое сообщение**: Мы — не ателье и не швейный цех. Мы — инженерное бюро, которое проектирует, конструирует и производит текстильные изделия промышленного и бытового назначения. Полный цикл: от конструкторской документации до эталонного образца с ВТО.

## 2.2 Услуги (карточки)

| # | Заголовок | Иконка | Описание |
|---|-----------|--------|----------|
| 1 | **Инженерное проектирование** | ✏️ PenTool | Разработка конструкторской документации текстильных изделий. Расчёт нагрузок, выбор материалов, проектирование узлов. |
| 2 | **Создание лекал** | 📏 Ruler | Точные лекала с припусками на швы и ВТО. Цифровая и ручная разработка. Градация по размерам. |
| 3 | **Пошив эталонных образцов** | ✂️ Scissors | Изготовление образцов-эталонов для запуска серии. Подбор фурнитуры, тестирование, доработка. |
| 4 | **Мелкосерийное производство** | 📦 Layers | Выпуск партий от 10 до 500 единиц. Контроль качества на каждом этапе. Упаковка и маркировка. |
| 5 | **Доработка и модификация** | ⚙️ Cog | Модернизация существующих изделий. Усиление конструкции, замена материалов, добавление элементов. |
| 6 | **CTA-карточка** | → | «Есть задача? Расскажите, что нужно спроектировать или сшить — обсудим.» Кнопка: «Обсудить проект» |

## 2.3 Процесс работы (горизонтальная шкала)

```
01 БРИФ          →  02 ПРОЕКТ         →  03 ОБРАЗЕЦ        →  04 СЕРИЯ
Обсуждаем задачу,   Чертежи, лекала,     Пошив эталона,       Запуск производства,
ТЗ, сроки, бюджет   спецификация         тестирование,        контроль, отгрузка
                     материалов           доработка
```

## 2.4 Преимущества (список с чекмарками)

- ✅ Инженерный подход, а не «пошив на глаз»
- ✅ Конструкторская документация на каждое изделие
- ✅ Подбор материалов под конкретные условия эксплуатации
- ✅ Тестирование на прочность, водостойкость, износ
- ✅ Полный цикл: от эскиза до упакованной партии
- ✅ Опыт работы с оксфордом, кордурой, ПВХ-тканями

## 2.5 Визуальное оформление

- **Фон**: тёмный (dark-900) с industrial-grid паттерном и лёгкими оранжевыми бликами
- **Карточки услуг**: bg-dark-800/60, border-dark-500/30, при наведении — orange border glow
- **Иконки**: lucide-react / Heroicons, оранжевый акцент на bg-orange/10
- **Фотографии** (подготовить):
  - Рабочее пространство мастерской (станки, раскройный стол)
  - Лекала на ватмане / экране
  - Образец изделия крупным планом (сумка-скрутка, чехол)
  - Процесс пошива (машина + оператор)
- **CTA**: Оранжевая кнопка «Обсудить проект» → модалка с формой

## 2.6 Форма заявки мастерской

**Поля**:
- Имя (обязательное)
- Телефон (обязательное)
- Email (обязательное)
- Комментарий / описание задачи (необязательное, textarea)

**Отправка**:
1. Данные → PHP-обработчик `submit-form.php`
2. Сохранение в таблицу `orders` (source = 'workshop')
3. Telegram: сообщение владельцу с пометкой «🏭 МАСТЕРСКАЯ»
4. Email: дубль на owner@uzelok64.ru

**Формат Telegram-сообщения**:
```
🏭 МАСТЕРСКАЯ — Новая заявка

👤 Имя: {name}
📞 Телефон: {phone}
📧 Email: {email}
💬 Комментарий: {message}

🕐 {datetime}
🌐 uzelok64.ru
```

---

# ЭТАП 3: НАБОР ПРОМТОВ ДЛЯ CURSOR

---

## Промт №1 — Core & DB (Фундамент)

```
Создай структуру PHP-проекта uzelok64.ru для хостинга Beget.

## Структура папок
Создай следующие директории:
- public_html/ (DocumentRoot)
- public_html/assets/css/
- public_html/assets/js/
- public_html/assets/images/brands/
- public_html/assets/images/products/
- public_html/assets/images/workshop/
- public_html/admin/
- core/
- config/
- database/
- database/migrations/
- templates/layout/
- templates/components/
- templates/sections/
- templates/pages/
- logs/

## Composer
Создай composer.json с PSR-4 автозагрузкой:
- namespace "Uzelok\\Core\\" → директория "core/"
- require-dev: friendsofphp/php-cs-fixer

Запусти composer dump-autoload.

## config/config.php
Создай файл конфигурации (возвращает массив) со следующими секциями:
- app_name, app_url, debug
- paths: root, public, database (database/uzelok.db), logs, templates, images
- ozon: client_id (заглушка 'YOUR_OZON_CLIENT_ID'), api_key (заглушка), base_url, tracked_skus (массив из 16 заглушек-SKU), sku_brand_map
- telegram: bot_token (заглушка), chat_id (заглушка), base_url
- email: to, from, from_name
- admin: username ('admin'), password (заглушка)
- ozon_stores: batya, buy, volna (ссылки на Ozon-магазины)

## core/Brand.php
Создай PHP 8.4 enum Brand: string с кейсами BATYA='batya', BUY='buy', VOLNA='volna'.
Добавь методы: label() (возвращает русское название), subtitle(), color() (hex), icon() (emoji).
Используй match expression.

## core/Database.php
Создай singleton-класс Database с:
- private readonly PDO $pdo (SQLite)
- static getInstance(string $dbPath): self
- getConnection(): PDO
- query(string $sql, array $params = []): PDOStatement
Включи PRAGMA journal_mode=WAL и foreign_keys=ON.
Используй constructor promotion, readonly.

## database/migrations/001_create_products.sql
Создай SQL-скрипт с тремя таблицами:
1. products: id, brand_type (CHECK IN), sku (UNIQUE), offer_id, title, price_ozon, ozon_url, image_local_path, image_ozon_url, description, category, is_active, sort_order, created_at, updated_at
2. orders: id, product_id (FK), customer_name, customer_phone, customer_email, message, source, status, telegram_sent, email_sent, created_at
3. sync_log: id, started_at, finished_at, products_updated, products_added, products_deactivated, status, error_message, trigger_type
Добавь индексы на brand_type, is_active, sku, status, created_at.

## core/helpers.php
Создай файл с функциями:
- sanitize(string $input): string — htmlspecialchars + trim
- formatPrice(int $price): string — форматирование с пробелами и ₽
- generateCsrfToken(): string — генерация CSRF
- validateCsrfToken(string $token): bool — проверка CSRF
- logError(string $message, string $logPath): void — запись в лог

## Инициализация БД
Создай скрипт database/init.php, который:
- Подключает composer autoload
- Загружает config.php
- Создаёт SQLite файл (если не существует)
- Выполняет SQL из 001_create_products.sql
- Выводит 'Database initialized successfully'

Весь код: declare(strict_types=1), PHP 8.4, PSR-12.
```

---

## Промт №2 — Backend Logic (Бизнес-логика)

```
Продолжаем проект uzelok64.ru. Файлы config/config.php, core/Database.php, core/Brand.php уже созданы. Теперь создай бизнес-логику.

## core/Product.php
Создай readonly class ProductDTO с constructor promotion: id, brand_type (Brand enum), title, price_ozon, ozon_url, image_local_path, description, is_active (bool), updated_at.

Создай class Product с constructor(private readonly Database $db).
Методы:
- getAll(?Brand $brand = null, bool $activeOnly = true): array — SELECT из products, если $brand !== null, фильтруй по brand_type. Возвращай массив ассоциативных массивов.
- getById(int $id): ?array — SELECT WHERE id = :id
- upsertFromOzon(array $data): void — INSERT OR REPLACE в products. Поля: sku, offer_id, brand_type, title, price_ozon, ozon_url, image_ozon_url, description, is_active=1, updated_at=CURRENT_TIMESTAMP
- deactivateExcept(array $activeSKUs): void — UPDATE products SET is_active=0 WHERE sku NOT IN (...)
- search(string $query): array — LIKE по title и description

## core/OzonService.php
Создай class OzonService с constructor(private readonly string $clientId, private readonly string $apiKey, private readonly string $baseUrl).
Методы:
- fetchProductData(array $skus): array — HTTP POST к {baseUrl}/v3/product/info/list. Headers: Client-Id, Api-Key, Content-Type: application/json. Body: {"offer_id": $skus}. Парсит JSON-ответ. Возвращает массив товаров. Использует curl. При ошибке — throw RuntimeException.
- downloadImage(string $url, string $savePath): bool — скачивает файл через curl и сохраняет локально. Возвращает true при успехе.
- private request(string $endpoint, array $body): array — общий метод HTTP-запроса к Ozon API.

## core/TelegramService.php
Создай class TelegramService с constructor(private readonly string $botToken, private readonly string $chatId).
Методы:
- sendMessage(string $text, string $parseMode = 'HTML'): bool — POST к https://api.telegram.org/bot{token}/sendMessage. Body JSON: chat_id, text, parse_mode. Через curl. Возвращает true если HTTP 200.
- sendOrderNotification(array $orderData, string $source = 'website'): bool — форматирует красивое сообщение с emoji и вызывает sendMessage. Формат:
  Для source='website': "🛒 ЗАЯВКА С САЙТА\n👤 {name}\n📞 {phone}\n📧 {email}\n📦 Товар: {product_title}\n💰 Цена Ozon: {price}\n💬 {message}\n🕐 {datetime}"
  Для source='workshop': "🏭 МАСТЕРСКАЯ\n👤 {name}\n📞 {phone}\n📧 {email}\n💬 {message}\n🕐 {datetime}"

## core/FormHandler.php
Создай class FormHandler с constructor(private readonly Database $db, private readonly TelegramService $telegram, private readonly array $emailConfig).
Методы:
- processOrder(array $postData): array — валидирует (name, phone, email обязательны), sanitize, сохраняет в таблицу orders, отправляет Telegram, отправляет email (mail()), возвращает ['success' => true/false, 'message' => '...']
- private validateInput(array $data): array — проверка обязательных полей, возвращает массив ошибок
- private sendEmail(array $orderData): bool — mail() с форматированным телом

## core/SyncController.php
Создай class SyncController с constructor(private readonly OzonService $ozon, private readonly Product $product, private readonly Database $db, private readonly string $logPath).
Методы:
- run(string $trigger = 'cron'): array — полный цикл синхронизации:
  1. Записать в sync_log начало (status='running')
  2. Вызвать $ozon->fetchProductData($skus)
  3. Для каждого товара вызвать $product->upsertFromOzon($data)
  4. Вызвать $product->deactivateExcept($activeSKUs)
  5. Обновить sync_log (status='success', counters)
  6. Логировать в файл
  7. При ошибке: status='error', error_message
  Возвращает: ['status' => 'success', 'updated' => N, 'added' => N, 'deactivated' => N]
- private log(string $message): void — file_put_contents($logPath, ..., FILE_APPEND)

## public_html/submit-form.php
Создай обработчик формы:
- Подключи autoload и config
- Проверь метод POST
- Проверь CSRF-токен
- Создай экземпляры Database, TelegramService, FormHandler
- Вызови FormHandler::processOrder($_POST)
- Верни JSON-ответ (header Content-Type: application/json)

## cron-sync.php (в корне проекта)
Создай скрипт для cron:
- Подключи autoload и config
- Создай экземпляры Database, OzonService, Product, SyncController
- Вызови SyncController::run('cron')
- Выведи результат

Весь код: declare(strict_types=1), PHP 8.4 (enums, readonly, match, constructor promotion), PSR-12.
```

---

## Промт №3 — Frontend/UI (Интерфейс)

```
Продолжаем проект uzelok64.ru. Backend готов (core/*.php, config.php, database). Теперь создай фронтенд.

## Общие правила
- Tailwind CSS через CDN: <script src="https://cdn.tailwindcss.com"></script> с кастомной конфигурацией цветов (dark-900: #0a0a0f, accent-orange: #f97316 и т.д.)
- Google Fonts: Inter (300-900), JetBrains Mono (400-600)
- Адаптивная вёрстка: mobile-first
- Тема: Industrial Dark Mode

## templates/layout/header.php
Создай шапку сайта:
- Фиксированная, bg-dark-900/90, backdrop-blur
- Логотип: "УЗЕЛОК64" (оранжевый акцент на 64), подпись "Батя • Буй • Волна"
- Навигация: Главная, Каталог, Мастерская, Контакты
- Мобильное меню (гамбургер, раскрывается вниз)
- Кнопка "Связаться" с иконкой телефона

## templates/layout/footer.php
Создай подвал:
- 4 колонки: О компании, Навигация, Мы на Ozon (ссылки на 3 магазина), Контакты
- Копирайт с текущим годом
- Информация об оплате и доставке

## templates/sections/hero.php
Hero-секция на весь экран:
- Фоновое изображение (hero-bg.jpg) с оверлеем dark-900/80
- Бейдж: "Дешевле, чем на Ozon — напрямую от производителя"
- Заголовок: "УЗЕЛОК" (крупно) + "БАТЯ • БУЙ • ВОЛНА" (градиент оранжевый)
- Подзаголовок: описание экосистемы + "Саратов → вся Россия"
- Две кнопки: "Смотреть каталог" (оранжевая), "Мастерская →" (outline)
- Три фичи: Гарантия качества, Доставка по РФ, Цены ниже Ozon
- Стрелка прокрутки вниз

## templates/sections/brands.php
Три карточки брендов в ряд (md:grid-cols-3):
- Каждая карточка: иконка (emoji), название бренда, подзаголовок, описание, кнопки "Смотреть товары" и "Ozon"
- При наведении — glow эффект цветом бренда
- При клике — переход к каталогу с фильтром по бренду (JS или GET-параметр ?brand=batya)

## templates/sections/catalog.php
Каталог товаров:
- Tabs фильтрации: "Все бренды", "БАТЯ", "БУЙ", "ВОЛНА" — с цветом бренда при активации
- Поле поиска
- Сетка карточек (sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4)
- PHP: $products = (new Product($db))->getAll($brand); foreach ($products as $p): include 'components/product-card.php';

## templates/components/product-card.php
Карточка товара:
- Изображение (или placeholder с emoji бренда)
- Бейдж бренда (цвет бренда)
- Название (line-clamp-2)
- Описание (line-clamp-2)
- Цена: "X XXX ₽" + пометка "на Ozon"
- Кнопка "Хотите дешевле? Заявка" (оранжевая) → открывает модалку
- Кнопка "Купить на Ozon" (outline) → ссылка на ozon_url

## templates/components/order-modal.php
Модальное окно заявки:
- Оверлей (bg-black/70, backdrop-blur)
- Заголовок с названием товара и ценой
- Форма: имя, телефон, email, комментарий
- Кнопка "Отправить заявку" (оранжевая)
- CSRF-токен (hidden input)
- Состояние "Отправлено" (зелёная галочка, текст)
- JS: fetch('/submit-form.php', {method: 'POST', body: formData})

## templates/sections/workshop.php
Блок мастерской (полное описание из Этапа 2):
- Заголовок "От идеи — до эталона"
- 5 карточек услуг + CTA-карточка
- Процесс работы (01-04)
- Преимущества (чеклист)
- Блок "Мастерская в Солнечном" с кнопкой "Обсудить проект"

## templates/sections/contacts.php
Блок контактов:
- Слева: карточки (адрес, телефон, email) + блок оплаты/доставки
- Справа: форма обратной связи (имя, телефон, email, сообщение)
- Форма отправляется через JS fetch на submit-form.php

## public_html/assets/js/app.js
JavaScript:
- Мобильное меню (toggle)
- Фильтрация по брендам (tabs, можно через GET или JS-скрытие)
- Открытие/закрытие модалки заявки
- Отправка формы через fetch (AJAX), показ состояния loading/success/error
- Плавная прокрутка к секциям (smooth scroll)

## public_html/index.php
Главный файл:
- Подключи autoload и config
- Создай Database, Product
- Определи текущий бренд из GET (?brand=batya)
- Загрузи товары: $products = $product->getAll($brand)
- Сгенерируй CSRF-токен
- Подключи шаблоны: header, hero, brands, catalog, workshop, contacts, footer, order-modal

Вёрстка: Tailwind CSS, тёмная тема, оранжевые акценты, адаптивность.
```

---

## Промт №4 — SEO, Security & Deploy (Финализация)

```
Завершаем проект uzelok64.ru. Все файлы созданы: core/, templates/, public_html/index.php. Теперь настрой безопасность, SEO и деплой.

## public_html/.htaccess
Создай .htaccess для Beget:

1. Установи PHP 8.4:
   AddHandler fcgid-script .php
   FCGIWrapper /usr/local/bin/php84-cgi

2. Перенаправление на HTTPS:
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

3. Перенаправление www → без www:
   RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
   RewriteRule ^(.*)$ https://%1/$1 [R=301,L]

4. Фронт-контроллер (все запросы → index.php, кроме реальных файлов):
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]

5. Защита директорий и файлов:
   - Запретить доступ к ../config/, ../database/, ../logs/, ../core/
   - Запретить доступ к .env, .git, composer.json, composer.lock
   - Запретить листинг директорий: Options -Indexes

6. Кэширование статики:
   - Изображения: 30 дней
   - CSS/JS: 7 дней

7. Gzip-сжатие:
   - text/html, text/css, application/javascript, application/json

8. Security headers:
   - X-Content-Type-Options: nosniff
   - X-Frame-Options: DENY
   - X-XSS-Protection: 1; mode=block
   - Referrer-Policy: strict-origin-when-cross-origin

## SEO

### public_html/robots.txt
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /submit-form.php
Sitemap: https://uzelok64.ru/sitemap.xml

### Meta-теги в header.php
Добавь в <head>:
- <meta charset="UTF-8">
- <meta name="viewport" content="width=device-width, initial-scale=1.0">
- <title>УЗЕЛОК64 — БАТЯ • БУЙ • ВОЛНА | Хозтовары, автоаксессуары, снаряжение</title>
- <meta name="description" content="Мультибрендовая экосистема: хозтовары БАТЯ, автоаксессуары БУЙ, снаряжение ВОЛНА. Дешевле, чем на Ozon. Саратов — доставка по РФ.">
- Open Graph: og:title, og:description, og:url, og:type, og:image
- <meta name="theme-color" content="#0a0a0f">
- <link rel="canonical" href="https://uzelok64.ru">
- Структурированные данные JSON-LD (Organization, LocalBusiness)

### Sitemap
Создай скрипт public_html/sitemap.php, который генерирует sitemap.xml:
- Главная страница (priority 1.0)
- Каталог по брендам (?brand=batya, ?brand=buy, ?brand=volna) (priority 0.8)
- Мастерская (priority 0.7)
- Контакты (priority 0.6)
- lastmod = текущая дата

## Админка

### public_html/admin/index.php
Защити HTTP Basic Auth:
- Проверяй username и password из config.php (password через password_verify)
- Показывай: последний sync_log, количество товаров по брендам, количество заявок за сегодня
- Кнопка "Запустить синхронизацию"
- Таблица последних заявок

### public_html/admin/sync.php
При POST-запросе:
- Проверь HTTP Auth
- Создай SyncController
- Вызови run('manual')
- Верни JSON с результатом

## GitHub Actions — .github/workflows/deploy.yml
Создай workflow:

```yaml
name: Deploy to Beget
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install Composer dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Deploy via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_HOST }}
          username: ${{ secrets.FTP_USER }}
          password: ${{ secrets.FTP_PASSWORD }}
          server-dir: /uzelok64.ru/
          exclude: |
            **/.git*
            **/.github*
            **/node_modules/**
            **/.env
            **/README.md
```

Секреты GitHub: FTP_HOST, FTP_USER, FTP_PASSWORD.

## .gitignore
database/uzelok.db
logs/*.log
config/config.php  (хранить config.example.php)
vendor/
.env

## Финальная проверка
- PSR-12: все файлы имеют declare(strict_types=1)
- Все SQL-запросы через prepared statements
- CSRF-токены на всех формах
- Все пользовательские данные проходят через sanitize()
- Нет захардкоженных паролей/токенов (только заглушки в config.example.php)
- .htaccess защищает config/, database/, logs/, core/
- Все внешние ссылки: rel="noopener noreferrer" target="_blank"
```

---

# ПРИЛОЖЕНИЕ: Сводная таблица файлов проекта

| Файл | Назначение | Создаётся в промте |
|------|------------|-------------------|
| config/config.php | Конфигурация | №1 |
| core/Brand.php | Enum брендов | №1 |
| core/Database.php | PDO SQLite singleton | №1 |
| core/helpers.php | Утилиты | №1 |
| database/migrations/001_create_products.sql | SQL-схема | №1 |
| database/init.php | Инициализация БД | №1 |
| composer.json | PSR-4 autoload | №1 |
| core/Product.php | Модель товаров | №2 |
| core/OzonService.php | Ozon API | №2 |
| core/TelegramService.php | Telegram | №2 |
| core/FormHandler.php | Обработка форм | №2 |
| core/SyncController.php | Синхронизация | №2 |
| public_html/submit-form.php | AJAX обработчик | №2 |
| cron-sync.php | Cron скрипт | №2 |
| templates/layout/header.php | Шапка | №3 |
| templates/layout/footer.php | Подвал | №3 |
| templates/sections/hero.php | Hero | №3 |
| templates/sections/brands.php | Бренды | №3 |
| templates/sections/catalog.php | Каталог | №3 |
| templates/sections/workshop.php | Мастерская | №3 |
| templates/sections/contacts.php | Контакты | №3 |
| templates/components/product-card.php | Карточка товара | №3 |
| templates/components/order-modal.php | Модалка заявки | №3 |
| public_html/assets/js/app.js | JS интерактивность | №3 |
| public_html/index.php | Точка входа | №3 |
| public_html/.htaccess | Apache конфиг | №4 |
| public_html/robots.txt | SEO | №4 |
| public_html/sitemap.php | Генерация sitemap | №4 |
| public_html/admin/index.php | Админка | №4 |
| public_html/admin/sync.php | Синхронизация (ручная) | №4 |
| .github/workflows/deploy.yml | CI/CD | №4 |
| .gitignore | Git исключения | №4 |
