<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "s3", "rackspace"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        'fullsize' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/fullsize'),
            'url' => env('APP_URL').'/diyhistory/archive/fullsize/',
            'visibility' => 'public',
        ],

        'thumbnails' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/square_thumbnails/'),
            'url' => env('APP_URL').'/diyhistory/archive/square_thumbnails/',
            'visibility' => 'public',
        ],

        'xml_public' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/xml/public'),
            'url' => env('APP_URL').'/diyhistory/archive/xml/public',
            'visibility' => 'public',
        ],

        'xml_source' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/xml/source'),
            'url' => env('APP_URL').'/diyhistory/archive/xml/source',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_KEY'),
            'secret' => env('AWS_SECRET'),
            'region' => env('AWS_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ],

    ],

];
