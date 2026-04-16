<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        $this->renderable(function (BusinessException $e) {
            return response()->json([
                'code'    => $e->getErrorCode()->value,
                'message' => $e->getMessage(),
                'data'    => null,
            ]);
        });

        $this->renderable(function (ValidationException $e) {
            return response()->json([
                'code'    => 42200,
                'message' => '参数验证失败',
                'data'    => ['errors' => $e->errors()],
            ], 422);
        });

        $this->renderable(function (AuthenticationException $e) {
            return response()->json([
                'code'    => 40100,
                'message' => '未认证',
                'data'    => null,
            ], 401);
        });

        $this->renderable(function (AuthorizationException $e) {
            return response()->json([
                'code'    => 40300,
                'message' => '无权限',
                'data'    => null,
            ], 403);
        });

        $this->renderable(function (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->json([
                'code'    => 40400,
                'message' => '资源不存在',
                'data'    => null,
            ], 404);
        });

        $this->renderable(function (Throwable $e) {
            if (app()->environment('production')) {
                return response()->json([
                    'code'    => 50000,
                    'message' => '系统错误',
                    'data'    => null,
                ], 500);
            }
        });
    }
}
