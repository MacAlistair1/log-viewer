<?php

namespace Jeeven\LogViewer\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class AssetController
{
    /** Only these exact files may ever be served — no path traversal possible. */
    protected const ALLOWED = [
        'css/log-viewer.css' => 'text/css; charset=UTF-8',
        'js/log-viewer.js'   => 'application/javascript; charset=UTF-8',
    ];

    public function serve(string $path): Response
    {
        if (!isset(self::ALLOWED[$path])) {
            abort(404);
        }

        $file = __DIR__ . '/../../../resources/dist/' . $path;

        if (!is_file($file)) {
            abort(404);
        }

        $mtime = filemtime($file);

        return response(file_get_contents($file), 200, [
            'Content-Type'  => self::ALLOWED[$path],
            'Cache-Control' => 'public, max-age=86400',
            'ETag'          => md5_file($file),
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
        ]);
    }
}
