# jeeven/log-viewer

A fully customizable, single-dashboard log viewer for Laravel.

- ✅ Laravel application logs (multiline stack traces grouped correctly)
- ✅ Apache / Nginx access & error logs (combined log format parsed)
- ✅ Any custom application log file (regex-configurable)
- ✅ Database-table-backed logs — e.g. an `api_logs` table with extra columns
- ✅ Auto-discovered folders (`storage/logs/*`) and manually grouped channels
  (database tables, access+error pairs) — one dashboard card, sidebar
  navigation to drill in
- ✅ Live-tail: optional auto-refresh that re-fetches entries in the
  background without a full page reload, for every channel type
- ✅ Auth-gated (Gate-based, per-action: view / download / delete)
- ✅ Memory-safe on huge files (streams from the end of the file, never loads
  the whole thing into RAM) with real, numbered pagination
- ✅ Fully self-hosted UI — a hand-built CSS/JS design system, **zero CDN
  calls, zero build step**. Works out of the box; publish the assets into
  `public/` to self-host permanently with your own cache headers/CDN
- ✅ Fully responsive (mobile → desktop), light/dark mode
- ✅ Supports **PHP 8.1 – 8.4** and **Laravel 9 – 13**

## 1. Install

```bash
composer require jeeven/log-viewer
```

Laravel auto-discovers the service provider. Nothing else required to boot.

## 2. Publish the config (recommended)

```bash
php artisan vendor:publish --tag=log-viewer-config
```

This gives you `config/log-viewer.php` where you define:
- the route prefix/middleware
- the auth Gates
- every log **channel** you want visible in the dashboard
- UI options: the meta footer, the "back to app" link, the GitHub icon, and
  auto-refresh defaults (see below)

## 3. Publish the assets (recommended for production)

The dashboard's CSS/JS work immediately with **no publishing required** — a
built-in route serves them straight from the package. For production you'll
usually want your own cache headers/CDN, so publish them into `public/`:

```bash
php artisan vendor:publish --tag=log-viewer-assets
```

This copies `resources/dist/{css,js}/log-viewer.*` to `public/vendor/log-viewer/`.
Once published, the package automatically prefers the published copy (with a
`?v=<mtime>` cache-busting query string) over its own route — no config change
needed. There is no build step either way: these are plain, hand-written CSS
and JS files, not compiled from Tailwind/Sass/webpack.

## 4. Publish the views (optional — full customization)

```bash
php artisan vendor:publish --tag=log-viewer-views
```

Views land in `resources/views/vendor/log-viewer/*.blade.php` and take
priority over the package's own views automatically. Restyle the layout,
table, filters, anything — plain Blade markup with the `lv-*` CSS classes
from `log-viewer.css` (or swap in your own stylesheet entirely; the markup
has no framework dependency).

> Publish everything at once with `php artisan vendor:publish --tag=log-viewer`.

## 5. Grant access

By default, **only `APP_ENV=local` can access the dashboard**. To allow it in
staging/production, define a Gate in your `AuthServiceProvider` (or any
service provider's `boot()`):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewLogViewer', function ($user) {
    return $user?->is_admin === true;
});

