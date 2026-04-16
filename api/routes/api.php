<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Load route groups
require __DIR__ . '/admin.php';
require __DIR__ . '/merchant.php';
require __DIR__ . '/buyer.php';
require __DIR__ . '/webhook.php';
