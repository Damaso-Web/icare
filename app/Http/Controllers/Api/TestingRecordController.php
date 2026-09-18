<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TestingRecord;
use App\Models\Appointment;
use App\Notifications\ReferralAcknowledgedNotification;
use Illuminate\Http\Request;

class TestingRecordController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = TestingRecord::with(['student', 'referredBy', 'tester', 'case'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($user->isTMDUStaff(), fn($q) => $q->where(
                'assigned_tester_user_id', $user->id
            ));

        return response()->json($query->latest()->paginate(20));
    }

    public function show(TestingRecord $testingRecord)
    {
        AuditLog::record('viewed', "Viewed testing record #{$testingRecord->id}.", $testingRecord);
        return response()->json($testingRecord->load(['student', 'referredBy', 'tester', 'case', 'documents']));
    }

    // TMDU acknowledges the referred case and the student is prompted to pick a
    // testing appointment, the same way GCU's initial acknowledge works.
    public function acknowledge(Request $request, TestingRecord $testingRecord)
    {
        $user = $request->user();

        if ($testingRecord->status !== 'pending') {
            return response()->json(['message' => 'This testing referral has already been acknowledged.'], 422);
        }

        $testingRecord->update([
            'status'                  => 'scheduled',
            'assigned_tester_user_id' => $testingRecord->assigned_tester_user_id ?? $user->id,
        ]);

        $token = \Illuminate\Support\Str::random(48);
        $appointment = Appointment::create([
            'case_id'             => $testingRecord->case_id,
            'student_id'          => $testingRecord->student_id,
            'staff_user_id'       => $user->id,
            'created_by_user_id'  => $user->id,
            'appointment_type'    => 'psychological_testing',
            'unit'                => 'TMDU',
            'scheduling_token'    => $token,
            'token_expires_at'    => now()->addDays(7),
            'request_status'      => 'awaiting_student',
            'status'              => 'pending',
            'appointment_date'    => now()->addDays(1)->format('Y-m-d'),
            'start_time'          => '08:00',
            'end_time'            => '09:00',
        ]);

        AuditLog::record('acknowledged', "TMDU acknowledged testing referral for testing record #{$testingRecord->id}.", $testingRecord);

        return response()->json([
            'testing_record' => $testingRecord,
            'appointment'    => $appointment,
        ]);
    }

    public function update(Request $request, TestingRecord $testingRecord)
    {
        $validated = $request->validate([
            'assigned_tester_user_id' => 'nullable|exists:users,id',
            'tests_administered'      => 'nullable|array',
            'testing_date'            => 'nullable|date',
            'report_date'             => 'nullable|date',
            'assessment_summary'      => 'nullable|string',
            'findings'                => 'nullable|string',
            'recommendations'         => 'nullable|string',
        ]);

        $old = $testingRecord->toArray();
        $testingRecord->update($validated);
        AuditLog::record('updated', "Updated testing record #{$testingRecord->id}.", $testingRecord, $old, $testingRecord->toArray());
        return response()->json($testingRecord);
    }

    public function updateStatus(Request $request, TestingRecord $testingRecord)
    {
        $request->validate([
            'status' => 'required|in:pending,scheduled,in_progress,completed,report_sent'
        ]);

        $old = ['status' => $testingRecord->status];
        $testingRecord->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated testing record #{$testingRecord->id} status to {$request->status}.", $testingRecord, $old);
        return response()->json($testingRecord);
    }

    public function sendToGcu(Request $request, TestingRecord $testingRecord)
    {
        $request->validate([
            'assessment_summary' => 'required|string',
            'findings'           => 'required|string',
            'recommendations'    => 'required|string',
        ]);

        $testingRecord->update([
            ...$request->only(['assessment_summary', 'findings', 'recommendations']),
            'status'             => 'report_sent',
            'report_date'        => today(),
            'report_sent_to_gcu' => true,
            'report_sent_at'     => now(),
        ]);

        AuditLog::record('report_sent', "Testing report sent to GCU for record #{$testingRecord->id}.", $testingRecord);
        return response()->json($testingRecord);
    }
}