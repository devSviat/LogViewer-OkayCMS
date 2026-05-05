<?php

use Okay\Core\OkayContainer\Reference\ParameterReference as PR;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\Sviat\LogViewer\Services\LogParser;
use Okay\Modules\Sviat\LogViewer\Services\LogService;
use Okay\Modules\Sviat\LogViewer\Services\LogSourceRegistry;

return [
    LogSourceRegistry::class => [
        'class' => LogSourceRegistry::class,
        'arguments' => [
            new PR('root_dir'),
        ],
    ],

    LogParser::class => [
        'class' => LogParser::class,
    ],

    LogService::class => [
        'class' => LogService::class,
        'arguments' => [
            new SR(LogSourceRegistry::class),
            new SR(LogParser::class),
        ],
    ],
];
