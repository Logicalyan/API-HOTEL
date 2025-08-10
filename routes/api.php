<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
    Route::get('/user', 'user')->middleware('auth:sanctum');
    Route::post('/reset-password-request', 'resetPasswordRequest');
    Route::post('/verify-otp', 'verifyOTP');
    Route::post('/reset-password', 'resetPassword');
});
