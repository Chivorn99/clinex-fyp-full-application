<?php

namespace App\Http\Controllers;

use App\Models\LabReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Jobs\ProcessLabReport;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentAiService;
use App\Models\Patient;
use App\Models\ExtractedData;
use App\Models\ExtractedLabInfo;
use DB;


class LabReportController extends Controller
{

    protected $documentAiService;


    /**
     * Inject the DocumentAiService into the controller.
     */
    public function __construct(DocumentAiService $documentAiService)
    {
        $this->documentAiService = $documentAiService;
    }

    /**
     * Process an uploaded lab report PDF.
     * This is the single endpoint for handling the PDF upload and extraction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function process(Request $request)
    {
        try {
            $request->validate([
                'pdf_file' => 'required|file|mimes:pdf|max:10240',
            ]);

            $pdfFile = $request->file('pdf_file');

            Log::info("Processing uploaded PDF: " . $pdfFile->getClientOriginalName());

            $structuredData = $this->documentAiService->processLabReport($pdfFile->getRealPath());

            if (!$structuredData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to extract data from the document. The format might not be recognized or the file could be empty.'
                ], 422); // Unprocessable Entity
            }

            return response()->json([
                'success' => true,
                'data' => $structuredData
            ]);

        } catch (ValidationException $e) {
            // Handle cases where the upload is not a PDF or is too large.
            Log::error('Validation failed for PDF upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Catch any other unexpected errors during processing.
            Log::error('A critical error occurred in LabReportController: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred on the server. Please check the logs.'
            ], 500);
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Start building the query to fetch Lab Reports, including related data
        // for batches, patients, and the uploader to avoid N+1 problems.
        $query = LabReport::with(['batch', 'patient', 'uploader']);

        // If a 'batch_id' is provided in the request, filter reports by that batch.
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // If a 'status' is provided, filter reports by their status.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // If a 'verified' filter is provided, check for reports that are
        // either verified (verified_at is not null) or not verified (verified_at is null).
        if ($request->filled('verified')) {
            if ($request->boolean('verified')) {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        // Fetch ALL lab reports that match the query criteria, ordered by the latest.
        // The paginate() method has been replaced with get() to disable pagination.
        $labReports = $query->latest()->get();

        // If the request expects a JSON response (e.g., it's an API call),
        // return the data in JSON format.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $labReports, // This now contains all reports, not a paginator object.
                'message' => 'Lab reports retrieved successfully'
            ]);
        }

        // Otherwise, return the standard view with the lab reports data.
        return view('lab-reports.index', compact('labReports'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|mimes:pdf|max:10240',
            'template_id' => 'required|exists:templates,id'
        ]);

        try {
            $uploadedFiles = [];
            $template = \App\Models\Template::findOrFail($request->template_id);

            foreach ($request->file('files') as $file) {
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('lab-reports', $filename, 'private');

                $labReport = LabReport::create([
                    'original_filename' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'template_id' => $template->id,
                    'status' => 'pending'
                ]);

                ProcessLabReport::dispatch($labReport);

                $uploadedFiles[] = [
                    'id' => $labReport->id,
                    'filename' => $labReport->original_filename,
                    'status' => $labReport->status,
                    'template' => $template->name
                ];
            }

            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . ' PDF files uploaded successfully with template: ' . $template->name,
                'data' => $uploadedFiles
            ], 201);

        } catch (\Exception $e) {
            Log::error('Batch upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LabReport $labReport)
    {
        $labReport->load(['batch', 'patient', 'uploader', 'verifier']);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'lab_report' => $labReport,
                    'extracted_data' => $labReport->extracted_data,
                    'needs_verification' => $labReport->status === 'processed' && !$labReport->verified_at,
                    'can_edit' => !$labReport->verified_at || auth()->user()->role === 'admin'
                ],
                'message' => 'Lab report retrieved successfully'
            ]);
        }

        return view('lab-reports.show', compact('labReport'));
    }

    // Removed duplicate verify method

    /**
     * Get test results for a specific lab report
     */
    public function testResults(LabReport $labReport)
    {
        $labReport->load(['extractedData', 'extractedLabInfo', 'patient']);

        // Group test results by category
        $groupedResults = $labReport->extractedData->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => [
                'lab_report' => $labReport,
                'lab_info' => $labReport->extractedLabInfo,
                'test_results_by_category' => $groupedResults,
                'test_results_flat' => $labReport->extractedData
            ],
            'message' => 'Test results retrieved successfully'
        ]);
    }

    /**
     * Get all lab reports with comprehensive data
     */
    public function getAllWithDetails(Request $request)
    {
        $query = LabReport::with([
            'batch',
            'patient',
            'uploader',
            'verifier',
            'extractedData',
            'extractedLabInfo'
        ]);

        // Apply existing filters from your index method
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('verified')) {
            if ($request->boolean('verified')) {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        $labReports = $query->latest()->paginate($request->get('per_page', 15));

        // Transform data for frontend consumption
        $labReports->getCollection()->transform(function ($report) {
            return [
                'id' => $report->id,
                'original_filename' => $report->original_filename,
                'status' => $report->status,
                'verified_at' => $report->verified_at,
                'processed_at' => $report->processed_at,
                'notes' => $report->notes,
                'patient' => $report->patient ? [
                    'id' => $report->patient->id,
                    'name' => $report->patient->name,
                    'patient_id' => $report->patient->patient_id,
                    'age' => $report->patient->age,
                    'gender' => $report->patient->gender,
                    'phone' => $report->patient->phone,
                ] : null,
                'batch' => [
                    'id' => $report->batch->id,
                    'name' => $report->batch->name,
                ],
                'lab_info' => $report->extractedLabInfo,
                'test_count' => $report->extractedData->count(),
                'categories' => $report->extractedData->pluck('category')->unique()->values(),
                'uploader' => $report->uploader->name ?? null,
                'verifier' => $report->verifier->name ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $labReports,
            'message' => 'Lab reports with details retrieved successfully'
        ]);
    }

    /**
     * Verify extracted data and store to database
     */
    public function verify(Request $request, LabReport $labReport)
    {
        if ($labReport->status !== 'processed') {
            return response()->json([
                'success' => false,
                'message' => 'Lab report must be processed before verification'
            ], 422);
        }

        if ($labReport->verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'This report has already been verified'
            ], 422);
        }

        $request->validate([
            'verified_data' => 'required|array',
            'verified_data.patientInfo' => 'required|array',
            'verified_data.labInfo' => 'required|array',
            'verified_data.testResults' => 'required|array',
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();

        try {
            $verifiedData = $request->verified_data;

            $patient = $this->createOrUpdatePatient($verifiedData['patientInfo']);

            $labInfo = $this->storeLabInfo($labReport, $verifiedData['labInfo']);

            $this->storeTestResults($labReport, $verifiedData['testResults']);

            $labReport->update([
                'patient_id' => $patient->id,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $request->notes,
                'status' => 'verified'
            ]);

            // 5. Update batch verified count
            $labReport->batch->increment('verified_reports');

            DB::commit();

            Log::info('Lab report verified successfully', [
                'lab_report_id' => $labReport->id,
                'patient_id' => $patient->id,
                'verified_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $labReport->fresh(['patient', 'batch']),
                'message' => 'Lab report verified and stored successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Lab report verification failed', [
                'lab_report_id' => $labReport->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store lab information
     */
    private function storeLabInfo($labReport, $labInfo)
    {
        return ExtractedLabInfo::updateOrCreate(
            ['lab_report_id' => $labReport->id],
            [
                'lab_id' => $labInfo['labId'],
                'requested_by' => $labInfo['requestedBy'],
                'requested_date' => $this->parseDate($labInfo['requestedDate']),
                'collected_date' => $this->parseDate($labInfo['collectedDate']),
                'analysis_date' => $this->parseDate($labInfo['analysisDate']),
                'validated_by' => $labInfo['validatedBy'],
            ]
        );
    }

    /**
     * Store test results
     */
    private function storeTestResults($labReport, $testResults)
    {
        ExtractedData::where('lab_report_id', $labReport->id)->delete();

        foreach ($testResults as $test) {
            ExtractedData::create([
                'lab_report_id' => $labReport->id,
                'category' => $test['category'],
                'test_name' => $test['testName'],
                'result' => $test['result'],
                'unit' => $test['unit'] ?? null,
                'reference' => $test['referenceRange'] ?? null,
                'flag' => $test['flag'],
                'coordinates' => null,
                'confidence_score' => 1.0,
                'is_verified' => true,
            ]);
        }
    }

    /**
     * Create or update patient from verified data
     */
    private function createOrUpdatePatient($patientInfo)
    {
        $patient = Patient::where('patient_id', $patientInfo['patientId'])
            ->orWhere(function ($query) use ($patientInfo) {
                $query->where('name', $patientInfo['name']);
                if (!empty($patientInfo['phone'])) {
                    $query->where('phone', $patientInfo['phone']);
                }
            })
            ->first();

        if ($patient) {
            // Update existing patient
            $patient->update([
                'name' => $patientInfo['name'],
                'age' => $patientInfo['age'],
                'gender' => $patientInfo['gender'],
                'phone' => $patientInfo['phone'] ?? $patient->phone,
            ]);
        } else {
            // Create new patient
            $patient = Patient::create([
                'patient_id' => $patientInfo['patientId'],
                'name' => $patientInfo['name'],
                'age' => $patientInfo['age'],
                'gender' => $patientInfo['gender'],
                'phone' => $patientInfo['phone'],
            ]);
        }

        return $patient;
    }

    /**
     * Parse date string to Carbon instance
     */
    private function parseDate($dateString)
    {
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y H:i', $dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Export verified lab reports as CSV
     */
    public function exportVerifiedCsv(Request $request)
    {
        $query = LabReport::with(['patient', 'batch', 'extractedData', 'extractedLabInfo', 'verifier'])
            ->where('status', 'verified')
            ->whereNotNull('verified_at');

        // Apply filters
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->filled('verified_by')) {
            $query->where('verified_by', $request->verified_by);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('verified_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('verified_at', '<=', $request->date_to);
        }

        $verifiedReports = $query->orderBy('verified_at', 'desc')->get();

        if ($verifiedReports->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No verified reports found for export'
            ], 404);
        }

        // Generate CSV content
        $csvContent = $this->generateVerifiedReportsCsv($verifiedReports);

        $filename = 'verified_lab_reports_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($csvContent));
    }

    /**
     * Generate CSV content for verified reports
     */
    private function generateVerifiedReportsCsv($reports)
    {
        $output = fopen('php://temp', 'r+');

        // CSV Headers
        $headers = [
            'Report ID',
            'Original Filename',
            'Batch Name',
            'Patient ID',
            'Patient Name',
            'Age',
            'Gender',
            'Phone',
            'Lab ID',
            'Requested By',
            'Requested Date',
            'Collected Date',
            'Analysis Date',
            'Validated By',
            'Test Name',
            'Result',
            'Unit',
            'Reference Range',
            'Flag',
            'Category',
            'Verified By',
            'Verified At',
            'Notes'
        ];

        fputcsv($output, $headers);

        foreach ($reports as $report) {
            $baseData = [
                'report_id' => $report->id,
                'filename' => $report->original_filename,
                'batch_name' => $report->batch->name ?? '',
                'patient_id' => $report->patient->patient_id ?? '',
                'patient_name' => $report->patient->name ?? '',
                'age' => $report->patient->age ?? '',
                'gender' => $report->patient->gender ?? '',
                'phone' => $report->patient->phone ?? '',
                'lab_id' => $report->extractedLabInfo->lab_id ?? '',
                'requested_by' => $report->extractedLabInfo->requested_by ?? '',
                'requested_date' => $report->extractedLabInfo->requested_date ?? '',
                'collected_date' => $report->extractedLabInfo->collected_date ?? '',
                'analysis_date' => $report->extractedLabInfo->analysis_date ?? '',
                'validated_by' => $report->extractedLabInfo->validated_by ?? '',
                'verified_by' => $report->verifier->name ?? '',
                'verified_at' => $report->verified_at ? $report->verified_at->format('Y-m-d H:i:s') : '',
                'notes' => $report->notes ?? ''
            ];

            // If report has test results, create a row for each test
            if ($report->extractedData->isNotEmpty()) {
                foreach ($report->extractedData as $testResult) {
                    $row = array_merge($baseData, [
                        'test_name' => $testResult->test_name,
                        'result' => $testResult->result,
                        'unit' => $testResult->unit ?? '',
                        'reference_range' => $testResult->reference ?? '',
                        'flag' => $testResult->flag ?? '',
                        'category' => $testResult->category ?? ''
                    ]);
                    fputcsv($output, array_values($row));
                }
            } else {
                // If no test results, create one row with empty test fields
                $row = array_merge($baseData, [
                    'test_name' => '',
                    'result' => '',
                    'unit' => '',
                    'reference_range' => '',
                    'flag' => '',
                    'category' => ''
                ]);
                fputcsv($output, array_values($row));
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Get PDF as base64 for authenticated requests
     */
    public function getPdfData(LabReport $labReport)
    {
        $filePath = $labReport->storage_path;
        
        if (!\Storage::disk('private')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $fileContent = \Storage::disk('private')->get($filePath);
        $base64Content = base64_encode($fileContent);
        
        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $labReport->original_filename,
                'content_type' => 'application/pdf',
                'base64_content' => $base64Content,
                'size' => strlen($fileContent)
            ],
            'message' => 'PDF content retrieved successfully'
        ]);
    }

    public function testExtract(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $pdfFile = $request->file('pdf_file');

        // Use your extraction engine/service
        $documentAiService = app(\App\Services\DocumentAiService::class);
        $extractedData = $documentAiService->processLabReport($pdfFile->getRealPath());

        if (!$extractedData) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data from the document.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $extractedData
        ]);
    }
}
