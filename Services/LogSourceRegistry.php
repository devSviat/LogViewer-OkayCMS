<?php

namespace Okay\Modules\Sviat\LogViewer\Services;

class LogSourceRegistry
{
    public const SOURCE_APP = 'app';
    public const SOURCE_SCHEDULER = 'scheduler';
    public const DEFAULT_SOURCE = self::SOURCE_APP;

    /** @var LogSource[] */
    private $sources = [];

    public function __construct(string $rootDir)
    {
        $rootDir = rtrim($rootDir, '/');

        $this->sources[self::SOURCE_APP] = new LogSource(
            self::SOURCE_APP,
            'sviat_log_viewer__source_app',
            $rootDir . '/Okay/log/',
            '/^app-(\d{4}-\d{2}-\d{2})\.(log|txt)$/'
        );

        $this->sources[self::SOURCE_SCHEDULER] = new LogSource(
            self::SOURCE_SCHEDULER,
            'sviat_log_viewer__source_scheduler',
            $rootDir . '/Okay/log/scheduler/',
            '/^scheduler-(\d{4}-\d{2}-\d{2})\.(log|txt)$/'
        );
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function get(string $key): ?LogSource
    {
        return $this->sources[$key] ?? null;
    }

    public function getOrDefault(string $key): LogSource
    {
        return $this->sources[$key] ?? $this->sources[self::DEFAULT_SOURCE];
    }

    /** @return LogSource[] */
    public function all(): array
    {
        return $this->sources;
    }
}
