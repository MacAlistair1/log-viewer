<?php

namespace Jeeven\LogViewer\Readers;

use Generator;

class LaravelLogReader extends AbstractFileReader
{
    // [2026-09-14 10:15:23] production.ERROR: Something broke {"context":...}
    protected const PATTERN = '/^\[(?<date>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})[^\]]*\]\s+(?<env>\w+)\.(?<level>\w+):\s+(?<message>.*)$/s';

    protected function resolvePaths(): array
    {
        $dir     = $this->config['path'] ?? storage_path('logs');
        $pattern = $this->config['pattern'] ?? 'laravel*.log';

        $files = glob(rtrim($dir, '/') . '/' . $pattern) ?: [];
        rsort($files); // newest date-named file first

        return $files;
    }

    /**
     * Lines arrive newest-line-first. A Laravel entry's FIRST line matches
     * PATTERN; any following lines (stack trace / context) belong to the
     * entry ABOVE it. Since we read backwards, we accumulate "tail" lines
     * until we hit the line that starts the entry, then flush.
     */
    protected function group(iterable $lines): Generator
    {
        $tailBuffer = [];

        foreach ($lines as $line) {
            if (preg_match(self::PATTERN, $line)) {
                $full = $line;
                if ($tailBuffer) {
                    $full .= "\n" . implode("\n", array_reverse($tailBuffer));
                }
                yield $full;
                $tailBuffer = [];
            } else {
                $tailBuffer[] = $line;
            }
        }

        // Any leftover lines at the very start of the file with no header —
        // surface them as a raw entry rather than silently dropping them.
        if ($tailBuffer) {
            yield implode("\n", array_reverse($tailBuffer));
        }
    }

    protected function parseEntry(string $raw): array
    {
        $firstLine = strtok($raw, "\n");

        if (preg_match(self::PATTERN, $raw, $m)) {
            $message = trim($m['message']);
            $stack   = trim(substr($raw, strlen($firstLine)));

            // Split off a JSON context blob if present at the end of the message.
            $context = null;
            if (preg_match('/(\{.*\})\s*$/s', $message, $jm)) {
                $decoded = json_decode($jm[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decoded;
                    $message = trim(substr($message, 0, -strlen($jm[1])));
                }
            }

            return [
                'date'    => $m['date'],
                'env'     => $m['env'],
                'level'   => strtolower($m['level']),
                'message' => $message,
                'context' => $context,
                'stack'   => $stack ?: null,
                'raw'     => $raw,
            ];
        }

        return [
            'date' => null, 'env' => null, 'level' => 'unknown',
            'message' => $firstLine, 'context' => null, 'stack' => null, 'raw' => $raw,
        ];
    }
}
