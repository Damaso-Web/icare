<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\ReferralFormOption;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deactivate / Archive / Reactivate for Management reference data. Used instead
 * of deleting when other records still point at the item (B431, B432).
 */
class ManagementStatusController extends Controller
{
    private const ACTION_FOR = ['inactive' => 'deactivated', 'archived' => 'archived', 'active' => 'reactivated'];

    public function update(Request $request, string $kind, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::ACTION_FOR))],
        ], ['status.required' => 'Please choose a status.']);

        [$model, $tab, $name] = $this->find($kind, $id);
        ManagementGuard::assertUnchanged($request, $model);

        $old = $model->status ?: 'active';
        $new = $validated['status'];
        abort_if($old === $new, 422, 'This item already has that status.');
        abort_if($old === 'archived' && $new === 'inactive', 422, 'An archived item can only be reactivated.');

        // Every change needs a reason that is allowed for this tab and action (checked here, not just in the dropdown).
        $action = ['inactive' => 'deactivate', 'archived' => 'archive', 'active' => 'reactivate'][$new];
        [$reason, $note] = ManagementGuard::requireStatusReason($request, $tab, $action);

        // The current school-calendar term can't be deactivated or archived until another term is set as current.
        if ($model instanceof AcademicYear && $model->is_current && $new !== 'active') {
            abort(422, 'This is the current term. Set another term as current first.');
        }

        // Archiving retires the item for good, so it is refused while records still use it.
        if ($new === 'archived' && !($model instanceof AcademicYear)) {
            ManagementGuard::blockIfLinked(['wellness' => 'service', 'disciplinary' => 'option', 'referral sources' => 'source'][$tab] ?? $tab, $this->links($kind, $model), 'Deactivate it instead, or reassign those records first.');
        }

        $model->forceFill(['status' => $new])->save();
        ManagementGuard::audit($tab, self::ACTION_FOR[$new], $name, $model, ['status' => $old], ['status' => $new], $reason, $note);

        return response()->json($model->fresh());
    }

    private function links(string $kind, $model): array
    {
        return match ($kind) {
            'colleges'     => ManagementCollegeController::links($model),
            'programs'     => ManagementProgramController::links($model),
            'departments'  => ManagementDepartmentController::links($model),
            'form-options' => ManagementFormOptionController::links($model),
            default        => [],
        };
    }

    private function find(string $kind, int $id): array
    {
        switch ($kind) {
            case 'colleges':
                $m = College::findOrFail($id);
                return [$m, 'college', $m->name];
            case 'programs':
                $m = Program::findOrFail($id);
                return [$m, 'program', $m->name];
            case 'departments':
                $m = Department::findOrFail($id);
                return [$m, 'department', $m->name];
            case 'academic-years':
                $m = AcademicYear::findOrFail($id);
                return [$m, 'school calendar', $m->label()];
            case 'form-options':
                $m = ReferralFormOption::findOrFail($id);
                return [$m, ManagementFormOptionController::tabFor($m), $m->label];
        }
        abort(404);
    }
}