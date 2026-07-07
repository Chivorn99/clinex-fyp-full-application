<?php

namespace Database\Factories;

use App\Models\LabReport;
use App\Models\ReportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabReportFactory extends Factory
{
    protected $model = LabReport::class;

    public function definition(): array
    {
        return [
            'batch_id' => ReportBatch::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => 'lab_report_' . $this->faker->unique()->numberBetween(1, 9999) . '.pdf',
            'stored_filename' => $this->faker->uuid() . '.pdf',
            'storage_path' => 'reports/' . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', $this->faker->unique()->text()),
            'status' => 'uploaded',
            'document_type' => 'lab_report',
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => 'processed',
            'processed_at' => now(),
            'processing_time' => $this->faker->numberBetween(5, 120),
            'extracted_data' => [
                'testResults' => [
                    [
                        'testName' => 'Glucose',
                        'result' => '5.2',
                        'unit' => 'mmol/L',
                        'referenceRange' => '3.9-6.1',
                        'category' => 'BIOCHEMISTRY',
                        'flag' => null,
                    ],
                    [
                        'testName' => 'WBC',
                        'result' => '12.5',
                        'unit' => '10^9/L',
                        'referenceRange' => '4.0-11.0',
                        'category' => 'HEMATOLOGY',
                        'flag' => 'H',
                    ],
                ],
                'patientInfo' => [
                    'name' => 'Test Patient',
                    'patientId' => 'PT00101',
                    'age' => '35 Y',
                    'gender' => 'M',
                    'phone' => '',
                ],
                'labInfo' => [
                    'labId' => '',
                    'requestedBy' => 'Dr. Test',
                    'requestedDate' => '2026-01-15',
                    'collectedDate' => '2026-01-15',
                    'analysisDate' => '2026-01-15',
                    'validatedBy' => 'Tech A',
                ],
            ],
        ]);
    }

    public function verified(): static
    {
        return $this->processed()->state(fn () => [
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => User::factory(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'processed_at' => now(),
            'processing_error' => 'OCR script timeout',
        ]);
    }

    public function consultation(): static
    {
        return $this->state(fn () => [
            'document_type' => 'consultation',
        ]);
    }
}
