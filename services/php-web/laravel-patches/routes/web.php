<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RateLimitMiddleware;

Route::get('/', fn() => redirect('/dashboard'));

// Панели (с rate-limit)
Route::middleware([RateLimitMiddleware::class . ':60'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);
    Route::get('/astronomy', [\App\Http\Controllers\AstronomyController::class, 'index']);
    Route::get('/iss', [\App\Http\Controllers\IssController::class, 'index']);
    Route::get('/osdr', [\App\Http\Controllers\OsdrController::class, 'index']);
    Route::get('/telemetry', [\App\Http\Controllers\TelemetryController::class, 'index']);
    Route::get('/telemetry/export', [\App\Http\Controllers\TelemetryController::class, 'export']);
    Route::get('/cms', [\App\Http\Controllers\CmsController::class, 'index']);
    Route::get('/page/{slug}', [\App\Http\Controllers\CmsController::class, 'page']);
});

// API (с более строгим rate-limit)
Route::middleware([RateLimitMiddleware::class . ':120'])->group(function () {
    Route::get('/api/iss/last', [\App\Http\Controllers\ProxyController::class, 'last']);
    Route::get('/api/iss/trend', [\App\Http\Controllers\ProxyController::class, 'trend']);
    Route::get('/api/jwst/feed', [\App\Http\Controllers\DashboardController::class, 'jwstFeed']);
    Route::get('/api/astro/events', [\App\Http\Controllers\AstroController::class, 'events']);
});
