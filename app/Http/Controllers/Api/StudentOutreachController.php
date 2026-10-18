<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralFormOption;
use App\Models\Student;
use App\Models\User;
use App\Notifications\FillOutFormsNotification;
use App\Notifications\NewReferralNotification;
use App\Notifications\ReferredStudentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * What a signed-in student can do about OTHER students: look one up by exact
 * Student ID Number, refer them to GCU, and (with ComplaintController::
 * storeByStudent) file a complaint - plus the lists of what they have sent.
 */
class StudentOutreachController extends Controller
{
    // Types a student may pick for someone else. Testing and disciplinary
    // referrals are not offered; a misconduct report is a Complaint instead.
    private const TYPES = ['counseling', 'class_attendance', 'academic_deficiency', 'leave_of_absence', 'withdrawal', 'readmission', 'shifting'];

    // Exact ID match only, so a student cannot browse the masterlist.
    public function lookup(Request $request)
    {
        $me = $request->user('student');
        $request->validate(['student_id' => 'required|string|max:50']);

        $student = Student::where('student_id', trim($request->student_id))->where('is_active', true)->first();
        if (! $student || $student->id === $me->id) {
            return response()->json(['message' => $student ? 'You cannot choose yourself.' : 'No active student found with that ID Number.'], 404);
        }

        return [
            'id'      => $student->id,
            'name'    => trim($student->first_name . ' ' . $student->last_name),
            'college' => $student->college,
            'program' => $student->program,
        ];
    }

    public function formOptions()
    {
        return [
            'referral_types'  => self::TYPES,
            'violation_types' => ReferralFormOption::where('category', 'act_of_misconduct')->where('status', 'active')
                ->orderBy('sort_order')->orderBy('id')->get(['value', 'label']),
        ];
    }

    public function refer(Request $request)
    {
        $me = $request->user('student');
        $validated = $request->validate([
            'student_id'        => 'required|exists:students,id',
            'referral_type'     => 'required|in:' . implode(',', self::TYPES),
            'nature_of_concern' => 'required|string|min:10|max:1000',
        ], [
            'nature_of_concern.min' => 'Please describe the concern in at least 10 characters.',
        ]);

        abort_if((int) $validated['student_id'] === (int) $me->id, 422, 'You cannot refer yourself here.');
        $student = Student::findOrFail($validated['student_id']);
        abort_unless($student->is_active, 422, 'This student record is inactive.');

        $case = CaseFile::where('student_id', $student->id)->first();
        $isExisting = (bool) $case;
        if (! $case) {
            $case = CaseFile::create([
                'student_id'   => $student->id,
                'case_type'    => $validated['referral_type'],
                'current_unit' => 'GCU',
                'status'       => 'open',
                'opened_date'  => today(),
            ]);
        } elseif (! $case->isOpen()) {
            $case->update(['status' => 'open', 'closed_date' => null]);
        }

        $name = trim($me->first_name . ' ' . $me->last_name);
        $referral = Referral::create([
            ...$validated,
            'case_id'                => $case->id,
            'referred_by_user_id'    => null,
            'referred_by_student_id' => $me->id,
            'referrer_name'          => $name,
            'referrer_role'          => 'student',
            'referrer_source'        => 'student',
            'referrer_college'       => $me->college,
            'is_self_referred'       => false,
            'status'                 => 'submitted',
        ]);

        AuditLog::record('created', "Student {$me->student_id} referred student {$student->student_id} ({$referral->referral_code}).", $referral);

        $recipients = User::whereIn('role', ['admin', 'gcu_staff'])->where('is_active', true)->get();
        Notification::send($recipients, new NewReferralNotification($referral));
        $student->notify(new ReferredStudentNotification($referral));
        if (! $isExisting) {
            $student->notify(new FillOutFormsNotification());
        }

        return response()->json($referral->load('student:id,student_id,first_name,last_name,college'), 201);
    }

    public function sentReferrals(Request $request)
    {
        return Referral::with('student:id,student_id,first_name,last_name,college')
            ->where('referred_by_student_id', $request->user('student')->id)
            ->whereNull('complaint_id')
            ->latest()->paginate(20);
    }
}