<?php

namespace Jeeven\LogViewer\Support;

use Generator;

/**
 * Reads a text file from the end towards the beginning, yielding one line
 * at a time (newest first) without ever loading the whole file into memory.
 * This keeps the package fast on multi-GB production log files.
 */
class LineReverseReader
{
    protected const CHUNK_SIZE = 8192; // 8kb per read

    public function __construct(protected string $path)
    {
    }

    /**
     * @return Generator<int, string> newest line first
     */
    public function lines(): Generator
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            return;
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            $pos     = filesize($this->path);
            $buffer  = '';

            while ($pos > 0) {
                $readSize = min(self::CHUNK_SIZE, $pos);
                $pos -= $readSize;

                fseek($handle, $pos);
                $chunk  = fread($handle, $readSize);
                $buffer = $chunk . $buffer;

                $lines = explode("\n", $buffer);

                // Keep the first (possibly incomplete) segment in the buffer
                // for the next iteration; everything else is a full line.
                $buffer = array_shift($lines);

                // Yield the completed lines, newest (i.e. last read) first.
                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $line = rtrim($lines[$i], "\r");
                    if ($line !== '') {
                        yield $line;
                    }
                }
            }

            if (trim($buffer) !== '') {
                yield rtrim($buffer, "\r");
            }
        } finally {
            fclose($handle);
        }
    }
}
