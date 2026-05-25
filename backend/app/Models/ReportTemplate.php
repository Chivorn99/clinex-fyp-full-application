<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hospital_code',
        'schema',
        'few_shot_examples',
        'llm_model',
        'is_active',
    ];

    protected $casts = [
        'schema' => 'array',
        'few_shot_examples' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the currently active template (default hospital).
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public function verifiedExamples()
    {
        return $this->hasMany(VerifiedExample::class, 'template_id');
    }

    /**
     * Build the template payload for the Python OCR script.
     * This JSON is passed via --template argument to document_ocr.py.
     */
    public function toPythonPayload(): array
    {
        $verified = $this->verifiedExamples()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($example) => [
                'input' => $example->original_text,
                'output' => $example->corrected_json,
            ])
            ->toArray();

        // Combine static examples and verified examples, limiting to 5 total
        $examples = array_merge($this->few_shot_examples ?? [], $verified);
        $examples = array_slice($examples, 0, 5);

        return [
            'name' => $this->name,
            'hospital_code' => $this->hospital_code,
            'llm_model' => $this->llm_model,
            'schema' => $this->schema,
            'few_shot_examples' => $examples,
        ];
    }
}
