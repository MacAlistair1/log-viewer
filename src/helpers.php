<?php

if (!function_exists('log_viewer_asset')) {
    /**
     * Resolve a log-viewer asset URL. If the host app has published the
     * assets (php artisan vendor:publish --tag=log-viewer-assets), the
     * public copy is served directly (own cache headers / CDN friendly).
     * Otherwise it falls back to the package's built-in asset route, so
     * the UI works correctly with zero setup.
     */
    function log_viewer_asset(string $path): string
    {
        $published = public_path('vendor/log-viewer/' . $path);

        if (is_file($published)) {
            return asset('vendor/log-viewer/' . $path) . '?v=' . filemtime($published);
        }

        return route('log-viewer.assets', ['path' => $path]);
    }
}
