<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\College;
use Illuminate\Http\Request;

class ManagementCollegeController extends Controller
{
    public function index()
    {
        return College::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255|unique:colleges,name',
            'abbrev' => 'nullable|string|max:20',
        ]);
        return response()->json(College::create($validated), 201);
    }

    public function update(Request $request, College $college)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255|unique:colleges,name,' . $college->id,
            'abbrev' => 'nullable|string|max:20',
        ]);
        $college->update($validated);
        return response()->json($college);
    }

    public function destroy(College $college)
    {
        if ($college->programs()->exists() || $college->departments()->exists()) {
            return response()->json(['message' => 'Cannot delete a college that still has programs or departments under it.'], 422);
        }
        $college->delete();
        return response()->json(['message' => 'College deleted.']);
    }
}