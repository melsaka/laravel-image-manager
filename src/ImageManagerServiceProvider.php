<?php

namespace Melsaka\ImageManager;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Melsaka\ImageManager\Services\ImageManagerService;

class ImageManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/image-manager.php',
            'image-manager'
        );

        $this->app->singleton(ImageManagerService::class);
    }

    public function boot(): void
    {
        $files = [
            __DIR__.'/../config/image-manager.php' => config_path('image-manager.php'),
        ];

        $pattern = database_path('migrations/*_create_images_table.php');

        if (count(glob($pattern)) === 0) {
            $files[__DIR__.'/../database/migrations/create_images_table.php.stub'] = database_path('migrations/'.date('Y_m_d_His', time()).'_create_images_table.php');
        }
        
        if ($this->app->runningInConsole()) {
            $this->publishes($files, 'image-manager');
        }
    }
}