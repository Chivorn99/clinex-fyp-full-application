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

    /**
     * Build the template payload for the Python OCR script.
     * This JSON is passed via --template argument to document_ocr.py.
     */
    public function toPythonPayload(): array
    {
        return [
            'name' => $this->name,
            'hospital_code' => $this->hospital_code,
            'llm_model' => $this->llm_model,
            'schema' => $this->schema,
            'few_shot_examples' => $this->few_shot_examples,
        ];
    }
}
