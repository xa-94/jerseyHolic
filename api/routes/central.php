<?php

/**
 * Central Routes — 平台管理 & 商户后台路由。
 *
 * 这些路由仅在 Central 域名（admin.jerseyholic.com、localhost 等）生效，
 * 由 TenancyServiceProvider::mapCentralRoutes() 注册，自动绑定 Central 域名。
 * 不经过租户识别中间件。
 *
 * 包含：
 *  - 平台管理员 API（/api/v1/admin/…）
 *  - 商户后台 API（/api/v1/merchant/…）
 *  - Webhook 回调（/api/v1/webhook/…）
 */

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes — 平台管理员 API
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Admin\StoreController as AdminStoreController;
use App\Http\Controllers\Admin\PaymentAccountGroupController;
use App\Http\Controllers\Admin\PaymentAccountController;
use App\Http\Controllers\Admin\PaymentGroupMappingController;
use App\Http\Controllers\Admin\BlacklistController;
use App\Http\Controllers\Admin\CommissionRuleController;
use App\Http\Controllers\Admin\SafeDescriptionController;
use App\Http\Controllers\Admin\RiskDashboardController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Merchant\SettlementController as MerchantSettlementController;
use App\Http\Controllers\Merchant\NotificationController as MerchantNotificationController;

// M4 Controllers
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\CategorySafeNameController;
use App\Http\Controllers\Admin\SensitiveBrandController;
use App\Http\Controllers\Admin\SyncMonitorController;
use App\Http\Controllers\Merchant\ProductController as MerchantProductController;
use App\Http\Controllers\Merchant\SyncRuleController;
use App\Http\Controllers\Merchant\SyncTriggerController;
use App\Http\Controllers\Merchant\SyncLogController;
use App\Http\Controllers\Merchant\StoreProductConfigController;

// Admin auth (public)
Route::prefix('api/v1/admin/auth')->middleware(['force.json'])->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
});

