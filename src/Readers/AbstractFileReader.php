<?php

namespace Jeeven\LogViewer\Readers;

use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Jeeven\LogViewer\Contracts\LogReader;
use Jeeven\LogViewer\Support\LineReverseReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class AbstractFileReader implements LogReader
{
    /** Hard ceiling on how many matching entries we'll ever count/scan for one request. */
    protected const MAX_SCAN = 200000;

    public function __construct(protected string $key, protected array $config) {}

    /** Resolve the concrete file path(s) this channel reads from. */
    abstract protected function resolvePaths(): array;

    /** Parse one raw line (or accumulated multiline block) into a normalized entry. */
    abstract protected function parseEntry(string $raw): array;

    /**
     * Group raw reverse-ordered lines into entries. Override for multiline
     * formats (e.g. Laravel stack traces). Default: 1 line = 1 entry.
     */
    protected function group(iterable $lines): Generator
    {
        foreach ($lines as $line) {
            yield $line;
        }
    }

    /**
     * Yields every parsed entry (newest first, across all resolved paths)
     * that matches the given filters. Shared by both the counting pass and
     * the page-slicing pass so the filter logic only lives in one place.
     */
    protected function filteredEntries(array $filters): Generator
    {
        $level  = $filters['level'] ?? null;
        $search = $filters['search'] ?? null;
        $from   = !empty($filters['from']) ? Carbon::parse($filters['from']) : null;
        $to     = !empty($filters['to']) ? Carbon::parse($filters['to']) : null;

        foreach ($this->resolvePaths() as $path) {
            foreach ($this->group((new LineReverseReader($path))->lines()) as $raw) {
                $entry = $this->parseEntry($raw);

                if ($level && strtolower($entry['level'] ?? '') !== strtolower($level)) {
                    continue;
                }
                if ($search && stripos($entry['message'] ?? $raw, $search) === false) {
                    continue;
                }
                if ($from && !empty($entry['date']) && Carbon::parse($entry['date'])->lt($from)) {
                    continue;
                }
                if ($to && !empty($entry['date']) && Carbon::parse($entry['date'])->gt($to)) {
                    continue;
                }

                yield $entry;
            }
        }
    }

    public function paginate(array $filters): array
    {
        $page    = max((int) ($filters['page'] ?? 1), 1);
        $perPage = max((int) ($filters['per_page'] ?? $this->config['per_page'] ?? config('log-viewer.ui.per_page', 50)), 1);

        $total = $this->cachedTotal($filters);

        $skip = ($page - 1) * $perPage;
        $data = [];
        $i    = 0;

        foreach ($this->filteredEntries($filters) as $entry) {
            if ($i++ < $skip) {
                continue;
            }
            $data[] = $entry;
            if (count($data) >= $perPage) {
                break;
            }
        }

        return [
            'data'         => $data,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total'        => $total,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Counting matches requires a full scan, which is the expensive part
     * on very large files. We cache the result per channel+filter
     * combination for a short window so paging through results (or
     * refreshing) doesn't re-scan the file on every click.
     */
    protected function cachedTotal(array $filters): int
    {
        $cacheKey = 'log-viewer:total:' . $this->key . ':' . md5(json_encode([
            'level'  => $filters['level']  ?? null,
            'search' => $filters['search'] ?? null,
            'from'   => $filters['from']   ?? null,
            'to'     => $filters['to']     ?? null,
        ]));

        try {
            return Cache::remember($cacheKey, 20, fn() => $this->countMatching($filters));
        } catch (\Throwable) {
            // No cache store configured/available — fall back to a live count.
            return $this->countMatching($filters);
        }
    }

    protected function countMatching(array $filters): int
    {
        $count = 0;
        foreach ($this->filteredEntries($filters) as $ignored) {
            $count++;
            if ($count >= self::MAX_SCAN) {
                break;
            }
        }
        return $count;
    }

    public function levels(): array
    {
        return ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
    }

    public function stats(): array
    {
        $paths = array_filter($this->resolvePaths(), 'is_file');
        $size  = array_sum(array_map('filesize', $paths));
        $mtime = $paths ? max(array_map('filemtime', $paths)) : null;

        return [
            'exists'        => count($paths) > 0,
            'size_human'    => $this->humanSize($size),
            'size_bytes'    => $size,
            'last_modified' => $mtime ? Carbon::createFromTimestamp($mtime) : null,
            'file_count'    => count($paths),
        ];
    }

    public function supportsDownload(): bool
    {
        return true;
    }

    public function supportsClear(): bool
    {
        return $this->isWritable();
    }

    /**
     * Whether every existing resolved file is writable by the current
     * process. Backs supportsClear() so the "Clear log" button never even
     * appears for files the web server user can't touch (e.g. a system log
     * owned by another user/process). If nothing exists yet, there's
     * nothing to protect against clearing, so this is true.
     */
    protected function isWritable(): bool
    {
        $paths = array_filter($this->resolvePaths(), 'is_file');

        foreach ($paths as $path) {
            if (!is_writable($path)) {
                return false;
            }
        }

        return true;
    }

    public function download(): mixed
    {
        $paths = array_values(array_filter($this->resolvePaths(), 'is_file'));

        if (count($paths) === 1) {
            return response()->download($paths[0]);
        }

        // Multiple files: stream a concatenated export.
        return new StreamedResponse(function () use ($paths) {
            foreach ($paths as $path) {
                readfile($path);
            }
        }, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $this->key . '.log"',
        ]);
    }

    public function clear(): bool
    {
        $failed = [];

        foreach ($this->resolvePaths() as $path) {
            if (!is_file($path)) {
                continue;
            }

            // Checked up front (clearer message) AND suppressed on the
            // call itself (in case permissions changed since the page
            // loaded, or on filesystems where is_writable() lies) — either
            // way we never let a raw PHP warning bubble up as a fatal
            // ErrorException.
            if (!is_writable($path) || @file_put_contents($path, '') === false) {
                $failed[] = basename($path);
            }
        }

        if ($failed) {
            throw new \RuntimeException(
                'Log Viewer: could not clear ' . implode(', ', $failed) .
                    ' — permission denied. Check the file\'s ownership/permissions on the server.'
            );
        }

        return true;
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
