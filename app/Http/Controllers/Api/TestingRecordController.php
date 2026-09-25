<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\TestingRecord;
use App\Models\User;
use App\Notifications\ParReadyNotification;
use App\Notifications\ParScheduledNotification;
use App\Notifications\TestingAppointmentReadyNotification;
use App\Notifications\TestingRequestSubmittedNotification;
use App\Notifications\TestingScheduledNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class TestingRecordController extends Controller
{
    // Reminder text shown to the student both in the notification and on the
    // appointment's "Required Documents" block (Appointment.required_documents),
    // so it shows up with zero extra frontend work.
    protected const TESTING_REMINDER = "Please bring your Official Receipt (OR), two (2) sharpened pencils with eraser, and arrive at least 15 minutes before your scheduled exam time.";

    /**
     * Step 1: TMDU acknowledges the GCU->TMDU referral (still stored purely on
     * TestingRecord - no separate Referral row per the team's decision).
     * This creates a self-schedulable appointment for the student to pick up
     * the physical "Assessment of Fees" form - NOT the testing appointment
     * itself, which TMDU sets directly later in scheduleTesting().
     */
    public function acknowledge(Request $request, TestingRecord $testingRecord)
    {
        $token = \Illuminate\Support\Str::random(48);

        $appointment = Appointment::create([
            'case_id'            => $testingRecord->case_id,
            'student_id'         => $testingRecord->student_id,
            'staff_user_id'      => $request->user()->id,
            'created_by_user_id' => $request->user()->id,
            'appointment_type'   => 'fee_form_pickup',
            'unit'               => 'TMDU',
            'scheduling_token'   => $token,
            'token_expires_at'   => now()->addDays(7),
            'request_status'     => 'awaiting_student',
            'status'             => 'pending',
            'appointment_date'   => now()->addDays(1)->format('Y-m-d'),
            'start_time'         => '08:00',
            'end_time'           => '09:00',
        ]);

        $testingRecord->update(['status' => 'fee_form_pending']);

        AuditLog::record('acknowledged', "Acknowledged testing referral for record #{$testingRecord->id}.", $testingRecord);

        if ($testingRecord->student) {
            Notification::send($testingRecord->student, new TestingAppointmentReadyNotification($testingRecord));
        }

        return response()->json([
            'testing_record'  => $testingRecord,
            'appointment'     => $appointment,
            'scheduling_link' => url("/schedule/{$token}"),
        ]);
    }

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

    // Streams the student's uploaded OR photo back to staff. Kept off the
    // Document system (Document.uploaded_by_user_id is a required staff FK,
    // which a student upload can't satisfy) - it's stored directly on
    // TestingRecord instead, so this is its own small endpoint.
    public function orPhoto(TestingRecord $testingRecord)
    {
        abort_unless(
            $testingRecord->or_photo_path && Storage::disk('local')->exists($testingRecord->or_photo_path),
            404,
            'No OR photo has been uploaded for this record.'
        );

        return Storage::disk('local')->response(
            $testingRecord->or_photo_path,
            $testingRecord->or_photo_original_name ?: 'or-photo.jpg'
        );
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
            // Widened to cover the full TMDU workflow. If the frontend/DB
            // still only expects the original 5 values anywhere else, that
            // needs to be updated alongside this.
            'status' => 'required|in:pending,fee_form_pending,or_submitted,scheduled,in_progress,completed,par_scheduled,report_sent'
        ]);

        $old = ['status' => $testingRecord->status];
        $testingRecord->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated testing record #{$testingRecord->id} status to {$request->status}.", $testingRecord, $old);
        return response()->json($testingRecord);
    }

    /**
     * Step 2 (student side): after picking up the Assessment of Fees form
     * and paying, the student uploads a photo of their OR and requests a
     * testing schedule. They do NOT pick a date/time - TMDU sets that next
     * in scheduleTesting(). Guarded by auth:student.
     */
    public function requestTestingByStudent(Request $request, TestingRecord $testingRecord)
    {
        abort_if($testingRecord->student_id !== $request->user()->id, 403, 'This testing record does not belong to you.');

        $request->validate([
            'or_photo' => 'required|image|max:5120',
        ]);

        $file = $request->file('or_photo');
        $path = $file->store("testing-or-photos/{$testingRecord->id}", 'local');

        $testingRecord->update([
            'status'                  => 'or_submitted',
            'or_photo_path'           => $path,
            'or_photo_original_name'  => $file->getClientOriginalName(),
            'or_uploaded_at'          => now(),
        ]);

        AuditLog::record('or_submitted', "Student submitted OR and requested testing schedule for record #{$testingRecord->id}.", $testingRecord);

        // Notify whoever's already assigned as tester, or fall back to every
        // TMDU staff member if no one's been assigned yet.
        $recipients = $testingRecord->tester
            ? collect([$testingRecord->tester])
            : User::where('role', 'tmdu_staff')->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TestingRequestSubmittedNotification($testingRecord));
        }

        return response()->json($testingRecord);
    }

    /**
     * Step 3: TMDU staff sets (and confirms) the actual testing appointment.
     * Unlike the fee-form pickup, the student does not self-schedule this -
     * TMDU picks the date/time directly and it's created already confirmed.
     */
    public function scheduleTesting(Request $request, TestingRecord $testingRecord)
    {
        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        $appointment = $testingRecord->case->appointments()
            ->where('appointment_type', 'psychological_testing')
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->latest()
            ->first();

        $attributes = [
            'case_id'             => $testingRecord->case_id,
            'student_id'          => $testingRecord->student_id,
            'staff_user_id'       => $request->user()->id,
            'created_by_user_id'  => $request->user()->id,
            'appointment_type'    => 'psychological_testing',
            'unit'                => 'TMDU',
            'appointment_date'    => $validated['appointment_date'],
            'start_time'          => $validated['start_time'],
            'end_time'            => $validated['end_time'],
            'status'              => 'confirmed',
            'request_status'      => 'confirmed',
            'required_documents'  => self::TESTING_REMINDER,
        ];

        if ($appointment) {
            $appointment->update($attributes);
        } else {
            $appointment = Appointment::create($attributes);
        }

        $testingRecord->update([
            'status'       => 'scheduled',
            'testing_date' => $validated['appointment_date'],
        ]);

        AuditLog::record('testing_scheduled', "Scheduled psychological testing for record #{$testingRecord->id}.", $testingRecord);

        if ($testingRecord->student) {
            Notification::send($testingRecord->student, new TestingScheduledNotification($testingRecord, $appointment));
        }

        return response()->json([
            'testing_record' => $testingRecord,
            'appointment'    => $appointment,
        ]);
    }

    /**
     * Step 4: after the exam, TMDU sets a follow-up appointment for when the
     * student will receive their PAR (Psychological Assessment Report).
     */
    public function schedulePar(Request $request, TestingRecord $testingRecord)
    {
        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        $appointment = $testingRecord->case->appointments()
            ->where('appointment_type', 'par_release')
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->latest()
            ->first();

        $attributes = [
            'case_id'             => $testingRecord->case_id,
            'student_id'          => $testingRecord->student_id,
            'staff_user_id'       => $request->user()->id,
            'created_by_user_id'  => $request->user()->id,
            'appointment_type'    => 'par_release',
            'unit'                => 'TMDU',
            'appointment_date'    => $validated['appointment_date'],
            'start_time'          => $validated['start_time'],
            'end_time'            => $validated['end_time'],
            'status'              => 'confirmed',
            'request_status'      => 'confirmed',
        ];

        if ($appointment) {
            $appointment->update($attributes);
        } else {
            $appointment = Appointment::create($attributes);
        }

        $testingRecord->update(['status' => 'par_scheduled']);

        AuditLog::record('par_scheduled', "Scheduled PAR release for record #{$testingRecord->id}.", $testingRecord);

        if ($testingRecord->student) {
            Notification::send($testingRecord->student, new ParScheduledNotification($testingRecord, $appointment));
        }

        return response()->json([
            'testing_record' => $testingRecord,
            'appointment'    => $appointment,
        ]);
    }

    public function sendToGcu(Request $request, TestingRecord $testingRecord)
    {
        $request->validate([
            'assessment_summary' => 'required|string',
            'findings'           => 'required|string',
            'recommendations'    => 'required|string',
            'report_file'        => 'nullable|file|max:10240',
        ]);

        $testingRecord->update([
            ...$request->only(['assessment_summary', 'findings', 'recommendations']),
            'status'             => 'report_sent',
            'report_date'        => today(),
            'report_sent_to_gcu' => true,
            'report_sent_at'     => now(),
        ]);

        // If a PAR file was attached, store it once and link it to both the
        // testing record and the case's latest referral, so it shows up
        // wherever either one is viewed. NOTE: this assumes CaseFile has a
        // `referrals()` relation and Referral is a valid `documentable` type -
        // please flag if either name is different in your copy of the models.
        if ($request->hasFile('report_file')) {
            $file = $request->file('report_file');
            $path = $file->store("testing-reports/{$testingRecord->id}", 'local');

            Document::create([
                'documentable_type'    => TestingRecord::class,
                'documentable_id'      => $testingRecord->id,
                'document_type'        => 'psychological_assessment_report',
                'file_path'            => $path,
                'original_filename'    => $file->getClientOriginalName(),
                'uploaded_by_user_id'  => $request->user()->id,
            ]);

            $latestReferral = $testingRecord->case?->latestReferral;
            if ($latestReferral) {
                Document::create([
                    'documentable_type'    => \App\Models\Referral::class,
                    'documentable_id'      => $latestReferral->id,
                    'document_type'        => 'psychological_assessment_report',
                    'file_path'            => $path,
                    'original_filename'    => $file->getClientOriginalName(),
                    'uploaded_by_user_id'  => $request->user()->id,
                ]);
            }
        }

        AuditLog::record('report_sent', "Testing report sent to GCU for record #{$testingRecord->id}.", $testingRecord);

        // Notify the original referring GCU counselor that the PAR is ready.
        if ($testingRecord->referredBy) {
            Notification::send($testingRecord->referredBy, new ParReadyNotification($testingRecord));
        }

        return response()->json($testingRecord);
    }
}