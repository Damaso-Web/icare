<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class ManagementDepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::with('college')->orderBy('name');
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
        $exists = Department::where('college_id', $validated['college_id'])->where('name', $validated['name'])->exists();
        if ($exists) {
            return response()->json(['message' => 'This department already exists for that college.'], 422);
        }
        return response()->json(Department::create($validated)->load('college'), 201);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'college_id' => 'required|exists:colleges,id',
            'name'       => 'required|string|max:255',
        ]);
        $department->update($validated);
        return response()->json($department->load('college'));
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }
}