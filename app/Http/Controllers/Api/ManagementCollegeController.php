<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\College;
use Illuminate\Http\Request;

class ManagementCollegeController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    public function index()
    {
        return College::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255', 'regex:' . self::LABEL_REGEX, 'unique:colleges,name'],
            'abbrev' => ['nullable', 'string', 'max:20', 'regex:' . self::LABEL_REGEX],
        ]);
        return response()->json(College::create($validated), 201);
    }

    public function update(Request $request, College $college)
    {
        $nameRules   = ['required', 'string', 'max:255', 'unique:colleges,name,' . $college->id];
        $abbrevRules = ['nullable', 'string', 'max:20'];
        if ($request->input('name') !== $college->name)     $nameRules[]   = 'regex:' . self::LABEL_REGEX;
        if ($request->input('abbrev') !== $college->abbrev) $abbrevRules[] = 'regex:' . self::LABEL_REGEX;

        $validated = $request->validate([
            'name'   => $nameRules,
            'abbrev' => $abbrevRules,
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