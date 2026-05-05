<?php

namespace Okay\Modules\Sviat\LogViewer\Services;

class LogSource
{
    /** @var string */
    private $key;

    /** @var string */
    private $labelKey;

    /** @var string */
    private $directory;

    /** @var string regex with one capture group for date, applied to filename */
    private $fileNameRegex;

    public function __construct(
        string $key,
        string $labelKey,
        string $directory,
        string $fileNameRegex
    ) {
        $this->key = $key;
        $this->labelKey = $labelKey;
        $this->directory = rtrim($directory, '/') . '/';
        $this->fileNameRegex = $fileNameRegex;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabelKey(): string
    {
        return $this->labelKey;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getFileNameRegex(): string
    {
        return $this->fileNameRegex;
    }
}
