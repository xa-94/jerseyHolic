<?php

namespace App\Providers;

use App\Services\ProductMappingService;
use Illuminate\Support\ServiceProvider;

class ProductMappingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductMappingService::class, function ($app) {
            return new ProductMappingService();
        });
    }

    public function boot(): void
    {
        //
    }
}
