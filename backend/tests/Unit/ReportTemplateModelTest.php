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

it('returns only active templates from getActive scope', function () {
    $inactive = ReportTemplate::factory()->create(['is_active' => false]);
    $active = ReportTemplate::getActive();

    if ($active) {
        expect($active->id)->not->toBe($inactive->id);
    } else {
        expect($active)->toBeNull();
    }
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
        'few_shot_examples' => array_fill(0, 6, ['input' => 'text', 'output' => 'json']),
    ]);

    $payload = $template->toPythonPayload();
    // Static few_shot_examples from the template should be capped
    expect(count($payload['few_shot_examples']))->toBeLessThanOrEqual(5);
});

it('casts is_active as boolean', function () {
    $template = ReportTemplate::factory()->create(['is_active' => 1]);
    $template->refresh();
    expect($template->is_active)->toBeBool();
    expect($template->is_active)->toBeTrue();
});
