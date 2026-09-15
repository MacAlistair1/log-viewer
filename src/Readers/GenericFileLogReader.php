<?php

namespace Jeeven\LogViewer\Readers;

class GenericFileLogReader extends AbstractFileReader
{
    protected function resolvePaths(): array
    {
        if (!empty($this->config['path'])) {
            return is_file($this->config['path']) ? [$this->config['path']] : [];
        }

        if (!empty($this->config['dir'])) {
            $files = glob(rtrim($this->config['dir'], '/') . '/' . ($this->config['pattern'] ?? '*.log')) ?: [];
            rsort($files);
            return $files;
        }

        return [];
    }

    protected function parseEntry(string $raw): array
    {
        $pattern = $this->config['pattern'] ?? null;

        if ($pattern && preg_match($pattern, $raw, $m)) {
            return [
                'date'    => $m['date'] ?? null,
                'level'   => strtolower($m['level'] ?? 'info'),
                'message' => trim($m['message'] ?? $raw),
                'raw'     => $raw,
            ];
        }

        return ['date' => null, 'level' => 'info', 'message' => $raw, 'raw' => $raw];
    }
}
