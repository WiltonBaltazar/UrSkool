<?php

use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\StudentProgressController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')->group(function (): void {
    Route::get('/user', [SessionController::class, 'user']);
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/refresh', [SessionController::class, 'refresh'])->middleware('throttle:30,1');
    Route::post('/logout', [SessionController::class, 'destroy'])->middleware('throttle:30,1');
});

Route::prefix('api')->group(function (): void {
    Route::post('/checkout', [EnrollmentController::class, 'store'])->middleware('throttle:15,1');
});

Route::middleware('auth:sanctum')->prefix('api')->group(function (): void {
    Route::get('/courses/{course}/access', [CourseController::class, 'access']);
    Route::get('/student/courses/{course}', [CourseController::class, 'studentShow']);
    Route::put('/student/courses/{course}/lessons/{lesson}/progress', [StudentProgressController::class, 'upsert']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('api/admin')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/courses', [AdminCourseController::class, 'index']);
    Route::post('/courses', [AdminCourseController::class, 'store']);
    Route::put('/courses/{course}', [AdminCourseController::class, 'update']);
    Route::delete('/courses/{course}', [AdminCourseController::class, 'destroy']);

    Route::get('/users', [UserController::class, 'index']);
    Route::put('/users/{user}', [UserController::class, 'update']);

    Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);
});

Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
