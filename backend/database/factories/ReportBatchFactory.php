<?php

namespace Database\Factories;

use App\Models\ReportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportBatchFactory extends Factory
{
    protected $model = ReportBatch::class;

    public function definition(): array
    {
        return [
            'name' => 'Batch ' . $this->faker->dateTime()->format('Y-m-d H:i'),
            'uploaded_by' => User::factory(),
            'total_reports' => $this->faker->numberBetween(1, 10),
            'processed_reports' => 0,
            'verified_reports' => 0,
            'failed_reports' => 0,
            'status' => 'pending',
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'processed_reports' => $this->faker->numberBetween(1, 10),
            'processing_started_at' => now()->subMinutes(5),
            'processing_completed_at' => now(),
        ]);
    }
}
