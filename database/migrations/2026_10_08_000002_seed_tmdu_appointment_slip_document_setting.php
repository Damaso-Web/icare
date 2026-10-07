<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// TMDU Appointment Slip (QF-TMDU-02): Revision No. 00, effectivity
// September 2, 2022, Ctrl No. blank on the printed form.
return new class extends Migration
{
    public function up(): void
    {
        $values = [
            'revision_no'      => '00',
            'effectivity_date' => '2022-09-02',
            'ctrl_no_year'     => null,
            'ctrl_no_term'     => null,
            'updated_at'       => now(),
        ];

        if (DB::table('document_settings')->where('document_code', 'QF-TMDU-02')->exists()) {
            DB::table('document_settings')->where('document_code', 'QF-TMDU-02')->update($values);
        } else {
            DB::table('document_settings')->insert(['document_code' => 'QF-TMDU-02', 'created_at' => now(), ...$values]);
        }
    }

    public function down(): void
    {
        DB::table('document_settings')->where('document_code', 'QF-TMDU-02')->delete();
    }
};