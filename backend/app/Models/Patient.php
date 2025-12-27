<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'name',
        'age',
        'gender',
        'phone',
        'email',
    ];

    public function labReports()
    {
        return $this->hasMany(LabReport::class);
    }
}
