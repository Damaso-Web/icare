<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\SessionNoteController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\TestingRecordController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StaffAvailabilityController;

// Public routes
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::get('/me',          [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Students
    Route::apiResource('students', StudentController::class);
    Route::get('students/{student}/history', [StudentController::class, 'history']);
    Route::get('students/{student}/cases',   [StudentController::class, 'cases']);

    // Referrals
    Route::apiResource('referrals', ReferralController::class);
    Route::post('referrals/{referral}/acknowledge', [ReferralController::class, 'acknowledge']);
    Route::post('referrals/{referral}/assign',      [ReferralController::class, 'assign']);
    Route::patch('referrals/{referral}/status',     [ReferralController::class, 'updateStatus']);
    Route::get('referrals/{referral}/tracking',     [ReferralController::class, 'tracking']);

    // Cases
    Route::apiResource('cases', CaseController::class);
    Route::patch('cases/{case}/status',        [CaseController::class, 'updateStatus']);
    Route::post('cases/{case}/close',          [CaseController::class, 'close']);
    Route::get('cases/{case}/summary',         [CaseController::class, 'summary']);
    Route::post('cases/{case}/refer-tmdu',     [CaseController::class, 'referToTmdu']);
    Route::post('cases/{case}/refer-external', [CaseController::class, 'referExternal']);
    Route::post('cases/{case}/handoff',        [CaseController::class, 'handoff']);

    // Session Notes
    Route::get('cases/{case}/session-notes',           [SessionNoteController::class, 'index']);
    Route::post('cases/{case}/session-notes',          [SessionNoteController::class, 'store']);
    Route::get('session-notes/{sessionNote}',          [SessionNoteController::class, 'show']);
    Route::put('session-notes/{sessionNote}',          [SessionNoteController::class, 'update']);
    Route::delete('session-notes/{sessionNote}',       [SessionNoteController::class, 'destroy']);

    // Appointments
    Route::apiResource('appointments', AppointmentController::class);
    Route::post('appointments/{appointment}/confirm',    [AppointmentController::class, 'confirm']);
    Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    Route::post('appointments/{appointment}/cancel',     [AppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/check-in',   [AppointmentController::class, 'checkIn']);
    Route::get('appointments/availability',              [AppointmentController::class, 'availability']);
    Route::post('appointments/check-conflict',           [AppointmentController::class, 'checkConflict']);

    // Staff Availability
    Route::apiResource('staff-availability', StaffAvailabilityController::class);

    // Testing Records
    Route::apiResource('testing-records', TestingRecordController::class);
    Route::patch('testing-records/{testingRecord}/status',      [TestingRecordController::class, 'updateStatus']);
    Route::post('testing-records/{testingRecord}/send-to-gcu',  [TestingRecordController::class, 'sendToGcu']);

    // Documents
    Route::post('documents/upload',                [DocumentController::class, 'upload']);
    Route::get('documents/{document}',             [DocumentController::class, 'show']);
    Route::delete('documents/{document}',          [DocumentController::class, 'destroy']);
    Route::get('documents/{document}/download',    [DocumentController::class, 'download']);

    // Notifications
    Route::get('notifications',              [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read',   [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all',    [NotificationController::class, 'markAllRead']);
    Route::get('notification-logs',          [NotificationController::class, 'logs']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('referrals',    [ReportController::class, 'referrals']);
        Route::get('appointments', [ReportController::class, 'appointments']);
        Route::get('cases',        [ReportController::class, 'cases']);
        Route::get('dashboard',    [ReportController::class, 'dashboardStats']);
    });

    // Admin only
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/toggle-active',  [UserController::class, 'toggleActive']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('audit-logs',        [AuditLogController::class, 'index']);
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show']);
    });
});