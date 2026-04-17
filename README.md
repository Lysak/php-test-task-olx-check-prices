<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# OLX Price Tracker

Сервіс відстеження цін на оголошення OLX.

## Запуск

### 1. Налаштування .env

```bash
cp .env.example .env
```

Заповни порти, які будуть прокидуватись на хост (будь-які вільні порти):

```env
FORWARD_APP_PORT=8080
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
FORWARD_MAILPIT_DASHBOARD_PORT=8025
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_SFTP_PORT=2222
FORWARD_VITE_PORT=5173
```

> Решта налаштувань (DB, Redis, Mail) вже заповнена в `.env.example` для локальної розробки через Docker і не потребує змін.

### 2. Запуск контейнерів

```bash
make up    # піднімає всі сервіси
```

### 3. Ініціалізація додатку

```bash
make app-bash
# всередині контейнера:
php artisan key:generate
php artisan migrate
exit
```

Або коротше через одну команду після `make up`:

```bash
make migrate  # якщо APP_KEY вже згенеровано
```

### Корисні команди

| Команда | Опис |
|---|---|
| `make up` | Запустити контейнери |
| `make down` | Зупинити контейнери |
| `make logs` | Логи всіх сервісів |
| `make migrate` | Запустити міграції |
| `make test` | Запустити тести |
| `make app-bash` | Bash всередині app-контейнера |

Mailpit (перегляд листів): `http://localhost:${FORWARD_MAILPIT_DASHBOARD_PORT}`

---

## Огляд

Сервіс дозволяє підписатись на відстеження ціни оголошення на OLX. Після підтвердження email сервіс щогодини перевіряє ціну і надсилає сповіщення при її зміні. Якщо оголошення зникає — підписники отримують повідомлення, підписки деактивуються.

---

## Архітектура

```
POST /subscribe → SubscriptionService
  ├─ Listing::firstOrCreate → якщо новий: OlxScraperService::fetchPrice
  ├─ Subscription::firstOrCreate (listing_id + email + token)
  └─ якщо нова: queue(VerificationMail)

GET /verify/{token} → verified_at = now() → підписка активна

─────────────────────────────────────────────────────────

CRON щогодини: prices:check
  └─ Listing::active + verified subscriptions
       └─ PriceCheckerService::check($listing)
            │
     OlxScraperService::fetchPrice
            │
      ┌─────┴──────┐
    null          ціна
      │              │
  failures++    current_price === null?
      │           так → зберегти як базову, без події
  >= threshold   ні → ціна змінилась?
      │           так → PriceHistory + event(PriceChanged)
  deactivated_at │   ні → skip
  event(ListingUnavailable)
            │
    ┌───────┴───────────────────────────┐
SendListingUnavailableNotifications  SendPriceChangeNotifications
  chunkById(40) → SendListingUnavailableMail   chunkById(40) → SendPriceChangeMail
  delete activeSubscriptions                   RateLimited('mail')
```

---

## База даних

### `listings`
| Колонка | Тип | Опис |
|---|---|---|
| id | bigint PK | |
| url | string unique | URL оголошення |
| title | string nullable | |
| current_price | decimal(10,2) nullable | null = ще не перевірялась |
| last_checked_at | timestamp nullable | |
| consecutive_failures | int default 0 | |
| deactivated_at | timestamp nullable | |

Scope `active`: `whereNull('deactivated_at')`

### `subscriptions`
| Колонка | Тип | Опис |
|---|---|---|
| id | bigint PK | |
| listing_id | FK | |
| email | string | |
| token | string unique | |
| verified_at | timestamp nullable | |

Унікальний індекс: `(listing_id, email)`. `activeSubscriptions()`: `hasMany→whereNotNull('verified_at')`

### `price_histories`
| Колонка | Тип |
|---|---|
| listing_id | FK |
| price | decimal(10,2) |
| recorded_at | timestamp |

---

## Ключові компоненти

### OlxScraperService
Парсить ціну з `application/ld+json`: GET запит → regex тег → `json_decode` → `data_get($data, 'offers.price')`. При HTTP-помилці → `null`.

### PriceCheckerService
- `null` від скрапера → `consecutive_failures++`; при `>= threshold` (config `listing.failure_threshold`, default 3) → `deactivated_at` + `event(ListingUnavailable)`
- Перша ціна (`current_price === null`) — зберігається як базова без нотифікації
- Зміна ціни → `PriceHistory` + `event(PriceChanged)`

### Jobs: SendPriceChangeMail / SendListingUnavailableMail
`$tries = 0`, `retryUntil(24h)`, `middleware: RateLimited('mail')` → `Limit::perMinute(40)`

---

## Маршрути

```php
Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
Route::get('/verify/{token}', [SubscriptionController::class, 'verify']);
Route::middleware('app.local')->get('/test/subscribe', [TestController::class, 'subscribe']);
```

---

## Конфігурація

**AppServiceProvider:**
```php
// register()
$this->app->bind(PriceScraperInterface::class, OlxScraperService::class);
$this->app->bind(ClientInterface::class, fn() => new Client(['timeout' => 10, 'connect_timeout' => 5]));

// boot()
EventServiceProvider::disableEventDiscovery(); // без цього — подвійна реєстрація listeners
RateLimiter::for('mail', fn() => Limit::perMinute(config('mail.rate_limit_per_minute', 40)));
```

**Events → Listeners:**
```
PriceChanged::class       → SendPriceChangeNotifications
ListingUnavailable::class → SendListingUnavailableNotifications
```

**Docker:** `docker compose up -d`. Сервіси: `app` (PHP-FPM + queue worker), `webserver` (nginx), `mariadb`, `redis`, `mailpit`.

---

## Тести

| Файл | Тип | Що тестується |
|---|---|---|
| `OlxScraperServiceTest` | Unit | парсинг, null без ld+json, null при HTTP-помилці |
| `PriceCheckerServiceTest` | Unit | перша ціна без події, зміна → подія, failures → деактивація |
| `SubscriptionServiceTest` | Feature | subscribe, дублікати, verify |
| `SubscriptionControllerTest` | Feature | валідація POST /subscribe, GET /verify |
| `CheckPricesCommandTest` | Feature | без підписок, з підписками → check викликається |
| `SendPriceChangeNotificationsTest` | Feature | N підписників → N jobs, 0 верифікованих → 0 jobs |
| `SendListingUnavailableNotificationsTest` | Feature | jobs dispatched + підписки видалені |
