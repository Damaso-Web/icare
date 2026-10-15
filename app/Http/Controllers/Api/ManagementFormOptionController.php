<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferralFormOption;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;

class ManagementFormOptionController extends Controller
{
    // Plain <input> labels/names only (never <textarea>-backed fields): letters, numbers,
    // spaces and . , ' - & ( ). No other special characters. On EDIT the check is skipped when
    // the value is unchanged, so existing records saved before this rule (e.g. seeded labels
    // that contain a slash) can still have their other fields updated.
    private const LABEL_REGEX = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    const CATEGORIES = ['referral_type', 'referral_source', 'act_of_misconduct'];

    public function index(Request $request)
    {
        $query = ReferralFormOption::orderBy('category')->orderBy('sort_order')->orderBy('id');
        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->boolean('active')) {
            $query->where('status', 'active');
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
        // New options go to the bottom: seeded options are numbered 1..n and a
        // blank sort_order would default to 0, which sorts to the top.
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = (int) ReferralFormOption::where('category', $validated['category'])->max('sort_order') + 1;
        }
        $validated['label'] = ManagementGuard::capFirst($validated['label']);
        $option = ReferralFormOption::create($validated);
        ManagementGuard::audit(self::tabFor($option), 'created', $option->label, $option, [], ['label' => $option->label, 'value' => $option->value]);
        return response()->json($option, 201);
    }

    // value is intentionally not editable - it's the stored value on existing
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
        $validated['label'] = ManagementGuard::capFirst($validated['label']);
        $old = $formOption->only(['label']);
        $formOption->update($validated);
        ManagementGuard::audit(self::tabFor($formOption), 'updated', $formOption->label, $formOption, $old, $formOption->only(['label']));
        return response()->json($formOption);
    }

    /** Which Management tab an option belongs to (decides the deletion reasons). */
    public static function tabFor(ReferralFormOption $o): string
    {
        return match ($o->category) {
            'referral_type'     => 'wellness',
            'act_of_misconduct' => 'disciplinary',
            default             => 'referral sources',
        };
    }

    public static function links(ReferralFormOption $o): array
    {
        return match ($o->category) {
            'referral_type'     => ['referrals' => \App\Models\Referral::where('referral_type', $o->value)->count()],
            'act_of_misconduct' => [
                'referrals'  => \App\Models\Referral::whereIn('violation_type', [$o->value, $o->label])->count(),
                'complaints' => \App\Models\Complaint::whereIn('violation_type', [$o->value, $o->label])->count(),
            ],
            default => [],
        };
    }

    public function usage(ReferralFormOption $formOption)
    {
        return response()->json(self::links($formOption));
    }

    public function destroy(Request $request, ReferralFormOption $formOption)
    {
        $tab = self::tabFor($formOption);
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, $tab);
        ManagementGuard::assertUnchanged($request, $formOption);
        ManagementGuard::blockIfLinked($tab === 'wellness' ? 'service' : 'option', self::links($formOption));

        $old = $formOption->only(['label', 'value']);
        $formOption->delete();
        ManagementGuard::audit($tab, 'deleted', $old['label'], $formOption, $old, [], $reason, $note);
        return response()->json(['message' => 'Option deleted.']);
    }
}