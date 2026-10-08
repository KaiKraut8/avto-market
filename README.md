# Vozi

Used-car marketplace: listings with photos, seller accounts, premium and
"push forward" placements, wishlists, live viewer counts and contact-seller. Laravel 13, Blade,
MySQL 8.4, vanilla JS bundled with Vite. The site is available in English, Slovenian, Croatian,
German, Dutch, French, Spanish and Portuguese.

## Running it

The dev container starts PHP-FPM, nginx (http://localhost:8000) and MySQL. On first creation it
installs dependencies, builds the assets and migrates the database (see `.devcontainer/`).

By hand:

```bash
composer install
cp .env.example .env && php artisan key:generate
npm ci && npm run build          # or `npm run dev` while working on CSS/JS
php artisan migrate --seed       # schema (car parts are no longer shown; their tables are kept for the old data)
php artisan storage:link         # serves car photos from storage/app/public
```

Set `XDEBUG_MODE=off` in front of composer/artisan commands to make them much faster.
The web server runs with Xdebug off, because it roughly doubles page times. To step-debug,
rebuild the container with `XDEBUG_MODE=debug` set, then start a request with the Xdebug
browser extension or `?XDEBUG_TRIGGER=1`.

The database settings are `KAI_DB_*` in `.env`, not `DB_*`: the dev container exports `DB_*`
for the old site, and real environment variables would override `.env`.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Pest tests, on the separate `kai_test` database (refuses to run anywhere else) |
| `php artisan legacy:import --fresh` | Copies cars, photos, parts, views, wishlists and inquiries from the old site's database (`app`) |
| `php artisan users:admin you@example.com` | Makes that account the only admin (`--create` makes the account too) |
| `php artisan cars:assign you@example.com 1 2 3` | Gives cars listed before accounts existed to a seller account |
| `php artisan db:seed --class=DemoSeeder` | Sample sellers and cars for a local setup |
| `./vendor/bin/pint` | Code style |

## Where things are

- Prices and the watch window: `config/pricing.php`
- Company details, opening hours, map link, and the "last updated" date of the privacy and cookie policies: `config/company.php`
- Privacy and cookie policies: `resources/views/pages/privacy.blade.php`, `cookies.blade.php`. Update them when the site starts collecting new data or setting new cookies
- Site icon and link-preview image: `resources/brand/icon.svg` is the source; the PNGs, `favicon.ico` and `og-image.png` in `public/` are made from it
- Countries and languages: `config/countries.php`, `config/locales.php`
- Translations: `lang/{code}.json` (site text, keyed by the English text) and `lang/{code}/*.php` (Laravel's own messages, from laravel-lang)
- Placement rules (premium, pushed, listing order, text search): `app/Models/Car.php`; the filters on All cars (make, year, price, country, deals only) and the sort orders: `app/Support/CarSearch.php`
- Home page motion (the car that drives as you scroll, the card you can turn around): `resources/js/home.js`, backdrop in `resources/views/components/hero-scene.blade.php`
- Who may change a car, and who may run a special deal: `app/Policies/CarPolicy.php`
- Who gets which alert (deals, saved searches, saves, inquiries): `app/Services/Alerts.php`; their text: `app/Support/AlertText.php`

## Premium

Two plans, paid monthly, every 3 months (14 % off, shown as the offer) or yearly (39 % off); see Payments:

- **Premium seller**: top placement in gold, special deals (1–50 % off for 3, 7 or 14 days, with an optional
  lower member price for premium buyers), insights per car on the account page, and an alert when someone saves a car.
- **Premium buyer**: member prices on deals, alerts for deals on cars like the ones in their wishlist
  (same first word of the name, or a price within 15 %), and up to 10 saved searches that alert on new matching cars and deals.

Every account is told when a car in its wishlist gets a deal; a wishlist saved before logging in is linked to the account on login.
A deal keeps the price it started from, so changing the car's price ends it. Alerts are sent during the request;
with many accounts they should move to a queue.

## Selling: 5 % commission

Every car is sold through the site, and the marketplace keeps `commission_rate` (5 %, `config/pricing.php`) of the price.
The rules are on `/how-buying-works`, linked from every car, the checkout, the new-car form (sellers must accept them) and the footer.

- **Buy this car**: the buyer pays 5 % of the price online (it goes to the admin's Mollie balance). That reserves the car
  and gives buyer and seller each other's details. The buyer pays the seller the other 95 % at the handover, so the
  buyer pays the listed price and the seller receives 95 % of it.
- The seller then **confirms the sale** (the car is marked sold and leaves the lists) or **cancels** it (the buyer's 5 % is
  refunded through Mollie and the car is for sale again). If two buyers pay at the same moment, the second is refunded automatically.
- **Sold elsewhere**: the seller marks the car sold, enters the price and pays the 5 % themselves; until it is paid they
  can't list new cars.
- The admin page shows commissions and open sales, and can cancel a reservation that waits too long (`reservation_days`).
- paysafecard is only offered up to 1.000 € per payment (`payments.max_amount`).

## Payments

Premium (both plans) and push forward are paid through [Mollie](https://www.mollie.com): cards, PayPal and paysafecard.

- **Going live**: create a Mollie account, enable the three methods in its dashboard (PayPal is linked there),
  and put the API key in `.env` as `MOLLIE_KEY` (`test_...` to try it with Mollie's test payments, `live_...` for real money).
  Without a key the site uses local test payments (a page where you choose whether the payment succeeds);
  a site with `APP_ENV=production` refuses to run without a key.
- **Renewals**: subscriptions renew until cancelled. Cards and PayPal are charged automatically a day before the period ends
  (retried daily, up to 3 times); paysafecard is prepaid and can't be charged again, so those customers get a reminder
  3 days before the end and pay the next period themselves. Cancelling keeps premium until the paid period ends.
- **The scheduler must run** for renewals: `* * * * * php /path/to/artisan schedule:run` in cron
  (locally: `php artisan schedule:work`, or once by hand: `php artisan subscriptions:renew`).
- **Webhook**: Mollie reports payments to `/webhooks/mollie`, which needs the site on a public address (`APP_URL`).
  On a local machine the return page checks the payment instead.
- Settings: `config/payments.php`; logic: `app/Services/Billing.php`; providers: `app/Services/Payments/`.

## Admin

One account is the admin (`php artisan users:admin`); there is no way to become admin from the website, and everyone
who signs up is a regular account. The admin may edit and delete any car, and has an **Admin** page with earnings
(from the payments table), the **payment account** (the Mollie balance, the bank account it pays out to and the payout
schedule) and **Withdraw to bank**, which asks for the password again and requests a payout through Mollie's Payouts API.

The balance and withdrawals need `MOLLIE_ACCESS_TOKEN` in `.env`: an access token from the Mollie dashboard
(Developers → Access tokens) with the scopes `balances.read`, `payouts.read` and `payouts.write`.
The bank account is set and verified in the Mollie dashboard only; the website can't change where money goes.
A manual withdrawal switches Mollie's automatic payouts off until they are switched back on in the dashboard.

## Chat assistant

A chat button sits in the corner of every page (a welcome bubble first, folding into a draggable circle after 5 seconds).
Who answers is set in `config/assistant.php` (`ASSISTANT_DRIVER`, default `auto`):

- **Ollama** (`OLLAMA_URL`, e.g. `http://192.168.0.180:11434`, model `OLLAMA_MODEL`, default `qwen3:30b-a3b`): a local model
  that searches the cars itself with tool calls (the same tools as the MCP server below). The answer must arrive within
  `OLLAMA_TIMEOUT` seconds (120); the answer is streamed into the chat as the model writes it, otherwise the built-in answer is shown.
- **Claude** (`ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`): told about the site's rules, prices and the cars for sale.
- **Built-in**: no model; answers the common questions and finds cars by make, model, year and price in any language.

Any model that fails or is too slow falls back to the built-in answers. The widget: `resources/js/assistant.js`.

## Car search for AI agents (MCP)

The cars for sale are searchable over the [Model Context Protocol](https://modelcontextprotocol.io) (read-only, public data),
with three tools: `search-cars` (text, make, year and price range, country, deals only, sort, limit), `get-car` (full details
by id) and `list-car-filters` (which makes, countries, years and prices exist). They search exactly like the filters on All cars.

- Over HTTP: `POST /mcp` (routes/ai.php, 60 requests a minute). For example, in Claude Code:
  `claude mcp add --transport http kai-cars https://your-site/mcp`
- Locally over stdio: `php artisan mcp:start kai-cars`; try it with `php artisan mcp:inspector kai-cars`.
- Code: `app/Mcp/` (server and tools) and `app/Services/CarCatalog.php` (the data, shared with the chat assistant).

## Old site

The original custom-PHP version is in `legacy/` until the switch to Laravel is complete. Its old
addresses (`/edit.php?id=2`, `/list.php`, ...) redirect to the new routes.
