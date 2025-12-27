<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'processor_id',
        'structure_data',
        'field_mappings',
        'mappings', 
        'status',
        'created_by',
    ];

   
    protected $casts = [
        'structure_data' => 'array',
        'field_mappings' => 'array',
        'mappings' => 'array',
    ];


    public function labReports(): HasMany
    {
        return $this->hasMany(LabReport::class, 'template_id');
    }

    public function scopeByProcessor($query, $processorId)
    {
        return $query->where('processor_id', $processorId);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reportBatches(): HasMany
    {
        return $this->hasMany(ReportBatch::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
