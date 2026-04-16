<?php

/**
 * Tenant Routes — 买家前台路由（需要租户上下文）。
 *
 * 这些路由运行在租户域名下（如 store1.jerseyholic.com），
 * 由 TenancyServiceProvider::mapTenantRoutes() 注册。
 * 请求会先经过 ResolveTenantByDomain 中间件识别租户，
 * 再经过 EnsureTenantContext 确保租户上下文已初始化。
 *
 * 数据库连接已自动切换到对应的租户数据库。
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController as BuyerAuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController as BuyerCategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ProductController as BuyerProductController;
use App\Http\Controllers\Api\OrderController as BuyerOrderController;

Route::prefix('api/v1')
    ->middleware(['force.json', 'set.locale'])
    ->group(function () {

        // ── Public routes (no auth required) ──

        Route::prefix('products')->group(function () {
            Route::get('/', [BuyerProductController::class, 'index']);
            Route::get('/search', [BuyerProductController::class, 'search']);
            Route::get('/category/{categoryId}', [BuyerProductController::class, 'byCategory']);
            Route::get('/{id}', [BuyerProductController::class, 'show']);
        });

        Route::prefix('categories')->group(function () {
            Route::get('', [BuyerCategoryController::class, 'index']);
            Route::get('{id}', [BuyerCategoryController::class, 'show']);
        });

        // Auth routes (public)
        Route::prefix('auth')->group(function () {
            Route::post('login', [BuyerAuthController::class, 'login']);
            Route::post('register', [BuyerAuthController::class, 'register']);
            Route::post('forgot-password', [BuyerAuthController::class, 'forgotPassword']);
            Route::post('reset-password', [BuyerAuthController::class, 'resetPassword']);
        });

        // Location data (public)
        Route::get('countries', [AddressController::class, 'countries']);
        Route::get('countries/{countryId}/zones', [AddressController::class, 'zones']);

        // Cart（支持游客和登录用户）
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/add', [CartController::class, 'add']);
            Route::put('/update', [CartController::class, 'update']);
            Route::delete('/{itemKey}', [CartController::class, 'remove']);
            Route::delete('/', [CartController::class, 'clear']);
            Route::get('/summary', [CartController::class, 'summary']);
        });

        // Checkout（支持游客和登录用户）
        Route::prefix('checkout')->group(function () {
            Route::post('/preview', [CheckoutController::class, 'preview']);
            Route::post('/submit', [CheckoutController::class, 'submit']);
        });

        // ── Protected routes (auth required) ──

        Route::middleware('auth:sanctum')->group(function () {

            Route::post('auth/logout', [BuyerAuthController::class, 'logout']);
            Route::get('auth/me', [BuyerAuthController::class, 'me']);

            Route::prefix('orders')->group(function () {
                Route::get('/', [BuyerOrderController::class, 'index']);
                Route::post('/', [BuyerOrderController::class, 'store']);
                Route::get('/{id}', [BuyerOrderController::class, 'show']);
                Route::post('/{id}/cancel', [BuyerOrderController::class, 'cancel']);
            });

            // Account
            Route::prefix('account')->group(function () {
                Route::get('profile', [AccountController::class, 'profile']);
                Route::put('profile', [AccountController::class, 'updateProfile']);
                Route::put('password', [AccountController::class, 'changePassword']);
                Route::get('orders', [AccountController::class, 'orderHistory']);

                // Addresses
                Route::prefix('addresses')->group(function () {
                    Route::get('/', [AddressController::class, 'index']);
                    Route::post('/', [AddressController::class, 'store']);
                    Route::get('/{id}', [AddressController::class, 'show']);
                    Route::put('/{id}', [AddressController::class, 'update']);
                    Route::delete('/{id}', [AddressController::class, 'destroy']);
                    Route::patch('/{id}/default', [AddressController::class, 'setDefault']);
                });
            });

            Route::prefix('wishlist')->group(function () {
                // TODO: WishlistController
            });
        });

        // Shipping rates (public)
        Route::post('shipping/rates', function () {
            // TODO: ShippingRateController
        });

        // Store info (public, tenant-specific)
        Route::get('store/info', function () {
            $store = request()->attributes->get('store');
            return response()->json([
                'success' => true,
                'data'    => [
                    'name'   => $store?->name ?? null,
                    'status' => $store?->status ?? null,
                ],
            ]);
        });
    });
