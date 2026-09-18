<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\CaseIntervention;
use App\Models\Referral;
use Illuminate\Http\Request;

class CaseInterventionController extends Controller
{
    public function store(Request $request, CaseFile $case)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized. Only OSS staff may access case files.');
        }

        $validated = $request->validate([
            'referral_id' => 'nullable|exists:referrals,id',
            'type'        => 'required|in:previous_intervention,follow_up,parent_conference,home_visit,referral_external,other',
            'description' => 'required|string',
            'excused'     => 'nullable|boolean',
        ]);

        if (!empty($validated['referral_id'])) {
            $referral = Referral::findOrFail($validated['referral_id']);
            if ($referral->case_id !== $case->id) {
                abort(422, 'This referral does not belong to this case.');
            }

            if ($referral->referral_type === 'class_attendance') {
                $locked = $case->interventions()
                    ->where('referral_id', $referral->id)
                    ->where('excused', false)
                    ->exists();

                if ($locked) {
                    abort(422, 'This referral has already been marked Unexcused. No further interventions can be added.');
                }
            } else {
                $validated['excused'] = null;
            }
        } else {
            $validated['excused'] = null;
        }

        $intervention = CaseIntervention::create([
            ...$validated,
            'case_id'              => $case->id,
            'person_in_charge_id'  => $user->id,
            'recorded_by_user_id'  => $user->id,
        ]);

        AuditLog::record('created', "Recorded a {$validated['type']} entry for case {$case->case_number}.", $intervention);

        return response()->json($intervention->load(['personInCharge', 'recordedBy', 'referral']), 201);
    }

    public function complete(Request $request, CaseIntervention $intervention)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized. Only OSS staff may access case files.');
        }

        $intervention->update([
            'is_completed'         => true,
            'completed_at'         => now(),
            'completed_by_user_id' => $user->id,
        ]);

        AuditLog::record('completed', "Marked an intervention as completed for case #{$intervention->case_id}.", $intervention);

        return response()->json($intervention->load(['personInCharge', 'recordedBy', 'referral', 'completedBy']));
    }

    public function destroy(Request $request, CaseIntervention $intervention)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized. Only OSS staff may access case files.');
        }

        AuditLog::record('deleted', "Deleted a previous intervention entry for case #{$intervention->case_id}.", $intervention, $intervention->toArray());
        $intervention->delete();

        return response()->json(['message' => 'Intervention entry deleted.']);
    }
}
