<?php

use Illuminate\Support\Facades\Route;
use Jeeven\LogViewer\Http\Controllers\AssetController;
use Jeeven\LogViewer\Http\Controllers\LogViewerController;

Route::group([
    'prefix' => config('log-viewer.route.prefix', 'log-viewer'),
    'domain' => config('log-viewer.route.domain'),
    'as'     => 'log-viewer.',
], function () {

    // Static assets (CSS/JS) — served directly by the package so the UI
    // works with zero setup. No auth needed: it's presentation only,
    // never log data. Publish the "log-viewer-assets" tag to self-host
    // these in /public instead, with your own cache headers/CDN.
    Route::get('/assets/{path}', [AssetController::class, 'serve'])
        ->where('path', '.*')
        ->name('assets');

    Route::group([
        'middleware' => array_merge(config('log-viewer.route.middleware', ['web']), ['log-viewer.auth']),
    ], function () {
        Route::get('/', [LogViewerController::class, 'index'])->name('index');
        Route::get('/{channel}', [LogViewerController::class, 'show'])->name('show');
        Route::get('/{channel}/download', [LogViewerController::class, 'download'])->name('download');
        Route::delete('/{channel}/clear', [LogViewerController::class, 'clear'])->name('clear');
    });
});
