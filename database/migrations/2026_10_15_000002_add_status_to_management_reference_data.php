<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['colleges', 'programs', 'departments', 'referral_form_options'];

    public function up(): void
    {
        // active | inactive | archived. Inactive and archived items are hidden from the
        // dropdowns for new records but stay in the table, so existing records keep their names.
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('status', 20)->default('active')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('status');
            });
        }
    }
};