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

    // Find a student by exact Student ID Number OR by full name (first and last
    // name both required), so a student cannot browse the masterlist. Returns
    // the matches (more than one when two students share a name). A student
    // can find themselves too; the complaint form rejects that on submit.
    public function lookup(Request $request)
    {
        $request->validate(['student_id' => 'required|string|max:100']);
        $q = trim($request->student_id);

        $found = Student::where('student_id', $q)->where('is_active', true)->get();

        if ($found->isEmpty()) {
            $tokens = collect(preg_split('/[\s,]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($t) => trim($t, '.'))->filter()->values();
            if ($tokens->count() >= 2) {
                $found = Student::where('is_active', true)
                    ->where(function ($w) use ($tokens) {
                        foreach ($tokens as $t) {
                            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $t) . '%';
                            $w->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like);
                        }
                    })
                    ->limit(100)->get()
                    ->filter(function ($s) use ($tokens) {
                        $first = preg_split('/\s+/u', mb_strtolower(trim($s->first_name)), -1, PREG_SPLIT_NO_EMPTY);
                        $last = preg_split('/\s+/u', mb_strtolower(trim($s->last_name)), -1, PREG_SPLIT_NO_EMPTY);
                        $middle = preg_split('/\s+/u', mb_strtolower(trim((string) $s->middle_name)), -1, PREG_SPLIT_NO_EMPTY);
                        $all = array_merge($first, $last, $middle);
                        // every typed word belongs to this student, and both a first and a last name word were typed
                        return $tokens->every(fn ($t) => in_array($t, $all, true))
                            && $tokens->contains(fn ($t) => in_array($t, $first, true))
                            && $tokens->contains(fn ($t) => in_array($t, $last, true));
                    })->take(10)->values();
            }
        }

        if ($found->isEmpty()) {
            return response()->json(['message' => 'No active student found. Enter the exact ID Number or the full name (first and last name).'], 404);
        }

        return ['matches' => $found->map(fn ($s) => [
            'id'      => $s->id,
            'name'    => trim($s->first_name . ' ' . $s->last_name),
            'college' => $s->college,
            'program' => $s->program,
            'is_me'   => (int) $s->id === (int) $request->user('student')->id,
        ])->values()];
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

        // Choosing yourself is allowed: it is recorded as a self-referral.
        $self = (int) $validated['student_id'] === (int) $me->id;
        if ($self) {
            $waiting = $me->referrals()->where('is_self_referred', true)->where('status', 'submitted')->exists();
            abort_if($waiting, 422, 'You already have a request waiting for GCU. Please wait until it is acknowledged.');
        }
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
            'referrer_source'        => $self ? 'self' : 'student',
            'referrer_college'       => $me->college,
            'is_self_referred'       => $self,
            'status'                 => 'submitted',
        ]);

        AuditLog::record('created', $self
            ? "Student {$me->student_id} referred themselves ({$referral->referral_code})."
            : "Student {$me->student_id} referred student {$student->student_id} ({$referral->referral_code}).", $referral);

        $recipients = User::whereIn('role', ['admin', 'gcu_staff'])->where('is_active', true)->get();
        Notification::send($recipients, new NewReferralNotification($referral));
        if (! $self) {
            $student->notify(new ReferredStudentNotification($referral));
        }
        if (! $isExisting) {
            $student->notify(new FillOutFormsNotification());
        }

        return response()->json($referral->load('student:id,student_id,first_name,last_name,college'), 201);
    }

    public function updateSent(Request $request, $id)
    {
        $referral = Referral::where('referred_by_student_id', $request->user('student')->id)->findOrFail($id);
        $referral->applyReferrerEdit($request);
        return response()->json($referral->load('student:id,student_id,first_name,last_name,college'));
    }

    public function sentReferrals(Request $request)
    {
        return ReferralController::sentList(
            Referral::where('referred_by_student_id', $request->user('student')->id)->whereNull('complaint_id'),
            $request
        );
    }
}