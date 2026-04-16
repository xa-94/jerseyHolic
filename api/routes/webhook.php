<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/webhook')
    ->middleware(['force.json'])
    ->withoutMiddleware(['auth'])
    ->group(function () {

        // PayPal IPN/Webhook
        Route::post('paypal/ipn', function () {
            // TODO: PayPalWebhookController@handleIpn
        })->name('webhook.paypal.ipn');

        Route::post('paypal/webhook', function () {
            // TODO: PayPalWebhookController@handleWebhook
        })->name('webhook.paypal.webhook');

        // Stripe Webhook
        Route::post('stripe/webhook', function () {
            // TODO: StripeWebhookController@handle
        })->name('webhook.stripe');

        // Logistics Provider Callbacks
        Route::post('logistics/{provider}/callback', function (string $provider) {
            // TODO: LogisticsWebhookController@handle
        })->name('webhook.logistics');

        // Antom Payment Callback
        Route::post('antom/notify', function () {
            // TODO: AntomWebhookController@handle
        })->name('webhook.antom');
    });
