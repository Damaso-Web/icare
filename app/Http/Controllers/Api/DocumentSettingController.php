<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentSetting;
use Illuminate\Http\Request;

class DocumentSettingController extends Controller
{
    /** TMDU's own forms (QF-TMDU-...). Every other code is a GCU / shared OSS form. */
    private function isTmduCode(string $code): bool
    {
        return str_starts_with($code, 'QF-TMDU');
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

        $setting = DocumentSetting::firstOrCreate(
            ['document_code' => $code],
            ['revision_no' => '01']
        );

        return response()->json($setting);
    }

    // The GCU Head (admin) edits GCU's headers; the System Admin edits TMDU's.
    public function update(Request $request, string $code)
    {
        $role = $request->user()?->role;
        $allowed = $this->isTmduCode($code) ? $role === 'system_admin' : $role === 'admin';
        abort_unless($allowed, 403, $this->isTmduCode($code)
            ? 'Only the System Admin can edit the TMDU document header.'
            : 'Only the GCU Head can edit this document header.');

        // The TMDU Appointment Slip (QF-TMDU-02) has a blank Ctrl No. on the
        // printed form, so its Ctrl No. fields are optional.
        $ctrlOptional = $code === 'QF-TMDU-02';

        $validated = $request->validate([
            'revision_no'       => ['required', 'string', 'max:4', 'regex:/^[0-9]{1,4}$/'],
            'effectivity_date'  => 'required|date',
            'ctrl_no_year'      => [$ctrlOptional ? 'nullable' : 'required', 'string', 'max:4', 'regex:/^[0-9]{1,4}$/'],
            'ctrl_no_term'      => [$ctrlOptional ? 'nullable' : 'required', 'in:1,2,S'],
        ]);

        $setting = DocumentSetting::firstOrCreate(['document_code' => $code]);
        $setting->update($validated);

        return response()->json($setting);
    }
}