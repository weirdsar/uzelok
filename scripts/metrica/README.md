# Яндекс.Метрика API — uzelok64.ru (108717789)

OAuth и настройка целей/фильтров через Management API. Логика скопирована из проекта **analitika/metrica-yandex** (sii5_counter_setup, auth_setup).

## Учётные данные

1. Скопируйте `analitika/metrica-yandex/.env` → сюда как **`scripts/metrica/.env`** (или создайте из `.env.example`).
2. В `.env` выставьте **`YANDEX_COUNTER_ID=108717789`**.
3. Для создания целей нужен токен со scope **`metrika:read metrika:write`** (`YANDEX_OAUTH_SCOPE` в `.env`).

Получение токена:

```bash
cd scripts/metrica
pip install -r requirements.txt
python3 auth_setup.py url
# открыть URL, разрешить доступ, затем:
python3 auth_setup.py token <код_из_браузера>
```

## Настройка счётчика и целей

```bash
cd scripts/metrica
python3 uzelok64_counter_setup.py           # план
python3 uzelok64_counter_setup.py --apply   # запись в Метрику
```

Создаются: часовой пояс Europe/Saratov, избранное, фильтр роботов, автоцели, дедупликация ecom (если доступна), **URL-цели** (`page=contacts`, `workshop`, `product`, `home`), **JS-цели** `order_sent`, `ozon_outbound_click` (дубли не создаются).

На сайте в `app.js` вызывается `ym(..., 'reachGoal', …)` при успешной отправке заявки и клике по кнопке Ozon — ID счётчика передаётся из `layout.php` в `window.__UZEL_YM_ID__`.

Кабинет: [metrica.yandex.com — счётчик 108717789](https://metrica.yandex.com/overview?id=108717789).
