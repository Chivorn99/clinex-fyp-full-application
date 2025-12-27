<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uploaded_by',
        'total_reports',
        'processed_reports',
        'verified_reports',
        'failed_reports',
        'status',
        'processing_started_at',
        'processing_completed_at',
    ];

    protected $casts = [
        'total_reports' => 'integer',
        'processed_reports' => 'integer',
        'verified_reports' => 'integer',
        'failed_reports' => 'integer',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function labReports()
    {
        return $this->hasMany(LabReport::class, 'batch_id');
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_reports === 0) {
            return 0;
        }
        return ($this->processed_reports / $this->total_reports) * 100;
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}