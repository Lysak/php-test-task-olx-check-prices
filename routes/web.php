<?php

use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => response()->json(['service' => 'OLX Price Tracker']));

Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
Route::get('/verify/{token}', [SubscriptionController::class, 'verify'])->name('verify');

// Dev-only route for manual testing
if (app()->isLocal()) {
    Route::get('/test/subscribe', [TestController::class, 'subscribe'])->name('test.subscribe');
}
