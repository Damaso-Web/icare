<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\DocumentSetting;
use App\Models\Referral;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Document headers (Revision No. / Effectivity / Ctrl No.), kept as versions:
 * saving creates a new version, exactly one version per form is active, and each
 * version remembers when it was active so older documents keep the header they
 * were made with (GET ...?at=<date>).
 */
class DocumentSettingController extends Controller
{
    /** TMDU's own forms (QF-TMDU-...). Every other code is a GCU / shared OSS form. */
    private function isTmduCode(string $code): bool
    {
        return str_starts_with($code, 'QF-TMDU');
    }

    // The GCU Head (admin) manages GCU's headers; the System Admin manages TMDU's.
    private function authorizeManage(Request $request, string $code): void
    {
        $role = $request->user()?->role;
        $allowed = $this->isTmduCode($code) ? $role === 'system_admin' : $role === 'admin';
        abort_unless($allowed, 403, $this->isTmduCode($code)
            ? 'Only the System Admin can manage the TMDU document header.'
            : 'Only the GCU Head can manage this document header.');
    }

    private function active(string $code): DocumentSetting
    {
        return DocumentSetting::where('document_code', $code)->where('is_active', true)->orderByDesc('version_no')->first()
            ?? DocumentSetting::where('document_code', $code)->orderByDesc('version_no')->first()
            ?? DocumentSetting::create(['document_code' => $code, 'revision_no' => '01', 'version_no' => 1, 'is_active' => true, 'status' => 'active', 'activated_at' => '2000-01-01 00:00:00']);
    }

    // Document headers are shown on the forms wherever they appear (Refer
    // Student, Referral Details...), so staff can read the shared OSS ones.
    // TMDU's own header is not for the GCU Head: only TMDU and the System
    // Admin may read it.
    public function show(Request $request, string $code)
    {
        $role = $request->user()?->role;
        if ($this->isTmduCode($code) && !in_array($role, ['tmdu_staff', 'tmdu_head', 'system_admin'], true)) {
            abort(403, "This document header belongs to TMDU.");
        }

        // ?at=<date>: the header that was in force when an older document was made.
        if ($request->filled('at')) {
            try {
                $at = Carbon::parse($request->query('at'));
                $version = DocumentSetting::where('document_code', $code)
                    ->where('activated_at', '<=', $at)
                    ->where(fn($q) => $q->whereNull('deactivated_at')->orWhere('deactivated_at', '>', $at))
                    ->orderByDesc('version_no')->first();
                if ($version) return response()->json($version);
            } catch (\Throwable $e) { /* fall through to the active header */ }
        }

        return response()->json($this->active($code));
    }

    public function versions(Request $request, string $code)
    {
        $this->authorizeManage($request, $code);
        $this->active($code);
        $rows = DocumentSetting::where('document_code', $code)->orderByDesc('version_no')->get()
            ->map(fn($v) => array_merge($v->toArray(), ['used_count' => $this->usedCount($v)]));
        return response()->json($rows);
    }

