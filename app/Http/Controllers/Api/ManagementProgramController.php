<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

class ManagementProgramController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    public function index(Request $request)
    {
        $query = Program::with('college')->orderBy('name');
        if ($request->college_id) {
            $query->where('college_id', $request->college_id);
        }
        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'college_id' => 'required|exists:colleges,id',
            'name'       => ['required', 'string', 'max:255', 'regex:' . self::LABEL_REGEX],
        ]);
        $exists = Program::where('college_id', $validated['college_id'])->where('name', $validated['name'])->exists();
        if ($exists) {
            return response()->json(['message' => 'This program already exists for that college.'], 422);
        }
        return response()->json(Program::create($validated)->load('college'), 201);
    }

    public function update(Request $request, Program $program)
    {
        $nameRules = ['required', 'string', 'max:255'];
        if ($request->input('name') !== $program->name) $nameRules[] = 'regex:' . self::LABEL_REGEX;

        $validated = $request->validate([
            'college_id' => 'required|exists:colleges,id',
            'name'       => $nameRules,
        ]);
        $program->update($validated);
        return response()->json($program->load('college'));
    }

    public function destroy(Program $program)
    {
        $program->delete();
        return response()->json(['message' => 'Program deleted.']);
    }
}