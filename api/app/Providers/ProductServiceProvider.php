<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Merchant\MasterProduct;
use App\Observers\MasterProductObserver;

class ProductServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/product-sync.php'), 'product-sync'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 注册 MasterProduct Observer
        MasterProduct::observe(MasterProductObserver::class);
    }
}
