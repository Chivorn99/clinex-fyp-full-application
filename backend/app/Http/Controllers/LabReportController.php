<?php

namespace App\Http\Controllers;

use App\Models\LabReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Jobs\ProcessLabReport;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentAiService;
use App\Services\ExportService;
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
                ], 422);
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

        $verifiedData = $request->verified_data;
        $documentType = $verifiedData['documentType'] ?? 'lab_report';

        // Validate based on document type
        if ($documentType === 'consultation') {
            $request->validate([
                'verified_data' => 'required|array',
                'verified_data.patientInfo' => 'required|array',
                'verified_data.consultationInfo' => 'nullable|array',
                'verified_data.vitalSigns' => 'nullable|array',
                'verified_data.clinicalRecords' => 'nullable|array',
                'verified_data.treatmentPlan' => 'nullable|array',
                'notes' => 'nullable|string|max:1000'
            ]);
        } else {
            $request->validate([
                'verified_data' => 'required|array',
                'verified_data.patientInfo' => 'required|array',
                'verified_data.labInfo' => 'required|array',
                'verified_data.testResults' => 'required|array',
                'notes' => 'nullable|string|max:1000'
            ]);
        }

        DB::beginTransaction();

        try {
            $verifiedData = $request->verified_data;

            $patient = $this->createOrUpdatePatient($verifiedData['patientInfo']);

            if ($documentType === 'consultation') {
                // Store consultation data as special categories in extracted_data
                $this->storeConsultationData($labReport, $verifiedData);
            } else {
                $labInfo = $this->storeLabInfo($labReport, $verifiedData['labInfo']);
                $this->storeTestResults($labReport, $verifiedData['testResults']);
            }

            $labReport->update([
                'patient_id' => $patient->id,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $request->notes,
                'status' => 'verified'
            ]);

            // 5. Update batch verified count
            $labReport->batch->increment('verified_reports');

            // 6. Auto-learning: Save as verified example if template exists
            if ($labReport->template_id && $labReport->raw_ocr_text) {
                // Keep max 5 examples per template
                $count = \App\Models\VerifiedExample::where('template_id', $labReport->template_id)->count();
                if ($count >= 5) {
                    $oldest = \App\Models\VerifiedExample::where('template_id', $labReport->template_id)
                        ->oldest()
                        ->first();
                    if ($oldest) {
                        $oldest->delete();
                    }
                }

                \App\Models\VerifiedExample::create([
                    'template_id' => $labReport->template_id,
                    'original_text' => $labReport->raw_ocr_text,
                    'corrected_json' => $verifiedData,
                ]);
            }

            DB::commit();

            Log::info('Lab report verified successfully', [
                'lab_report_id' => $labReport->id,
                'patient_id' => $patient->id,
                'document_type' => $documentType,
                'verified_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $labReport->fresh(['patient', 'batch']),
                'message' => ($documentType === 'consultation' ? 'Consultation form' : 'Lab report') . ' verified and stored successfully'
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
     * Store consultation form data as special categories in extracted_data table.
     */
    private function storeConsultationData($labReport, $verifiedData)
    {
        ExtractedData::where('lab_report_id', $labReport->id)->delete();

        // Store vital signs
        $vitalSigns = $verifiedData['vitalSigns'] ?? [];
        $vitalSignsMap = [
            'systolicBp' => 'Systolic BP',
            'diastolicBp' => 'Diastolic BP',
            'pulse' => 'Pulse',
            'respiratoryRate' => 'Respiratory Rate',
            'temperature' => 'Temperature',
            'o2Saturation' => 'O2 Saturation',
            'height' => 'Height',
            'weight' => 'Weight',
        ];
        foreach ($vitalSignsMap as $key => $testName) {
            if (!empty($vitalSigns[$key])) {
                ExtractedData::create([
                    'lab_report_id' => $labReport->id,
                    'category' => 'VITAL_SIGNS',
                    'test_name' => $testName,
                    'result' => $vitalSigns[$key],
                    'unit' => null,
                    'reference' => null,
                    'flag' => null,
                    'confidence_score' => 1.0,
                    'is_verified' => true,
                ]);
            }
        }

        // Store clinical records
        $clinical = $verifiedData['clinicalRecords'] ?? [];
        $clinicalMap = [
            'chiefComplaint' => 'Chief Complaint',
            'currentMedications' => 'Current Medications',
            'evaluationSummary' => 'Evaluation Summary',
        ];
        foreach ($clinicalMap as $key => $testName) {
            if (!empty($clinical[$key])) {
                ExtractedData::create([
                    'lab_report_id' => $labReport->id,
                    'category' => 'CLINICAL_RECORDS',
                    'test_name' => $testName,
                    'result' => $clinical[$key],
                    'unit' => null,
                    'reference' => null,
                    'flag' => null,
                    'confidence_score' => 1.0,
                    'is_verified' => true,
                ]);
            }
        }

        // Store treatment plan
        $treatmentPlan = $verifiedData['treatmentPlan'] ?? [];
        foreach ($treatmentPlan as $item) {
            if (!empty($item['code'])) {
                ExtractedData::create([
                    'lab_report_id' => $labReport->id,
                    'category' => 'TREATMENT_PLAN',
                    'test_name' => $item['type'] ?? 'Unknown',
                    'result' => $item['code'],
                    'unit' => null,
                    'reference' => null,
                    'flag' => null,
                    'confidence_score' => 1.0,
                    'is_verified' => true,
                ]);
            }
        }

        // Store consultation info
        $consultInfo = $verifiedData['consultationInfo'] ?? [];
        $consultMap = [
            'paymentType' => 'Payment Type',
            'physician' => 'Physician',
            'evaluateAt' => 'Evaluate At',
        ];
        foreach ($consultMap as $key => $testName) {
            if (!empty($consultInfo[$key])) {
                ExtractedData::create([
                    'lab_report_id' => $labReport->id,
                    'category' => 'CONSULTATION_INFO',
                    'test_name' => $testName,
                    'result' => $consultInfo[$key],
                    'unit' => null,
                    'reference' => null,
                    'flag' => null,
                    'confidence_score' => 1.0,
                    'is_verified' => true,
                ]);
            }
        }

        // Also store lab info equivalent for consultation
        ExtractedLabInfo::updateOrCreate(
            ['lab_report_id' => $labReport->id],
            [
                'lab_id' => '',
                'requested_by' => $consultInfo['physician'] ?? '',
                'requested_date' => $this->parseDate($consultInfo['evaluateAt'] ?? ''),
                'collected_date' => null,
                'analysis_date' => null,
                'validated_by' => '',
            ]
        );
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
     * Export a single lab report as a styled XLSX file.
     */
    public function exportXlsx(Request $request)
    {
        $reportId = $request->query('report_id');

        if (!$reportId) {
            return response()->json([
                'success' => false,
                'message' => 'report_id is required'
            ], 422);
        }

        $report = LabReport::find($reportId);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found'
            ], 404);
        }

        $exportService = app(ExportService::class);

        return $exportService->exportSingleReport($report);
    }

    /**
     * Export all verified reports as a styled XLSX file (with optional filters).
     */
    public function exportBulkXlsx(Request $request)
    {
        $query = LabReport::where('status', 'verified')
            ->whereNotNull('verified_at');

        // Apply optional filters
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

        $reports = $query->orderBy('verified_at', 'desc')->get();

        if ($reports->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No verified reports found for export'
            ], 404);
        }

        $exportService = app(ExportService::class);

        return $exportService->exportBulkReports($reports);
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
        
        // Detect actual MIME type from file extension
        $extension = strtolower(pathinfo($labReport->original_filename ?? $filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
            'bmp'  => 'image/bmp',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $labReport->original_filename,
                'content_type' => $contentType,
                'base64_content' => $base64Content,
                'size' => strlen($fileContent)
            ],
            'message' => 'File content retrieved successfully'
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
