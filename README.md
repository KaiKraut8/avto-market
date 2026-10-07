# KAI Garage

Used-car marketplace: listings with photos and documented parts, seller accounts, premium and
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
php artisan migrate --seed       # schema + part categories
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
| `php artisan cars:assign you@example.com 1 2 3` | Gives cars listed before accounts existed to a seller account |
| `php artisan db:seed --class=DemoSeeder` | Sample sellers and cars for a local setup |
| `./vendor/bin/pint` | Code style |

## Where things are

- Prices and the watch window: `config/pricing.php` (purchases are simulated, nothing is charged)
- Company details, opening hours, map link: `config/company.php`
- Countries and languages: `config/countries.php`, `config/locales.php`
- Translations: `lang/{code}.json` (site text, keyed by the English text) and `lang/{code}/*.php` (Laravel's own messages, from laravel-lang)
- Placement rules (premium, pushed, listing order, search): `app/Models/Car.php`
- Who may change a car, and who may run a special deal: `app/Policies/CarPolicy.php`
- Who gets which alert (deals, saved searches, saves, inquiries): `app/Services/Alerts.php`; their text: `app/Support/AlertText.php`

## Premium

Two plans, both simulated purchases:

- **Premium seller**: top placement in gold, special deals (1–50 % off for 3, 7 or 14 days, with an optional
  lower member price for premium buyers), insights per car on the account page, and an alert when someone saves a car.
- **Premium buyer**: member prices on deals, alerts for deals on cars like the ones in their wishlist
  (same first word of the name, or a price within 15 %), and up to 10 saved searches that alert on new matching cars and deals.

Every account is told when a car in its wishlist gets a deal; a wishlist saved before logging in is linked to the account on login.
A deal keeps the price it started from, so changing the car's price ends it. Alerts are sent during the request;
with many accounts they should move to a queue.

## Old site

The original custom-PHP version is in `legacy/` until the switch to Laravel is complete. Its old
addresses (`/edit.php?id=2`, `/list.php`, ...) redirect to the new routes.
