<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Report templates define the expected data schema for different hospital
     * lab report formats. The Python OCR Intelligence Coordinator uses these
     * templates to build few-shot prompts for the local LLM (Ollama).
     */
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // e.g. "Phnom Penh General Hospital"
            $table->string('hospital_code')->unique();      // e.g. "PPGH"
            $table->json('schema');                          // Expected fields & test categories
            $table->json('few_shot_examples');               // 2-3 complete input→output pairs for few-shot prompting
            $table->string('llm_model')->default('phi3:mini'); // Ollama model to use
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
