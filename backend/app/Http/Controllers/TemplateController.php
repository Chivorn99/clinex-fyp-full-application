<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\TemplateAnalyzerService;
use App\Services\DocumentAiService;
use App\Services\TemplateZonesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\CreateTemplateFromPdf;
use Illuminate\Support\Facades\Log;
use App\Events\PdfProcessingProgress;

class TemplateController extends Controller
{
    protected $templateAnalyzer;
    protected $templateZonesService;
    protected $documentAiService;

    public function __construct(TemplateAnalyzerService $templateAnalyzer, TemplateZonesService $templateZonesService, DocumentAiService $documentAiService)
    {
        $this->templateAnalyzer = $templateAnalyzer;
        $this->templateZonesService = $templateZonesService;
        $this->documentAiService = $documentAiService;
    }

    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = Template::orderBy('created_at', 'desc')->get();
        return view('templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new template.
     */
    public function create()
    {
        return view('templates.create', [
            'aiData' => null,
            'clinexFields' => [
                'patient_info' => ['patient_id', 'name', 'age', 'gender', 'lab_id', 'phone'],
                'table_columns' => ['test_name', 'value', 'unit', 'reference_range', 'flag'],
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'processor_id' => 'required|string',
            'mappings' => 'required|array'
        ]);

        $template = Template::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template saved successfully!',
            'redirect_url' => route('dashboard')
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Template $template)
    {
        return view('templates.show', compact('template'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Template $template)
    {
        return view('templates.edit', compact('template'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Template $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'processor_id' => 'required|string|max:255',
            'mappings' => 'required|array'
        ]);

        try {
            $template->update([
                'name' => $request->name,
                'description' => $request->description,
                'processor_id' => $request->processor_id,
                'mappings' => $request->mappings
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'template' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Template $template)
    {
        try {
            $template->delete();
            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract data from specific zones in a PDF
     */
    public function extractFromZones(Request $request)
    {
        try {
            // Validate request
            if (!$request->hasFile('pdf') || !$request->has('zones')) {
                return response()->json(['success' => false, 'error' => 'PDF file and zones are required'], 400);
            }

            // Get the uploaded file and save it temporarily
            $pdf = $request->file('pdf');
            $fileName = uniqid() . '.pdf';
            
            // Save to storage and get normalized path
            $path = $pdf->storeAs('private/temp_pdfs', $fileName);
            $fullPath = Storage::path($path);
            
            // Fix path separators
            $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
            
            // Check if file exists
            if (!file_exists($fullPath)) {
                throw new \Exception("Saved PDF file not found at {$fullPath}");
            }
            
            Log::info("PDF saved for zone extraction at: {$fullPath}");
            
            // Decode zones
            $zones = json_decode($request->zones, true);
            
            try {
                // Get DocumentAI service
                $aiService = app(DocumentAiService::class);
                
                // Extract text from zones
                $extractedData = $aiService->extractTextFromZones($fullPath, $zones);
                
                // Fix UTF-8 encoding issues
                $extractedData = $this->fixUtf8Encoding($extractedData);
                
                // Check for empty table extraction results
                if (empty($extractedData['tables']) && isset($extractedData['entities']) && count($extractedData['entities']) > 0) {
                    Log::info("Table extraction returned no results, using text-based table parsing");
                    
                    // Check if any entity looks like a table and convert it
                    foreach ($extractedData['entities'] as $key => $value) {
                        if (strlen($value) > 100 && (stripos($key, 'table') !== false || stripos($key, 'result') !== false)) {
                            // This might be tabular data in text form
                            $tableRows = explode("\n", $value);
                            $parsedTable = [];
                            foreach ($tableRows as $row) {
                                $parsedTable[] = preg_split('/\s{2,}/', $row);
                            }
                            
                            if (count($parsedTable) > 1) {
                                $extractedData['tables'][] = [
                                    'name' => $key,
                                    'rows' => $parsedTable
                                ];
                                // Remove from entities since we moved it to tables
                                unset($extractedData['entities'][$key]);
                            }
                        }
                    }
                }
                
                // Clean up temporary file
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                
                // Return the results
                return response()->json([
                    'success' => true,
                    'data' => $extractedData
                ]);
            } catch (\Exception $e) {
                // Clean up if file exists
                if (isset($fullPath) && file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                
                Log::error('Zone extraction processing failed: ' . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error processing zones request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error processing zones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fix UTF-8 encoding issues in extracted data
     */
    private function fixUtf8Encoding($data)
    {
        if (is_string($data)) {
            // Convert to UTF-8 if needed
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->fixUtf8Encoding($value);
            }
        }
        return $data;
    }

    /**
     * Get templates for template selection during upload
     */
    public function getTemplatesForUpload()
    {
        $templates = Template::select('id', 'name', 'description', 'processor_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Process a PDF using a template's zones
     */
    public function processWithTemplate(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240',
            'template_id' => 'required|exists:templates,id',
        ]);
        
        $template = Template::findOrFail($request->template_id);
        $pdfFile = $request->file('pdf');
        $filePath = $pdfFile->getRealPath();
        
        $extractedData = $this->templateZonesService->processWithTemplate($filePath, $template);
        
        if (!$extractedData) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data using template'
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => $extractedData
        ]);
    }
    
    /**
     * Analyze a PDF file and return extracted data as JSON.
     */
    public function analyzePdf(Request $request, DocumentAiService $documentAiService)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
            'processor_id' => 'required|string',
        ]);

        try {
            $file = $request->file('pdf_file');
            $processorId = $request->input('processor_id');
            $document = $documentAiService->processDocument($file->getPathname(), $processorId);

            if (!$document) {
                throw new \Exception('The document could not be processed by Google AI.');
            }

            // Parse the complex response into a simple array
            $parsedData = $this->templateAnalyzer->parse($document);
            $pdfBase64 = base64_encode(file_get_contents($file->getPathname()));

            return response()->json([
                'success' => true,
                'data' => $parsedData,
                'pdf_preview_src' => 'data:application/pdf;base64,' . $pdfBase64,
            ]);
        } catch (\Exception $e) {
            Log::error('Template analysis failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Analysis error: ' . $e->getMessage()], 500);
        }
    }
}
