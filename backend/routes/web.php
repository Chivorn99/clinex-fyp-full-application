<?php

use App\Http\Controllers\LabReportCorrectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LabReportController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ReportManagementController;
use App\Http\Controllers\UserController;
use App\Models\LabReport;
use App\Models\Patient;
use App\Http\Controllers\PatientController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $recentReportsCount = LabReport::count();
    $recentPatientsCount = Patient::count();
    $verifiedReportsCount = LabReport::whereNotNull('verified_at')->count();
    $processedReportsCount = LabReport::where('status', 'processed')->count();

    return view('dashboard', compact(
        'recentReportsCount',
        'recentPatientsCount',
        'verifiedReportsCount',
        'processedReportsCount'
    ));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Lab report upload and processing routes
    Route::get('/upload', function () {
        return view('lab-reports.upload');
    })->name('lab-reports.upload');

    // Lab report correction routes (web interface)
    // Route::get('/lab-reports/{labReport}/correct', [LabReportCorrectionController::class, 'show'])
    //     ->name('lab-reports.correct');
    // Route::post('/lab-reports/{labReport}/corrections', [LabReportCorrectionController::class, 'saveCorrections'])
    //     ->name('lab-reports.save-corrections');
    // Route::get('/lab-reports/{labReport}/pdf', [LabReportCorrectionController::class, 'showPdf'])
    //     ->name('lab-reports.pdf');

    // User management routes (admin only)
    Route::middleware(['auth', 'can:admin'])->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    // Template management routes (admin only)
    Route::middleware('can:admin')->group(function () {
        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
        Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::patch('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        // Template creation from PDF
        Route::post('/templates/create-from-pdf', [TemplateController::class, 'processPdfForTemplate'])->name('templates.create-from-pdf');
        Route::get('/templates/custom-categories', [TemplateController::class, 'getCustomCategories'])->name('templates.custom-categories');
        Route::post('/templates/extract-pdf', [TemplateController::class, 'extractFromPdf'])->name('templates.extract-pdf');

        // Newer Version
        Route::post('/templates/analyze', [TemplateController::class, 'analyze'])->name('templates.analyze');

        // Zonal extraction route
        Route::post('/templates/extract-zones', [TemplateController::class, 'extractFromZones'])
            ->middleware(['auth', 'verified'])
            ->name('templates.extract-zones');

        Route::get('/lab-reports/process', function () {
            return view('lab-reports.processor'); // Point to the new view
        })->name('lab-reports.processor');
        Route::post('/report/process', [LabReportController::class, 'process'])
            ->name('report.process');
    });

    Route::middleware('can:admin')->group(function () {
        // User Management Routes
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.update-role');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        // Report Management Routes
        Route::get('/reports', [ReportManagementController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportManagementController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/download', [ReportManagementController::class, 'download'])->name('reports.download');
        Route::get('/reports/{report}/edit', [ReportManagementController::class, 'edit'])->name('reports.edit');
        Route::patch('/reports/{report}', [ReportManagementController::class, 'update'])->name('reports.update');

        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::patch('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

        Route::get('/reports/{report}/edit', [ReportManagementController::class, 'edit'])->name('reports.edit');
        Route::delete('/reports/{report}', [ReportManagementController::class, 'destroy'])->name('reports.destroy');

        Route::post('/lab-reports/test-extract', [LabReportController::class, 'testExtract'])->name('lab-reports.test-extract');

        // Template Management Routes
        // Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        // Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
        // Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        // Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
        // Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        // Route::patch('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        // Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        // Template creation from PDF
        // Route::post('/templates/create-from-pdf', [TemplateController::class, 'processPdfForTemplate'])->name('templates.create-from-pdf');
        // Route::get('/templates/custom-categories', [TemplateController::class, 'getCustomCategories'])->name('templates.custom-categories');
        // Route::post('/templates/extract-pdf', [TemplateController::class, 'extractFromPdf'])->name('templates.extract-pdf');

        // // Newer Version
        // Route::post('/templates/analyze', [TemplateController::class, 'analyze'])->name('templates.analyze');



        // Zonal extraction route
        // Route::post('/templates/extract-zones', [TemplateController::class, 'extractFromZones'])
        //     ->middleware(['auth', 'verified'])
        //     ->name('templates.extract-zones');

        // Route::get('/lab-reports/process', function () {
        //     return view('lab-reports.processor'); // Point to the new view
        // })->name('lab-reports.processor');
        // Route::post('/report/process', [LabReportController::class, 'process'])
        //     ->name('report.process');
    });
});

require __DIR__ . '/auth.php';
