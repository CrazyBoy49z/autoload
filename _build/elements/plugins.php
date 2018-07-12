<?php

return [
    'update' => [
        'file' => 'update',
        'description' => '',
        'events' => [
            'OnBeforeCacheUpdate' => []
        ],
    ],
    'autoload.routeEvent' => [
        'file' => 'routeEvent',
        'description' => '',
        'events' => [
            'OnMODXInit' => []
        ],
    ],
];
