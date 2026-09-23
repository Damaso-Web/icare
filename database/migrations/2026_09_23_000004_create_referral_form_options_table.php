<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_form_options', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // referral_type, referral_source, act_of_misconduct
            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_form_options');
    }
};