<?php

namespace Okay\Modules\Sviat\LogViewer\Services;

class LogParser
{
    private const ENTRY_REGEX = '/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+([^\s]+)\.([^:]+):\s+(.+)$/';
    private const TRACE_REGEX = '/#\d+\s+/';
    private const PREVIEW_LIMIT = 200;

    /**
     * Parses raw log content into structured entries.
     *
     * Output entry:
     *   timestamp, channel, level, message (preview), full_message,
     *   trace, has_trace, is_long_message, file_date, id
     *
     * @param string $content
     * @param string $fileDate YYYY-MM-DD, used to build a stable id
     * @return array
     */
    public function parse(string $content, string $fileDate): array
    {
        $entries = [];
        $current = null;
        $traceLines = [];

        $lines = preg_split("/\r\n|\n|\r/", $content);
        foreach ($lines as $line) {
            if (preg_match(self::ENTRY_REGEX, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $this->finalize($current, $traceLines, $fileDate);
                }

                $current = [
                    'timestamp'   => $m[1],
                    'channel'     => $m[2],
                    'level'       => strtoupper(trim($m[3])),
                    'full_message' => trim($m[4]),
                ];
                $traceLines = [];
                continue;
            }

            if ($current !== null && trim($line) !== '') {
                $traceLines[] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->finalize($current, $traceLines, $fileDate);
        }

        return $entries;
    }

    /**
     * Filter entries by level (case-insensitive).
     */
    public function filterByLevel(array $entries, string $level): array
    {
        if ($level === '') {
            return $entries;
        }

        $level = strtoupper($level);
        return array_values(array_filter($entries, static function ($e) use ($level) {
            return $e['level'] === $level;
        }));
    }

    /**
     * Filter entries by free-text needle in full_message (case-insensitive).
     */
    public function filterByText(array $entries, string $needle): array
    {
        $needle = trim($needle);
        if ($needle === '') {
            return $entries;
        }

        return array_values(array_filter($entries, static function ($e) use ($needle) {
            return mb_stripos($e['full_message'], $needle) !== false;
        }));
    }

    private function finalize(array $current, array $traceLines, string $fileDate): array
    {
        $full = $current['full_message'];
        $hasInlineTrace = (bool) preg_match(self::TRACE_REGEX, $full);
        $hasMultilineTrace = !empty($traceLines);

        $trace = '';
        if ($hasMultilineTrace) {
            $trace = implode("\n", $traceLines);
        } elseif ($hasInlineTrace) {
            $trace = preg_replace(self::TRACE_REGEX, "\n$0", $full);
            $trace = trim($trace);
        }

        $isLong = mb_strlen($full) > self::PREVIEW_LIMIT;
        $preview = $isLong ? mb_substr($full, 0, self::PREVIEW_LIMIT) . '…' : $full;

        return [
            'id' => md5($current['timestamp'] . '|' . $fileDate . '|' . $full),
            'timestamp' => $current['timestamp'],
            'channel' => $current['channel'],
            'level' => $current['level'],
            'message' => $preview,
            'full_message' => $full,
            'trace' => $trace,
            'has_trace' => $hasInlineTrace || $hasMultilineTrace,
            'is_long_message' => $isLong,
            'file_date' => $fileDate,
        ];
    }
}
