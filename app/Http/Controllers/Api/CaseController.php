<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\CaseHandoff;
use App\Models\TestingRecord;
use App\Models\User;
use App\Notifications\UnreachableStudentNotification;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isFaculty() || $user->isDeanSecretary()) {
            abort(403, 'Access denied.');
        }

        $query = CaseFile::with(['student', 'counselor', 'referral'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->unit,   fn($q) => $q->where('current_unit', $request->unit))
            ->when($request->type,   fn($q) => $q->where('case_type', $request->type))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($s) =>
                $s->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            ));

        if ($user->isTMDUStaff()) {
            $query->where('current_unit', 'TMDU');
        }

        if ($user->isSDUHead()) {
            $query->where('current_unit', 'SDU');
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show(CaseFile $case)
    {
        AuditLog::record('viewed', "Viewed case {$case->case_number}.", $case);
        return response()->json($case->load([
            'student',
            'counselor',
            'referral.referredBy',
            'sessionNotes.recordedBy',
            'appointments.staff',
            'testingRecord',
            'handoffs.fromUser',
            'handoffs.toUser',
            'documents',
        ]));
    }

    public function update(Request $request, CaseFile $case)
    {
        $old = $case->toArray();
        $case->update($request->only([
            'primary_counselor_id',
            'target_resolution_date',
            'presenting_concern',
            'background_info',
            'interventions_applied',
            'outcomes',
            'recommendations',
        ]));
        AuditLog::record('updated', "Updated case {$case->case_number}.", $case, $old, $case->toArray());
        return response()->json($case);
    }

    public function updateStatus(Request $request, CaseFile $case)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,awaiting_testing,awaiting_external,on_hold,resolved,closed'
        ]);
        $old = ['status' => $case->status];
        $case->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated case {$case->case_number} status to {$request->status}.", $case, $old);
        return response()->json($case);
    }

    public function close(Request $request, CaseFile $case)
    {
        $request->validate([
            'interventions_applied' => 'required|string',
            'outcomes'              => 'required|string',
            'recommendations'       => 'nullable|string',
            'closure_summary'       => 'required|string',
        ]);

        $case->update([
            ...$request->only(['interventions_applied', 'outcomes', 'recommendations', 'closure_summary']),
            'status'      => 'closed',
            'closed_date' => today(),
        ]);

        AuditLog::record('closed', "Closed case {$case->case_number}.", $case);
        return response()->json($case);
    }

    public function summary(CaseFile $case)
    {
        AuditLog::record('exported', "Exported summary for case {$case->case_number}.", $case);
        return response()->json($case->load([
            'student',
            'counselor',
            'referral',
            'sessionNotes',
            'testingRecord',
            'handoffs',
        ]));
    }

    public function referToTmdu(Request $request, CaseFile $case)
    {
        $request->validate(['reason' => 'required|string']);

        $testing = TestingRecord::create([
            'case_id'             => $case->id,
            'student_id'          => $case->student_id,
            'referred_by_user_id' => $request->user()->id,
            'status'              => 'pending',
        ]);

        $case->update([
            'referred_to_tmdu' => true,
            'current_unit'     => 'TMDU',
            'status'           => 'awaiting_testing',
        ]);

        CaseHandoff::create([
            'case_id'      => $case->id,
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $request->user()->id,
            'from_unit'    => 'GCU',
            'to_unit'      => 'TMDU',
            'reason'       => $request->reason,
        ]);

        AuditLog::record('referred_tmdu', "Case {$case->case_number} referred to TMDU.", $case);
        return response()->json(['case' => $case, 'testing_record' => $testing]);
    }

    public function referExternal(Request $request, CaseFile $case)
    {
        $request->validate([
            'destination' => 'required|string',
            'reason'      => 'required|string',
        ]);

        $case->update([
            'referred_externally'           => true,
            'external_referral_destination' => $request->destination,
            'status'                        => 'awaiting_external',
        ]);

        AuditLog::record('referred_external', "Case {$case->case_number} referred externally to {$request->destination}.", $case);
        return response()->json($case);
    }

    public function handoff(Request $request, CaseFile $case)
    {
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'to_unit'    => 'required|in:GCU,SDU,TMDU',
            'reason'     => 'required|string',
            'notes'      => 'nullable|string',
        ]);

        CaseHandoff::create([
            'case_id'      => $case->id,
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $request->to_user_id,
            'from_unit'    => $case->current_unit,
            'to_unit'      => $request->to_unit,
            'reason'       => $request->reason,
            'notes'        => $request->notes,
        ]);

        $case->update(['current_unit' => $request->to_unit]);

        AuditLog::record('handoff', "Case {$case->case_number} handed off to {$request->to_unit}.", $case);
        return response()->json($case);
    }

    // FR 2.7: Alert Dean's Secretary for Unreachable Students
    public function flagUnreachable(Request $request, CaseFile $case)
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $case->update([
            'student_unreachable'     => true,
            'unreachable_flagged_at'  => now(),
            'unreachable_flagged_by'  => $request->user()->id,
            'unreachable_notes'       => $request->notes,
        ]);

        // Find Dean's Secretary of the student's college
        $deanSecretaries = User::where('role', 'dean_secretary')
            ->where('college', $case->student->college)
            ->where('is_active', true)
            ->get();

        // Send notification to each Dean's Secretary
        foreach ($deanSecretaries as $secretary) {
            $secretary->notify(new UnreachableStudentNotification($case, $request->notes ?? ''));
        }

        AuditLog::record('unreachable_flagged', "Student flagged as unreachable for case {$case->case_number}.", $case);

        return response()->json([
            'message'  => 'Student flagged as unreachable. Dean\'s Secretary has been notified.',
            'case'     => $case,
            'notified' => $deanSecretaries->count(),
        ]);
    }
}