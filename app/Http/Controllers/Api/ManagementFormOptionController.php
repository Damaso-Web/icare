<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferralFormOption;
use Illuminate\Http\Request;

class ManagementFormOptionController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    const CATEGORIES = ['referral_type', 'referral_source', 'act_of_misconduct'];

    public function index(Request $request)
    {
        $query = ReferralFormOption::orderBy('category')->orderBy('sort_order')->orderBy('id');
        if ($request->category) {
            $query->where('category', $request->category);
        }
        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'   => 'required|string|in:' . implode(',', self::CATEGORIES),
            'value'      => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'label'      => ['required', 'string', 'max:255', 'regex:' . self::LABEL_REGEX],
            'sort_order' => 'nullable|integer',
        ]);
        $exists = ReferralFormOption::where('category', $validated['category'])->where('value', $validated['value'])->exists();
        if ($exists) {
            return response()->json(['message' => 'This option already exists for that category.'], 422);
        }
        return response()->json(ReferralFormOption::create($validated), 201);
    }

    // value is intentionally not editable — it's the stored value on existing
    // referral records, so renaming it would silently reinterpret history.
    // Only the display label and ordering can change; retire+recreate for a real rename.
    public function update(Request $request, ReferralFormOption $formOption)
    {
        $labelRules = ['required', 'string', 'max:255'];
        if ($request->input('label') !== $formOption->label) $labelRules[] = 'regex:' . self::LABEL_REGEX;

        $validated = $request->validate([
            'label'      => $labelRules,
            'sort_order' => 'nullable|integer',
        ]);
        $formOption->update($validated);
        return response()->json($formOption);
    }

    public function destroy(ReferralFormOption $formOption)
    {
        $formOption->delete();
        return response()->json(['message' => 'Option deleted.']);
    }
}