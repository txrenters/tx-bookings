<?php

namespace App\Services\Logs;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Reads Laravel's own log files for the in-app viewer.
 *
 * Only ever reads the tail of a file. A log can grow to hundreds of megabytes
 * and pulling one into memory to show the last few errors would take the app
 * down, which is the opposite of what a debugging tool should do.
 */
class LogReader
{
    /**
     * How much of the end of a file to read, in bytes.
     */
    protected int $tailBytes = 512_000;

    /**
     * The most entries returned in one go.
     */
    protected int $maxEntries = 200;

    /**
     * The start of a log entry: "[2026-08-24 16:07:43]".
     */
    protected string $entryPattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]\s(\w+)\.(\w+):\s(.*)$/';

    /**
     * List the available log files, newest first.
     *
     * @return array<int, array{name: string, sizeBytes: int, sizeLabel: string, modifiedAt: string}>
     */
    public function files(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];

        return Collection::make($files)
            ->sortByDesc(fn (string $path) => filemtime($path))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'sizeBytes' => filesize($path) ?: 0,
                'sizeLabel' => $this->humanSize(filesize($path) ?: 0),
                'modifiedAt' => date('c', filemtime($path) ?: 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Read the most recent entries from one log file, newest first.
     *
     * @return array<int, array{id: string, level: string, environment: string, loggedAt: string, message: string, context: string|null}>
     */
    public function entries(string $file, ?string $level = null, ?string $search = null): array
    {
        $path = $this->pathFor($file);

        if ($path === null) {
            return [];
        }

        return Collection::make($this->parse($this->tail($path)))
            ->when(
                filled($level) && $level !== 'all',
                fn (Collection $entries) => $entries->filter(
                    fn (array $entry) => strtolower($entry['level']) === strtolower((string) $level),
                ),
            )
            ->when(
                filled($search),
                fn (Collection $entries) => $entries->filter(
                    fn (array $entry) => Str::contains(
                        $entry['message'].' '.($entry['context'] ?? ''),
                        (string) $search,
                        ignoreCase: true,
                    ),
                ),
            )
            ->reverse()
            ->take($this->maxEntries)
            ->values()
            ->all();
    }

    /**
     * Get the distinct levels present in a file, for the filter.
     *
     * @return array<int, string>
     */
    public function levels(string $file): array
    {
        $path = $this->pathFor($file);

        if ($path === null) {
            return [];
        }

        return Collection::make($this->parse($this->tail($path)))
            ->pluck('level')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Delete the contents of a log file without removing it.
     */
    public function clear(string $file): bool
    {
        $path = $this->pathFor($file);

        if ($path === null) {
            return false;
        }

        return file_put_contents($path, '') !== false;
    }

    /**
     * Resolve a file name to a real path inside the log directory.
     *
     * Rejects anything that is not a plain .log file sitting directly in
     * storage/logs, so a crafted name cannot walk out of the directory.
     */
    protected function pathFor(string $file): ?string
    {
        if ($file === '' || ! preg_match('/^[A-Za-z0-9._-]+\.log$/', $file) || str_contains($file, '..')) {
            return null;
        }

        $path = storage_path('logs/'.$file);
        $real = realpath($path);
        $base = realpath(storage_path('logs'));

        if ($real === false || $base === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /**
     * Read the last chunk of a file as text.
     */
    protected function tail(string $path): string
    {
        $size = filesize($path) ?: 0;

        if ($size === 0) {
            return '';
        }

        $offset = max(0, $size - $this->tailBytes);

        $handle = new SplFileObject($path, 'r');
        $handle->fseek($offset);

        $contents = (string) $handle->fread($this->tailBytes) ?: '';

        // A mid-entry start would parse as a fragment, so drop the partial head.
        return $offset > 0 ? (string) Str::after($contents, "\n") : $contents;
    }

    /**
     * Split raw log text into structured entries, oldest first.
     *
     * @return array<int, array{id: string, level: string, environment: string, loggedAt: string, message: string, context: string|null}>
     */
    protected function parse(string $contents): array
    {
        $entries = [];
        $current = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
            if (preg_match($this->entryPattern, $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $this->finish($current);
                }

                $current = [
                    'id' => $index.'-'.substr(md5($line), 0, 8),
                    'loggedAt' => $matches[1],
                    'environment' => $matches[2],
                    'level' => strtolower($matches[3]),
                    'message' => $matches[4],
                    'lines' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->finish($current);
        }

        return $entries;
    }

    /**
     * Turn a gathered entry into its final shape.
     *
     * @param  array<string, mixed>  $entry
     * @return array{id: string, level: string, environment: string, loggedAt: string, message: string, context: string|null}
     */
    protected function finish(array $entry): array
    {
        $context = trim(implode("\n", $entry['lines']));

        return [
            'id' => $entry['id'],
            'level' => $entry['level'],
            'environment' => $entry['environment'],
            'loggedAt' => $entry['loggedAt'],
            'message' => $entry['message'],
            'context' => $context === '' ? null : $context,
        ];
    }

    /**
     * Format a byte count for display.
     */
    protected function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
