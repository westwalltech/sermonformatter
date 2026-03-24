<?php

use Illuminate\Support\Facades\Route;
use NewSong\SermonFormatter\Http\Controllers\SermonFormatterController;

Route::prefix('sermon-formatter')->name('sermon-formatter.')->group(function () {
    // Routes that require 'view sermon formatter' permission
    Route::middleware('can:view sermon formatter')->group(function () {
        Route::get('/', [SermonFormatterController::class, 'dashboard'])->name('dashboard');
        Route::get('/stats', [SermonFormatterController::class, 'stats'])->name('stats');
        Route::get('/logs', [SermonFormatterController::class, 'logs'])->name('logs');
        Route::get('/logs/data', [SermonFormatterController::class, 'logsData'])->name('logs.data');
        Route::get('/status/{entryId}', [SermonFormatterController::class, 'status'])->name('status');
    });

    // Routes that require 'process sermons' permission
    Route::middleware('can:process sermons')->group(function () {
        Route::post('/analyze', [SermonFormatterController::class, 'analyze'])->name('analyze');
        Route::post('/confirm', [SermonFormatterController::class, 'confirm'])->name('confirm');
        Route::post('/cleanup-temp', [SermonFormatterController::class, 'cleanupTempFile'])->name('cleanup-temp');
        Route::post('/upload', [SermonFormatterController::class, 'upload'])->name('upload');
        Route::post('/reprocess/{entryId}', [SermonFormatterController::class, 'reprocess'])->name('reprocess');
        Route::post('/bulk-reprocess', [SermonFormatterController::class, 'bulkReprocess'])->name('bulk-reprocess');
    });

    // Routes that require 'manage sermon formatter settings' permission
    Route::middleware('can:manage sermon formatter settings')->group(function () {
        Route::get('/specs', [SermonFormatterController::class, 'specs'])->name('specs');
        Route::get('/specs/content', [SermonFormatterController::class, 'specsContent'])->name('specs.content');
        Route::post('/specs', [SermonFormatterController::class, 'saveSpecs'])->name('specs.save');
        Route::post('/test', [SermonFormatterController::class, 'test'])->name('test');
    });
});
