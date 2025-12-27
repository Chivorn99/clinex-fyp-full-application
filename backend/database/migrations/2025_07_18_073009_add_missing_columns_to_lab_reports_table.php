<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lab_reports', function (Blueprint $table) {
            // Add missing columns that controller expects
            $table->timestamp('uploaded_at')->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('processing_completed_at');
            $table->integer('processing_time')->nullable()->after('processed_at'); // in seconds
            $table->json('extracted_data')->nullable()->after('processing_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_reports', function (Blueprint $table) {
            $table->dropColumn([
                'uploaded_at', 
                'processed_at', 
                'processing_time', 
                'extracted_data'
            ]);
        });
    }
};
