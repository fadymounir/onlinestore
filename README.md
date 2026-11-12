# Simple

An Arabic, RTL storefront with a Filament admin panel, built on Laravel.

- **Catalogue** — a nested-set category tree with a mega menu, products with
  variants, attributes and photos. The demo data is a scrape of a real shop;
  see [`database/data/README.md`](database/data/README.md).
- **Shop** — cart for guests and customers, coupons, cash on delivery,
  per-governorate shipping, wishlists and reviews.
- **Admin** — Filament panel at `/admin` with roles and permissions.

## Running it

```sh
docker compose up -d          # joins the shared global_network services
php artisan migrate:fresh --seed
```

The storefront is then on http://localhost:9000 and the panel on
http://localhost:9000/admin.

## Installing it as an app

The storefront is a PWA: it installs to a phone's home screen, opens in its own
window with no browser chrome, and shows an offline page instead of the
browser's error page when the network drops.

- **Android / Chrome / Edge** — an "تثبيت التطبيق" card appears when the
  browser says the site is installable, and its button opens the browser's own
  install prompt. The same card is offered on desktop Chromium.
- **iPhone / iPad** — Safari has no install API, so after a few seconds on the
  site a card explains the manual route: **Share → Add to Home Screen**. Do it
  from Safari itself; Chrome and Firefox on iOS add a bookmark, not an app.

Both cards can be dismissed, and stay away for a month. Any button anywhere on
the storefront can open the install prompt by carrying `data-pwa-install`.

```sh
npm run build          # the worker precaches the built assets, so build first
php artisan pwa:manifest   # rewrites public/manifest.json from the settings
npm run logo               # regenerates every icon from resources/images
```

A service worker only runs on a secure origin, so installing works over
`https://` or on `localhost` — never over plain HTTP on a LAN address.

| File | What it is |
| --- | --- |
| `public/manifest.json` | name, icons, colours and display mode. Rewritten whenever an admin saves the settings, so the installed app is named after the shop. |
| `public/sw.js` | the service worker. Caches only the build output, the icons and the offline page — never a page, a session or anything under `/storage`. Bump its `VERSION` when its strategies change. |
| `resources/js/pwa.js` | registers the worker, runs the install cards, and offers the update. |
| `resources/views/shop/offline.blade.php` | the offline page, at `/offline`. |

When a new worker is deployed, visitors are shown a "يتوفر إصدار جديد" card
rather than being swapped over mid-session; pressing **تحديث الآن** activates
the new version and reloads.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
