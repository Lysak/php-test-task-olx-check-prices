<?php

use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => response()->json(['service' => 'OLX Price Tracker']));

Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
Route::get('/verify/{token}', [SubscriptionController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
Route::get('/unsubscribe/{token}', [SubscriptionController::class, 'unsubscribe'])->name('unsubscribe');

// Dev-only route for manual testing
if (app()->isLocal()) {
    Route::get('/test/subscribe', [TestController::class, 'subscribe'])->name('test.subscribe');
}
