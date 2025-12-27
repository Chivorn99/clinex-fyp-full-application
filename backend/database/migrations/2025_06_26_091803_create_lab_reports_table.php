<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lab_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('report_batches')->onDelete('set null');
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('set null');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');

            // File info
            $table->string('original_filename');
            $table->string('stored_filename')->nullable();
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('file_hash', 64)->unique()->nullable();

            // Basic fields
            $table->date('report_date')->nullable();
            $table->text('notes')->nullable();
            
            // Status and processing
            $table->enum('status', ['uploaded', 'processing', 'processed', 'verified', 'failed'])->default('uploaded');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('processing_error')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_reports');
    }
};
