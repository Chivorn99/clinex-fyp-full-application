<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifiedExample extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'original_text',
        'corrected_json',
    ];

    protected $casts = [
        'corrected_json' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(ReportTemplate::class);
    }
}
