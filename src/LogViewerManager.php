<?php

namespace Jeeven\LogViewer;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jeeven\LogViewer\Contracts\LogReader;
use Jeeven\LogViewer\Readers\ApacheNginxLogReader;
use Jeeven\LogViewer\Readers\DatabaseLogReader;
use Jeeven\LogViewer\Readers\GenericFileLogReader;
use Jeeven\LogViewer\Readers\LaravelLogReader;

class LogViewerManager
{
    /** Driver names that represent a group of sub-channels rather than a readable log themselves. */
    protected const GROUP_DRIVERS = ['directory', 'group'];

    /** @var array<string, class-string<LogReader>> */
    protected array $drivers = [
        'laravel'  => LaravelLogReader::class,
        'apache'   => ApacheNginxLogReader::class,
        'nginx'    => ApacheNginxLogReader::class,
        'file'     => GenericFileLogReader::class,
        'database' => DatabaseLogReader::class,
    ];

    /** Register a custom driver, e.g. LogViewer::extend('redis', RedisLogReader::class). */
    public function extend(string $driver, string $readerClass): static
    {
        $this->drivers[$driver] = $readerClass;
        return $this;
    }

    /**
     * Top-level configured channels, exactly as written in config/log-viewer.php.
     * A group-type channel ("directory" or "group") stays as ONE entry here
     * — it shows as a single card on the dashboard. Its contents are only
     * expanded when the user opens it (see groupChannels()).
     */
    public function channels(): array
    {
        return config('log-viewer.channels', []);
    }

    public function rawChannelConfig(string $key): ?array
    {
        return $this->channels()[$key] ?? null;
    }

    /** Whether a channel config represents a group of sub-channels (sidebar UI) rather than a directly-readable log. */
    public function isGroup(?array $config): bool
    {
        return $config && in_array($config['driver'] ?? null, self::GROUP_DRIVERS, true);
    }

    /**
     * A group channel's sub-channels, however they're produced:
     *  - "directory": auto-discovered by scanning a folder on disk.
     *  - "group": whatever is manually listed under its "channels" key —
     *    e.g. every database-table log, or a web server's access + error
     *    logs, grouped under one dashboard card.
     * Returns [] for any non-group / unknown channel.
     *
     * @return array<string, array> sub-channel slug => channel config
     */
    public function groupChannels(string $groupKey): array
    {
        $config = $this->rawChannelConfig($groupKey);

        if (!$this->isGroup($config)) {
            return [];
        }

        return match ($config['driver']) {
            'directory' => $this->discoverDirectoryChannels($config),
            'group'     => $config['channels'] ?? [],
            default     => [],
        };
    }

    /**
     * Resolve a reader for either a plain channel key ("nginx_access") or a
     * composite "group/sub" key ("database_logs/api_logs") produced by a
     * group channel.
     */
    public function reader(string $key): LogReader
    {
        if (str_contains($key, '/')) {
            [$groupKey, $subKey] = explode('/', $key, 2);
            $subs = $this->groupChannels($groupKey);

            if (!isset($subs[$subKey])) {
                throw new InvalidArgumentException("Log viewer channel [{$key}] is not configured.");
            }

            $config = $subs[$subKey];
        } else {
            $config = $this->rawChannelConfig($key);

            if (!$config) {
                throw new InvalidArgumentException("Log viewer channel [{$key}] is not configured.");
            }

            if ($this->isGroup($config)) {
                throw new InvalidArgumentException("Log viewer channel [{$key}] is a group — open it and pick a sub-channel.");
            }
        }

        $driver = $config['driver'] ?? 'file';

        if (!isset($this->drivers[$driver])) {
            throw new InvalidArgumentException("Log viewer driver [{$driver}] is not registered.");
        }

        return new $this->drivers[$driver]($key, $config);
    }

    /**
     * Dashboard-card stats for a top-level channel. Group channels get an
     * aggregate (combined size, most recent activity, how many
     * sub-channels) since they aren't read directly.
     */
    public function statsFor(string $key): array
    {
        $config = $this->rawChannelConfig($key);

        if ($this->isGroup($config)) {
            return $this->groupStats($key);
        }

        return $this->reader($key)->stats();
    }

    protected function groupStats(string $groupKey): array
    {
        $subs = $this->groupChannels($groupKey);
        $sizeBytes = 0;
        $rows = 0;
        $lastModified = null;
        $active = 0;

        foreach ($subs as $subKey => $subConfig) {
            $driver = $subConfig['driver'] ?? null;
            if (!isset($this->drivers[$driver])) {
                continue;
            }

            $stats = (new $this->drivers[$driver]($groupKey . '/' . $subKey, $subConfig))->stats();

            $sizeBytes += $stats['size_bytes'] ?? 0;
            $rows += $stats['row_count'] ?? 0;
            if (!empty($stats['exists'])) {
                $active++;
            }
            if (!empty($stats['last_modified']) && $stats['last_modified'] instanceof Carbon) {
                if (!$lastModified || $stats['last_modified']->gt($lastModified)) {
                    $lastModified = $stats['last_modified'];
                }
            }
        }

        // Prefer a row count summary when the group is made of database
        // channels (byte size isn't meaningful there); otherwise show size.
        $sizeDisplay = ($sizeBytes === 0 && $rows > 0)
            ? number_format($rows) . ' rows'
            : $this->humanSize($sizeBytes);

        return [
            'exists'        => count($subs) > 0,
            'size_human'    => $sizeDisplay,
            'last_modified' => $lastModified,
            'file_count'    => null,
            'channel_count' => count($subs),
            'active_count'  => $active,
        ];
    }

    /**
     * Scan a base directory (e.g. storage/logs) and turn it into many
     * virtual channel configs:
     *  - loose *.log files are grouped by their name with any trailing
     *    "-YYYY-MM-DD" rotation suffix stripped, so laravel.log and
     *    laravel-2026-09-14.log become one "Laravel" group, while
     *    minios3-2025-07-11.log and minios3-2025-07-14.log become one
     *    "Minios3" group.
     *  - every immediate subfolder (ccms/, nid/, pumori/, ...) becomes its
     *    own channel covering every *.log file inside it.
     *
     * @return array<string, array> sub-channel slug => channel config
     */
    protected function discoverDirectoryChannels(array $config): array
    {
        $base = rtrim($config['path'] ?? '', '/\\');
        $exclude = $config['exclude'] ?? [];
        $channels = [];

        if (!is_dir($base)) {
            return $channels;
        }

        // ---- 1) loose *.log files directly inside the base directory ----
        $groups = [];
        foreach (glob($base . '/*.log') ?: [] as $file) {
            if (in_array(basename($file), $exclude, true)) {
                continue;
            }
            $name = basename($file, '.log');
            // Strip a trailing "-YYYY-MM-DD" (Laravel's daily log rotation).
            $group = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $name) ?: $name;
            $groups[$group][] = $file;
        }

        foreach ($groups as $group => $files) {
            $channels[Str::slug($group)] = [
                'driver'  => $config['file_driver'] ?? 'laravel',
                'label'   => Str::headline($group),
                'path'    => $base,
                'pattern' => $group . '*.log',
            ];
        }

        // ---- 2) every immediate subfolder becomes its own channel ----
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $folder = basename($dir);
            if (in_array($folder, $exclude, true)) {
                continue;
            }
            $channels[Str::slug($folder)] = [
                'driver'  => $config['folder_driver'] ?? 'laravel',
                'label'   => Str::headline($folder),
                'path'    => $dir,
                'pattern' => '*.log',
            ];
        }

        return $channels;
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
