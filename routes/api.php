<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\CaseInterventionController;
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
use App\Http\Controllers\Api\PublicSchedulingController;
use App\Http\Controllers\Api\StudentAuthController;
use App\Http\Controllers\Api\CallSlipController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\DevController;
use App\Http\Controllers\Api\CronController;

// Public routes
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('schedule/{token}', [PublicSchedulingController::class, 'show']);
Route::post('schedule/{token}/check-availability', [PublicSchedulingController::class, 'checkAvailability']);
Route::get('schedule/{token}/month-availability', [PublicSchedulingController::class, 'monthAvailability']);
Route::post('schedule/{token}/submit', [PublicSchedulingController::class, 'submit']);

// External cron endpoints (secured via X-Cron-Secret header, checked inside the controller)
Route::post('cron/follow-up-reminders', [CronController::class, 'followUpReminders']);
Route::post('cron/detect-no-shows',     [CronController::class, 'detectNoShows']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::get('/me',          [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);

    // Dev/QA role switcher - locked to one designated tester account inside the controller
    Route::post('dev/switch-role',       [DevController::class, 'switchRole']);
    Route::post('dev/switch-to-student', [DevController::class, 'switchToStudent']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Students
    Route::apiResource('students', StudentController::class);
    Route::get('students/{student}/history', [StudentController::class, 'history']);
    Route::post('students/{student}/toggle-active', [StudentController::class, 'toggleActive']);
    Route::post('students/import', [StudentController::class, 'import']);
    Route::post('students/{student}/graduate', [StudentController::class, 'graduate']);
    Route::post('students/import-preview', [StudentController::class, 'importPreview']);
    Route::post('students/import-confirm', [StudentController::class, 'importConfirm']);
    Route::post('students/check-duplicate-name', [StudentController::class, 'checkDuplicateName']);
    Route::get('students/{student}/temp-password',  [StudentController::class, 'viewTempPassword']);
    Route::post('students/{student}/reset-password', [StudentController::class, 'resetPassword']);
    Route::get('student/dashboard', [StudentAuthController::class, 'dashboard']);
    Route::put('student/profile', [StudentAuthController::class, 'updateProfile']);
    

    // Referrals
    Route::apiResource('referrals', ReferralController::class);
    Route::post('referrals/{referral}/acknowledge', [ReferralController::class, 'acknowledge']);
    Route::get('referrals-archived',              [ReferralController::class, 'archived']);
    Route::post('referrals/{referral}/archive',   [ReferralController::class, 'archive']);
    Route::post('referrals/{referral}/unarchive', [ReferralController::class, 'unarchive']);
    Route::post('referrals/{referral}/assign',      [ReferralController::class, 'assign']);
    Route::patch('referrals/{referral}/status',     [ReferralController::class, 'updateStatus']);
    Route::get('referrals/{referral}/tracking',     [ReferralController::class, 'tracking']);
    Route::post('referrals/{referral}/feedback',        [ReferralController::class, 'sendFeedback']);
    Route::post('referrals/{referral}/admission-slip',  [ReferralController::class, 'saveAdmissionSlip']);
    Route::get('referrals/{referral}/session-notes',    [SessionNoteController::class, 'indexByReferral']);
    Route::post('referrals/{referral}/session-notes',   [SessionNoteController::class, 'storeByReferral']);

    // Cases
    Route::apiResource('cases', CaseController::class);
    Route::patch('cases/{case}/status',        [CaseController::class, 'updateStatus']);
    Route::post('cases/{case}/close',          [CaseController::class, 'close']);
    Route::get('cases/{case}/summary',         [CaseController::class, 'summary']);
    Route::post('cases/{case}/refer-tmdu',     [CaseController::class, 'referToTmdu']);
    Route::post('cases/{case}/refer-external', [CaseController::class, 'referExternal']);
    Route::post('cases/{case}/handoff',        [CaseController::class, 'handoff']);
    Route::post('cases/{case}/handoffs/{handoff}/acknowledge', [CaseController::class, 'acknowledgeHandoff']);


    Route::post('cases/{case}/flag-unreachable',            [CaseController::class, 'flagUnreachable']);
    Route::post('cases/{case}/flag-follow-up',               [CaseController::class, 'flagFollowUp']);
    Route::post('cases/{case}/resolve-follow-up',             [CaseController::class, 'resolveFollowUp']);
    Route::post('cases/{case}/interventions',                 [CaseInterventionController::class, 'store']);
    Route::post('interventions/{intervention}/complete',      [CaseInterventionController::class, 'complete']);
    Route::delete('interventions/{intervention}',              [CaseInterventionController::class, 'destroy']);

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
    Route::post('appointments/{appointment}/escalate-no-show', [AppointmentController::class, 'escalateNoShow']);
    Route::get('appointments/availability',              [AppointmentController::class, 'availability']);
    Route::post('appointments/check-conflict',           [AppointmentController::class, 'checkConflict']);

    // Call Slips (Dean's Secretary - escalated no-show follow-up)
    Route::get('call-slips',                          [CallSlipController::class, 'index']);
    Route::post('call-slips/{appointment}/contacted', [CallSlipController::class, 'markContacted']);
    Route::post('call-slips/{appointment}/reschedule', [CallSlipController::class, 'requestReschedule']);
    Route::post('call-slips/{appointment}/escalate',  [CallSlipController::class, 'escalateToDeptChair']);

    // Staff Availability
    Route::apiResource('staff-availability', StaffAvailabilityController::class);

    // Testing Records
    Route::apiResource('testing-records', TestingRecordController::class);
    Route::patch('testing-records/{testingRecord}/status',      [TestingRecordController::class, 'updateStatus']);
    Route::post('testing-records/{testingRecord}/send-to-gcu',  [TestingRecordController::class, 'sendToGcu']);
    Route::post('testing-records/{testingRecord}/acknowledge',  [TestingRecordController::class, 'acknowledge']);

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
        Route::get('recurring-concerns', [ReportController::class, 'recurringConcerns']);
        Route::get('dashboard',    [ReportController::class, 'dashboardStats']);
    });

       // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/toggle-active',  [UserController::class, 'toggleActive']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('users/{user}/temp-password',   [UserController::class, 'viewTempPassword']);
        Route::post('users/import', [UserController::class, 'import']);
        Route::get('audit-logs',        [AuditLogController::class, 'index']);
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show']);

        // Backup & Recovery
        Route::get('backups',                [BackupController::class, 'index']);
        Route::post('backups/run',           [BackupController::class, 'store']);
        Route::post('backups/restore-data',  [BackupController::class, 'restoreData']);
        Route::post('backups/restore-config',[BackupController::class, 'restoreConfig']);
    });
});

