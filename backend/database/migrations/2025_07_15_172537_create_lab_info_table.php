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
        Schema::create('extracted_lab_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_report_id')->unique()->constrained('lab_reports')->onDelete('cascade');
            $table->string('lab_id')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('requested_date')->nullable();
            $table->string('collected_date')->nullable();
            $table->string('analysis_date')->nullable();
            $table->string('validated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extracted_lab_info');
    }
};
