<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;

class ManagementProgramController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    public function index(Request $request)
    {
        $query = Program::with(['college', 'department'])->orderBy('id');
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
            'college_id'    => ['required', \Illuminate\Validation\Rule::exists('colleges', 'id')->where('status', 'active')],
            'name'          => ['required', 'string', 'max:255', 'regex:' . self::LABEL_REGEX],
            'department_id' => ['nullable', \Illuminate\Validation\Rule::exists('departments', 'id')->where('college_id', $request->college_id)],
        ], [
            'department_id.exists' => 'That department does not belong to the selected college.',
        ]);
        $exists = Program::where('college_id', $validated['college_id'])->where('name', $validated['name'])->exists();
        if ($exists) {
            return response()->json(['message' => 'This program already exists for that college.'], 422);
        }
        $validated['name'] = ManagementGuard::capFirst($validated['name']);
        $program = Program::create($validated)->load(['college', 'department']);
        ManagementGuard::audit('program', 'created', $program->name, $program, [], ['name' => $program->name, 'college' => $program->college?->name, 'department' => $program->department?->name]);
        return response()->json($program, 201);
    }

    public function update(Request $request, Program $program)
    {
        ManagementGuard::assertEditable($program);
        $nameRules = ['required', 'string', 'max:255'];
        if ($request->input('name') !== $program->name) $nameRules[] = 'regex:' . self::LABEL_REGEX;

        $validated = $request->validate([
            'college_id'    => 'required|exists:colleges,id',
            'name'          => $nameRules,
            'department_id' => ['nullable', \Illuminate\Validation\Rule::exists('departments', 'id')->where('college_id', $request->college_id)],
        ], [
            'department_id.exists' => 'That department does not belong to the selected college.',
        ]);
        $validated['name'] = ManagementGuard::capFirst($validated['name']);
        $old = ['name' => $program->name, 'college' => $program->college?->name, 'department' => $program->department?->name];
        $program->update($validated);
        $program->load(['college', 'department']);
        ManagementGuard::audit('program', 'updated', $program->name, $program, $old, ['name' => $program->name, 'college' => $program->college?->name, 'department' => $program->department?->name]);
        return response()->json($program);
    }

    public static function links(Program $program): array
    {
        return ['students' => \App\Models\Student::where('program', $program->name)->count()];
    }

    public function usage(Program $program)
    {
        return response()->json(self::links($program));
    }

    public function destroy(Request $request, Program $program)
    {
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'program');
        ManagementGuard::assertUnchanged($request, $program);
        ManagementGuard::blockIfLinked('program', self::links($program));

        $old = ['name' => $program->name];
        $program->delete();
        ManagementGuard::audit('program', 'deleted', $old['name'], $program, $old, [], $reason, $note);
        return response()->json(['message' => 'Program deleted.']);
    }
}