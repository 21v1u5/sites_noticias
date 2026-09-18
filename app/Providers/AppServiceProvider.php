<?php

namespace App\Providers;

use App\Contracts\ImageProvider;
use App\Services\Images\PexelsImageProvider;
use App\Services\Images\UnsplashImageProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageProvider::class, function () {
            return match (config('services.images.provider')) {
                'pexels' => $this->app->make(PexelsImageProvider::class),
                default => $this->app->make(UnsplashImageProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
