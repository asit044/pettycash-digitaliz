<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\RequestFileController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Volt::route('requests', 'pages.requests.index')->name('requests.index');
    Volt::route('requests/create', 'pages.requests.create')
        ->middleware('can:create-requests')
        ->name('requests.create');
    Volt::route('requests/{id}', 'pages.requests.show')->name('requests.show');

    Volt::route('admin', 'pages.admin.index')
        ->middleware('can:review-requests')
        ->name('admin.index');
    Volt::route('finance', 'pages.finance.index')
        ->middleware('can:process-requests')
        ->name('finance.index');
    Volt::route('reports', 'pages.reports.index')
        ->middleware('can:view-any-requests')
        ->name('reports.index');
    Volt::route('settings', 'pages.settings.index')
        ->middleware('can:manage-settings')
        ->name('settings.index');

    Route::get('requests/{id}/download/{file}', RequestFileController::class)
        ->name('requests.files.download');

    Route::get('exports.csv', [ExportController::class, 'csv'])
        ->middleware('can:export-reports')
        ->name('exports.csv');

    Route::get('exports.pdf', [ExportController::class, 'pdf'])
        ->middleware('can:export-reports')
        ->name('exports.pdf');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
