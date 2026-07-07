<?php

namespace Database\Factories;

use App\Models\ExtractedData;
use App\Models\LabReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExtractedDataFactory extends Factory
{
    protected $model = ExtractedData::class;

    public function definition(): array
    {
        return [
            'lab_report_id' => LabReport::factory(),
            'category' => $this->faker->randomElement(['HEMATOLOGY', 'BIOCHEMISTRY', 'SEROLOGY', 'URINE']),
            'test_name' => $this->faker->randomElement(['WBC', 'RBC', 'Glucose', 'Creatinine', 'Hemoglobin']),
            'result' => $this->faker->randomFloat(1, 0.1, 100),
            'unit' => $this->faker->randomElement(['mg/dL', 'mmol/L', '10^9/L', 'g/dL']),
            'reference' => '3.5-10.0',
            'flag' => $this->faker->randomElement([null, 'H', 'L']),
            'confidence_score' => $this->faker->randomFloat(2, 0.7, 1.0),
            'is_verified' => false,
        ];
    }
}
