<?php

namespace Jeeven\LogViewer\Contracts;

interface LogReader
{
    /**
     * Return a paginated, filtered, newest-first list of entries.
     *
     * @param  array{level?:string,search?:string,from?:string,to?:string,page?:int,per_page?:int}  $filters
     * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(array $filters): array;

    /**
     * Distinct levels available for this channel (for the filter dropdown).
     */
    public function levels(): array;

    /**
     * Lightweight stats for the dashboard card (size, count, last modified…).
     */
    public function stats(): array;

    /**
     * Whether this channel supports being downloaded as a raw file/export.
     */
    public function supportsDownload(): bool;

    /**
     * Whether this channel supports being cleared/truncated.
     */
    public function supportsClear(): bool;

    public function download(): mixed;

    public function clear(): bool;
}