    // Saving a header adds a NEW version and makes it the active one; the old versions stay.
    public function update(Request $request, string $code)
    {
        $this->authorizeManage($request, $code);

        // The TMDU Appointment Slip (QF-TMDU-02) has a blank Ctrl No. on the
        // printed form, so its Ctrl No. fields are optional.
        $ctrlOptional = $code === 'QF-TMDU-02';

        $validated = $request->validate([
            'revision_no'       => ['required', 'string', 'max:4', 'regex:/^[0-9]{1,4}$/'],
            'effectivity_date'  => 'required|date',
            'ctrl_no_year'      => [$ctrlOptional ? 'nullable' : 'required', 'string', 'max:4', 'regex:/^[0-9]{1,4}$/'],
            'ctrl_no_term'      => [$ctrlOptional ? 'nullable' : 'required', 'in:1,2,S'],
        ]);

        $current = $this->active($code);
        $same = $current->revision_no === $validated['revision_no']
            && optional($current->effectivity_date)->format('Y-m-d') === Carbon::parse($validated['effectivity_date'])->format('Y-m-d')
            && (string) $current->ctrl_no_year === (string) ($validated['ctrl_no_year'] ?? '')
            && (string) $current->ctrl_no_term === (string) ($validated['ctrl_no_term'] ?? '');
        abort_if($same, 422, 'Nothing changed. Edit the header before saving a new version.');

        $new = DB::transaction(function () use ($code, $validated, $current, $request) {
            $now = now();
            DocumentSetting::where('document_code', $code)->where('is_active', true)
                ->update(['is_active' => false, 'deactivated_at' => $now]);
            return DocumentSetting::create($validated + [
                'document_code'      => $code,
                'version_no'         => (int) DocumentSetting::where('document_code', $code)->max('version_no') + 1,
                'status'             => 'active',
                'is_active'          => true,
                'activated_at'       => $now,
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        ManagementGuard::audit('document header', 'created', "{$code} v{$new->version_no}", $new,
            $this->snapshot($current), $this->snapshot($new));
        return response()->json($new);
    }

    // Make an older (non-archived) version the active one again.
    public function activate(Request $request, string $code, DocumentSetting $version)
    {
        $this->authorizeManage($request, $code);
        abort_unless($version->document_code === $code, 404);
        abort_if($version->status === 'archived', 422, 'An archived header cannot be activated. Restore it by saving a new version instead.');
        abort_if($version->is_active, 422, 'This version is already the active header.');

        $previous = $this->active($code);
        DB::transaction(function () use ($code, $version) {
            $now = now();
            DocumentSetting::where('document_code', $code)->where('is_active', true)->update(['is_active' => false, 'deactivated_at' => $now]);
            // A re-activated version starts a fresh active period so documents made in between keep their own header.
            $version->forceFill(['is_active' => true, 'activated_at' => $now, 'deactivated_at' => null])->save();
        });

        ManagementGuard::audit('document header', 'activated', "{$code} v{$version->version_no}", $version,
            ['active version' => 'v' . $previous->version_no], ['active version' => 'v' . $version->version_no]);
        return response()->json($version->fresh());
    }

    public function archive(Request $request, string $code, DocumentSetting $version)
    {
        $this->authorizeManage($request, $code);
        abort_unless($version->document_code === $code, 404);
        abort_if($version->is_active, 422, 'This is the active header. Set another version as active first.');
        abort_if($version->status === 'archived', 422, 'This version is already archived.');
        [$reason, $note] = ManagementGuard::requireStatusReason($request, 'document header', 'archive');

        $version->forceFill(['status' => 'archived'])->save();
        ManagementGuard::audit('document header', 'archived', "{$code} v{$version->version_no}", $version,
            ['status' => 'active'], ['status' => 'archived'], $reason, $note);
        return response()->json($version->fresh());
    }

    public function destroy(Request $request, string $code, DocumentSetting $version)
    {
        $this->authorizeManage($request, $code);
        abort_unless($version->document_code === $code, 404);
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'document header');
        abort_if($version->is_active, 422, 'This is the active header. Set another version as active first.');

        $used = $this->usedCount($version);
        if ($used > 0) {
            abort(422, "This header was used in {$used} generated " . ($used === 1 ? 'document' : 'documents') . ', so it cannot be deleted. Archive it instead.');
        }

        $name = "{$code} v{$version->version_no}";
        $old = $this->snapshot($version);
        $version->delete();
        ManagementGuard::audit('document header', 'deleted', $name, $version, $old, [], $reason, $note);
        return response()->json(['message' => 'Header version deleted.']);
    }

    private function snapshot(DocumentSetting $v): array
    {
        return [
            'version'          => 'v' . $v->version_no,
            'revision_no'      => $v->revision_no,
            'effectivity_date' => optional($v->effectivity_date)->format('Y-m-d'),
            'ctrl_no'          => $v->ctrl_no,
        ];
    }

    /** Documents created while this version was the one in force. */
    private function usedCount(DocumentSetting $v): int
    {
        $from = $v->activated_at ?: Carbon::create(2000, 1, 1);
        $to   = $v->is_active ? now() : ($v->deactivated_at ?: now());
        try {
            return (int) match ($v->document_code) {
                'QF-OSS-01'     => Referral::whereBetween('created_at', [$from, $to])->count(),
                'QF-OSS-03'     => DB::table('feedback_slips')->whereBetween('created_at', [$from, $to])->count(),
                'QF-OSS-GCU-01' => CaseFile::whereBetween('created_at', [$from, $to])->count(),
                'QF-OSS-GCU-09' => Referral::whereNotNull('admission_excused')->whereBetween('created_at', [$from, $to])->count(),
                'QF-TMDU-02'    => DB::table('testing_records')->whereBetween('created_at', [$from, $to])->count(),
                default         => 0,
            };
        } catch (\Throwable $e) {
            return 0;
        }
    }
}