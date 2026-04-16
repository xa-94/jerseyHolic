<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/merchant')
    ->middleware(['auth:sanctum', 'force.json'])
    ->group(function () {

        // Shop Management
        Route::prefix('shop')->group(function () {
            // TODO: MerchantShopController
        });

        // Products
        Route::prefix('products')->group(function () {
            // TODO: MerchantProductController
        });

        // Orders (read-only)
        Route::prefix('orders')->group(function () {
            // TODO: MerchantOrderController
        });

        // Settlements
        Route::prefix('settlements')->group(function () {
            // TODO: MerchantSettlementController
        });
    });
