# Ozon — первоисточник данных для карточек

**На Ozon у нас 22 активные карточки** (сумма по кабинетам в синке).

**Важно:** в URL витрины `https://www.ozon.ru/product/ЧИСЛО/` должно быть поле **`sku`** из ответа Seller API (`/v3/product/info/list`), а не `id` / `product_id` — иначе Ozon редиректит на поиск или чужую карточку. В SQLite в `products.sku` хранится именно витринный SKU.

Обновить таблицу ниже:

`php8.4 scripts/dump-products-for-ozon-md.php`

После полного синка на проде (`php8.4 cron/sync.php`) идентификаторы строк каталога **`products.id`** могут смениться; актуальные ссылки на сайт — колонка «Ссылка на сайте».

- **ID в каталоге** — `products.sku` (витринный SKU Ozon).
- **инфографика** — файлы `user_content/SKU_<sku>_infografika.*` (число = `products.sku`). После `cron/sync.php` копирование в `public_html/.../user-content` и запись `user_gallery_json` идёт в **строгом режиме** (только совпадение имени с `products.sku`, без карты `infographics.map.json`). Для проверки: `php8.4 scripts/verify-user-content-strict.php`.

### Лишний файл в `user_content`

| Файл | Заметка |
| --- | --- |
| `7260551758.webp` | Не по шаблону `SKU_<sku>_infografika.*` — скрипт синка инфографики его **не привязывает** к карточке. Если это картинка для одного из 22 товаров, переименуйте в `SKU_<витринный_sku>_infografika.webp` (число из колонки «ID в каталоге») или удалите дубликат. |

### Дубликаты offer_id в разных кабинетах

У одного и того же `offer_id` (например `ЯПП-0001`, `МДТ-0001`, `ОСИ-0003`, `ОСИ-0005`, `ОСИ-0006`) в каталоге может быть **несколько строк** с **разными** витринными SKU — это разные кабинеты. Инфографику сопоставляйте по `sku` в БД и витрине.

| НОМЕР | товар на озоне | ID в каталоге | Ссылка на сайте | ID в каталоге на сайте | Ссылка на OZON с САЙТА | инфографика |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | https://www.ozon.ru/product/1665082272/ | 1665082272 | https://uzelok64.ru/?page=product&id=45 | 1665082272 | https://www.ozon.ru/product/1665082272/ | SKU_1665082272_infografika.png |
| 2 | https://www.ozon.ru/product/1821900553/ | 1821900553 | https://uzelok64.ru/?page=product&id=46 | 1821900553 | https://www.ozon.ru/product/1821900553/ | SKU_1821900553_infografika.png |
| 3 | https://www.ozon.ru/product/3352968330/ | 3352968330 | https://uzelok64.ru/?page=product&id=47 | 3352968330 | https://www.ozon.ru/product/3352968330/ | SKU_3352968330_infografika.png |
| 4 | https://www.ozon.ru/product/3353068030/ | 3353068030 | https://uzelok64.ru/?page=product&id=48 | 3353068030 | https://www.ozon.ru/product/3353068030/ | SKU_3353068030_infografika.png |
| 5 | https://www.ozon.ru/product/1980916909/ | 1980916909 | https://uzelok64.ru/?page=product&id=49 | 1980916909 | https://www.ozon.ru/product/1980916909/ | SKU_1980916909_infografika.png |
| 6 | https://www.ozon.ru/product/1714219311/ | 1714219311 | https://uzelok64.ru/?page=product&id=50 | 1714219311 | https://www.ozon.ru/product/1714219311/ | SKU_1714219311_infografika.jpg |
| 7 | https://www.ozon.ru/product/2384564507/ | 2384564507 | https://uzelok64.ru/?page=product&id=51 | 2384564507 | https://www.ozon.ru/product/2384564507/ | SKU_2384564507_infografika.png |
| 8 | https://www.ozon.ru/product/3352903699/ | 3352903699 | https://uzelok64.ru/?page=product&id=52 | 3352903699 | https://www.ozon.ru/product/3352903699/ | SKU_3352903699_infografika.png |
| 9 | https://www.ozon.ru/product/3526089555/ | 3526089555 | https://uzelok64.ru/?page=product&id=53 | 3526089555 | https://www.ozon.ru/product/3526089555/ | SKU_3526089555_infografika.jpg |
| 10 | https://www.ozon.ru/product/1821825795/ | 1821825795 | https://uzelok64.ru/?page=product&id=54 | 1821825795 | https://www.ozon.ru/product/1821825795/ | SKU_1821825795_infografika.jpg |
| 11 | https://www.ozon.ru/product/3347697391/ | 3347697391 | https://uzelok64.ru/?page=product&id=55 | 3347697391 | https://www.ozon.ru/product/3347697391/ | SKU_3347697391_infografika.png |
| 12 | https://www.ozon.ru/product/3352955195/ | 3352955195 | https://uzelok64.ru/?page=product&id=56 | 3352955195 | https://www.ozon.ru/product/3352955195/ | SKU_3352955195_infografika.jpg |
| 13 | https://www.ozon.ru/product/1821875833/ | 1821875833 | https://uzelok64.ru/?page=product&id=57 | 1821875833 | https://www.ozon.ru/product/1821875833/ | SKU_1821875833_infografika.png |
| 14 | https://www.ozon.ru/product/1666958285/ | 1666958285 | https://uzelok64.ru/?page=product&id=58 | 1666958285 | https://www.ozon.ru/product/1666958285/ | SKU_1666958285_infografika.png |
| 15 | https://www.ozon.ru/product/1980935458/ | 1980935458 | https://uzelok64.ru/?page=product&id=59 | 1980935458 | https://www.ozon.ru/product/1980935458/ | SKU_1980935458_infografika.png |
| 16 | https://www.ozon.ru/product/1714942722/ | 1714942722 | https://uzelok64.ru/?page=product&id=60 | 1714942722 | https://www.ozon.ru/product/1714942722/ | SKU_1714942722_infografika.jpg |
| 17 | https://www.ozon.ru/product/1821864874/ | 1821864874 | https://uzelok64.ru/?page=product&id=61 | 1821864874 | https://www.ozon.ru/product/1821864874/ | SKU_1821864874_infografika.jpg |
| 18 | https://www.ozon.ru/product/1893558422/ | 1893558422 | https://uzelok64.ru/?page=product&id=62 | 1893558422 | https://www.ozon.ru/product/1893558422/ | SKU_1893558422_infografika.jpg |
| 19 | https://www.ozon.ru/product/1893584234/ | 1893584234 | https://uzelok64.ru/?page=product&id=63 | 1893584234 | https://www.ozon.ru/product/1893584234/ | SKU_1893584234_infografika.jpg |
| 20 | https://www.ozon.ru/product/1615516645/ | 1615516645 | https://uzelok64.ru/?page=product&id=64 | 1615516645 | https://www.ozon.ru/product/1615516645/ | SKU_1615516645_infografika.png |
| 21 | https://www.ozon.ru/product/3773752744/ | 3773752744 | https://uzelok64.ru/?page=product&id=65 | 3773752744 | https://www.ozon.ru/product/3773752744/ | SKU_3773752744_infografika.png |
| 22 | https://www.ozon.ru/product/1884174749/ | 1884174749 | https://uzelok64.ru/?page=product&id=66 | 1884174749 | https://www.ozon.ru/product/1884174749/ | SKU_1884174749_infografika.jpg |

