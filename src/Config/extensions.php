<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Extensions Paths
    |--------------------------------------------------------------------------
    | Specify one or more directories where your extensions are located.
    | For example: [base_path('Extensions'), resource_path('extensions')]
    */
    'extensions_paths' => [
        base_path('modules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Activator Class
    |--------------------------------------------------------------------------
    | The class responsible for managing the activation and deactivation of extensions.
    */
    'activator' => \Gigabait93\Extensions\Activators\FileActivator::class,

    /*
    |--------------------------------------------------------------------------
    | JSON File for File Storage
    |--------------------------------------------------------------------------
    */
    'json_file' => base_path('extensions.json'),
];
