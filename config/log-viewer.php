<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix'     => env('LOG_VIEWER_PATH', 'log-viewer'),
        'domain'     => env('LOG_VIEWER_DOMAIN', null),
        // Base middleware stack. The package's own "log-viewer.auth" middleware
        // is appended automatically — do not add it here.
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    | - "guard"  : the auth guard checked before anything else.
    | - "gate"   : a Gate ability name. Define it in your AuthServiceProvider:
    |
    |     Gate::define('viewLogViewer', fn ($user) => $user->is_admin);
    |
    |   If the gate is never defined, the package falls back to allowing
    |   access only when the app environment is "local".
    | - "downloads" / "deletes" gates work the same way, per-action.
    */
    'authorization' => [
        'enabled' => env('LOG_VIEWER_AUTH_ENABLED', true),
        'guard'   => env('LOG_VIEWER_GUARD', 'web'),
        'gates'   => [
            'view'     => 'viewLogViewer',
            'download' => 'downloadLogViewer',
            'delete'   => 'deleteLogViewer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'title'          => env('LOG_VIEWER_TITLE', 'Log Viewer'),
        'per_page'       => 50,
        'date_format'    => 'Y-m-d H:i:s',
        'default_theme'  => 'system',
        // "Back to <app>" link shown on the dashboard. Set to null to hide.
        'back_to_app_url' => null, // defaults to url('/') when null
        'auto_refresh' => [
            // Default state when a page first loads (before any user choice
            // is remembered). Safe to leave off by default.
            'enabled' => env('LOG_VIEWER_AUTO_REFRESH', false),
            // How often to refresh, in milliseconds.
            'interval' => (int) env('LOG_VIEWER_AUTO_REFRESH_INTERVAL', 10000),
            // Show the on/off toggle so users can override the default
            // above. Set to false to force "enabled" with no UI control.
            'user_toggle' => env('LOG_VIEWER_AUTO_REFRESH_TOGGLE', true),
        ],
        // Any of these can be overridden by publishing the views.
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    | Each channel is read by a "driver". Built-in drivers:
    |
    |   - directory : GROUP channel, auto-discovered from a folder — every
    |                  loose *.log file (grouped by name, rotation-suffix
    |                  aware) and every subfolder becomes its own
    |                  sub-channel. Ideal for storage/logs when it looks like:
    |                    storage/logs/laravel.log
    |                    storage/logs/minios3-2025-07-11.log
    |                    storage/logs/ccms/*.log
    |                    storage/logs/nid/*.log
    |
    |   - group     : GROUP channel, manually listed sub-channels under a
    |                  "channels" key. Use this when the sub-channels can't
    |                  be discovered from a folder — e.g. bundling several
    |                  database-table logs together, or an access+error log
    |                  pair for one web server.
    |
    |   - laravel   : a single Laravel-format log file/glob (multiline aware)
    |   - apache    : an Apache access/error log file
    |   - nginx     : an Nginx access/error log file
    |   - file      : any plain-text application log file (regex configurable)
    |   - database  : a database table (e.g. api_logs) treated as a log source
    |
    | Both "directory" and "group" render the same way in the UI: ONE card
    | on the dashboard, and opening it shows a sidebar of every sub-channel
    | with the log entries in the center panel — no separate page needed.
    |
    | Add as many channels as you like. The "key" (array key) becomes the
    | channel's slug in the UI and URLs.
    */
    'channels' => [

        // Auto-discovers every log file/folder under storage/logs, e.g.
        // laravel.log, minios3-*.log (grouped), ccms/, dban-pep/, debugging/,
        // kyc-update/, mobile_banking/, nid/, pumori/ — shown as ONE card on
        // the dashboard, with a sidebar listing each one inside.
        'application_logs' => [
            'driver'  => 'directory',
            'label'   => 'Application Logs',
            'path'    => storage_path('logs'),
            // Driver used for loose files vs. subfolder files. "laravel" is
            // multiline/stack-trace aware and safely falls back to raw
            // lines for files that aren't in Laravel's log format.
            'file_driver'   => 'laravel',
            'folder_driver' => 'laravel',
            // File names to ignore when scanning for loose *.log files.
            'exclude' => ['.gitignore'],
        ],

        // One card for "Nginx Logs" — access + error live in the sidebar
        // instead of being two separate top-level channels.
        'nginx_logs' => [
            'driver' => 'group',
            'label'  => 'Nginx Logs',
            'channels' => [
                'access' => [
                    'driver' => 'nginx',
                    'label'  => 'Access Log',
                    'allow_clear' => true,
                    'path'   => env('NGINX_ACCESS_LOG', '/var/log/nginx/access.log'),
                    'format' => 'combined', // combined | common
                ],
                'error' => [
                    'driver' => 'nginx',
                    'label'  => 'Error Log',
                    'allow_clear' => false,
                    'path'   => env('NGINX_ERROR_LOG', '/var/log/nginx/error.log'),
                    'format' => 'error',
                ],
            ],
        ],

        // Same pattern for Apache.
        'apache_logs' => [
            'driver' => 'group',
            'label'  => 'Apache Logs',
            'channels' => [
                'access' => [
                    'driver' => 'apache',
                    'label'  => 'Access Log',
                    'allow_clear' => true,
                    'path'   => env('APACHE_ACCESS_LOG', '/var/log/apache2/access.log'),
                    'format' => 'combined',
                ],
                'error' => [
                    'driver' => 'apache',
                    'label'  => 'Error Log',
                    'allow_clear' => false,
                    'path'   => env('APACHE_ERROR_LOG', '/var/log/apache2/error.log'),
                    'format' => 'error',
                ],
            ],
        ],

        'queue_worker' => [
            'driver'  => 'file',
            'label'   => 'Queue Worker Log',
            'path'    => storage_path('logs/worker.log'),
            // optional: custom line pattern. Leave null to show raw lines.
            'pattern' => '/^\[(?<date>.*?)\]\s+(?<level>\w+):\s+(?<message>.*)$/',
        ],

        // One card for "Database Logs" — every database-table log lives in
        // its sidebar instead of being its own top-level dashboard card.
        'database_logs' => [
            'driver' => 'group',
            'label'  => 'Database Logs',
            'channels' => [

                // id | title | request | response | url | status_code | user_id | created_at | updated_at | model_id | model_type
                'api_logs' => [
                    'driver'     => 'database',
                    'label'      => 'API Logs',
                    'connection' => null,     // null = default connection
                    'table'      => 'api_logs',
                    'allow_clear' => true,
                    'columns'    => [
                        'id'         => 'id',
                        'level'      => 'status_code',   // column used to color-code / filter entries
                        'message'    => 'url',            // primary line shown in the list
                        'created_at' => 'created_at',
                        // extra columns shown when a row is expanded (label => column)
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
                    // "level" holds an HTTP status code here, so let the
                    // reader bucket it into error/warning/notice/success.
                    // Options: null (use the raw value as-is) | 'http_status'
                    'level_type' => 'http_status',
                    'searchable' => ['url', 'title', 'model_type'],
                    'per_page'   => 50,
                ],

                // id | api | api_type | loggable_id | loggable_type | request | response | status_code | created_at | updated_at
                'third_parties_logs' => [
                    'driver'     => 'database',
                    'label'      => 'Third Party Logs',
                    'connection' => null,
                    'table'      => 'third_parties_logs',
                    'allow_clear' => false,
                    'columns'    => [
                        'id'         => 'id',
                        'level'      => 'status_code',   // e.g. "sent" / "failed" / "pending"
                        'message'    => 'api',            // primary line shown in the list
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
                    // "level_type" left as null: status_code here is already
                    // a human word (sent/failed/pending) so it's used as-is;
                    // the UI still auto-colors it (green/red/amber) by keyword.
                    'level_type' => null,
                    'searchable' => ['api', 'loggable_type'],
                    'per_page'   => 50,
                ],

            ],
        ],

    ],

];
