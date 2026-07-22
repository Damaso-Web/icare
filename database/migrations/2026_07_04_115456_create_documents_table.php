<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->morphs('documentable');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk')->default('private');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->enum('document_type', [
                'referral_slip',
                'client_information_form',
                'psychological_assessment_report',
                'session_notes',
                'admission_slip',
                'incident_report',
                'supporting_document',
                'other',
            ])->default('supporting_document');
            $table->text('description')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};