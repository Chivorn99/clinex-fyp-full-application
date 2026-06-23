<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LabReport;
use App\Models\ReportTemplate;
use App\Models\ReportBatch;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // Dashboard Overview

    /**
     * Aggregate stats for the admin dashboard overview.
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_users'     => User::count(),
            'total_reports'   => LabReport::count(),
            'total_patients'  => Patient::count(),
            'total_batches'   => ReportBatch::count(),
            'verified_reports'   => LabReport::whereNotNull('verified_at')->count(),
            'unverified_reports' => LabReport::whereNull('verified_at')->count(),
            'processing_reports' => LabReport::where('status', 'processing')->count(),
            'failed_reports'     => LabReport::where('status', 'failed')->count(),
            'users_by_role'      => User::select('role', DB::raw('count(*) as count'))
                                       ->groupBy('role')
                                       ->pluck('count', 'role'),
            'reports_this_week'  => LabReport::where('created_at', '>=', now()->subWeek())->count(),
            'reports_today'      => LabReport::whereDate('created_at', today())->count(),
        ];

        return response()->json($stats);
    }

    // User Management

    /**
     * List all users with pagination.
     */
    public function listUsers(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 20));

        return response()->json($users);
    }

    /**
     * Update a user's role.
     */
    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'doctor', 'lab_technician'])],
        ]);

        // Prevent demoting yourself
        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot change your own role.'], 403);
        }

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'User role updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Update a user's granular permissions.
     */
    public function updateUserPermissions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        // Validate that all keys are valid permissions
        $invalidKeys = array_diff(
            array_keys($validated['permissions']),
            User::AVAILABLE_PERMISSIONS
        );

        if (!empty($invalidKeys)) {
            return response()->json([
                'message' => 'Invalid permissions: ' . implode(', ', $invalidKeys),
            ], 422);
        }

        // Admins don't need explicit permissions
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Admin users have all permissions by default.',
            ], 422);
        }

        $user->update(['permissions' => $validated['permissions']]);

        return response()->json([
            'message' => 'User permissions updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    // Template Management

    /**
     * List all report templates.
     */
    public function listTemplates(): JsonResponse
    {
        $templates = ReportTemplate::orderBy('created_at', 'desc')->get();
        return response()->json($templates);
    }

    /**
     * Update a report template.
     */
    public function updateTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'hospital_code'     => ['sometimes', 'string', 'max:100'],
            'is_active'         => ['sometimes', 'boolean'],
            'llm_model'         => ['sometimes', 'string', 'max:100'],
            'schema'            => ['sometimes', 'array'],
            'few_shot_examples' => ['sometimes', 'array'],
        ]);

        $template->update($validated);

        return response()->json([
            'message'  => 'Template updated successfully.',
            'template' => $template->fresh(),
        ]);
    }

    /**
     * Toggle a template's active status.
     */
    public function toggleTemplate(ReportTemplate $template): JsonResponse
    {
        $template->update(['is_active' => !$template->is_active]);

        return response()->json([
            'message'   => $template->is_active ? 'Template activated.' : 'Template deactivated.',
            'is_active' => $template->is_active,
        ]);
    }

    /**
     * Create a new report template.
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'hospital_code'     => ['required', 'string', 'max:100', 'unique:report_templates,hospital_code'],
            'is_active'         => ['sometimes', 'boolean'],
            'llm_model'         => ['sometimes', 'string', 'max:100'],
            'schema'            => ['required', 'array'],
            'few_shot_examples' => ['sometimes', 'array'],
        ]);

        $template = ReportTemplate::create($validated);

        return response()->json([
            'message'  => 'Template created successfully.',
            'template' => $template,
        ], 201);
    }

    /**
     * Delete a report template.
     */
    public function deleteTemplate(ReportTemplate $template): JsonResponse
    {
        $template->delete();
        return response()->json(['message' => 'Template deleted successfully.']);
    }

    // Report Oversight

    /**
     * List all reports with filters for admin oversight.
     */
    public function listReports(Request $request): JsonResponse
    {
        $query = LabReport::with(['patient']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('is_verified')) {
            $isVerified = filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN);
            $query->where(function ($q) use ($isVerified) {
                if ($isVerified) {
                    $q->whereNotNull('verified_at');
                } else {
                    $q->whereNull('verified_at');
                }
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $reports = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 20));

        return response()->json($reports);
    }

    /**
     * Bulk delete reports.
     */
    public function bulkDeleteReports(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_ids' => ['required', 'array', 'min:1'],
            'report_ids.*' => ['integer', 'exists:lab_reports,id'],
        ]);

        $count = LabReport::whereIn('id', $validated['report_ids'])->delete();

        return response()->json([
            'message' => "{$count} report(s) deleted successfully.",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Bulk reprocess reports.
     */
    public function bulkReprocessReports(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_ids' => ['required', 'array', 'min:1'],
            'report_ids.*' => ['integer', 'exists:lab_reports,id'],
        ]);

        $reports = LabReport::whereIn('id', $validated['report_ids'])->get();
        $queued = 0;

        foreach ($reports as $report) {
            if ($report->storage_path && $report->fileExists()) {
                $report->update(['status' => 'pending', 'verified_at' => null, 'verified_by' => null]);
                \App\Jobs\ProcessSingleLabReport::dispatch($report);
                $queued++;
            }
        }

        return response()->json([
            'message' => "{$queued} report(s) queued for reprocessing.",
            'queued_count' => $queued,
        ]);
    }

    // System Health

    /**
     * System health status — Ollama, DB, queue, disk.
     */
    public function systemHealth(): JsonResponse
    {
        $health = [];

        // Database check
        try {
            DB::connection()->getPdo();
            $health['database'] = [
                'status' => 'healthy',
                'type' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $health['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // AI Engines — detect all configured OCR/AI providers
        $engines = [];

        // Google Document AI
        $googleProjectId = env('GOOGLE_CLOUD_PROJECT_ID', '');
        $googleProcessorId = env('GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID', '');
        $googleCredentials = env('GOOGLE_APPLICATION_CREDENTIALS', '');
        $engines[] = [
            'name' => 'Google Document AI',
            'enabled' => !empty($googleProjectId) && !empty($googleProcessorId) && !empty($googleCredentials),
            'type' => 'ocr',
        ];

        // PaddleOCR
        $engines[] = [
            'name' => 'PaddleOCR',
            'enabled' => filter_var(env('PADDLE_OCR_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'type' => 'ocr',
        ];

        // KiriOCR
        $engines[] = [
            'name' => 'KiriOCR',
            'enabled' => filter_var(env('KIRI_OCR_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'type' => 'ocr',
        ];

        // Ollama LLM (post-processing)
        $ollamaEnabled = filter_var(env('OLLAMA_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $engines[] = [
            'name' => 'Ollama LLM',
            'enabled' => $ollamaEnabled,
            'type' => 'llm',
            'model' => env('OLLAMA_MODEL', 'phi3:mini'),
        ];

        $enabledCount = count(array_filter($engines, fn($e) => $e['enabled']));

        $health['ai_engines'] = [
            'status' => $enabledCount > 0 ? 'healthy' : 'unhealthy',
            'engines' => $engines,
            'enabled_count' => $enabledCount,
            'total_count' => count($engines),
        ];

        // Queue check — accurate real-time stats
        $pendingJobs = 0;
        $processingJobs = 0;
        $failedJobs = DB::table('failed_jobs')->count();

        // Count pending jobs from the Redis queue
        try {
            $pendingJobs = \Illuminate\Support\Facades\Queue::size();
        } catch (\Exception $e) {
            // Redis unavailable — try database jobs table as fallback
            try {
                $pendingJobs = DB::table('jobs')->count();
            } catch (\Exception $e2) {
                $pendingJobs = 0;
            }
        }

        // Count actively processing batches (more accurate than queue size)
        $staleThreshold = now()->subMinutes((int) config('clinex.stale_job_threshold_minutes', 120));

        $processingBatches = ReportBatch::where('status', 'processing')->count();
        $processingReports = LabReport::where('status', 'processing')->count();
        $processingJobs = $processingBatches + $processingReports;

        // Detect stale jobs (stuck in processing beyond threshold)
        $staleBatches = ReportBatch::where('status', 'processing')
            ->where('updated_at', '<', $staleThreshold)->count();
        $staleReports = LabReport::where('status', 'processing')
            ->where('updated_at', '<', $staleThreshold)->count();
        $staleProcessingJobs = $staleBatches + $staleReports;

        $health['queue'] = [
            'pending_jobs'          => $pendingJobs,
            'failed_jobs'           => $failedJobs,
            'processing_jobs'       => $processingJobs,
            'stale_processing_jobs' => $staleProcessingJobs,
        ];

        // Disk usage
        $uploadsPath = storage_path('app/uploads');
        if (is_dir($uploadsPath)) {
            $totalSize = 0;
            $fileCount = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploadsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                    $fileCount++;
                }
            }
            $health['disk'] = [
                'uploads_size_bytes' => $totalSize,
                'uploads_size_human' => $this->formatBytes($totalSize),
                'uploads_file_count' => $fileCount,
            ];
        } else {
            $health['disk'] = [
                'uploads_size_bytes' => 0,
                'uploads_size_human' => '0 B',
                'uploads_file_count' => 0,
            ];
        }

        return response()->json($health);
    }

    /**
     * Format bytes to human readable string.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    /**
     * Flush all failed jobs from the failed_jobs table.
     */
    public function flushFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();

        return response()->json([
            'success' => true,
            'message' => "Cleared {$count} failed job(s).",
            'cleared' => $count,
        ]);
    }

    /**
     * Reset jobs stuck in 'processing' status beyond the stale threshold.
     */
    public function resetStaleJobs(): JsonResponse
    {
        $thresholdMinutes = (int) config('clinex.stale_job_threshold_minutes', 120);
        $cutoff = now()->subMinutes($thresholdMinutes);

        $resetReports = LabReport::where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => 'failed',
                'processing_error' => 'Stale: manually reset by admin',
                'processed_at' => now(),
            ]);

        $resetBatches = ReportBatch::where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
            ]);

        $total = $resetReports + $resetBatches;

        \Illuminate\Support\Facades\Log::info('Admin reset stale processing jobs', [
            'reports_reset' => $resetReports,
            'batches_reset' => $resetBatches,
            'threshold_minutes' => $thresholdMinutes,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Reset {$total} stale job(s).",
            'reset_reports' => $resetReports,
            'reset_batches' => $resetBatches,
        ]);
    }
}
