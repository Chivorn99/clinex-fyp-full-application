<?php

namespace App\Http\Controllers;

use App\Models\ReportBatch;
use App\Models\LabReport;
use App\Jobs\ProcessLabReportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ReportBatchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ReportBatch::with(['uploader', 'labReports'])
            ->withCount('labReports');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', $request->uploaded_by);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $batches = $query->latest()->paginate($request->get('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $batches,
                'message' => 'Batches retrieved successfully'
            ]);
        }

        return view('batches.index', compact('batches'));
    }

    /**
     * Upload multiple lab reports as a batch.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1|max:20', 
            'files.*' => 'required|file|mimes:pdf|max:10240',
            'auto_process' => 'nullable|in:true,false,1,0',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // Generate auto-incrementing batch name for today
            $batchName = $this->generateBatchName();

            // Create batch record
            $batch = ReportBatch::create([
                'name' => $batchName,
                'description' => null, // Remove description
                'uploaded_by' => auth()->id(),
                'total_reports' => count($request->file('files')),
                'processed_reports' => 0,
                'verified_reports' => 0,
                'failed_reports' => 0,
                'status' => 'pending',
            ]);

            $uploadedFiles = [];
            $batchFolder = 'lab_reports/batch_' . $batch->id;

            // Create batch directory
            Storage::disk('private')->makeDirectory($batchFolder);

            // Upload files and create lab report records
            foreach ($request->file('files') as $index => $file) {
                $originalName = $file->getClientOriginalName();
                $timestamp = time() + $index; // Ensure unique timestamps
                $storedName = $timestamp . '_' . Str::random(8) . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.pdf';
                $storagePath = $batchFolder . '/' . $storedName;

                // Store file
                Storage::disk('private')->putFileAs($batchFolder, $file, $storedName);

                // Create lab report record
                $labReport = LabReport::create([
                    'batch_id' => $batch->id,
                    'uploaded_by' => auth()->id(),
                    'original_filename' => $originalName,
                    'stored_filename' => $storedName,
                    'storage_path' => $storagePath,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'file_hash' => hash_file('sha256', $file->getPathname()),
                    'status' => 'uploaded',
                    'uploaded_at' => now(),
                ]);

                $uploadedFiles[] = [
                    'id' => $labReport->id,
                    'original_name' => $originalName,
                    'stored_name' => $storedName,
                    'size' => $file->getSize(),
                    'status' => 'uploaded'
                ];
            }

            DB::commit();

            Log::info('Batch uploaded successfully', [
                'batch_id' => $batch->id,
                'total_files' => count($uploadedFiles),
                'user_id' => auth()->id()
            ]);

            // Auto-process if requested
            if ($request->boolean('auto_process', true)) {
                $this->startBatchProcessing($batch);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'batch' => $batch->load(['uploader', 'labReports']),
                        'uploaded_files' => $uploadedFiles,
                    ],
                    'message' => 'Batch uploaded successfully'
                ], 201);
            }

            return redirect()->route('batches.show', $batch)
                ->with('success', 'Batch uploaded successfully');

        } catch (\Exception $e) {
            DB::rollback();
            
            // Clean up uploaded files on error
            if (isset($batchFolder)) {
                Storage::disk('private')->deleteDirectory($batchFolder);
            }

            Log::error('Batch upload failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload batch',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to upload batch: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Generate auto-incrementing batch name for today
     */
    private function generateBatchName()
    {
        $today = now()->format('d/m/Y');
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        
        // Count batches created today
        $todayBatchCount = ReportBatch::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        
        $batchNumber = $todayBatchCount + 1;
        
        return "Batch{$batchNumber} - {$today}";
    }

    /**
     * Start batch processing with parallel processing.
     */
    private function startBatchProcessing(ReportBatch $batch)
    {
        try {
            $batch->update([
                'status' => 'processing',
                'processing_started_at' => now()
            ]);

            // Dispatch the batch processing job
            ProcessLabReportBatch::dispatch($batch)->onQueue('lab-reports');

            Log::info('Batch processing started', [
                'batch_id' => $batch->id,
                'total_reports' => $batch->total_reports
            ]);

        } catch (\Exception $e) {
            $batch->update([
                'status' => 'failed',
                'processing_completed_at' => now()
            ]);

            Log::error('Failed to start batch processing', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Start processing a batch manually.
     */
    public function process(Request $request, ReportBatch $reportBatch)
    {
        if (in_array($reportBatch->status, ['processing', 'queued'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch is already being processed'
                ], 422);
            }
            return back()->withErrors(['error' => 'Batch is already being processed']);
        }

        try {
            $this->startBatchProcessing($reportBatch);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $reportBatch->fresh(),
                    'message' => 'Batch processing started'
                ]);
            }

            return back()->with('success', 'Batch processing started');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start batch processing',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to start batch processing: ' . $e->getMessage()]);
        }
    }

    /**
     * Get real-time batch processing status with individual file updates.
     */
    public function status(Request $request, ReportBatch $reportBatch)
    {
        // Refresh the batch from database to get latest counts
        $reportBatch->refresh();
        
        // Load lab reports with their current status
        $reportBatch->load([
            'labReports' => function ($query) {
                $query->select('id', 'batch_id', 'original_filename', 'status', 'processing_error', 'processed_at', 'processing_time')
                      ->orderBy('created_at');
            }
        ]);

        // Calculate status counts
        $statusCounts = $reportBatch->labReports->countBy('status');
        
        // Calculate progress
        $totalProcessed = ($statusCounts['processed'] ?? 0) + ($statusCounts['failed'] ?? 0);
        $progressPercentage = $reportBatch->total_reports > 0
            ? ($totalProcessed / $reportBatch->total_reports) * 100
            : 0;

        // Check if processing is complete
        $isComplete = $totalProcessed >= $reportBatch->total_reports;
        
        // Update batch status if all files are processed
        if ($isComplete && $reportBatch->status === 'processing') {
            $this->updateFinalBatchStatus($reportBatch);
            $reportBatch->refresh();
        }

        $response = [
            'success' => true,
            'data' => [
                'batch' => $reportBatch,
                'lab_reports' => $reportBatch->labReports->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'original_filename' => $report->original_filename,
                        'status' => $report->status,
                        'processing_error' => $report->processing_error,
                        'processed_at' => $report->processed_at,
                        'processing_time' => $report->processing_time,
                        'has_extracted_data' => !empty($report->extracted_data)
                    ];
                }),
                'progress_percentage' => round($progressPercentage, 2),
                'status_counts' => $statusCounts,
                'total_processed' => $totalProcessed,
                'is_complete' => $isComplete,
                'is_processing' => in_array($reportBatch->status, ['processing', 'queued']),
                'processing_duration' => $reportBatch->processing_started_at 
                    ? \Carbon\Carbon::parse($reportBatch->processing_started_at)->diffInSeconds($reportBatch->processing_completed_at ? \Carbon\Carbon::parse($reportBatch->processing_completed_at) : now()) 
                    : null
            ],
            'message' => 'Batch status retrieved successfully'
        ];

        if ($request->expectsJson()) {
            return response()->json($response);
        }

        return view('batches.status', $response['data']);
    }

    /**
     * Get live updates for batch processing (WebSocket alternative).
     */
    public function liveStatus(Request $request, ReportBatch $reportBatch)
    {
        // This endpoint is designed for polling from frontend
        $reportBatch->refresh();
        
        $reportBatch->load([
            'labReports' => function ($query) {
                $query->select('id', 'batch_id', 'original_filename', 'status', 'processed_at', 'processing_time')
                      ->orderBy('created_at');
            }
        ]);

        $statusCounts = $reportBatch->labReports->countBy('status');
        $totalProcessed = ($statusCounts['processed'] ?? 0) + ($statusCounts['failed'] ?? 0);
        $progressPercentage = $reportBatch->total_reports > 0
            ? ($totalProcessed / $reportBatch->total_reports) * 100
            : 0;

        // Update batch status if complete
        if ($totalProcessed >= $reportBatch->total_reports && $reportBatch->status === 'processing') {
            $this->updateFinalBatchStatus($reportBatch);
            $reportBatch->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'batch_id' => $reportBatch->id,
                'status' => $reportBatch->status,
                'progress_percentage' => round($progressPercentage, 2),
                'total_reports' => $reportBatch->total_reports,
                'processed_reports' => $statusCounts['processed'] ?? 0,
                'failed_reports' => $statusCounts['failed'] ?? 0,
                'pending_reports' => $statusCounts['uploaded'] ?? 0,
                'is_complete' => $totalProcessed >= $reportBatch->total_reports,
                'is_processing' => in_array($reportBatch->status, ['processing', 'queued']),
                'lab_reports' => $reportBatch->labReports->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'filename' => $report->original_filename,
                        'status' => $report->status,
                        'processed_at' => $report->processed_at,
                        'processing_time' => $report->processing_time
                    ];
                }),
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ReportBatch $reportBatch)
    {
        $reportBatch->load(['uploader', 'labReports.patient']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $reportBatch,
                'message' => 'Batch retrieved successfully'
            ]);
        }

        return view('batches.show', compact('reportBatch'));
    }

    /**
     * Update final batch status and counts.
     */
    private function updateFinalBatchStatus($reportBatch)
    {
        $reportBatch->refresh();

        // Get actual counts from database
        $statusCounts = $reportBatch->labReports()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $processedCount = $statusCounts['processed'] ?? 0;
        $failedCount = $statusCounts['failed'] ?? 0;
        $totalProcessed = $processedCount + $failedCount;

        // Determine final status
        $finalStatus = 'completed';
        if ($totalProcessed < $reportBatch->total_reports) {
            $finalStatus = 'partial';
        } elseif ($failedCount > 0 && $processedCount === 0) {
            $finalStatus = 'failed';
        }

        $reportBatch->update([
            'processed_reports' => $processedCount,
            'failed_reports' => $failedCount,
            'status' => $finalStatus,
            'processing_completed_at' => now()
        ]);

        Log::info('Batch processing completed', [
            'batch_id' => $reportBatch->id,
            'total_reports' => $reportBatch->total_reports,
            'processed' => $processedCount,
            'failed' => $failedCount,
            'final_status' => $finalStatus
        ]);
    }

    /**
     * Retry failed reports in a batch.
     */
    public function retryFailed(Request $request, ReportBatch $reportBatch)
    {
        $failedReports = $reportBatch->labReports()->where('status', 'failed')->get();

        if ($failedReports->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed reports to retry'
                ], 422);
            }
            return back()->withErrors(['error' => 'No failed reports to retry']);
        }

        // Reset failed reports to uploaded status
        $reportBatch->labReports()->where('status', 'failed')->update([
            'status' => 'uploaded',
            'processing_error' => null,
            'processed_at' => null,
            'processing_time' => null,
            'extracted_data' => null
        ]);

        // Update batch counts
        $reportBatch->update([
            'failed_reports' => 0,
            'processed_reports' => $reportBatch->processed_reports - $failedReports->count(),
            'status' => 'processing',
            'processing_started_at' => now()
        ]);

        ProcessLabReportBatch::dispatch($reportBatch)->onQueue('lab-reports');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $reportBatch->fresh(),
                'message' => "Retrying {$failedReports->count()} failed reports"
            ]);
        }

        return back()->with('success', "Retrying {$failedReports->count()} failed reports");
    }

    /**
     * Delete a batch and all its files.
     */
    public function destroy(Request $request, ReportBatch $reportBatch)
    {
        try {
            $batchFolder = 'lab_reports/batch_' . $reportBatch->id;
            Storage::disk('private')->deleteDirectory($batchFolder);
            $reportBatch->delete();
            Log::info('Batch deleted successfully', [
                'batch_id' => $reportBatch->id,
                'user_id' => auth()->id()
            ]);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Batch deleted successfully'
                ]);
            }
            return redirect()->route('batches.index')
                ->with('success', 'Batch deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete batch', [
                'batch_id' => $reportBatch->id,
                'error' => $e->getMessage()
            ]);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete batch',
                    'error' => $e->getMessage()
                ], 500);
            }
            return back()->withErrors(['error' => 'Failed to delete batch']);
        }
    }

    /**
     * Get lab reports ready for verification (status = 'processed')
     */
    public function getReportsForVerification(Request $request, ReportBatch $reportBatch)
    {
        $query = $reportBatch->labReports()
            ->with(['patient', 'uploader'])
            ->where('status', 'processed')
            ->whereNull('verified_at');

        // Optional: Filter by specific criteria
        if ($request->filled('search')) {
            $query->where('original_filename', 'like', '%' . $request->search . '%');
        }

        $reportsToVerify = $query->latest('processed_at')->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'batch' => $reportBatch->only(['id', 'name', 'status']),
                'reports_to_verify' => $reportsToVerify,
                'total_pending_verification' => $reportsToVerify->total(),
            ],
            'message' => 'Reports ready for verification retrieved successfully'
        ]);
    }

    /**
     * Get all processed reports across all batches that need verification
     */
    public function getAllReportsForVerification(Request $request)
    {
        $query = LabReport::with(['batch', 'patient', 'uploader'])
            ->where('status', 'processed')
            ->whereNull('verified_at');

        // Filter by batch
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // Filter by uploader
        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', $request->uploaded_by);
        }

        // Search by filename or patient name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhereHas('patient', function($patientQuery) use ($search) {
                      $patientQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('patient_id', 'like', "%{$search}%");
                  });
            });
        }

        $reportsToVerify = $query->latest('processed_at')->paginate($request->get('per_page', 15));

        // Transform data for verification interface
        $reportsToVerify->getCollection()->transform(function ($report) {
            return [
                'id' => $report->id,
                'original_filename' => $report->original_filename,
                'processed_at' => $report->processed_at,
                'processing_time' => $report->processing_time,
                'extracted_data_summary' => [
                    'has_patient_info' => !empty($report->extracted_data['patientInfo'] ?? null),
                    'has_lab_info' => !empty($report->extracted_data['labInfo'] ?? null),
                    'test_count' => count($report->extracted_data['testResults'] ?? []),
                    'categories' => collect($report->extracted_data['testResults'] ?? [])->pluck('category')->unique()->values()
                ],
                'patient' => $report->patient ? [
                    'id' => $report->patient->id,
                    'name' => $report->patient->name,
                    'patient_id' => $report->patient->patient_id
                ] : null,
                'batch' => [
                    'id' => $report->batch->id,
                    'name' => $report->batch->name
                ],
                'uploader' => $report->uploader->name ?? 'Unknown',
                'verification_url' => route('lab-reports.show', $report->id),
                'verify_url' => route('lab-reports.verify', $report->id)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $reportsToVerify,
            'summary' => [
                'total_pending' => $reportsToVerify->total(),
                'per_page' => $reportsToVerify->perPage(),
                'current_page' => $reportsToVerify->currentPage()
            ],
            'message' => 'Reports ready for verification retrieved successfully'
        ]);
    }

    /**
     * Export verified reports from a specific batch as CSV
     */
    public function exportBatchVerifiedCsv(Request $request, ReportBatch $reportBatch)
    {
        $verifiedReports = $reportBatch->labReports()
            ->with(['patient', 'extractedData', 'extractedLabInfo', 'verifier'])
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->orderBy('verified_at', 'desc')
            ->get();

        if ($verifiedReports->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No verified reports found in this batch'
            ], 404);
        }

        // Generate CSV content (reuse the same method from LabReportController)
        $csvContent = $this->generateVerifiedReportsCsv($verifiedReports, $reportBatch);

        $filename = 'batch_' . $reportBatch->id . '_verified_reports_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($csvContent));
    }

    /**
     * Generate CSV content for batch verified reports
     */
    private function generateVerifiedReportsCsv($reports, $batch = null)
    {
        // Same implementation as in LabReportController
        // You can either copy the method or create a shared service/trait
    }
}