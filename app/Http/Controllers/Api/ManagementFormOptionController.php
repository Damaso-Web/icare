<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferralFormOption;
use Illuminate\Http\Request;

class ManagementFormOptionController extends Controller
{
    const CATEGORIES = ['referral_type', 'referral_source', 'act_of_misconduct'];

    public function index(Request $request)
    {
        $query = ReferralFormOption::orderBy('category')->orderBy('sort_order')->orderBy('label');
        if ($request->category) {
            $query->where('category', $request->category);
        }
        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'   => 'required|string|in:' . implode(',', self::CATEGORIES),
            'value'      => 'required|string|max:100',
            'label'      => 'required|string|max:255',
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
        $validated = $request->validate([
            'label'      => 'required|string|max:255',
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