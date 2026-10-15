<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;

class ManagementDepartmentController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    public function index(Request $request)
    {
        $query = Department::with('college')->orderBy('id');
        // ?active=1: only what can be picked for a new record (the item and its college are both active).
        if ($request->boolean('active')) {
            $query->where('status', 'active')->whereHas('college', fn($c) => $c->where('status', 'active'));
        }
        if ($request->college_id) {
            $query->where('college_id', $request->college_id);
        }
        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'college_id' => ['required', \Illuminate\Validation\Rule::exists('colleges', 'id')->where('status', 'active')],
            'name'       => ['required', 'string', 'max:255', 'regex:' . self::LABEL_REGEX],
        ]);
        $exists = Department::where('college_id', $validated['college_id'])->where('name', $validated['name'])->exists();
        if ($exists) {
            return response()->json(['message' => 'This department already exists for that college.'], 422);
        }
        $validated['name'] = ManagementGuard::capFirst($validated['name']);
        $department = Department::create($validated)->load('college');
        ManagementGuard::audit('department', 'created', $department->name, $department, [], ['name' => $department->name, 'college' => $department->college?->name]);
        return response()->json($department, 201);
    }

    public function update(Request $request, Department $department)
    {
        $nameRules = ['required', 'string', 'max:255'];
        if ($request->input('name') !== $department->name) $nameRules[] = 'regex:' . self::LABEL_REGEX;

        $validated = $request->validate([
            'college_id' => 'required|exists:colleges,id',
            'name'       => $nameRules,
        ]);
        $validated['name'] = ManagementGuard::capFirst($validated['name']);
        $old = ['name' => $department->name, 'college' => $department->college?->name];
        $department->update($validated);
        $department->load('college');
        ManagementGuard::audit('department', 'updated', $department->name, $department, $old, ['name' => $department->name, 'college' => $department->college?->name]);
        return response()->json($department);
    }

    public static function links(Department $department): array
    {
        return ['employees' => \App\Models\User::where('department', $department->name)->count()];
    }

    public function usage(Department $department)
    {
        return response()->json(self::links($department));
    }

    public function destroy(Request $request, Department $department)
    {
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'department');
        ManagementGuard::assertUnchanged($request, $department);
        ManagementGuard::blockIfLinked('department', self::links($department));

        $old = ['name' => $department->name];
        $department->delete();
        ManagementGuard::audit('department', 'deleted', $old['name'], $department, $old, [], $reason, $note);
        return response()->json(['message' => 'Department deleted.']);
    }
}