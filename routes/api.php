<?php

use App\Adapters\Http\Controllers\AuthController;
use App\Adapters\Http\Controllers\BookController;
use App\Adapters\Http\Controllers\HealthController;
use App\Adapters\Http\Controllers\LoanController;
use App\Adapters\Http\Controllers\MemberController;
use App\Adapters\Http\Controllers\ReportController;
use App\Adapters\Http\Middleware\Authenticate;
use App\Adapters\Http\Middleware\RequireRole;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(Authenticate::class)->group(function (): void {
    // Any authenticated user
    Route::get('/categories', [BookController::class, 'categories']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/books/{id}/cover', [BookController::class, 'cover']);

    // admin only
    Route::middleware(RequireRole::class . ':admin')->group(function (): void {
        Route::post('/users', [AuthController::class, 'createUser']);
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::put('/books/{id}/cover', [BookController::class, 'setCover']);
        Route::delete('/books/{id}/cover', [BookController::class, 'removeCover']);
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });

    // admin and librarian
    Route::middleware(RequireRole::class . ':admin,librarian')->group(function (): void {
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        // "/loans/overdue" must be registered before "/loans/{id}".
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem']);
    });
});
