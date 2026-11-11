<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DiscrepancyReportController;
use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');

    Route::get('/reports/discrepancy', [DiscrepancyReportController::class, 'index'])->name('reports.discrepancy');

    Route::middleware('admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/discrepancy/export', [DiscrepancyReportController::class, 'export'])->name('reports.discrepancy.export');
        Route::get('/reports/discrepancy/loans', [DiscrepancyReportController::class, 'getLoansByBorrower'])->name('reports.discrepancy.loans');

        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('/borrowers/trashed', [BorrowerController::class, 'trashed'])->name('borrowers.trashed');
        Route::post('/borrowers/{borrower}/restore', [BorrowerController::class, 'restore'])->name('borrowers.restore');
        Route::get('/loans/trashed', [LoanController::class, 'trashed'])->name('loans.trashed');
        Route::post('/loans/{loan}/restore', [LoanController::class, 'restore'])->name('loans.restore');
        Route::patch('/loans/{loan}/close', [LoanController::class, 'close'])->name('loans.close');
        Route::patch('/loans/{loan}/unclose', [LoanController::class, 'unclose'])->name('loans.unclose');
    });

    Route::get('/payments/penalty-due', [PaymentController::class, 'penaltyDue'])->name('payments.penalty-due');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::resource('borrowers', BorrowerController::class);
    Route::resource('loans', LoanController::class);
    Route::resource('payments', PaymentController::class);
});

require __DIR__.'/auth.php';
