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
use App\Notifications\TesterAssignedNotification;
use App\Notifications\TestingAppointmentReadyNotification;
use App\Notifications\TestingRequestSubmittedNotification;
use App\Notifications\TestingScheduledNotification;
use Illuminate\Http\Request;
use App\Notifications\ParOnHoldNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class TestingRecordController extends Controller
{
    // Reminder text shown to the student both in the notification and on the
    // appointment's "Required Documents" block (Appointment.required_documents),
    // so it shows up with zero extra frontend work.
    protected const TESTING_REMINDER = "Please bring your Official Receipt (OR), two (2) sharpened pencils with eraser, and arrive at least 15 minutes before your scheduled exam time.";

    // Every staff-side action on a testing record - acknowledging it,
    // scheduling, administering, issuing results - requires a psychometrician
    // (TMDU staff or head) to already be assigned. Nothing should happen to a
    // record that's just sitting in TMDU's queue unowned; whoever picks it up
    // has to claim it via assign() first. Viewing, the student-facing
    // requestTestingByStudent(), and assign() itself are exempt.
    // A notification (mail/database) failing must never turn a successful
    // action into a 500 - e.g. Reassign Test Administrator used to fail
    // outright whenever the mail channel couldn't deliver.
    private function safeNotify($notifiables, $notification): void
    {
        try {
            Notification::send($notifiables, $notification);
        } catch (\Throwable $e) {
            Log::error('Notification failed: ' . $e->getMessage(), ['notification' => get_class($notification)]);
        }
    }

    // Appointments belong to a specific referral (this record's own Case
    // Referral Slip). Older rows with no referral_id fall back to the case.
    private function recordAppointments(TestingRecord $record, string $type)
    {
        return Appointment::where('case_id', $record->case_id)
            ->where('appointment_type', $type)
            ->where(fn($q) => $q->where('referral_id', $record->referral_id)->orWhereNull('referral_id'));
    }

    private function ensureTesterAssigned(TestingRecord $testingRecord): void
    {
        if (!$testingRecord->assigned_tester_user_id) {
            abort(422, 'Assign a tester to this record before making any changes.');
        }
    }

    // Populates the "Assign Tester" control on the Testing Record Details
    // page. Deliberately not admin-only like the main Users list - any TMDU
    // staff/head needs to see the roster to claim or hand off a record.
    public function availableTesters()
    {
        return response()->json(
            User::whereIn('role', ['tmdu_staff'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
        );
    }

    // Claims (or reassigns) the psychometrician responsible for this record.
    // This is the one action exempt from ensureTesterAssigned() - it's what
    // unblocks everything else.
    public function assign(Request $request, TestingRecord $testingRecord)
    {
        $validated = $request->validate([
            'tester_user_id' => 'required|exists:users,id',
        ]);

        $tester = User::findOrFail($validated['tester_user_id']);
        abort_unless(
            in_array($tester->role, ['tmdu_staff'], true),
            422,
            'The selected user is not TMDU staff.'
        );

        $old = ['assigned_tester_user_id' => $testingRecord->assigned_tester_user_id];
        $testingRecord->update(['assigned_tester_user_id' => $tester->id]);

        AuditLog::record(
            'tester_assigned',
            "Assigned {$tester->name} as tester for testing record #{$testingRecord->id}.",
            $testingRecord,
            $old
        );

        $this->safeNotify($tester, new TesterAssignedNotification($testingRecord));

        return response()->json($testingRecord->load('tester'));
    }

    /**
     * Step 1: TMDU acknowledges the GCU->TMDU referral. This is now normally
     * handled by ReferralController::acknowledge() instead (since referring
     * to TMDU creates a real, shared Referral row) - this endpoint is kept
     * as a fallback for TestingRecords that predate that change and have no
     * referral_id. Just flips the status - there is no fee-form/OR step
     * anymore, so an acknowledged record simply sits as "Pending" until TMDU
     * directly schedules the test in scheduleTesting().
     */
    public function acknowledge(Request $request, TestingRecord $testingRecord)
    {
        $this->ensureTesterAssigned($testingRecord);

        $testingRecord->update(['status' => 'pending']);

        AuditLog::record('acknowledged', "Acknowledged testing referral for record #{$testingRecord->id}.", $testingRecord);

        if ($testingRecord->student) {
            $this->safeNotify($testingRecord->student, new TestingAppointmentReadyNotification($testingRecord));
        }

        return response()->json([
            'testing_record' => $testingRecord,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = TestingRecord::with(['student', 'referredBy', 'tester', 'case'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            // TMDU's Testing Records is where an acknowledged GCU referral
            // lands: records assigned to this tester, plus unclaimed records
            // whose referral TMDU has already acknowledged.
            ->when($user->isTMDUStaff(), fn($q) => $q->where(function ($qq) use ($user) {
                $qq->where('assigned_tester_user_id', $user->id)
                   ->orWhere(function ($un) {
                       $un->whereNull('assigned_tester_user_id')
                          ->whereHas('referral', fn($r) => $r->whereNotNull('acknowledged_at'));
                   });
            }));

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
            'appointments' => fn($q) => $q
                ->where(fn($w) => $w->where('referral_id', $testingRecord->referral_id)->orWhereNull('referral_id')),
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
        // Once the PAR/report has been issued to GCU, the record is final -
        // this is the same lock the frontend enforces by disabling the
        // fields, but that's UX only; a direct API call must be refused too,
        // or the report could be silently rewritten after the fact.
        if ($testingRecord->status === 'test_results_issued') {
            abort(422, 'This testing record has already been issued to GCU and can no longer be edited.');
        }
        $this->ensureTesterAssigned($testingRecord);

        $validated = $request->validate([
            'assigned_tester_user_id' => 'nullable|exists:users,id',
            'tests_administered'      => 'nullable|array',
            'testing_date'            => 'nullable|date',
            'report_date'             => 'nullable|date',
            'assessment_summary'      => 'nullable|string|max:3000',
            'findings'                => 'nullable|string|max:3000',
            'recommendations'         => 'nullable|string|max:3000',
        ]);

        $old = $testingRecord->toArray();
        $testingRecord->update($validated);
        AuditLog::record('updated', "Updated testing record #{$testingRecord->id}.", $testingRecord, $old, $testingRecord->toArray());
        return response()->json($testingRecord);
    }

    // General-purpose status setter - kept only for early-stage manual
    // corrections (e.g. undoing an accidental "Confirm Testing Schedule").
    // It deliberately does NOT allow setting 'awaiting_results' or
    // 'test_results_issued': those stages have real preconditions attached
    // (schedulePar()'s test-taking attendance gate, and sendToGcu()/
    // attachPar()'s PAR/assessment-summary requirement) that this endpoint
    // has no way to also check, so allowing it to set them directly would
    // let staff skip straight to "Results Released" with the student never
    // having attended anything. Use scheduleTesting() / administerTests() /
    // schedulePar() / sendToGcu() to reach those stages instead - nothing
    // in the current UI calls this for anything past 'test_administered'.
    public function updateStatus(Request $request, TestingRecord $testingRecord)
    {
        // Same lock as update() - once results are issued, staff can't loop
        // the status back to an earlier stage from this endpoint to reopen
        // editing on the record.
        if ($testingRecord->status === 'test_results_issued') {
            abort(422, 'This testing record has already been issued to GCU and can no longer be edited.');
        }
        $this->ensureTesterAssigned($testingRecord);

        $request->validate([
            // Only the early, precondition-free stages are settable here.
            // Legacy values (fee_form_pending, or_submitted, in_progress,
            // par_scheduled) are accepted too, so an older record can still
            // be corrected back to whichever equivalent stage it used to
            // have, but nothing here ever writes those values going
            // forward - see the controller notes above and in Index.vue.
            'status' => 'required|in:pending,fee_form_pending,or_submitted,scheduled,in_progress,test_administered'
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
            $this->safeNotify($recipients, new TestingRequestSubmittedNotification($testingRecord));
        }

        return response()->json($testingRecord);
    }

    /**
     * "Schedule Test Taking": TMDU staff sets (and confirms) the actual
     * testing appointment directly - the student does not self-schedule
     * this. Available once the record is "Pending" (right after Acknowledge,
     * no fee-form/OR step in between). Sends the student the pencils/arrival
     * reminder (TESTING_REMINDER) and moves the record to "Scheduled for
     * Testing".
     */
    public function scheduleTesting(Request $request, TestingRecord $testingRecord)
    {
        $this->ensureTesterAssigned($testingRecord);

        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        $appointment = $this->recordAppointments($testingRecord, 'psychological_testing')
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->latest()
            ->first();

        $attributes = [
            'case_id'             => $testingRecord->case_id,
            'referral_id'         => $testingRecord->referral_id,
            'student_id'          => $testingRecord->student_id,
            'staff_user_id'       => $request->user()->id,
            'created_by_user_id'  => $request->user()->id,
            'appointment_type'    => 'psychological_testing',
            'unit'                => 'TMDU',
            'appointment_date'    => $validated['appointment_date'],
            'start_time'          => $validated['start_time'],
            'end_time'            => $validated['end_time'],
            // Pending, not confirmed - TMDU set this date directly with the
            // student face-to-face (never self-scheduled, so request_status
            // stays 'confirmed' to keep it out of the awaiting-student UI),
            // but it only becomes a settled outcome (attended/no-show/etc.)
            // after that appointment actually happens.
            'status'              => 'pending',
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
            $this->safeNotify($testingRecord->student, new TestingScheduledNotification($testingRecord, $appointment));
        }

        return response()->json([
            'testing_record' => $testingRecord,
            'appointment'    => $appointment,
        ]);
    }

    /**
     * "Psychological Tests Administered" action on the Testing Record
     * Details page. Records which tests were given and when, and moves the
     * record to its own resting status, "Test Administered" - separate from
     * "Awaiting Results", which now only happens once PAR release is
     * actually scheduled (see schedulePar()).
     */
    public function administerTests(Request $request, TestingRecord $testingRecord)
    {
        $this->ensureTesterAssigned($testingRecord);

        $validated = $request->validate([
            'tests_administered' => 'required|array|min:1',
            'testing_date'       => 'required|date',
        ]);

        $old = $testingRecord->only(['tests_administered', 'testing_date', 'status']);

        $testingRecord->update([
            'tests_administered' => $validated['tests_administered'],
            'testing_date'       => $validated['testing_date'],
            'status'             => 'test_administered',
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
     * "Schedule PAR Release": after the exam, TMDU sets an appointment for
     * when the student will receive their PAR (Psychological Assessment
     * Report). Available once a record is "Test Administered" - and only
     * once the student has actually attended the scheduled test-taking
     * appointment; a test that was administered without that appointment
     * being marked attended (AppointmentController::checkIn()) can't move
     * forward. Moves the record to "Awaiting Results". This is not itself
     * what moves it to "Results Released" (Attach PAR / sendToGcu() does).
     */
    public function schedulePar(Request $request, TestingRecord $testingRecord)
    {
        $this->ensureTesterAssigned($testingRecord);

        $testAppointment = $this->recordAppointments($testingRecord, 'psychological_testing')
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->first();

        if (!$testAppointment || !$testAppointment->checked_in) {
            abort(422, 'The student must have attended the scheduled test-taking appointment before PAR release can be scheduled.');
        }

        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        $appointment = $this->recordAppointments($testingRecord, 'par_release')
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->latest()
            ->first();

        $attributes = [
            'case_id'             => $testingRecord->case_id,
            'referral_id'         => $testingRecord->referral_id,
            'on_hold_at'          => null,
            'student_id'          => $testingRecord->student_id,
            'staff_user_id'       => $request->user()->id,
            'created_by_user_id'  => $request->user()->id,
            'appointment_type'    => 'par_release',
            'unit'                => 'TMDU',
            'appointment_date'    => $validated['appointment_date'],
            'start_time'          => $validated['start_time'],
            'end_time'            => $validated['end_time'],
            // Same as scheduleTesting() - pending until the face-to-face
            // release actually happens, never self-scheduled by the student.
            'status'              => 'pending',
            'request_status'      => 'confirmed',
        ];

        if ($appointment) {
            $appointment->update($attributes);
        } else {
            $appointment = Appointment::create($attributes);
        }

        $testingRecord->update(['status' => 'awaiting_results']);

        AuditLog::record('par_scheduled', "Scheduled PAR release for record #{$testingRecord->id}.", $testingRecord);

        if ($testingRecord->student) {
            $this->safeNotify($testingRecord->student, new ParScheduledNotification($testingRecord, $appointment));
        }

        return response()->json([
            'testing_record' => $testingRecord,
            'appointment'    => $appointment,
        ]);
    }

    /**
     * PAR Release appointment actions: Results Released, On Hold, Cancel.
     * On Hold re-notifies the student to go and collect their PAR results.
     */
    public function parAction(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'action' => 'required|in:released,on_hold,cancel',
        ]);

        abort_unless($appointment->appointment_type === 'par_release' && $appointment->unit === 'TMDU', 422, 'This is not a PAR release appointment.');
        abort_if(in_array($appointment->status, ['completed', 'cancelled'], true), 422, 'This PAR release is already ' . $appointment->status . '.');

        $record = TestingRecord::where('case_id', $appointment->case_id)
            ->where(fn($q) => $q->where('referral_id', $appointment->referral_id)->orWhereNull('referral_id'))
            ->latest()
            ->first();

        switch ($validated['action']) {
            case 'released':
                $appointment->update(['status' => 'completed', 'request_status' => 'completed', 'on_hold_at' => null]);
                $message = 'PAR marked as released. You can now attach the PAR results.';
                break;

            case 'on_hold':
                abort_if($appointment->on_hold_at, 422, 'This PAR release is already on hold.');
                $appointment->update(['on_hold_at' => now()]);
                if ($appointment->student) {
                    $this->safeNotify($appointment->student, new ParOnHoldNotification($appointment));
                }
                $message = 'PAR release put on hold. The student was notified to collect their PAR results.';
                break;

            default:
                $appointment->update([
                    'status'         => 'cancelled',
                    'request_status' => 'cancelled',
                    'cancelled_at'   => now(),
                    'on_hold_at'     => null,
                ]);
                $message = 'PAR release cancelled. Schedule a new PAR release when ready.';
        }

        AuditLog::record('par_release_' . $validated['action'], "PAR release {$validated['action']} for appointment {$appointment->appointment_code}.", $appointment);

        return response()->json(['message' => $message, 'appointment' => $appointment->fresh(), 'testing_record' => $record]);
    }

    // "Attach Psychological Assessment Records (PAR)" action on the Testing
    // Record Details page. Findings is now optional/unused by that panel
    // (Assessment Summary + Recommended Actions only) but the column and
    // validation stay nullable rather than removed, so older records/API
    // callers that still send it keep working.
    public function sendToGcu(Request $request, TestingRecord $testingRecord)
    {
        // Results can only be issued once - without this, re-submitting this
        // action would silently overwrite the report (and re-attach a new
        // file over the original) after it's already gone out to GCU.
        if ($testingRecord->status === 'test_results_issued') {
            abort(422, 'Test results have already been issued to GCU for this record.');
        }
        $this->ensureTesterAssigned($testingRecord);

        // The PAR can only be attached once the scheduled PAR release
        // appointment has been marked "Results Released".
        $released = $this->recordAppointments($testingRecord, 'par_release')
            ->where('status', 'completed')
            ->exists();
        if (!$released) {
            abort(422, 'PAR results can only be attached after the scheduled PAR release has been marked as Results Released.');
        }

        $request->validate([
            'assessment_summary' => 'required|string|max:3000',
            'findings'           => 'nullable|string|max:3000',
            'recommendations'    => 'required|string|max:3000',
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

            $docBase = [
                'document_type'       => 'psychological_assessment_report',
                'disk'                => 'local',
                'path'                => $path,
                'stored_filename'     => basename($path),
                'original_filename'   => $file->getClientOriginalName(),
                'mime_type'           => $file->getClientMimeType() ?: 'application/octet-stream',
                'file_size'           => $file->getSize() ?: 0,
                'is_confidential'     => true,
                'uploaded_by_user_id' => $request->user()->id,
            ];

            Document::create($docBase + [
                'documentable_type' => TestingRecord::class,
                'documentable_id'   => $testingRecord->id,
            ]);

            // GCU never opens TMDU's own Case Referral Slip, so the PAR is
            // also attached to the GCU referral this escalation started from.
            $linkedReferral = $testingRecord->sourceReferral ?? $testingRecord->case?->latestReferral;
            if ($linkedReferral) {
                Document::create($docBase + [
                    'documentable_type' => \App\Models\Referral::class,
                    'documentable_id'   => $linkedReferral->id,
                ]);
            }
        }

        AuditLog::record('test_results_issued', "Test results issued to GCU for record #{$testingRecord->id}.", $testingRecord);

        // Notify the original referring GCU counselor that the PAR is ready.
        if ($testingRecord->referredBy) {
            $this->safeNotify($testingRecord->referredBy, new ParReadyNotification($testingRecord));
        }

        return response()->json($testingRecord);
    }
}