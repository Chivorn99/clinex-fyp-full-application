<?php

use App\Http\Controllers\LabReportController;
use App\Http\Controllers\LabReportCorrectionController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportBatchController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\Auth\OtpPasswordController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/register', [RegisteredUserController::class, 'registerApi']);
// ->middleware('throttle:login');

Route::post('/login', [AuthenticatedSessionController::class, 'loginApi']);
// ->middleware('throttle:login');

Route::post('password/otp-request', [OtpPasswordController::class, 'sendOtpApi']);
Route::post('password/otp-verify', [OtpPasswordController::class, 'resetPasswordApi']);
Route::post('password/otp-verify-only', [OtpPasswordController::class, 'verifyOtpApi']);

// DEVELOPMENT ONLY: Routes for testing OTP functionality
Route::get('password/otp-get/{email}', [OtpPasswordController::class, 'getOtpForTesting']);
Route::get('password/otp-get', [OtpPasswordController::class, 'getOtpForTesting']);
Route::get('password/otp-all', [OtpPasswordController::class, 'getAllOtpsForTesting']);

// User Management API routes
Route::middleware('auth:sanctum')->group(function () {
    // User CRUD operations
    Route::apiResource('users', UserController::class);
    Route::post('/logout', [AuthenticatedSessionController::class, 'logoutApi']);

    // Profile management
    Route::get('/profile', [ProfileController::class, 'showUser']);
    Route::post('/profile/update', [ProfileController::class, 'updateApi']);
    Route::delete('/profile', [ProfileController::class, 'destroyApi']);

    Route::get('users/role/{role}', [UserController::class, 'getByRole']);
    Route::get('users/{id}/profile-picture', [UserController::class, 'getProfilePicture']);

    Route::get('/lab-reports/export/verified-csv', [LabReportController::class, 'exportVerifiedCsv'])->name('lab-reports.export-verified-csv');
    Route::get('/batches/{reportBatch}/export/verified-csv', [ReportBatchController::class, 'exportBatchVerifiedCsv'])->name('batches.export-verified-csv');

    Route::get('/{labReport}/pdf-data', [LabReportController::class, 'getPdfData'])->name('pdf-data');
});

// Lab Report Batch Processing Routes
Route::middleware('auth:sanctum')->prefix('batches')->name('batches.')->group(function () {
    // Core CRUD operations
    Route::get('/', [ReportBatchController::class, 'index'])->name('index');
    Route::post('/', [ReportBatchController::class, 'store'])->name('store');
    Route::get('/{reportBatch}', [ReportBatchController::class, 'show'])->name('show');
    Route::post('/{reportBatch}/process', [ReportBatchController::class, 'process'])->name('process');
    Route::post('/{reportBatch}/retry-failed', [ReportBatchController::class, 'retryFailed'])->name('retry-failed');
    Route::delete('/{reportBatch}', [ReportBatchController::class, 'destroy'])->name('destroy');

    // Status monitoring
    Route::get('/{reportBatch}/status', [ReportBatchController::class, 'status'])->name('status');
    Route::get('/{reportBatch}/live-status', [ReportBatchController::class, 'liveStatus'])->name('live-status');

    // NEW: Verification endpoints
    Route::get('/{reportBatch}/reports-for-verification', [ReportBatchController::class, 'getReportsForVerification'])->name('reports-for-verification');
});

// NEW: Global verification endpoint
Route::middleware('auth:sanctum')->get('/reports-for-verification', [ReportBatchController::class, 'getAllReportsForVerification'])->name('reports-for-verification');

// Individual Lab Report Routes
Route::middleware('auth:sanctum')->prefix('lab-reports')->name('lab-reports.')->group(function () {
    Route::get('/', [LabReportController::class, 'index'])->name('index');
    Route::get('/details', [LabReportController::class, 'getAllWithDetails'])->name('details');
    Route::get('/{labReport}', [LabReportController::class, 'show'])->name('show');
    Route::get('/{labReport}/test-results', [LabReportController::class, 'testResults'])->name('test-results');
    Route::post('/{labReport}/verify', [LabReportController::class, 'verify'])->name('verify');
    Route::delete('/{labReport}', [LabReportController::class, 'destroy'])->name('destroy');
    Route::get('/{labReport}/download', [LabReportController::class, 'download'])->name('download');
});

// Patient Management Routes
Route::middleware('auth:sanctum')->prefix('patients')->name('patients.')->group(function () {
    Route::get('/', [PatientController::class, 'index'])->name('index');
    Route::post('/', [PatientController::class, 'store'])->name('store');
    Route::get('/search', [PatientController::class, 'search'])->name('search');
    Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    Route::put('/{patient}', [PatientController::class, 'update'])->name('update');
    Route::delete('/{patient}', [PatientController::class, 'destroy'])->name('destroy');
    Route::get('/{patient}/lab-reports', [PatientController::class, 'labReports'])->name('lab-reports');
});

// Health check route (optional but useful)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});