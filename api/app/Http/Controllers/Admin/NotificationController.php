<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\NotificationResource;
use App\Models\Central\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 — 通知管理 Controller（M3-022）
 *
 * 路由前缀：/api/v1/admin/notifications
 * 中间件：auth:sanctum + force.json + central.only
 */
class NotificationController extends BaseAdminController
{
    /**
     * 管理员通知列表
     *
     * GET /api/v1/admin/notifications
     *
     * Query params:
     *  - type      string  通知类型筛选（settlement/risk/blacklist/account/payment）
     *  - is_read   int     已读状态筛选（0=未读 / 1=已读）
     *  - per_page  int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $adminId = $request->user()->id;

        $query = Notification::query()
            ->forUser(Notification::USER_TYPE_ADMIN, $adminId);

        if ($request->filled('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', $request->integer('is_read'));
        }

        $paginator = $query->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginate($paginator->through(
            fn ($item) => new NotificationResource($item)
        ));
    }

    /**
     * 标记通知为已读
     *
     * PATCH /api/v1/admin/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $adminId = $request->user()->id;

        $notification = Notification::query()
            ->forUser(Notification::USER_TYPE_ADMIN, $adminId)
            ->findOrFail($id);

        $notification->markAsRead();

        return $this->success(
            new NotificationResource($notification->fresh()),
            '已标记为已读'
        );
    }
}
