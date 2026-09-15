<?php

namespace Jeeven\LogViewer;

use Illuminate\Support\ServiceProvider;
use Jeeven\LogViewer\Http\Middleware\Authorize;

class LogViewerServiceProvider extends ServiceProvider
{
    public const VERSION = '1.0.0';

    public function register(): void
    {
        // Belt-and-braces: normally Composer's "files" autoload entry loads
        // this, but if the app's autoloader cache is stale (common with
        // local "path" repositories, or right after editing composer.json
        // without a `composer dump-autoload`), require it directly so
        // log_viewer_asset() is never "undefined function".
        if (!function_exists('log_viewer_asset')) {
            require_once __DIR__ . '/helpers.php';
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/log-viewer.php', 'log-viewer');

        $this->app->singleton(LogViewerManager::class, fn () => new LogViewerManager());
        $this->app->alias(LogViewerManager::class, 'log-viewer');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'log-viewer');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->app['router']->aliasMiddleware('log-viewer.auth', Authorize::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/log-viewer.php' => config_path('log-viewer.php'),
            ], 'log-viewer-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/log-viewer'),
            ], 'log-viewer-views');

            $this->publishes([
                __DIR__ . '/../resources/dist' => public_path('vendor/log-viewer'),
            ], 'log-viewer-assets');

            $this->publishes([
                __DIR__ . '/../config/log-viewer.php' => config_path('log-viewer.php'),
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/log-viewer'),
                __DIR__ . '/../resources/dist' => public_path('vendor/log-viewer'),
            ], 'log-viewer');
        }
    }
}