// Student authentication routes (completely separate from staff auth:sanctum group)
Route::post('student/login', [StudentAuthController::class, 'login']);

Route::middleware('auth:student')->group(function () {
    Route::get('student/referrals/{id}', [StudentAuthController::class, 'showReferral']);
    Route::get('student/appointments/{id}', [StudentAuthController::class, 'showAppointment']);
    Route::get('student/appointments',                 [AppointmentController::class, 'indexByStudent']);
    Route::post('student/appointments',                [AppointmentController::class, 'storeByStudent']);
    Route::post('student/appointments/check-conflict', [AppointmentController::class, 'checkConflictByStudent']);
    Route::post('student/appointments/{appointment}/request-reschedule', [AppointmentController::class, 'requestRescheduleByStudent']);
    Route::post('student/appointments/{appointment}/cancel', [AppointmentController::class, 'cancelByStudent']);
    Route::post('student/logout', [StudentAuthController::class, 'logout']);
    Route::get('student/me', [StudentAuthController::class, 'me']);
    Route::get('student/dashboard', [StudentAuthController::class, 'dashboard']);
    Route::put('student/password', [StudentAuthController::class, 'changePassword']);
    Route::put('student/profile', [StudentAuthController::class, 'updateProfile']);
    Route::get('student/notifications', [NotificationController::class, 'index']);
    Route::post('student/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('student/notifications/read-all', [NotificationController::class, 'markAllRead']);
});
