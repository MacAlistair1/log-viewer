<?php

namespace Jeeven\LogViewer\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Jeeven\LogViewer\LogViewerManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LogViewerController
{
    public function __construct(protected LogViewerManager $manager) {}

    public function index(): View
    {
        $channels = [];

        foreach ($this->manager->channels() as $key => $config) {

            $stats = $this->manager->statsFor($key);

            if (isset($stats['exists']) && $stats['exists'] == true && $stats['active_count'] > 0) {
                $channels[$key] = [
                    'label'    => $config['label'] ?? ucfirst($key),
                    'driver'   => $config['driver'],
                    'is_group' => $this->manager->isGroup($config),
                    'stats'    => $this->manager->statsFor($key),
                ];
            }
        }

        return view('log-viewer::index', compact('channels'));
    }

    /**
     * Plain channels ("nginx_access") render straight to a single-column
     * entries view. Group channels — "directory" (auto-discovered from a
     * folder, e.g. application_logs) or "group" (manually listed
     * sub-channels, e.g. database_logs, nginx_logs) — get a sidebar of
     * every sub-channel alongside the entries panel; with no ?sub= given,
     * it redirects to the first one so there's always something in the URL
     * to bookmark/share.
     */
    public function show(string $channel, Request $request): View|RedirectResponse
    {
        $config = $this->manager->rawChannelConfig($channel);

        if (!$config) {
            abort(404);
        }

        $isGroup = $this->manager->isGroup($config);
        $sub = $request->query('sub');
        $sidebarItems = null;

        if ($isGroup) {
            $subs = $this->manager->groupChannels($channel);

            if (empty($subs)) {
                // No sub-channels discovered/configured yet — show the
                // empty sidebar/shell rather than a hard redirect loop.
                return view('log-viewer::show', [
                    'channelKey'   => $channel,
                    'subKey'       => null,
                    'channelMeta'  => $config,
                    'groupMeta'    => null,
                    'sidebarItems' => [],
                    'isEmptyGroup' => true,
                    'result'       => ['data' => [], 'per_page' => 0, 'current_page' => 1, 'total' => 0, 'last_page' => 1],
                    'levels'       => [],
                    'filters'      => [],
                    'canDownload'  => false,
                    'canDelete'    => false,
                ]);
            }

            if (!$sub || !isset($subs[$sub])) {
                return redirect()->route('log-viewer.show', ['channel' => $channel, 'sub' => array_key_first($subs)]);
            }

            $sidebarItems = [];
            foreach ($subs as $subKey => $subConfig) {

                $stats =  $this->manager->reader($channel . '/' . $subKey)->stats();
                $exists = $stats['exists'] ?? true;

                if ($channel == 'database_logs') {
                    $size_bytes =  $stats['row_count'] ?? 0;
                } else {
                    $size_bytes =  $stats['size_bytes'] ?? 0;
                }

                if ($exists && $size_bytes > 0) {
                    $sidebarItems[$subKey] = [
                        'label'  => $subConfig['label'] ?? ucfirst($subKey),
                        'size_bytes' => $size_bytes,
                        'exists' => $exists,
                    ];
                }
            }
        }

        $fullKey = $isGroup ? $channel . '/' . $sub : $channel;
        $reader  = $this->manager->reader($fullKey);

        $result = $reader->paginate($request->only(['level', 'search', 'from', 'to', 'page', 'per_page']));

        return view('log-viewer::show', [
            'channelKey'   => $channel,
            'subKey'       => $sub,
            'channelMeta'  => $isGroup ? ($this->manager->groupChannels($channel)[$sub] ?? []) : $config,
            'groupMeta'    => $isGroup ? $config : null,
            'sidebarItems' => $sidebarItems,
            'isEmptyGroup' => false,
            'result'       => $result,
            'levels'       => $reader->levels(),
            'filters'      => $request->only(['level', 'search', 'from', 'to']),
            'canDownload'  => $reader->supportsDownload() && Gate::allows(config('log-viewer.authorization.gates.download', 'downloadLogViewer')) !== false,
            'canDelete'    => $reader->supportsClear() && Gate::allows(config('log-viewer.authorization.gates.delete', 'deleteLogViewer')) !== false,
        ]);
    }

    public function download(string $channel, Request $request)
    {
        $this->authorizeAction('download');

        $sub     = $request->query('sub');
        $fullKey = $sub ? $channel . '/' . $sub : $channel;
        $reader  = $this->manager->reader($fullKey);

        if (!$reader->supportsDownload()) {
            abort(404);
        }

        return $reader->download();
    }

    public function clear(string $channel, Request $request): RedirectResponse
    {
        $this->authorizeAction('delete');

        $sub     = $request->query('sub');
        $fullKey = $sub ? $channel . '/' . $sub : $channel;
        $reader  = $this->manager->reader($fullKey);

        if (!$reader->supportsClear()) {
            abort(404);
        }

        $reader->clear();

        return back()->with('status', 'Log cleared successfully.');
    }

    protected function authorizeAction(string $action): void
    {
        $ability = config("log-viewer.authorization.gates.{$action}");

        // Only block if the host app explicitly defined the gate and denied it.
        if ($ability && Gate::has($ability) && !Gate::allows($ability)) {
            throw new AccessDeniedHttpException("Log Viewer: not authorized to {$action}.");
        }
    }
}
