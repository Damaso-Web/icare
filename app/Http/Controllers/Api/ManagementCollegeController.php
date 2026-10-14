<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;

class ManagementCollegeController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

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
        $validated['name'] = ManagementGuard::capFirst($validated['name']);
        $validated['abbrev'] = isset($validated['abbrev']) ? mb_strtoupper(trim($validated['abbrev'])) : null;
        $college = College::create($validated);
        ManagementGuard::audit('college', 'created', $college->name, $college, [], $college->only(['name', 'abbrev']));
        return response()->json($college, 201);
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
        $validated['name'] = ManagementGuard::capFirst($validated['name']);
        $validated['abbrev'] = isset($validated['abbrev']) ? mb_strtoupper(trim($validated['abbrev'])) : null;
        $old = $college->only(['name', 'abbrev']);
        $college->update($validated);
        ManagementGuard::audit('college', 'updated', $college->name, $college, $old, $college->only(['name', 'abbrev']));
        return response()->json($college);
    }

    /** What still points at this college (also used to warn before the delete dialog opens). */
    public static function links(College $college): array
    {
        return [
            'students'    => \App\Models\Student::where('college', $college->name)->count(),
            'employees'   => \App\Models\User::where('college', $college->name)->count(),
            'programs'    => $college->programs()->count(),
            'departments' => $college->departments()->count(),
        ];
    }

    public function usage(College $college)
    {
        return response()->json(self::links($college));
    }

    public function destroy(Request $request, College $college)
    {
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'college');
        ManagementGuard::assertUnchanged($request, $college);
        // Checked again here, at the moment of deletion, not only when the dialog opened.
        ManagementGuard::blockIfLinked('college', self::links($college));

        $old = $college->only(['name', 'abbrev']);
        $college->delete();
        ManagementGuard::audit('college', 'deleted', $old['name'], $college, $old, [], $reason, $note);
        return response()->json(['message' => 'College deleted.']);
    }
}