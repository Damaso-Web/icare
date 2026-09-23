<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

class ManagementProgramController extends Controller
{
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
            'name'       => 'required|string|max:255',
        ]);
        $exists = Program::where('college_id', $validated['college_id'])->where('name', $validated['name'])->exists();
        if ($exists) {
            return response()->json(['message' => 'This program already exists for that college.'], 422);
        }
        return response()->json(Program::create($validated)->load('college'), 201);
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'college_id' => 'required|exists:colleges,id',
            'name'       => 'required|string|max:255',
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