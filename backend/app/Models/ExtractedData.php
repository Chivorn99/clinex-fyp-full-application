<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedData extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_report_id',
        'category',     
        'test_name',      
        'result',         
        'unit',          
        'reference',       
        'flag',          
        'coordinates',
        'confidence_score',
        'is_verified',
    ];

    public function labReport(): BelongsTo
    {
        return $this->belongsTo(LabReport::class);
    }
}
