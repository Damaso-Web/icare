<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCasesConstraint extends Command
{
    protected $signature = 'cases:fix-constraint';
    protected $description = 'Ensures cases.student_id does not have an incorrect unique constraint';

    public function handle()
    {
        $indexes = DB::select("SHOW INDEX FROM cases WHERE Key_name = 'cases_student_id_unique'");

        if (count($indexes) > 0) {
            DB::statement('ALTER TABLE cases DROP FOREIGN KEY cases_student_id_foreign');
            DB::statement('ALTER TABLE cases DROP INDEX cases_student_id_unique');
            DB::statement('ALTER TABLE cases ADD INDEX cases_student_id_index (student_id)');
            DB::statement('ALTER TABLE cases ADD CONSTRAINT cases_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT');

            $this->info('Fixed: removed incorrect unique constraint on cases.student_id.');
        } else {
            $this->info('OK: cases.student_id constraint is correct.');
        }
    }
}