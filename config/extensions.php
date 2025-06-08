<?php

return [

    /*
    |----------------------------------------------------------------------
    | Protected extensions and automatic activation
    |----------------------------------------------------------------------
    | List of extensions that are forbidden to turn off or delete.
    */
    'protected' => [
        'Themer',
    ],

    /*
    |----------------------------------------------------------------------
    | Load order
    |----------------------------------------------------------------------
    | Hard order loading active extensions.
    | The extensions that are not here will be downloaded in an arbitrary order after the list.
    */
    'load_order' => [
        'Themer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions Paths
    |--------------------------------------------------------------------------
    | Specify one or more directories where your extensions are located.
    | For example: [base_path('Extensions'), resource_path('extensions')]
    */
    'paths' => [
        base_path('modules'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Activator Class
    |--------------------------------------------------------------------------
    | The class responsible for managing the activation and deactivation of extensions.
    | \Gigabait93\Extensions\Activators\DbActivator::class
    */
    'activator' => \Gigabait93\Extensions\Activators\FileActivator::class,

    /*
    |--------------------------------------------------------------------------
    | JSON File for File Storage
    |--------------------------------------------------------------------------
    */
    'json_file' => base_path('storage/extensions.json'),
];
