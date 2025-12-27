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
        Schema::create('extracted_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_report_id')->constrained('lab_reports')->onDelete('cascade');
            $table->string('category');
            $table->string('test_name');
            $table->text('result')->nullable();
            $table->text('unit')->nullable();
            $table->text('reference')->nullable();
            $table->enum('flag',['H','L'])->nullable();
            $table->json('coordinates')->nullable(); 
            $table->float('confidence_score')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extracted_data');
    }
};