// Optional, per-action — omit to inherit the "view" ability
Gate::define('downloadLogViewer', fn ($user) => $user?->is_admin === true);
Gate::define('deleteLogViewer',   fn ($user) => $user?->role === 'super-admin');
```

Visit `/log-viewer`.

## 6. Add your channels

Edit `config/log-viewer.php` → `channels`. Drivers that ship out of the box:
`directory`, `group`, `laravel`, `apache`, `nginx`, `file`, `database`.

### Laravel logs
```php
'laravel' => [
    'driver'  => 'laravel',
    'label'   => 'Laravel Application Log',
    'path'    => storage_path('logs'),
    'pattern' => 'laravel*.log',
],
```

### Auto-discovered folder of logs (e.g. `storage/logs` with subfolders)

If your `storage/logs` looks like this —

```
storage/logs/laravel.log
storage/logs/minios3-2025-07-11.log
storage/logs/minios3-2025-07-14.log
storage/logs/ccms/*.log
storage/logs/dban-pep/*.log
storage/logs/debugging/*.log
storage/logs/kyc-update/*.log
storage/logs/mobile_banking/*.log
storage/logs/nid/*.log
storage/logs/pumori/*.log
```

— you don't need to hand-write a channel per file or folder. Use the
`directory` driver and it auto-discovers one channel per loose-file group
(rotation-suffix aware: `laravel.log` + `laravel-2026-09-14.log` become one
"Laravel" group) and one channel per subfolder:

```php
'application_logs' => [
    'driver'        => 'directory',
    'label'         => 'Application Logs',
    'path'          => storage_path('logs'),
    'file_driver'   => 'laravel', // driver used for loose files
    'folder_driver' => 'laravel', // driver used for each subfolder
    'exclude'       => ['.gitignore'],
],
```

On the dashboard this renders as **one card** ("Application Logs — 9 logs").
Opening it shows a sidebar listing every discovered file/folder (Laravel,
Minios3, Ccms, Dban Pep, Debugging, Kyc Update, Mobile Banking, Nid,
Pumori, ...) with the first one loaded in the center panel; clicking any
sidebar item swaps the center panel to that log's entries — no extra page,
no JS framework, just a normal link (`?sub=ccms`) so every view is
bookmarkable/shareable. New folders/files dropped into `storage/logs` show
up the next time the page loads, no config change needed.

Prefer a single fixed channel instead? Use the plain `laravel` driver:
```php
'laravel' => [
    'driver'  => 'laravel',
    'label'   => 'Laravel Application Log',
    'path'    => storage_path('logs'),
    'pattern' => 'laravel*.log',
],
```

### Apache / Nginx logs — grouped under one card

Rather than separate top-level channels for access vs. error logs, use the
`group` driver to bundle them — one card on the dashboard, access/error in
the sidebar:

```php
'nginx_logs' => [
    'driver' => 'group',
    'label'  => 'Nginx Logs',
    'channels' => [
        'access' => [
            'driver' => 'nginx',
            'label'  => 'Access Log',
            'allow_clear' => true,
            'path'   => '/var/log/nginx/access.log',
            'format' => 'combined', // combined | error
        ],
        'error' => [
            'driver' => 'nginx',
            'label'  => 'Error Log',
            'allow_clear' => false,
            'path'   => '/var/log/nginx/error.log',
            'format' => 'error',
        ],
    ],
],
```
Same pattern for `apache_logs` with `'driver' => 'apache'` sub-channels.
Prefer them as separate top-level cards instead? Just move each inner array
up to be its own top-level channel with `'driver' => 'nginx'` directly.

### Any other application log file
```php
'queue_worker' => [
    'driver'  => 'file',
    'label'   => 'Queue Worker Log',
    'path'    => storage_path('logs/worker.log'),
    'pattern' => '/^\[(?<date>.*?)\]\s+(?<level>\w+):\s+(?<message>.*)$/',
],
```

### Database tables — grouped under one card

Same `group` pattern for database-table logs: one "Database Logs" card,
every table in the sidebar. Works with **any** table shape — just map your
own column names. Two real examples:

**`api_logs`** — `id, title, request, response, url, status_code, user_id, created_at, updated_at, model_id, model_type`

**`third_parties_logs`** — `id, api, api_type, loggable_id, loggable_type, request, response, status_code, created_at, updated_at`

```php
'database_logs' => [
    'driver' => 'group',
    'label'  => 'Database Logs',
    'channels' => [

        'api_logs' => [
            'driver'     => 'database',
            'label'      => 'API Logs',
            'table'      => 'api_logs',
            'allow_clear' => true, // enable clear button on ui
            'columns'    => [
                'id'         => 'id',
                'level'      => 'status_code',   // an HTTP status code
                'message'    => 'url',
                'created_at' => 'created_at',
                'extra'      => [
                    'Title'       => 'title',
                    'User ID'     => 'user_id',
                    'Model'       => 'model_type',
                    'Model ID'    => 'model_id',
                    'Status Code' => 'status_code',
                    'Request'     => 'request',
                    'Response'    => 'response',
                ],
            ],
            // Bucket the numeric HTTP status into error/warning/notice/success
            // for badge coloring. Set to null to use the raw column value as-is.
            'level_type' => 'http_status',
            'searchable' => ['url', 'title', 'model_type'],
        ],

        'third_parties_logs' => [
            'driver'     => 'database',
            'label'      => 'Third Party Logs',
            'table'      => 'third_parties_logs',
            'allow_clear' => false,
            'columns'    => [
                'id'         => 'id',
                'level'      => 'status_code',   // e.g. "sent" / "failed" / "pending"
                'message'    => 'api',
                'created_at' => 'created_at',
                'extra'      => [
                    'Type'          => 'api_type',
                    'Loggable ID'   => 'loggable_id',
                    'Loggable Type' => 'loggable_type',
                    'Status'        => 'status_code',
                    'Request'       => 'request',
                    'Response'      => 'response',
                ],
            ],
            // status_code is already a human word here, so no http_status
            // mapping — the UI still auto-colors it (green/red/amber) by keyword.
            'level_type' => null,
            'searchable' => ['api', 'loggable_type'],
        ],

    ],
],
```

`level_type` controls how the raw "level" column becomes a badge color:
- `'http_status'` → buckets numeric codes into error (5xx) / warning (4xx) / notice (3xx) / success (2xx/1xx)
- `null` (default) → looks for keywords like `fail`, `error`, `pending`, `sent`, `success`, `ok` in the raw value; anything else still displays as its own label with a neutral badge

The raw value is always shown as the badge text; only the color is bucketed.

### `directory` vs `group` — which to use

Both render identically in the UI (one dashboard card → sidebar → entries):

| | `directory` | `group` |
|---|---|---|
| Sub-channels come from | scanning a folder on disk | a `channels` array you write |
| Use when | files/folders change over time and you want them auto-discovered | the set is fixed and known ahead of time (tables, a server's access+error pair) |
| Example | `application_logs` (storage/logs/*) | `database_logs`, `nginx_logs`, `apache_logs` |

## 7. Register a custom driver (optional)

```php
use Jeeven\LogViewer\LogViewerManager;

app(LogViewerManager::class)->extend('redis', \App\Logs\RedisLogReader::class);
```
Your class must implement `Jeeven\LogViewer\Contracts\LogReader`.

## Auto-refresh (live-tail)

Any log view — file-based, database-based, or a sidebar sub-channel inside a
`directory`/`group` channel — can auto-refresh in the background. It's
client-side: the page periodically re-fetches its own URL and swaps in the
fresh entries + pagination, leaving the filter form untouched (so typing in
the search box is never interrupted mid-refresh).

Configure the default in `config/log-viewer.php` → `ui.auto_refresh`:

```php
'auto_refresh' => [
    // Default state when a page first loads (before any user choice
    // is remembered).
    'enabled' => env('LOG_VIEWER_AUTO_REFRESH', false),
    // How often to refresh, in milliseconds.
    'interval' => (int) env('LOG_VIEWER_AUTO_REFRESH_INTERVAL', 10000),
    // Show the on/off toggle so users can override the default above.
    // Set to false to force "enabled" with no UI control.
    'user_toggle' => env('LOG_VIEWER_AUTO_REFRESH_TOGGLE', true),
],
```

Or via `.env`:

```
LOG_VIEWER_AUTO_REFRESH=true
LOG_VIEWER_AUTO_REFRESH_INTERVAL=15000
LOG_VIEWER_AUTO_REFRESH_TOGGLE=true
```

Behavior:
- **Per-log memory** — turning it on/off is remembered per channel (and
  per sub-channel) in `localStorage`, not globally, so enabling it on
  `application_logs → ccms` doesn't turn it on for `database_logs → api_logs`.
- **Pauses on a hidden tab** — no requests fire while the browser tab isn't
  visible; it catches up immediately when you switch back.
- **No extra server code needed for custom drivers** — it works by
  re-requesting the same URL and swapping the entries panel client-side, so
  a custom driver registered via `LogViewerManager::extend()` gets it for
  free.
- **Interacts with pagination caching** — file-based channels cache their
  total entry count for 20 seconds (see below), so at the default 10s
  interval you'll typically get one fresh count roughly every other tick,
  not a full rescan on every refresh.
- Disable the toggle UI entirely (e.g. to force it always-on for an ops
  dashboard) by setting `'user_toggle' => false` — the page will still
  auto-refresh at the configured interval, it just won't show a button to
  turn it off.

## Pagination

File-based channels (`laravel`, `directory`, `file`, `apache`, `nginx`) show
real, numbered pagination with an accurate total entry count, not just
Previous/Next. Counting matches in a multi-hundred-MB log file requires a
full scan, so the total is cached per channel+filter combination for 20
seconds via Laravel's default cache store — paging through results doesn't
re-scan the file on every click. If your app has no cache store configured
at all, it transparently falls back to a live (uncached) count, so nothing
breaks — it'll just be a little slower on very large files.

Database channels (`api_logs`, `third_parties_logs`, ...) already used a
real `COUNT(*)` query, so their pagination was always accurate — they render
through the same numbered pagination component as file channels.

## Troubleshooting

**`Call to undefined function log_viewer_asset()`**
This is a stale Composer autoloader, most common right after adding the
package or when installing it via a local `path` repository. Fix:

```bash
composer dump-autoload
```

If you're developing the package locally via a path repository, make sure
your app's root `composer.json` has something like:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/log-viewer" }
    ],
    "require": {
        "jeeven/log-viewer": "@dev"
    }
}
```

then run `composer update jeeven/log-viewer`. The package also loads its
helper file directly from the service provider as a fallback, so this should
now self-heal even without `dump-autoload` — just make sure you're on the
latest version of the package files.

## Version support

| PHP     | Laravel    |
|---------|------------|
| 8.1–8.4 | 9, 10, 11, 12, 13 |

## License

MIT