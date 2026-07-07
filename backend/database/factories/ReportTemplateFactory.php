<?php

namespace Database\Factories;

use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportTemplateFactory extends Factory
{
    protected $model = ReportTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Hospital Template',
            'hospital_code' => strtoupper($this->faker->unique()->lexify('???')),
            'is_active' => false,
            'llm_model' => 'phi3:mini',
            'schema' => [
                'panels' => ['hematology', 'biochemistry', 'serology', 'urine'],
                'fields' => ['testName', 'result', 'unit', 'referenceRange', 'flag'],
            ],
            'few_shot_examples' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
