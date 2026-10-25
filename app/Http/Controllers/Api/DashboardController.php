<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Referral;
use App\Models\TestingRecord;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isGCUStaff()) {
            return response()->json($this->gcuDashboard($user));
        }

        if ($user->isSDUHead()) {
            return response()->json($this->sduDashboard());
        }

        if ($user->isTMDUStaff()) {
            return response()->json($this->tmduDashboard($user));
        }

        if ($user->isDeanSecretary()) {
            return response()->json($this->deanSecretaryDashboard($user));
        }

        if ($user->isFaculty()) {
            return response()->json($this->facultyDashboard($user));
        }

        return response()->json(['message' => 'No dashboard available.'], 403);
    }

    /** GCU's open SIFs: same rules as the Student Information Files list. */
    private function gcuOpenCases($user)
    {
        return CaseFile::where('current_unit', 'GCU')
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('student', fn($s) => $s->where('is_active', true))
            ->whereHas('referrals', fn($r) => $r->whereNotNull('acknowledged_at'))
            // Bug (10/25/2026): the Open Cases card counted every GCU case,
            // even for a gcu_staff counselor whose Student Information Files
            // list (CaseController::index(), B623) only shows their own
            // caseload - so a counselor with nothing assigned to them yet
            // still saw a dashboard full of other counselors' numbers. Only
            // the GCU Head (admin) keeps unit-wide visibility, same carve-out
            // already applied to the list itself.
            ->when($user->role === 'gcu_staff', fn($q) => $q->where(fn($w) => $w
                ->where('primary_counselor_id', $user->id)
                ->orWhere('previous_counselor_id', $user->id)));
    }

    /** GCU's referral inbox: same rules as the Referral Queue (no TMDU slips, complaints or archived).
     *  Deliberately NOT scoped to one counselor - a 'submitted' referral has no counselor assigned
     *  yet (that only happens on acknowledge/assign), so this is a shared inbox everyone in GCU can
     *  see and pick up, same as ReferralController::index() leaves it unscoped for gcu_staff too. */
    private function gcuPendingReferrals()
    {
        return Referral::where('status', 'submitted')
            ->where('is_archived', false)
            ->whereNull('complaint_id')
            ->notTmduOwned();
    }

    /** GCU appointments: same caseload scoping as AppointmentController::index() (B625) -
     *  a gcu_staff counselor only sees appointments for a case assigned to them (or with no
     *  case/counselor yet); only the GCU Head (admin) keeps unit-wide visibility. */
    private function gcuAppointments($user)
    {
        return Appointment::where('unit', 'GCU')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))
            ->when($user->role === 'gcu_staff', fn($q) => $q->where(fn($w) => $w
                ->whereNull('case_id')
                ->orWhereHas('case', fn($c) => $c->where('primary_counselor_id', $user->id))
                ->orWhereHas('case', fn($c) => $c->whereNull('primary_counselor_id'))));
    }

    private function gcuDashboard($user): array
    {
        return [
            'today' => today()->toDateString(),
            'stats' => [
                // Counted per unit and with the same filters as the pages the
                // cards link to (Student Information Files / Referral Queue), so
                // the number on the card always matches the list behind it.
                // Bug (10/25/2026): open_cases and appointments_today used to
                // count the whole GCU unit regardless of who was logged in, so
                // a gcu_staff counselor with nothing assigned to them yet still
                // saw a dashboard full of numbers - clicking through to the
                // actual Student Information Files / Appointments lists (which
                // already scope to their own caseload) showed none of it. Now
                // scoped the same way those lists are.
                'open_cases'        => $this->gcuOpenCases($user)->count(),
                'pending_referrals' => $this->gcuPendingReferrals()->count(),
                'appointments_today'=> $this->gcuAppointments($user)->where('appointment_date', today())->count(),
            ],
            'recent_referrals' => $this->gcuPendingReferrals()
                ->with(['student', 'referredBy'])
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => $this->gcuAppointments($user)
                ->with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
            'my_cases' => CaseFile::with(['student', 'latestReferral'])
                ->where('primary_counselor_id', $user->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    private function sduDashboard(): array
    {
        return [
            'stats' => [
                'active_cases'      => CaseFile::where('current_unit', 'SDU')
                                        ->whereIn('status', ['open', 'in_progress'])
                                        ->whereHas('student', fn($s) => $s->where('is_active', true))->count(),
                'pending_complaints' => Complaint::where('status', 'pending')->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'SDU')
                                        ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))->count(),
            ],
            'recent_cases' => CaseFile::with('student')
                ->where('current_unit', 'SDU')
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'SDU')
                ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
        ];
    }

    // B554: the Testing Records page (TestingRecordController::index()) only
    // shows a TMDU staff/head member their own assigned records plus unclaimed
    // records whose referral TMDU has already acknowledged - the dashboard's
    // counts were ignoring that scope entirely and counting every TestingRecord
    // system-wide, so "Pending Testing" could show a higher number than the
    // Testing Records page actually lists. Reusing the exact same scope here
    // keeps the two in sync.
    private function tmduVisibleTesting($user)
    {
        return TestingRecord::where(function ($qq) use ($user) {
            $qq->where('assigned_tester_user_id', $user->id)
               ->orWhere(function ($un) {
                   $un->whereNull('assigned_tester_user_id')
                      ->whereHas('referral', fn($r) => $r->whereNotNull('acknowledged_at'));
               });
        });
    }

    // B554 follow-up: TestingRecord has no 'in_progress' or 'completed' status
    // (its real values are pending/scheduled/test_administered/awaiting_results/
    // test_results_issued - see TestingRecordController), so those two cards
    // always counted zero. Grouped the same way the Testing Records page's own
    // stat tiles group them (testing/Index.vue), so the two stay in sync.
    private const IN_PROGRESS_STATUSES = ['scheduled', 'test_administered', 'awaiting_results'];

    private function tmduDashboard($user): array
    {
        return [
            'stats' => [
                'pending_testing'   => $this->tmduVisibleTesting($user)->where('status', 'pending')->count(),
                'in_progress'       => $this->tmduVisibleTesting($user)->whereIn('status', self::IN_PROGRESS_STATUSES)->count(),
                'completed'         => $this->tmduVisibleTesting($user)->where('status', 'test_results_issued')->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'TMDU')
                                        ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))->count(),
            ],
            'testing_queue' => $this->tmduVisibleTesting($user)->with(['student', 'referredBy'])
                ->whereIn('status', ['pending', 'scheduled'])
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'TMDU')
                ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
        ];
    }

    // B564: the Referral Queue (ReferralController::index()) that "Total"
    // etc. link to excludes archived referrals, Complaint-originated ones,
    // and anything already escalated to TMDU by default - this was counting
    // every referral the faculty member ever sent, with none of those
    // exclusions, so the dashboard number could run ahead of the list.
    private function facultyVisibleReferrals($user)
    {
        return Referral::where('referred_by_user_id', $user->id)
            ->where('is_archived', false)
            ->whereNull('complaint_id')
            ->notTmduOwned();
    }

    private function facultyDashboard($user): array
    {
        return [
            'stats' => [
                'my_referrals'      => $this->facultyVisibleReferrals($user)->count(),
                'pending'           => $this->facultyVisibleReferrals($user)->where('status', 'submitted')->count(),
                'acknowledged'      => $this->facultyVisibleReferrals($user)->where('status', 'acknowledged')->count(),
                'completed'         => $this->facultyVisibleReferrals($user)->whereIn('status', ['completed', 'closed'])->count(),
            ],
            'recent_referrals' => $this->facultyVisibleReferrals($user)
                ->with('student')
                ->latest()
                ->take(5)
                ->get(),
            // B608: a Dept Chair's "My Submitted Complaints" dashboard card
            // (Faculty and Deans don't file complaints directly, so this is
            // effectively Dept-Chair-only data, but harmless for any faculty
            // account that happens to have filed one too).
            'recent_complaints' => Complaint::where('filed_by_user_id', $user->id)
                ->with('complainee')
                ->latest()
                ->take(5)
                ->get(),
            // B639: the Dept Chair dashboard header shows this in place of
            // "OSS", so it needs the chair's own college abbreviation (e.g.
            // "CIS"). Null when none can be resolved - the frontend falls
            // back to "OSS" in that case.
            'affiliation_abbrev' => \App\Support\CollegeName::abbrev($user->college),
        ];
    }

    // B567: same mismatch as B554/B564 - the Referral Queue
    // (ReferralController::index()) that the Dean's cards link to excludes
    // archived referrals, Complaint-originated ones, and anything already
    // escalated to TMDU by default - this query had none of those
    // exclusions, so e.g. "Total" could count referrals the list itself
    // never shows (Total showed 21 while the list behind it had 13).
    private function deanSecretaryVisibleReferrals($user)
    {
        return Referral::whereHas('student', fn($s) => $s->whereIn('college', \App\Support\CollegeName::equivalents($user->college)))
            ->where('is_archived', false)
            ->whereNull('complaint_id')
            ->notTmduOwned();
    }

    private function deanSecretaryDashboard($user): array
    {
        $collegeReferrals = $this->deanSecretaryVisibleReferrals($user);

        return [
            'stats' => [
                'my_referrals' => (clone $collegeReferrals)->count(),
                'pending'      => (clone $collegeReferrals)->where('status', 'submitted')->count(),
                'acknowledged' => (clone $collegeReferrals)->where('status', 'acknowledged')->count(),
                'completed'    => (clone $collegeReferrals)->whereIn('status', ['completed', 'closed'])->count(),
            ],
            'recent_referrals' => (clone $collegeReferrals)->with('student')
                ->latest()
                ->take(5)
                ->get(),
            // Bug (10/24/2026, B633): a Dean had no "My Submitted Complaints"
            // section at all - this reuses the same card and query shape
            // already built for Dept Chair (B608), kept personal (what the
            // Dean themself filed) to match what its "View all" link (Sent
            // Complaints) actually shows.
            'recent_complaints' => Complaint::where('filed_by_user_id', $user->id)
                ->with('complainee')
                ->latest()
                ->take(5)
                ->get(),
            // B637 (revised, 10/25/2026): the Dean dashboard header shows
            // this in place of "OSS", so it needs the Dean's own college
            // abbreviation (e.g. "CIS"). Null when none can be resolved -
            // the frontend falls back to "OSS" in that case.
            'affiliation_abbrev' => \App\Support\CollegeName::abbrev($user->college),
        ];
    }
}