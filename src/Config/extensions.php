<?php
// src/Config/extensions.php

return [
    /*
    |--------------------------------------------------------------------------
    | Extensions Paths
    |--------------------------------------------------------------------------
    | Specify one or more directories where your extensions are located.
    | For example: [base_path('Extensions'), resource_path('extensions')]
    */
    'extensions_paths' => [
        base_path('Modules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Method for Extension Statuses
    |--------------------------------------------------------------------------
    | Possible values: "database" or "file".
    | The default value is determined by the environment variable EXTENSIONS_ACTIVATOR.
    */
    'storage' => env('EXTENSIONS_ACTIVATOR', 'file'),

    /*
    |--------------------------------------------------------------------------
    | JSON File for File Storage
    |--------------------------------------------------------------------------
    */
    'json_file' => storage_path('extensions.json'),

    /*
    |--------------------------------------------------------------------------
    | Table for Database Storage
    |--------------------------------------------------------------------------
    */
    'table' => 'extensions',
];
