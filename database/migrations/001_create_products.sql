CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand_type TEXT NOT NULL CHECK (brand_type IN ('batya', 'buy', 'volna')),
    sku TEXT UNIQUE,
    offer_id TEXT,
    title TEXT NOT NULL,
    price_ozon INTEGER NOT NULL DEFAULT 0,
    ozon_url TEXT NOT NULL DEFAULT '',
    image_local_path TEXT DEFAULT '',
    image_ozon_url TEXT DEFAULT '',
    description TEXT DEFAULT '',
    category TEXT DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_products_brand ON products (brand_type);

CREATE INDEX IF NOT EXISTS idx_products_active ON products (is_active);

CREATE INDEX IF NOT EXISTS idx_products_sku ON products (sku);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER,
    customer_name TEXT NOT NULL,
    customer_phone TEXT NOT NULL,
    customer_email TEXT NOT NULL DEFAULT '',
    message TEXT DEFAULT '',
    source TEXT DEFAULT 'website',
    status TEXT DEFAULT 'new',
    telegram_sent INTEGER DEFAULT 0,
    email_sent INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id)
);

CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status);

CREATE INDEX IF NOT EXISTS idx_orders_created ON orders (created_at);

CREATE TABLE IF NOT EXISTS sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME,
    products_updated INTEGER DEFAULT 0,
    products_added INTEGER DEFAULT 0,
    products_deactivated INTEGER DEFAULT 0,
    status TEXT DEFAULT 'running',
    error_message TEXT DEFAULT '',
    trigger_type TEXT DEFAULT 'cron'
);
