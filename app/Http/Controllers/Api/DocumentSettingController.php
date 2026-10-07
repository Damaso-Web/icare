<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentSetting;
use Illuminate\Http\Request;

class DocumentSettingController extends Controller
{
    // Any authenticated staff can read - these values are displayed on the
    // referral form header wherever it appears (Create + Show).
    public function show(string $code)
    {
        $setting = DocumentSetting::firstOrCreate(
            ['document_code' => $code],
            ['revision_no' => '01']
        );

        return response()->json($setting);
    }

    // Admin only (enforced via role:admin middleware on the route).
    public function update(Request $request, string $code)
    {
        // The TMDU Appointment Slip (QF-TMDU-02) has a blank Ctrl No. on the
        // printed form, so its Ctrl No. fields are optional.
        $ctrlOptional = $code === 'QF-TMDU-02';

        $validated = $request->validate([
            'revision_no'       => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'effectivity_date'  => 'required|date',
            'ctrl_no_year'      => [$ctrlOptional ? 'nullable' : 'required', 'string', 'max:4', 'regex:/^[0-9]{1,4}$/'],
            'ctrl_no_term'      => [$ctrlOptional ? 'nullable' : 'required', 'in:1,2,S'],
        ]);

        $setting = DocumentSetting::firstOrCreate(['document_code' => $code]);
        $setting->update($validated);

        return response()->json($setting);
    }
}