// Admin protected routes
Route::prefix('api/v1/admin')
    ->middleware(['auth:sanctum', 'force.json', 'central.only'])
    ->group(function () {

        // Auth management
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('auth/me', [AdminAuthController::class, 'me']);

        // Dashboard
        Route::get('dashboard', function () {
            return response()->json([
                'code'    => 0,
                'message' => 'success',
                'data'    => ['section' => 'admin dashboard'],
            ]);
        });

        // Products
        Route::prefix('products')->group(function () {
            Route::get('/', [AdminProductController::class, 'index']);
            Route::post('/', [AdminProductController::class, 'store']);
            Route::get('/{id}', [AdminProductController::class, 'show']);
            Route::put('/{id}', [AdminProductController::class, 'update']);
            Route::delete('/{id}', [AdminProductController::class, 'destroy']);
            Route::patch('/{id}/stock', [AdminProductController::class, 'updateStock']);
            Route::patch('/{id}/toggle-status', [AdminProductController::class, 'toggleStatus']);
            Route::post('/bulk-delete', [AdminProductController::class, 'bulkDelete']);
            Route::post('/bulk-status', [AdminProductController::class, 'bulkUpdateStatus']);
            Route::get('/export', [AdminProductController::class, 'export']);
        });

        // Categories
        Route::prefix('categories')->group(function () {
            Route::get('tree', [AdminCategoryController::class, 'tree']);
            Route::post('reorder', [AdminCategoryController::class, 'reorder']);
            Route::get('', [AdminCategoryController::class, 'index']);
            Route::post('', [AdminCategoryController::class, 'store']);
            Route::get('{id}', [AdminCategoryController::class, 'show']);
            Route::put('{id}', [AdminCategoryController::class, 'update']);
            Route::patch('{id}', [AdminCategoryController::class, 'update']);
            Route::delete('{id}', [AdminCategoryController::class, 'destroy']);
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('/export', [AdminOrderController::class, 'export']);
            Route::get('/{id}', [AdminOrderController::class, 'show']);
            Route::patch('/{id}/pay-status', [AdminOrderController::class, 'updatePayStatus']);
            Route::patch('/{id}/ship-status', [AdminOrderController::class, 'updateShipStatus']);
            Route::post('/{id}/refund', [AdminOrderController::class, 'refund']);
            Route::post('/{id}/history', [AdminOrderController::class, 'addHistory']);
        });

        // Payment Account Groups (M3-003)
        Route::prefix('payment-account-groups')->group(function () {
            Route::get('/',          [PaymentAccountGroupController::class, 'index']);
            Route::post('/',         [PaymentAccountGroupController::class, 'store']);
            Route::get('/{id}',      [PaymentAccountGroupController::class, 'show']);
            Route::put('/{id}',      [PaymentAccountGroupController::class, 'update']);
            Route::delete('/{id}',   [PaymentAccountGroupController::class, 'destroy']);
        });

        // Payment Accounts (M3-003)
        Route::prefix('payment-accounts')->group(function () {
            Route::get('/',              [PaymentAccountController::class, 'index']);
            Route::post('/',             [PaymentAccountController::class, 'store']);
            Route::get('/{id}',          [PaymentAccountController::class, 'show']);
            Route::put('/{id}',          [PaymentAccountController::class, 'update']);
            Route::patch('/{id}/status', [PaymentAccountController::class, 'toggleStatus']);
            Route::delete('/{id}',       [PaymentAccountController::class, 'destroy']);
        });

        // Product Mappings (P0 Security)
        Route::prefix('product-mappings')->group(function () {
            // TODO: ProductMappingController
        });

        // Customers
        Route::prefix('customers')->group(function () {
            // TODO: CustomerController
        });

        // Shipments
        Route::prefix('shipments')->group(function () {
            // TODO: ShipmentController
        });

        // Merchants / Stores Management (multi-tenant admin)
        Route::prefix('merchants')->group(function () {
            Route::get('/',              [AdminMerchantController::class, 'index']);
            Route::post('/',             [AdminMerchantController::class, 'store']);
            Route::get('/{id}',          [AdminMerchantController::class, 'show']);
            Route::put('/{id}',          [AdminMerchantController::class, 'update']);
            Route::delete('/{id}',       [AdminMerchantController::class, 'destroy']);
            Route::patch('/{id}/status', [AdminMerchantController::class, 'changeStatus']);
            Route::patch('/{id}/level',  [AdminMerchantController::class, 'updateLevel']);
            Route::post('/{id}/review',  [AdminMerchantController::class, 'review']);

            // Merchant Payment Group Mappings (M3-004)
            Route::prefix('{id}/payment-group-mappings')->group(function () {
                Route::get('/',              [PaymentGroupMappingController::class, 'index']);
                Route::post('/',             [PaymentGroupMappingController::class, 'store']);
                Route::put('/{mappingId}',   [PaymentGroupMappingController::class, 'update']);
                Route::delete('/{mappingId}',[PaymentGroupMappingController::class, 'destroy']);
            });
        });

        // Stores Management (M2-003)
        Route::prefix('stores')->group(function () {
            Route::get('/',                          [AdminStoreController::class, 'index']);
            Route::post('/',                         [AdminStoreController::class, 'store']);
            Route::get('/{id}',                      [AdminStoreController::class, 'show']);
            Route::put('/{id}',                      [AdminStoreController::class, 'update']);
            Route::delete('/{id}',                   [AdminStoreController::class, 'destroy']);
            Route::patch('/{id}/status',              [AdminStoreController::class, 'updateStatus']);
            Route::patch('/{id}/categories',          [AdminStoreController::class, 'updateCategories']);
            Route::patch('/{id}/markets',             [AdminStoreController::class, 'updateMarkets']);
            Route::patch('/{id}/languages',           [AdminStoreController::class, 'updateLanguages']);
            Route::patch('/{id}/currencies',          [AdminStoreController::class, 'updateCurrencies']);
            Route::patch('/{id}/payment-accounts',    [AdminStoreController::class, 'updatePaymentAccounts']);
            Route::patch('/{id}/logistics',           [AdminStoreController::class, 'updateLogistics']);
            Route::post('/{id}/domains',              [AdminStoreController::class, 'addDomain']);
            Route::delete('/{id}/domains/{domainId}', [AdminStoreController::class, 'removeDomain']);
        });

        Route::prefix('settlements')->group(function () {
            Route::get('/',           [\App\Http\Controllers\Admin\SettlementController::class, 'index']);
            Route::get('/{id}',       [\App\Http\Controllers\Admin\SettlementController::class, 'show']);
            Route::post('/generate',  [\App\Http\Controllers\Admin\SettlementController::class, 'generate']);

            // 审核流程（M3-014）
            Route::post('/{id}/submit-review', [\App\Http\Controllers\Admin\SettlementController::class, 'submitReview']);
            Route::post('/{id}/approve',       [\App\Http\Controllers\Admin\SettlementController::class, 'approve']);
            Route::post('/{id}/reject',        [\App\Http\Controllers\Admin\SettlementController::class, 'reject']);
            Route::post('/{id}/mark-paid',     [\App\Http\Controllers\Admin\SettlementController::class, 'markPaid']);
            Route::post('/{id}/cancel',        [\App\Http\Controllers\Admin\SettlementController::class, 'cancel']);
        });

        // 退款影响管理（M3-015）
        Route::prefix('refund-impact')->group(function () {
            Route::get('/merchant/{merchantId}', [\App\Http\Controllers\Admin\RefundImpactController::class, 'summary']);
            Route::post('/process',              [\App\Http\Controllers\Admin\RefundImpactController::class, 'process']);
        });

        // 黑名单管理（M3-018）
        Route::apiResource('blacklist', BlacklistController::class);

        // 风控仪表板（M3-016）
        Route::prefix('risk')->group(function () {
            Route::get('dashboard', [RiskDashboardController::class, 'dashboard']);
            Route::get('merchants/{merchantId}/score', [RiskDashboardController::class, 'merchantScore']);
        });

        // 佣金规则管理（M3-012）
        Route::apiResource('commission-rules', CommissionRuleController::class);

        // 安全描述管理（M3-010）
        Route::apiResource('safe-descriptions', SafeDescriptionController::class);

        // 通知管理（M3-022）
        Route::prefix('notifications')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index']);
            Route::patch('/{id}/read', [AdminNotificationController::class, 'markAsRead']);
        });

        // RBAC Management
        Route::prefix('rbac')->middleware('check.permission:rbac.manage')->group(function () {
            Route::get('roles', [\App\Http\Controllers\Admin\RbacController::class, 'roleIndex']);
            Route::post('roles', [\App\Http\Controllers\Admin\RbacController::class, 'roleStore']);
            Route::put('roles/{id}', [\App\Http\Controllers\Admin\RbacController::class, 'roleUpdate']);
            Route::delete('roles/{id}', [\App\Http\Controllers\Admin\RbacController::class, 'roleDestroy']);

            Route::get('permissions', [\App\Http\Controllers\Admin\RbacController::class, 'permissionIndex']);
            Route::get('permissions/tree', [\App\Http\Controllers\Admin\RbacController::class, 'permissionTree']);

            Route::post('admins/{id}/roles', [\App\Http\Controllers\Admin\RbacController::class, 'assignRoles']);
            Route::get('admins/{id}/permissions', [\App\Http\Controllers\Admin\RbacController::class, 'adminPermissions']);
        });

        // Settings
        Route::prefix('settings')->group(function () {
            // TODO: SettingController
        });

        // Facebook Pixel
        Route::prefix('fb-pixels')->group(function () {
            // TODO: FbPixelController
        });

        // Operation Logs
        Route::prefix('logs')->group(function () {
            // TODO: OperationLogController
        });

        // ============================================================
        // M4 品类管理
        // ============================================================
        Route::prefix('product-categories')->group(function () {
            // L1 品类
            Route::get('l1',        [ProductCategoryController::class, 'l1Index']);
            Route::post('l1',       [ProductCategoryController::class, 'l1Store']);
            Route::get('l1/{id}',   [ProductCategoryController::class, 'showL1']);
            Route::put('l1/{id}',   [ProductCategoryController::class, 'l1Update']);
            Route::delete('l1/{id}',[ProductCategoryController::class, 'l1Destroy']);

            // L2 品类
            Route::get('l2',        [ProductCategoryController::class, 'l2Index']);
            Route::post('l2',       [ProductCategoryController::class, 'l2Store']);
            Route::get('l2/{id}',   [ProductCategoryController::class, 'showL2']);
            Route::put('l2/{id}',   [ProductCategoryController::class, 'l2Update']);
            Route::delete('l2/{id}',[ProductCategoryController::class, 'l2Destroy']);

            // 品类树
            Route::get('tree',      [ProductCategoryController::class, 'tree']);
        });

        // M4 安全映射名称管理
        Route::prefix('category-safe-names')->group(function () {
            Route::get('/',           [CategorySafeNameController::class, 'index']);
            Route::post('/',          [CategorySafeNameController::class, 'store']);
            Route::put('/{id}',       [CategorySafeNameController::class, 'update']);
            Route::delete('/{id}',    [CategorySafeNameController::class, 'destroy']);
            Route::post('/clear-cache', [CategorySafeNameController::class, 'clearCache']);
            Route::post('/preview',   [CategorySafeNameController::class, 'preview']);
        });

        // M4 敏感品牌管理
        Route::prefix('sensitive-brands')->group(function () {
            Route::get('/',           [SensitiveBrandController::class, 'index']);
            Route::post('/',          [SensitiveBrandController::class, 'store']);
            Route::get('/{id}',       [SensitiveBrandController::class, 'show']);
            Route::put('/{id}',       [SensitiveBrandController::class, 'update']);
            Route::delete('/{id}',    [SensitiveBrandController::class, 'destroy']);
            Route::post('/check',     [SensitiveBrandController::class, 'check']);
        });

        // M4 同步监控（管理端）
        Route::prefix('sync-monitor')->group(function () {
            Route::get('overview',                [SyncMonitorController::class, 'overview']);
            Route::get('merchants/{id}/stats',    [SyncMonitorController::class, 'merchantStats']);
            Route::get('recent-failures',         [SyncMonitorController::class, 'recentFailures']);
        });
    });

