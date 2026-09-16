<?php

namespace Jeeven\LogViewer\Readers;

use Illuminate\Support\Facades\DB;
use Jeeven\LogViewer\Contracts\LogReader;

class DatabaseLogReader implements LogReader
{
    public function __construct(protected string $key, protected array $config) {}

    protected function query()
    {
        $connection = $this->config['connection'] ?? null;
        return ($connection ? DB::connection($connection) : DB::connection())
            ->table($this->config['table']);
    }

    protected function cols(): array
    {
        return $this->config['columns'];
    }

    public function paginate(array $filters): array
    {
        $cols    = $this->cols();
        $perPage = (int) ($filters['per_page'] ?? $this->config['per_page'] ?? config('log-viewer.ui.per_page', 50));
        $page    = max((int) ($filters['page'] ?? 1), 1);

        $q = $this->query();

        if (!empty($filters['level']) && !empty($cols['level'])) {
            $q->where($cols['level'], $filters['level']);
        }

        if (!empty($filters['search'])) {
            $searchable = $this->config['searchable'] ?? [$cols['message'] ?? null];
            $term = $filters['search'];
            $q->where(function ($sub) use ($searchable, $term) {
                foreach (array_filter($searchable) as $i => $col) {
                    $i === 0 ? $sub->where($col, 'like', "%{$term}%") : $sub->orWhere($col, 'like', "%{$term}%");
                }
            });
        }

        if (!empty($filters['from']) && !empty($cols['created_at'])) {
            $q->where($cols['created_at'], '>=', $filters['from']);
        }
        if (!empty($filters['to']) && !empty($cols['created_at'])) {
            $q->where($cols['created_at'], '<=', $filters['to']);
        }

        $total = (clone $q)->count();

        $rows = $q->orderBy($cols['created_at'] ?? $cols['id'], 'desc')
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(function ($row) use ($cols) {
            $row = (array) $row;
            $extra = [];
            foreach (($cols['extra'] ?? []) as $label => $col) {
                $extra[$label] = $row[$col] ?? null;
            }

            $rawLevel = $row[$cols['level']] ?? null;

            return [
                'id'         => $row[$cols['id']] ?? null,
                'date'       => $row[$cols['created_at']] ?? null,
                // "level" drives badge color; "level_label" is what's shown.
                'level'      => $this->resolveLevel($rawLevel),
                'level_label' => $rawLevel,
                'message'    => $row[$cols['message']] ?? null,
                'context'    => $extra ?: null,
                'stack'      => null,
                'raw'        => null,
            ];
        })->all();

        return [
            'data'         => $data,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total'        => $total,
            'last_page'    => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * Turn the raw "level" column value into one of the standard colorable
     * categories the UI knows how to badge (error/warning/notice/success/info).
     * The raw value itself is still shown to the user via "level_label".
     */
    protected function resolveLevel(mixed $raw): string
    {
        $type = $this->config['level_type'] ?? null;

        if ($type === 'http_status') {
            $code = (int) $raw;
            return match (true) {
                $code >= 500 => 'error',
                $code >= 400 => 'warning',
                $code >= 300 => 'notice',
                $code > 0    => 'success',
                default      => 'unknown',
            };
        }

        // No explicit type: infer a color bucket from common status words,
        // otherwise fall back to showing the raw value as its own bucket.
        $word = strtolower((string) $raw);

        return match (true) {
            str_contains($word, 'fail') || str_contains($word, 'error') || str_contains($word, 'reject') => 'error',
            str_contains($word, 'pending') || str_contains($word, 'queue') || str_contains($word, 'warn') => 'warning',
            str_contains($word, 'sent') || str_contains($word, 'success') || str_contains($word, 'ok') || str_contains($word, 'delivered') => 'success',
            default => $word !== '' ? $word : 'unknown',
        };
    }

    public function levels(): array
    {
        $cols = $this->cols();
        if (empty($cols['level'])) {
            return [];
        }

        return $this->query()->distinct()->pluck($cols['level'])->filter()->values()->all();
    }

    public function stats(): array
    {
        $cols = $this->cols();
        $count = $this->query()->count();
        $last = !empty($cols['created_at'])
            ? $this->query()->orderBy($cols['created_at'], 'desc')->value($cols['created_at'])
            : null;

        return [
            'exists'        => true,
            'size_human'    => number_format($count) . ' rows',
            'size_bytes'    => 0,
            'row_count'     => $count,
            'last_modified' => $last ? \Illuminate\Support\Carbon::parse($last) : null,
            'file_count'    => null,
        ];
    }

    public function supportsDownload(): bool
    {
        return true;
    }

    public function supportsClear(): bool
    {
        return (bool) ($this->config['allow_clear'] ?? false);
    }

    public function download(): mixed
    {
        $cols = $this->cols();
        $rows = $this->query()->orderBy($cols['created_at'] ?? $cols['id'], 'desc')->limit(10000)->get();

        $csv = fopen('php://temp', 'w+');
        if ($rows->isNotEmpty()) {
            fputcsv($csv, array_keys((array) $rows->first()));
            foreach ($rows as $row) {
                fputcsv($csv, (array) $row);
            }
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $this->key . '.csv"',
        ]);
    }

    public function clear(): bool
    {
        try {
            return (bool) $this->query()->delete();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Log Viewer: could not clear the "' . ($this->config['table'] ?? $this->key) . '" table — ' . $e->getMessage()
            );
        }
    }
}
