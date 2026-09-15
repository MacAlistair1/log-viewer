<?php

namespace Jeeven\LogViewer\Readers;

class ApacheNginxLogReader extends AbstractFileReader
{
    // Combined Log Format: IP - user [date] "METHOD path HTTP/x" status size "referer" "agent"
    protected const COMBINED = '/^(?<ip>\S+) \S+ (?<user>\S+) \[(?<date>[^\]]+)\] "(?<method>\S+) (?<path>\S+) (?<protocol>[^"]+)" (?<status>\d{3}) (?<size>\S+)(?: "(?<referer>[^"]*)" "(?<agent>[^"]*)")?/';

    // Error log format (Apache): [date] [level] [pid ...] message
    // Nginx error format: date [level] pid#tid: message
    protected const APACHE_ERROR = '/^\[(?<date>[^\]]+)\]\s+\[[^:]*:(?<level>\w+)\][^\]]*\]\s+(?:\[pid[^\]]*\]\s+)?(?<message>.*)$/';
    protected const NGINX_ERROR  = '/^(?<date>\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}) \[(?<level>\w+)\] (?<message>.*)$/';

    protected function resolvePaths(): array
    {
        return is_file($this->config['path'] ?? '') ? [$this->config['path']] : [];
    }

    protected function parseEntry(string $raw): array
    {
        $format = $this->config['format'] ?? 'combined';

        if ($format === 'error') {
            $pattern = str_contains($this->key, 'nginx') ? self::NGINX_ERROR : self::APACHE_ERROR;
            if (preg_match($pattern, $raw, $m)) {
                return [
                    'date'    => $m['date'],
                    'level'   => strtolower($m['level'] ?? 'error'),
                    'message' => trim($m['message']),
                    'raw'     => $raw,
                ];
            }
            return ['date' => null, 'level' => 'unknown', 'message' => $raw, 'raw' => $raw];
        }

        if (preg_match(self::COMBINED, $raw, $m)) {
            $status = (int) $m['status'];
            $level  = match (true) {
                $status >= 500 => 'error',
                $status >= 400 => 'warning',
                default        => 'info',
            };

            return [
                'date'    => $m['date'],
                'level'   => $level,
                'message' => sprintf('%s %s → %s (%s)', $m['method'], $m['path'], $m['status'], $m['ip']),
                'ip'      => $m['ip'],
                'method'  => $m['method'],
                'path'    => $m['path'],
                'status'  => $m['status'],
                'size'    => $m['size'],
                'agent'   => $m['agent'] ?? null,
                'raw'     => $raw,
            ];
        }

        return ['date' => null, 'level' => 'unknown', 'message' => $raw, 'raw' => $raw];
    }

    public function levels(): array
    {
        return ($this->config['format'] ?? 'combined') === 'error'
            ? ['error', 'warn', 'notice', 'crit', 'alert']
            : ['info', 'warning', 'error'];
    }

    public function supportsClear(): bool
    {
        // Web server log files usually require elevated permissions —
        // disable clearing by default; can be overridden via config.
        return (bool) ($this->config['allow_clear'] ?? false);
    }
}
