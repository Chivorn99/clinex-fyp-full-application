<?php

namespace App\Http\Controllers;

use App\Models\VerifiedExample;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VerifiedExamplesController extends Controller
{
    /**
     * Get a paginated list of verified examples.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VerifiedExample::with('template')->latest();

        if ($request->has('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        $examples = $query->paginate(15);

        return response()->json($examples);
    }

    /**
     * Store a new manually created example.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:report_templates,id',
            'raw_text' => 'required|string',
            'corrected_json' => 'required|array'
        ]);

        $example = VerifiedExample::create([
            'template_id' => $validated['template_id'],
            'raw_text' => $validated['raw_text'],
            'corrected_json' => $validated['corrected_json'],
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'Training example created successfully.',
            'example' => $example->load('template')
        ], 201);
    }

    /**
     * Update an existing example.
     */
    public function update(Request $request, VerifiedExample $verifiedExample): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'sometimes|exists:report_templates,id',
            'raw_text' => 'sometimes|string',
            'corrected_json' => 'sometimes|array',
            'is_active' => 'sometimes|boolean'
        ]);

        $verifiedExample->update($validated);

        return response()->json([
            'message' => 'Training example updated successfully.',
            'example' => $verifiedExample->load('template')
        ]);
    }

    /**
     * Delete an example.
     */
    public function destroy(VerifiedExample $verifiedExample): JsonResponse
    {
        $verifiedExample->delete();

        return response()->json([
            'message' => 'Training example deleted successfully.'
        ]);
    }
}
