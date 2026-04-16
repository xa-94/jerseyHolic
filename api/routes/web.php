<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'code'    => 0,
        'message' => 'success',
        'data'    => [
            'name'    => 'JerseyHolic Unified Commerce API',
            'version' => '1.0.0',
            'status'  => 'healthy',
        ],
    ]);
});
