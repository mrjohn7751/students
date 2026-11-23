<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware(['can:view-personal-data', 'throttle:personal-data'])->group(function () {
        Route::get('/sifat/{check}', [QualityController::class, 'show'])->name('quality.show');

        Route::get('/talabalar', [StudentController::class, 'index'])->name('students.index');
        Route::get('/talabalar/{hemisId}', [StudentController::class, 'show'])->whereNumber('hemisId')->name('students.show');
    });

    Route::prefix('admin/foydalanuvchilar')->name('admin.users.')->middleware(['can:manage-users', 'throttle:personal-data'])->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/yangi', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/tahrirlash', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
    });

    Route::get('/sifat/{check}/export', [QualityController::class, 'export'])
        ->middleware(['can:export-data', 'throttle:exports'])->name('quality.export');
});
