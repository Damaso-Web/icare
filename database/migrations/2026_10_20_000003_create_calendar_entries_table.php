<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_entries')) return;
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->index();
            $table->string('name', 120);
            $table->string('type', 30);                       // holiday | class_suspension | office_closed
            $table->string('status', 20)->default('active');  // active | archived
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_entries');
    }
};