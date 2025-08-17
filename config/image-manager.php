<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk where images will be stored. This should be a disk configured
    | in your config/filesystems.php file. like r2, aws, etc..
    | R2 example would be like:
    | 'r2' => [
    |     'driver' => 's3',
    |     'key' => env('R2_ACCESS_KEY_ID'),
    |     'secret' => env('R2_SECRET_ACCESS_KEY'),
    |     'region' => 'auto',
    |     'bucket' => env('R2_BUCKET'),
    |     'url' => env('R2_CDN_URL'),
    |     'endpoint' => env('R2_ENDPOINT'),
    |     'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
    |     'throw' => false,
    | ],
    |
    */
    'storage_disk' => env('IMAGE_MANAGER_STORAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    |
    | The base path where images will be stored within the storage disk.
    |
    */
    'base_path' => env('IMAGE_MANAGER_BASE_PATH', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Image Format
    |--------------------------------------------------------------------------
    |
    | The format to convert images to. Options: 'webp', 'jpeg', 'png', 'original'
    | Using 'original' will keep the original format.
    |
    */
    'format' => env('IMAGE_MANAGER_FORMAT', 'webp'),

    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    |
    | The quality setting for compressed image formats (1-100).
    | Higher values mean better quality but larger file sizes.
    |
    */
    'quality' => env('IMAGE_MANAGER_QUALITY', 90),

    /*
    |--------------------------------------------------------------------------
    | Model Configurations
    |--------------------------------------------------------------------------
    |
    | Configure image handling for different models. Each model can have
    | multiple image types, and each type can have multiple sizes.
    |
    | Available resize modes:
    | - 'cover': Resize and crop to exact dimensions
    | - 'scale': Scale proportionally (may not match exact dimensions)
    | - 'resize': Resize to exact dimensions (may distort)
    |
    */
    'models' => [
        'user' => [
            'types' => [
                'avatar' => [
                    'sizes' => [
                        'thumbnail' => [
                            'width' => 100,
                            'height' => 100,
                            'mode' => 'cover',
                        ],
                        'medium' => [
                            'width' => 300,
                            'height' => 300,
                            'mode' => 'cover',
                        ],
                    ],
                ],
            ],
        ],
        // End of user model settings
    ],
];