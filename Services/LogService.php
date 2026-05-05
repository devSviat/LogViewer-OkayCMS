<?php

namespace Okay\Modules\Sviat\LogViewer\Services;

class LogService
{
    public const MAX_PARSE_BYTES = 10 * 1024 * 1024; // 10 MiB
    public const DATE_REGEX = '/^\d{4}-\d{2}-\d{2}$/';

    /** @var LogSourceRegistry */
    private $registry;

    /** @var LogParser */
    private $parser;

    public function __construct(LogSourceRegistry $registry, LogParser $parser)
    {
        $this->registry = $registry;
        $this->parser = $parser;
    }

    public function getRegistry(): LogSourceRegistry
    {
        return $this->registry;
    }

    /**
     * @return array<string, array{filename:string,path:string,date:string,size:int,modified:int}>
     *         keyed by date desc
     */
    public function listFiles(LogSource $source): array
    {
        $dir = $source->getDirectory();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }

        foreach ($entries as $name) {
            if (!preg_match($source->getFileNameRegex(), $name, $m)) {
                continue;
            }

            $date = $m[1];
            $path = $dir . $name;

            if (!is_file($path)) {
                continue;
            }

            $files[$date] = [
                'filename' => $name,
                'path'     => $path,
                'date'     => $date,
                'size'     => (int) @filesize($path),
                'modified' => (int) @filemtime($path),
            ];
        }

        krsort($files);
        return $files;
    }

    public function isValidDate(string $date): bool
    {
        return (bool) preg_match(self::DATE_REGEX, $date);
    }

    /**
     * Returns parsed entries for a single date, or null if file missing.
     * Returns ['too_large' => true, 'size' => bytes] sentinel array if the file
     * exceeds MAX_PARSE_BYTES (caller renders a warning + download link).
     *
     * Caller passes the result of listFiles() to avoid a second scandir.
     *
     * @return array|null
     */
    public function readEntriesForDate(string $date, array $logFiles): ?array
    {
        if (!isset($logFiles[$date])) {
            return null;
        }

        $info = $logFiles[$date];
        $size = (int) $info['size'];
        if ($size > self::MAX_PARSE_BYTES) {
            return [
                'too_large' => true,
                'size'      => $size,
                'entries'   => [],
            ];
        }

        $content = @file_get_contents($info['path']);
        if ($content === false) {
            return null;
        }

        return [
            'too_large' => false,
            'size'      => $size,
            'entries'   => $this->parser->parse($content, $date),
        ];
    }

    /**
     * Reads and merges entries from all files of the source.
     * Skips files larger than MAX_PARSE_BYTES.
     */
    public function readAllEntries(LogSource $source): array
    {
        $merged = [];
        foreach ($this->listFiles($source) as $info) {
            if ($info['size'] > self::MAX_PARSE_BYTES) {
                continue;
            }
            $content = @file_get_contents($info['path']);
            if ($content === false) {
                continue;
            }
            $entries = $this->parser->parse($content, $info['date']);
            foreach ($entries as $e) {
                $merged[] = $e;
            }
        }

        usort($merged, static function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return $merged;
    }

    /**
     * Deletes a single log file. Refuses to delete today's file.
     *
     * @return array{ok:bool,reason?:string}
     */
    public function deleteFile(LogSource $source, string $date): array
    {
        if (!$this->isValidDate($date)) {
            return ['ok' => false, 'reason' => 'invalid_date'];
        }

        if ($date === date('Y-m-d')) {
            return ['ok' => false, 'reason' => 'today_protected'];
        }

        $path = $this->resolveFilePath($source, $date);
        if ($path === null) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        if (@unlink($path)) {
            return ['ok' => true];
        }
        return ['ok' => false, 'reason' => 'delete_failed'];
    }

    /**
     * Resolves an actual file path within the source dir for a validated date.
     * Walks scandir results — never concatenates user input into a path.
     */
    public function resolveFilePath(LogSource $source, string $date): ?string
    {
        if (!$this->isValidDate($date)) {
            return null;
        }
        $files = $this->listFiles($source);
        return $files[$date]['path'] ?? null;
    }
}
