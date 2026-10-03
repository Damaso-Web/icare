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
    /**
     * Enforce the mutually-exclusive intervention rules:
     *  - admin / system_admin / gcu_staff: may record every type EXCEPT `sanction`
     *  - sdu_head:                        may ONLY record `sanction`
     *  - tmdu_staff / faculty / dean_secretary: no access
     */
    private function authorizeInterventionAccess(?string $type = null): void
    {
        $role = request()->user()?->role;
        $counselors = ['admin', 'system_admin', 'gcu_staff'];

        if (in_array($role, $counselors, true)) {
            if ($type === 'sanction') {
                abort(403, 'Sanctions are recorded by SDU only.');
            }
            return;
        }

        if ($role === 'sdu_head') {
            if ($type !== 'sanction') {
                abort(403, 'SDU can only record sanctions given to the student.');
            }
            return;
        }

        abort(403, 'Unauthorized. Only GCU staff and SDU may record interventions.');
    }

    public function store(Request $request, CaseFile $case)
    {
        $user = $request->user();

        $validated = $request->validate([
            'referral_id' => 'nullable|exists:referrals,id',
            'type'        => 'required|in:previous_intervention,follow_up,parent_conference,home_visit,referral_external,sanction,detailed_report,other',
            'description' => 'required|string',
        ]);

        $this->authorizeInterventionAccess($validated['type']);

        // The excused/unexcused determination (and its "locked once
        // Unexcused" rule) used to live here, folded into the plain
        // Previous Interventions log for class_attendance referrals. It's
        // now its own Admission Slip (QF-OSS-GCU-09), with its own
        // admission_excused column and lock, handled entirely by
        // ReferralController::saveAdmissionSlip() - this endpoint no longer
        // has anything to do with it.
        if (!empty($validated['referral_id'])) {
            $referral = Referral::findOrFail($validated['referral_id']);
            if ($referral->case_id !== $case->id) {
                abort(422, 'This referral does not belong to this case.');
            }

            // Session Notes/Follow-up/Previous Interventions all require the
            // referral to be acknowledged and have an attended appointment,
            // same broader SIF edit gate the rest of the referral enforces.
            // Sanctions/Detailed Reports (SDU's Incident Report domain) are
            // exempt - those aren't gated by this referral-level workflow.
            if (!in_array($validated['type'], ['sanction', 'detailed_report'], true) && !$referral->canEditSif()) {
                abort(422, 'This referral must be acknowledged and have an appointment set and attended before entries can be added.');
            }
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
        $this->authorizeInterventionAccess($intervention->type);

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
        $this->authorizeInterventionAccess($intervention->type);

        AuditLog::record('deleted', "Deleted a previous intervention entry for case #{$intervention->case_id}.", $intervention, $intervention->toArray());
        $intervention->delete();

        return response()->json(['message' => 'Intervention entry deleted.']);
    }
}