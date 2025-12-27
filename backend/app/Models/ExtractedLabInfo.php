<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedLabInfo extends Model
{
    use HasFactory;

    protected $table = 'extracted_lab_info';

    protected $fillable = [
        'lab_report_id',
        'lab_id',
        'requested_by',
        'requested_date',
        'collected_date',
        'analysis_date',
        'validated_by',
    ];

    public function labReport(): BelongsTo
    {
        return $this->belongsTo(LabReport::class);
    }
}
