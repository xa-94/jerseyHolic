<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;

// Admin auth (no auth required)
Route::prefix('api/v1/admin/auth')->middleware(['force.json'])->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
});

Route::prefix('api/v1/admin')
    ->middleware(['auth:sanctum', 'force.json'])
    ->group(function () {

        // Auth management
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('auth/me', [AdminAuthController::class, 'me']);

        // Dashboard
        Route::get('dashboard', function () {
            return response()->json(['code' => 0, 'message' => 'success', 'data' => ['section' => 'admin dashboard']]);
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
            Route::get('/',           [AdminOrderController::class, 'index']);
            Route::get('/export',     [AdminOrderController::class, 'export']);
            Route::get('/{id}',       [AdminOrderController::class, 'show']);
            Route::patch('/{id}/pay-status',  [AdminOrderController::class, 'updatePayStatus']);
            Route::patch('/{id}/ship-status', [AdminOrderController::class, 'updateShipStatus']);
            Route::post('/{id}/refund',       [AdminOrderController::class, 'refund']);
            Route::post('/{id}/history',      [AdminOrderController::class, 'addHistory']);
        });

        // Payment Accounts
        Route::prefix('payment-accounts')->group(function () {
            // TODO: Route::apiResource('', PaymentAccountController::class);
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

        // Merchants
        Route::prefix('merchants')->group(function () {
            // TODO: MerchantController
        });

        // RBAC Management
        Route::prefix('rbac')->middleware('check.permission:rbac.manage')->group(function () {
            // Roles
            Route::get('roles', [\App\Http\Controllers\Admin\RbacController::class, 'roleIndex']);
            Route::post('roles', [\App\Http\Controllers\Admin\RbacController::class, 'roleStore']);
            Route::put('roles/{id}', [\App\Http\Controllers\Admin\RbacController::class, 'roleUpdate']);
            Route::delete('roles/{id}', [\App\Http\Controllers\Admin\RbacController::class, 'roleDestroy']);

            // Permissions
            Route::get('permissions', [\App\Http\Controllers\Admin\RbacController::class, 'permissionIndex']);
            Route::get('permissions/tree', [\App\Http\Controllers\Admin\RbacController::class, 'permissionTree']);

            // Admin role assignment
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
    });
