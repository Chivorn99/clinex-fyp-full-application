<?php

/*
|--------------------------------------------------------------------------
| Clinex — Unit Tests: ReportTemplate Model
|--------------------------------------------------------------------------
*/

use App\Models\ReportTemplate;
use App\Models\VerifiedExample;

it('has active scope', function () {
    ReportTemplate::factory()->create(['is_active' => true]);
    ReportTemplate::factory()->create(['is_active' => false]);

    $active = ReportTemplate::getActive();
    expect($active)->not->toBeNull();
    expect($active->is_active)->toBeTrue();
});

it('returns null when no active template exists', function () {
    ReportTemplate::factory()->create(['is_active' => false]);
    expect(ReportTemplate::getActive())->toBeNull();
});

it('casts schema as array', function () {
    $template = ReportTemplate::factory()->create([
        'schema' => ['panels' => ['hematology', 'biochemistry']],
    ]);

    $template->refresh();
    expect($template->schema)->toBeArray();
    expect($template->schema['panels'])->toContain('hematology');
});

it('casts few_shot_examples as array', function () {
    $template = ReportTemplate::factory()->create([
        'few_shot_examples' => [['input' => 'test', 'output' => 'result']],
    ]);

    $template->refresh();
    expect($template->few_shot_examples)->toBeArray();
});

it('generates python payload with template data', function () {
    $template = ReportTemplate::factory()->create([
        'name' => 'KVH Template',
        'hospital_code' => 'KVH',
        'llm_model' => 'phi3:mini',
        'schema' => ['panels' => ['hematology']],
        'few_shot_examples' => [['input' => 'raw text', 'output' => '{"testResults": []}']],
    ]);

    $payload = $template->toPythonPayload();

    expect($payload)->toHaveKeys(['name', 'hospital_code', 'llm_model', 'schema', 'few_shot_examples']);
    expect($payload['name'])->toBe('KVH Template');
    expect($payload['hospital_code'])->toBe('KVH');
    expect($payload['llm_model'])->toBe('phi3:mini');
});

it('limits few shot examples to 5 total', function () {
    $template = ReportTemplate::factory()->create([
        'few_shot_examples' => array_fill(0, 3, ['input' => 'text', 'output' => 'json']),
    ]);

    // Create 4 verified examples
    for ($i = 0; $i < 4; $i++) {
        VerifiedExample::create([
            'template_id' => $template->id,
            'original_text' => "Verified example {$i}",
            'corrected_json' => ['testResults' => []],
        ]);
    }

    $payload = $template->toPythonPayload();
    expect(count($payload['few_shot_examples']))->toBeLessThanOrEqual(5);
});

it('casts is_active as boolean', function () {
    $template = ReportTemplate::factory()->create(['is_active' => 1]);
    $template->refresh();
    expect($template->is_active)->toBeBool();
    expect($template->is_active)->toBeTrue();
});
