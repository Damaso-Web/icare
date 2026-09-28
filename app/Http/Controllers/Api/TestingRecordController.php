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
     * Step 1: TMDU acknowledges the GCU->TMDU referral. This is now normally
     * handled by ReferralController::acknowledge() instead (since referring
     * to TMDU creates a real, shared Referral row) - this endpoint is kept
     * as a fallback for TestingRecords that predate that change and have no
     * referral_id. This creates a self-schedulable appointment for the
     * student to pick up the physical "Assessment of Fees" form - NOT the
     * testing appointment itself, which TMDU sets directly later in
     * scheduleTesting().
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

    // Loads everything the Testing Record Details page needs in one call:
    // the GCU-authored referral this record was created from (mirroring how
    // ReferralController::show() loads it for an Incident Report), plus the
    // usual student/tester/case/document relations.
    public function show(TestingRecord $testingRecord)
    {
        AuditLog::record('viewed', "Viewed testing record #{$testingRecord->id}.", $testingRecord);

        return response()->json($testingRecord->load([
            'student',
            'referredBy',
            'tester',
            'case',
            'documents',
            'referral.complaint.complainee',
            'referral.complaint.filedBy',
            'referral.complaint.attachments',
            'referral.case',
            // Testing Action / Appointments panel on the Testing Record
            // Details page - every appointment sharing this record's case_id
            // (TestingRecord::appointments()), same pattern as CaseFile's.
            'appointments.staff',
        ]));
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
            // "completed"/"report_sent" were renamed to "test_administered"/
            // "test_results_issued" to match the team's terminology, and
            // "awaiting_results" was added for the Testing Record Details
            // page's status bar (see administerTests()) - all three changes
            // came from widen-only migrations on this DB enum.
            'status' => 'required|in:pending,fee_form_pending,or_submitted,scheduled,in_progress,test_administered,awaiting_results,par_scheduled,test_results_issued'
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
     *
     * This is also the moment TMDU confirms (face-to-face, at the same
     * office visit) that they've physically received and stamped the
     * student's Official Receipt - the two happen together in person, so
     * `or_stamped_confirmed` is required here rather than as its own
     * separate step/endpoint.
     */
    public function scheduleTesting(Request $request, TestingRecord $testingRecord)
    {
        $validated = $request->validate([
            'appointment_date'     => 'required|date',
            'start_time'           => 'required',
            'end_time'             => 'required',
            'or_stamped_confirmed' => 'required|accepted',
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
            'status'                => 'scheduled',
            'testing_date'          => $validated['appointment_date'],
            // Only set once, in case scheduleTesting() is ever called again
            // to reschedule the same test (shouldn't overwrite who actually
            // stamped the OR the first time).
            'or_stamped_at'         => $testingRecord->or_stamped_at ?? now(),
            'or_stamped_by_user_id' => $testingRecord->or_stamped_by_user_id ?? $request->user()->id,
        ]);

        AuditLog::record('testing_scheduled', "Scheduled psychological testing for record #{$testingRecord->id} (OR confirmed stamped).", $testingRecord);

        if ($testingRecord->student) {
            Notification::send($testingRecord->student, new TestingScheduledNotification($testingRecord, $appointment));
        }

        return response()->json([
            'testing_record' => $testingRecord,
            'appointment'    => $appointment,
        ]);
    }

    /**
     * "Psychological Tests Administered" action on the Testing Record
     * Details page. Records which tests were given and when, and moves the
     * status bar straight to "Awaiting Results" - by design there's no
     * separate resting state for "Test Administered" alone; the bar step
     * still lights up as passed once the record reaches Awaiting Results.
     */
    public function administerTests(Request $request, TestingRecord $testingRecord)
    {
        $validated = $request->validate([
            'tests_administered' => 'required|array|min:1',
            'testing_date'       => 'required|date',
        ]);

        $old = $testingRecord->only(['tests_administered', 'testing_date', 'status']);

        $testingRecord->update([
            'tests_administered' => $validated['tests_administered'],
            'testing_date'       => $validated['testing_date'],
            'status'             => 'awaiting_results',
        ]);

        AuditLog::record(
            'tests_administered',
            "Recorded psychological tests administered for record #{$testingRecord->id}.",
            $testingRecord,
            $old,
            $testingRecord->toArray()
        );

        return response()->json($testingRecord);
    }

    /**
     * Step 4: after the exam, TMDU sets a follow-up appointment for when the
     * student will receive their PAR (Psychological Assessment Report).
     * Available while a record is in the "Awaiting Results" bar step - it's
     * an optional in-person scheduling step, not itself what moves the bar
     * to "Results Released" (Attach PAR / sendToGcu() does that).
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

    // "Attach Psychological Assessment Records (PAR)" action on the Testing
    // Record Details page. Findings is now optional/unused by that panel
    // (Assessment Summary + Recommended Actions only) but the column and
    // validation stay nullable rather than removed, so older records/API
    // callers that still send it keep working.
    public function sendToGcu(Request $request, TestingRecord $testingRecord)
    {
        $request->validate([
            'assessment_summary' => 'required|string',
            'findings'           => 'nullable|string',
            'recommendations'    => 'required|string',
            'report_file'        => 'nullable|file|max:10240',
        ]);

        $testingRecord->update([
            ...$request->only(['assessment_summary', 'findings', 'recommendations']),
            'status'             => 'test_results_issued',
            'report_date'        => today(),
            'report_sent_to_gcu' => true,
            'report_sent_at'     => now(),
        ]);

        // If a PAR file was attached, store it once and link it to both the
        // testing record and its referral, so it shows up wherever either
        // one is viewed. Prefer the referral this record was actually
        // created from (referral_id); fall back to the case's latest
        // referral for older records that predate that link.
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

            $linkedReferral = $testingRecord->referral ?? $testingRecord->case?->latestReferral;
            if ($linkedReferral) {
                Document::create([
                    'documentable_type'    => \App\Models\Referral::class,
                    'documentable_id'      => $linkedReferral->id,
                    'document_type'        => 'psychological_assessment_report',
                    'file_path'            => $path,
                    'original_filename'    => $file->getClientOriginalName(),
                    'uploaded_by_user_id'  => $request->user()->id,
                ]);
            }
        }

        AuditLog::record('test_results_issued', "Test results issued to GCU for record #{$testingRecord->id}.", $testingRecord);

        // Notify the original referring GCU counselor that the PAR is ready.
        if ($testingRecord->referredBy) {
            Notification::send($testingRecord->referredBy, new ParReadyNotification($testingRecord));
        }

        return response()->json($testingRecord);
    }
}