/*
|--------------------------------------------------------------------------
| Merchant Routes — 公开注册
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Merchant\RegisterController as MerchantRegisterController;

// 商户公开注册（无需认证）
Route::prefix('api/v1/merchant')
    ->middleware(['force.json'])
    ->group(function () {
        Route::post('register', [MerchantRegisterController::class, 'register']);
    });

/*
|--------------------------------------------------------------------------
| Merchant Routes — 商户后台 API
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\Merchant\ApiKeyController;
use App\Http\Controllers\Merchant\UserController as MerchantUserController;
use App\Http\Controllers\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Merchant\OrderController as MerchantOrderController;

// Merchant Auth (public — 无需认证)
Route::prefix('api/v1/merchant/auth')->middleware(['force.json'])->group(function () {
    Route::post('login', [MerchantAuthController::class, 'login']);
});

// Merchant protected routes
Route::prefix('api/v1/merchant')
    ->middleware(['auth:merchant', 'force.json', 'central.only'])
    ->group(function () {

        // Auth management
        Route::post('auth/logout',  [MerchantAuthController::class, 'logout']);
        Route::get('auth/me',      [MerchantAuthController::class, 'me']);
        Route::post('auth/refresh', [MerchantAuthController::class, 'refresh']);

        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/',                   [MerchantUserController::class, 'index']);
            Route::post('/',                  [MerchantUserController::class, 'store']);
            Route::get('/{id}',               [MerchantUserController::class, 'show']);
            Route::put('/{id}',               [MerchantUserController::class, 'update']);
            Route::delete('/{id}',            [MerchantUserController::class, 'destroy']);
            Route::patch('/{id}/password',    [MerchantUserController::class, 'changePassword']);
            Route::patch('/{id}/permissions', [MerchantUserController::class, 'updatePermissions']);
            Route::post('/{id}/unlock',       [MerchantUserController::class, 'unlock']);
        });
        // Shop Management
        Route::prefix('shop')->group(function () {
            // TODO: MerchantShopController
        });

        // M4 主商品管理
        Route::prefix('products')->group(function () {
            Route::get('/',              [MerchantProductController::class, 'index']);
            Route::post('/',             [MerchantProductController::class, 'store']);
            Route::get('/{id}',          [MerchantProductController::class, 'show']);
            Route::put('/{id}',          [MerchantProductController::class, 'update']);
            Route::delete('/{id}',       [MerchantProductController::class, 'destroy']);
            Route::post('/batch-delete', [MerchantProductController::class, 'batchDelete']);
            Route::post('/batch-status', [MerchantProductController::class, 'batchStatus']);
        });

        // M4 同步规则
        Route::prefix('sync-rules')->group(function () {
            Route::get('/',          [SyncRuleController::class, 'index']);
            Route::post('/',         [SyncRuleController::class, 'store']);
            Route::get('/{id}',      [SyncRuleController::class, 'show']);
            Route::put('/{id}',      [SyncRuleController::class, 'update']);
            Route::delete('/{id}',   [SyncRuleController::class, 'destroy']);
        });

        // M4 同步触发
        Route::prefix('sync')->group(function () {
            Route::post('single/{masterProductId}',  [SyncTriggerController::class, 'syncSingle']);
            Route::post('batch',                     [SyncTriggerController::class, 'syncBatch']);
            Route::post('full/{storeId}',            [SyncTriggerController::class, 'syncFull']);
            Route::post('incremental/{storeId}',     [SyncTriggerController::class, 'syncIncremental']);
        });

        // M4 同步日志
        Route::prefix('sync-logs')->group(function () {
            Route::get('/',              [SyncLogController::class, 'index']);
            Route::get('/stats/{storeId}', [SyncLogController::class, 'stats']);
            Route::get('/trend/{storeId}', [SyncLogController::class, 'trend']);
            Route::post('/{id}/retry',   [SyncLogController::class, 'retry']);
        });

        // M4 站点商品配置
        Route::prefix('store-configs')->group(function () {
            Route::get('/{storeId}',          [StoreProductConfigController::class, 'show']);
            Route::put('/{storeId}',          [StoreProductConfigController::class, 'update']);
            Route::post('/{storeId}/preview', [StoreProductConfigController::class, 'preview']);
        });

        // Dashboard
        Route::get('dashboard', [MerchantDashboardController::class, 'index']);
        Route::get('stores', [MerchantDashboardController::class, 'stores']);

        // Orders (read-only)
        Route::prefix('orders')->group(function () {
            Route::get('/', [MerchantOrderController::class, 'index']);
            Route::get('/{id}', [MerchantOrderController::class, 'show']);
        });

        // Settlements（M3-013 商户端结算查看）
        Route::prefix('settlements')->group(function () {
            Route::get('/', [MerchantSettlementController::class, 'index']);
            Route::get('/{id}', [MerchantSettlementController::class, 'show']);
        });

        // 通知管理（M3-022 商户端）
        Route::prefix('notifications')->group(function () {
            Route::get('/', [MerchantNotificationController::class, 'index']);
            Route::patch('/{id}/read', [MerchantNotificationController::class, 'markAsRead']);
        });

        // API Keys (RSA 密钥管理)
        Route::prefix('api-keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index']);
            Route::post('/', [ApiKeyController::class, 'store']);
            Route::post('/download', [ApiKeyController::class, 'download']);
            Route::get('/{keyId}', [ApiKeyController::class, 'show']);
            Route::post('/{keyId}/rotate', [ApiKeyController::class, 'rotate']);
            Route::delete('/{keyId}', [ApiKeyController::class, 'revoke']);
        });
    });

/*
|--------------------------------------------------------------------------
| Webhook Routes — 第三方回调（不需认证）
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1/webhook')
    ->middleware(['force.json'])
    ->withoutMiddleware(['auth'])
    ->group(function () {

        // PayPal Webhook（M3-008）
        Route::post('paypal', [\App\Http\Controllers\Api\PaymentController::class, 'paypalWebhook'])
            ->middleware('verify.paypal.webhook')
            ->name('webhook.paypal');

        // Stripe Webhook（M3-009）
        Route::post('stripe', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handle'])
            ->middleware('verify.stripe.webhook')
            ->name('webhook.stripe');

        // Logistics Provider Callbacks
        Route::post('logistics/{provider}/callback', function (string $provider) {
            // TODO: LogisticsWebhookController@handle
        })->name('webhook.logistics');

        // Antom Payment Callback
        Route::post('antom/notify', function () {
            // TODO: AntomWebhookController@handle
        })->name('webhook.antom');
    });
