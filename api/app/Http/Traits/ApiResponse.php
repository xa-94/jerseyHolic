<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success($data = null, string $message = 'success'): JsonResponse
    {
        return response()->json([
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    protected function error(int $code = 50000, string $message = 'error', $data = null): JsonResponse
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    protected function paginate($paginator): JsonResponse
    {
        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => $paginator->items(),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}
