<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentProcessingConfigController;
use App\Http\Controllers\DocumentProcessingController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\ExamDocumentController;
use App\Http\Controllers\ExamPeriodController;
use App\Http\Controllers\FormattingPresetController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\SchoolProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Periode Ujian (Admin & Operator)
    Route::get('/exam-periods', [ExamPeriodController::class, 'index'])->name('exam-periods.index');
    Route::post('/exam-periods', [ExamPeriodController::class, 'store'])->name('exam-periods.store');
    Route::put('/exam-periods/{examPeriod}', [ExamPeriodController::class, 'update'])->name('exam-periods.update');
    Route::patch('/exam-periods/{examPeriod}/archive', [ExamPeriodController::class, 'archive'])->name('exam-periods.archive');
    Route::patch('/exam-periods/{examPeriod}/reopen', [ExamPeriodController::class, 'reopen'])->name('exam-periods.reopen');

    // Dokumen Soal, Konfigurasi Snapshot, Proses Standarisasi & Unduhan File Privat (Admin & Operator)
    Route::get('/exam-documents', [ExamDocumentController::class, 'index'])->name('exam-documents.index');
    Route::get('/exam-documents/create', [ExamDocumentController::class, 'create'])->name('exam-documents.create');
    Route::post('/exam-documents', [ExamDocumentController::class, 'store'])->name('exam-documents.store');
    Route::get('/exam-documents/{examDocument}', [ExamDocumentController::class, 'show'])->name('exam-documents.show');
    Route::put('/exam-documents/{examDocument}', [ExamDocumentController::class, 'update'])->name('exam-documents.update');
    Route::put('/exam-documents/{examDocument}/processing-config', [DocumentProcessingConfigController::class, 'update'])
        ->name('exam-documents.processing-config.update');
    Route::post('/exam-documents/{examDocument}/process', [DocumentProcessingController::class, 'store'])
        ->name('exam-documents.process');
    Route::get('/document-files/{documentFile}/download', [DocumentFileController::class, 'download'])->name('document-files.download');

    // Preset Format & Template Kop (Dapat dilihat Admin & Operator; dikelola Admin)
    Route::get('/formatting-presets', [FormattingPresetController::class, 'index'])->name('formatting-presets.index');
    Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
    Route::get('/document-templates/{documentTemplate}/download', [DocumentTemplateController::class, 'download'])->name('document-templates.download');

    // Data Referensi Mapel & Kelas (Admin & Operator)
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data.index');
    Route::post('/master-data/subjects', [MasterDataController::class, 'storeSubject'])->name('master-data.subjects.store');
    Route::put('/master-data/subjects/{subject}', [MasterDataController::class, 'updateSubject'])->name('master-data.subjects.update');
    Route::post('/master-data/classes', [MasterDataController::class, 'storeSchoolClass'])->name('master-data.classes.store');
    Route::put('/master-data/classes/{schoolClass}', [MasterDataController::class, 'updateSchoolClass'])->name('master-data.classes.update');

    // Logo sekolah terotorisasi untuk pengguna aktif
    Route::get('/school-profile/logo', [SchoolProfileController::class, 'logo'])->name('school-profile.logo');

    // Khusus Administrator
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('/formatting-presets', [FormattingPresetController::class, 'store'])->name('formatting-presets.store');
        Route::put('/formatting-presets/{formattingPreset}', [FormattingPresetController::class, 'update'])->name('formatting-presets.update');
        Route::patch('/formatting-presets/{formattingPreset}/default', [FormattingPresetController::class, 'setDefault'])->name('formatting-presets.default');

        Route::post('/document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::put('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
        Route::patch('/document-templates/{documentTemplate}/default', [DocumentTemplateController::class, 'setDefault'])->name('document-templates.default');

        Route::get('/school-profile', [SchoolProfileController::class, 'edit'])->name('school-profile.edit');
        Route::put('/school-profile', [SchoolProfileController::class, 'update'])->name('school-profile.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });
});