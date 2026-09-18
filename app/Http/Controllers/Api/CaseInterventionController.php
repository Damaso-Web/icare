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
            'is_completed'         => false,
        ]);

        AuditLog::record('created', "Recorded a {$validated['type']} intervention for case {$case->case_number}.", $intervention);

        return response()->json($intervention->load(['personInCharge', 'recordedBy', 'referral']), 201);
    }

    // Feature 56: Record completion of required intervention
    public function markCompleted(Request $request, CaseIntervention $intervention)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized. Only OSS staff may access case files.');
        }

        $intervention->update([
            'is_completed'          => true,
            'completed_at'          => now(),
            'completed_by_user_id'  => $user->id,
        ]);

        AuditLog::record('intervention_completed', "Marked intervention as completed for case {$intervention->caseFile->case_number}.", $intervention);

        return response()->json($intervention->load(['personInCharge', 'recordedBy', 'completedBy', 'referral']));
    }
}