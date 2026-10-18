<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Class Admission Slip (QF-OSS-GCU-09): Revision No. 01, effectivity 07/04/23,
// Ctrl No. 26-1. Only fills the values in if the GCU Head has not set them yet.
return new class extends Migration
{
    public function up(): void
    {
        $values = [
            'revision_no'      => '01',
            'effectivity_date' => '2023-07-04',
            'ctrl_no_year'     => '26',
            'ctrl_no_term'     => '1',
            'updated_at'       => now(),
        ];

        $row = DB::table('document_settings')->where('document_code', 'QF-OSS-GCU-09')->first();
        if (!$row) {
            DB::table('document_settings')->insert(['document_code' => 'QF-OSS-GCU-09', 'created_at' => now(), ...$values]);
        } elseif (!$row->effectivity_date || !$row->ctrl_no_year || !$row->ctrl_no_term) {
            DB::table('document_settings')->where('document_code', 'QF-OSS-GCU-09')->update($values);
        }
    }

    public function down(): void
    {
        // Leave the setting in place: it may have been edited since.
    }
};