2026 09 22 100000 add on observation to cases status · PHP
<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
 
return new class extends Migration
{
    /**
     * Case Files now tracks three working states - Open, On Observation and
     * Resolved. "On Observation" is the middle ground for a case that has been
     * acted on but still needs watching before it can be resolved.
     *
     * The legacy values stay in the enum on purpose: in_progress, awaiting_testing
     * and closed are still written programmatically elsewhere (for example
     * CaseController@referToTesting sets awaiting_testing), and existing rows
     * must keep validating. Only the staff-facing pickers are narrowed.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `cases`
            MODIFY COLUMN `status` ENUM(
                'open',
                'on_observation',
                'in_progress',
                'awaiting_testing',
                'awaiting_external',
                'on_hold',
                'resolved',
                'closed'
            ) NOT NULL DEFAULT 'open'
        ");
    }
 
    public function down(): void
    {
        // Park anything still on the new value back on 'open' so the narrowed
        // enum below cannot silently blank those rows.
        DB::table('cases')->where('status', 'on_observation')->update(['status' => 'open']);
 
        DB::statement("
            ALTER TABLE `cases`
            MODIFY COLUMN `status` ENUM(
                'open',
                'in_progress',
                'awaiting_testing',
                'awaiting_external',
                'on_hold',
                'resolved',
                'closed'
            ) NOT NULL DEFAULT 'open'
        ");
    }
};
 