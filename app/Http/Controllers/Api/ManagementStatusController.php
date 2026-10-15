<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $model->forceFill(['status' => $new])->save();
        ManagementGuard::audit($tab, self::ACTION_FOR[$new], $name, $model, ['status' => $old], ['status' => $new]);

        return response()->json($model->fresh());
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
            case 'form-options':
                $m = ReferralFormOption::findOrFail($id);
                return [$m, ManagementFormOptionController::tabFor($m), $m->label];
        }
        abort(404);
    }
}