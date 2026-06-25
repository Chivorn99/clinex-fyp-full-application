<?php

namespace App\Jobs;

use App\Models\LabReport;
use App\Models\ReportTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessSingleLabReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $labReport;
    public $timeout = 600; // 10 minutes timeout per file

    public function __construct(LabReport $labReport)
    {
        $this->labReport = $labReport;
    }

    public function handle()
    {
        try {
            // Update status to processing
            $this->labReport->update(['status' => 'processing']);

            // Get file path — handle both local and S3/Spaces storage
            $storagePath = $this->labReport->storage_path;
            $isS3 = config('filesystems.disks.private.driver') === 's3';

            if ($isS3) {
                // S3/Spaces: download to a temp file for the Python OCR script
                $filePath = storage_path('app/tmp_' . $this->labReport->id . '_' . basename($storagePath));
                file_put_contents($filePath, Storage::disk('private')->get($storagePath));
            } else {
                $filePath = Storage::disk('private')->path($storagePath);
            }

            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // Use the v2 OCR script with layout classification
            $pythonScript = base_path('scripts/python/clinex_ocr_v2.py');

            // Resolve Python binary: prefer python3 (Docker), fall back to python
            $pythonPath = config('app.python_path', 'python3');

            $command = [
                $pythonPath,
                $pythonScript,
                '--file',
                $filePath,
                '--output-format',
                'json'
            ];

            if ($this->labReport->document_type) {
                $command[] = '--document-type';
                $command[] = $this->labReport->document_type;
            }

            // Load active report template for LLM extraction
            $templateFile = null;
            $activeTemplate = $this->labReport->template_id 
                ? ReportTemplate::find($this->labReport->template_id)
                : ReportTemplate::getActive();
                
            if ($activeTemplate) {
                $templateFile = storage_path('app/private/template_' . $this->labReport->id . '.json');
                file_put_contents($templateFile, json_encode($activeTemplate->toPythonPayload()));
                $command[] = '--template';
                $command[] = $templateFile;
            }

            Log::info('Processing single lab report', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'command' => implode(' ', $command)
            ]);

            $process = new Process($command);
            $process->setTimeout(600); // 10 minutes per file

            // Pass environment variables so Python can find credentials
            $credentialsEnv = env('GOOGLE_APPLICATION_CREDENTIALS', '');
            $credentialsPath = str_starts_with($credentialsEnv, 'app/')
                ? storage_path($credentialsEnv)
                : storage_path('app/' . $credentialsEnv);

            $env = array_merge(getenv() ?: [], [
                'GOOGLE_APPLICATION_CREDENTIALS' => $credentialsPath,
                'GOOGLE_CLOUD_PROJECT_ID' => env('GOOGLE_CLOUD_PROJECT_ID', ''),
                'GOOGLE_CLOUD_LOCATION' => env('GOOGLE_CLOUD_LOCATION', ''),
                'GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID' => env('GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID', ''),
                'PADDLE_OCR_ENABLED' => env('PADDLE_OCR_ENABLED', 'false'),
                'PADDLE_OCR_LANGUAGE' => env('PADDLE_OCR_LANGUAGE', 'ch'),
                'PADDLE_OCR_CONFIDENCE_THRESHOLD' => env('PADDLE_OCR_CONFIDENCE_THRESHOLD', '0.85'),
                'PADDLE_OCR_DEVICE' => env('PADDLE_OCR_DEVICE', 'auto'),
                'PADDLE_OCR_GPU_ID' => env('PADDLE_OCR_GPU_ID', '0'),
                'OLLAMA_ENABLED' => env('OLLAMA_ENABLED', 'false'),
                'OLLAMA_HOST' => env('OLLAMA_HOST', 'http://ollama:11434'),
                'OLLAMA_MODEL' => env('OLLAMA_MODEL', 'phi3:mini'),
                'OLLAMA_TIMEOUT' => env('OLLAMA_TIMEOUT', '600'),
            ]);
            $process->setEnv($env);

            $process->mustRun();
            $output = $process->getOutput();

            // Clean up temp template file
            if ($templateFile && file_exists($templateFile)) {
                unlink($templateFile);
            }

            // Clean up temp S3 download file
            if (isset($isS3) && $isS3 && isset($filePath) && file_exists($filePath)) {
                @unlink($filePath);
            }

            if (empty($output)) {
                throw new \Exception('No output received from OCR script');
            }

            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON output: ' . json_last_error_msg());
            }

            // Handle array result (script returns array with single item)
            if (is_array($result) && isset($result[0])) {
                $result = $result[0];
            }

            // Update lab report with results
            $isSuccess = isset($result['success']) ? $result['success'] : !isset($result['error']);

            // Normalize: flatten grouped test_results → flat testResults array
            if ($isSuccess) {
                $result = $this->normalizeExtractedData($result);
            }

            // Detect document type from normalized result
            $docType = $result['documentType'] ?? 'lab_report';

            $updateData = [
                'processed_at' => now(),
                'processing_time' => $result['processingTime'] ?? null,
                'status' => $isSuccess ? 'processed' : 'failed',
                'extracted_data' => $isSuccess ? $result : null,
                'raw_ocr_text' => $isSuccess ? ($result['rawText'] ?? $result['raw_text'] ?? null) : ($result['rawText'] ?? null),
                'processing_error' => $isSuccess ? null : ($result['error'] ?? 'Unknown error'),
                'document_type' => $docType,
            ];

            $this->labReport->update($updateData);

            Log::info('Lab report processed successfully', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'status' => $updateData['status'],
                'processing_time' => $updateData['processing_time']
            ]);

        } catch (\Exception $e) {
            $this->labReport->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Lab report processing failed', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'error' => $e->getMessage()
            ]);

            // Don't re-throw to prevent job retry (we've already marked as failed)
        }

        // Check if this was the last report in the batch and update batch status if needed
        $this->checkBatchCompletion();
    }

    /**
     * Normalize extracted data from Python script output.
     *
     * Handles three formats:
     * - Lab report grouped: { "test_results": { "biochemistry": [...] } } → flat testResults
     * - Lab report flat: { "testResults": [...] } → pass through
     * - Consultation form: { "document_classification": "Consultation_Form", "patient_header": {...}, ... }
     *   → normalized to { "documentType": "consultation", "patientInfo": {...}, "consultationInfo": {...}, ... }
     */
    private function normalizeExtractedData(array $result): array
    {
        // Detect consultation form from clinex_ocr_v2.py output
        $classification = $result['document_classification'] ?? null;
        if ($classification === 'Consultation_Form') {
            return $this->normalizeConsultationData($result);
        }

        // Carry document type for lab reports
        $result['documentType'] = 'lab_report';

        // Already has flat testResults — nothing to do
        if (isset($result['testResults']) && is_array($result['testResults'])) {
            return $result;
        }

        // Has grouped test_results — flatten into testResults
        // Also handle panels from clinex_ocr_v2.py
        $groupedResults = $result['test_results'] ?? $result['panels'] ?? $result['panel_results'] ?? null;
        if (is_array($groupedResults)) {
            $flatTests = [];
            foreach ($groupedResults as $panel => $tests) {
                if (!is_array($tests)) {
                    continue;
                }
                foreach ($tests as $test) {
                    if (!is_array($test)) {
                        continue;
                    }
                    if (empty($test['category'])) {
                        $test['category'] = strtoupper($panel);
                    }
                    // Normalize clinex_ocr_v2.py key names to frontend format
                    if (isset($test['test_name']) && !isset($test['testName'])) {
                        $test['testName'] = $test['test_name'];
                        unset($test['test_name']);
                    }
                    if (isset($test['reference_range']) && !isset($test['referenceRange'])) {
                        $test['referenceRange'] = $test['reference_range'];
                        unset($test['reference_range']);
                    }
                    if (isset($test['value']) && !isset($test['result'])) {
                        $test['result'] = $test['value'];
                        unset($test['value']);
                    }
                    $flatTests[] = $test;
                }
            }
            $result['testResults'] = $flatTests;
            unset($result['test_results']);
            unset($result['panel_results']);
            unset($result['panels']);
        } else {
            $result['testResults'] = [];
        }

        // Normalize clinex_ocr_v2.py patient_header → patientInfo
        if (isset($result['patient_header']) && !isset($result['patientInfo'])) {
            $header = $result['patient_header'];
            $result['patientInfo'] = [
                'name' => $header['patient_name'] ?? '',
                'patientId' => $header['patient_id'] ?? '',
                'age' => $header['age_string'] ?? '',
                'gender' => $header['gender'] ?? '',
                'phone' => '',
            ];
            unset($result['patient_header']);
        }

        // Normalize clinex_ocr_v2.py report_metadata → labInfo
        if (isset($result['report_metadata']) && !isset($result['labInfo'])) {
            $meta = $result['report_metadata'];
            // requested_by / requested_date may be in patient_header (already consumed)
            // or in report_metadata depending on version
            $result['labInfo'] = [
                'labId' => $meta['lab_id'] ?? '',
                'requestedBy' => $result['patientInfo']['requestedBy']
                    ?? $meta['requested_by']
                    ?? ($result['patient_header']['requested_by'] ?? ''),
                'requestedDate' => $meta['requested_date']
                    ?? ($result['patient_header']['requested_date'] ?? ''),
                'collectedDate' => $meta['collected_timestamp'] ?? $meta['collected_date'] ?? '',
                'analysisDate' => $meta['analysis_timestamp'] ?? $meta['analysis_date'] ?? '',
                'validatedBy' => $meta['technician_name_khmer'] ?? $meta['validated_by'] ?? '',
            ];
            unset($result['report_metadata']);
        }

        return $result;
    }

    /**
     * Normalize consultation form data from clinex_ocr_v2.py output.
     */
    private function normalizeConsultationData(array $result): array
    {
        $header = $result['patient_header'] ?? [];
        $vitalSigns = $result['vital_signs'] ?? [];
        $clinical = $result['clinical_records'] ?? [];
        $routing = $result['treatment_routing'] ?? [];

        // Extract gender letter from gender_raw (e.g. "ប្រុស/M" → "M")
        $genderRaw = $header['gender_raw'] ?? $header['gender'] ?? '';
        $gender = $genderRaw;
        if (preg_match('/([MF])$/i', $genderRaw, $gm)) {
            $gender = strtoupper($gm[1]);
        }

        $normalized = [
            'documentType' => 'consultation',
            'rawText' => $result['raw_text'] ?? null,
            'patientInfo' => [
                'name' => $header['patient_name_khmer'] ?? $header['name_khmer'] ?? '',
                'patientId' => '',
                'age' => $header['age_string_raw'] ?? $header['age_string'] ?? '',
                'gender' => $gender,
                'phone' => '',
            ],
            'consultationInfo' => [
                'paymentType' => $header['payment_type'] ?? '',
                'physician' => $header['attending_physician'] ?? '',
                'evaluateAt' => $header['evaluation_timestamp'] ?? '',
            ],
            'vitalSigns' => [
                'systolicBp' => $vitalSigns['systolic_bp'] ?? '',
                'diastolicBp' => $vitalSigns['diastolic_bp'] ?? '',
                'pulse' => $vitalSigns['pulse'] ?? '',
                'respiratoryRate' => $vitalSigns['respiratory_rate'] ?? '',
                'temperature' => $vitalSigns['temperature_celsius'] ?? '',
                'o2Saturation' => $vitalSigns['o2_saturation_percentage'] ?? '',
                'height' => $vitalSigns['height_cm'] ?? '',
                'weight' => $vitalSigns['weight_kg'] ?? '',
            ],
            'clinicalRecords' => [
                'chiefComplaint' => $clinical['chief_complaint_raw'] ?? '',
                'currentMedications' => $clinical['current_medications'] ?? '',
                'evaluationSummary' => $clinical['evaluation_summary'] ?? '',
            ],
            'treatmentPlan' => [],
        ];

        // treatment_routing is now an array of {type, code} from v2
        if (is_array($routing)) {
            foreach ($routing as $item) {
                if (is_array($item) && !empty($item['code'])) {
                    // New format: [{type: "Prescription", code: "PRE001226"}, ...]
                    $normalized['treatmentPlan'][] = [
                        'type' => $item['type'] ?? 'Unknown',
                        'code' => $item['code'],
                    ];
                } elseif (is_string($item)) {
                    // Shouldn't happen, but safety
                    continue;
                } else {
                    // Old dict format fallback: {prescription_id: "PRE001226", ...}
                    // This branch handles legacy data
                }
            }
            // If routing was a dict (old format), handle it
            if (empty($normalized['treatmentPlan']) && !isset($routing[0])) {
                $typeMap = [
                    'prescription_id' => 'Prescription',
                    'laboratory_order_id' => 'Laboratory',
                    'echography_order_id' => 'Echography',
                    'xray_order_id' => 'Xray',
                    'ecg_order_id' => 'ECG',
                    'ent_endoscopy_order_id' => 'ENT Endoscopy',
                ];
                foreach ($routing as $key => $code) {
                    if ($code) {
                        $type = $typeMap[$key] ?? ucfirst(str_replace('_order_id', '', str_replace('_id', '', $key)));
                        $normalized['treatmentPlan'][] = [
                            'type' => $type,
                            'code' => $code,
                        ];
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Check if all reports in the batch are processed and update final batch status.
     */
    private function checkBatchCompletion()
    {
        if (!$this->labReport->batch_id) {
            return;
        }

        $batch = $this->labReport->batch;
        
        // Use a lock to prevent race conditions when multiple jobs finish at the exact same time
        $lock = \Illuminate\Support\Facades\Cache::lock('batch_completion_' . $batch->id, 10);
        
        if ($lock->get()) {
            try {
                $batch->refresh();
                
                if ($batch->status !== 'processing') {
                    return;
                }

                $statusCounts = $batch->labReports()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $processedCount = $statusCounts['processed'] ?? 0;
                $failedCount = $statusCounts['failed'] ?? 0;
                $totalProcessed = $processedCount + $failedCount;

                if ($totalProcessed >= $batch->total_reports) {
                    $finalStatus = 'completed';
                    if ($failedCount > 0 && $processedCount > 0) {
                        $finalStatus = 'completed_with_errors';
                    } elseif ($failedCount > 0 && $processedCount === 0) {
                        $finalStatus = 'failed';
                    }

                    $batch->update([
                        'processed_reports' => $processedCount,
                        'failed_reports' => $failedCount,
                        'status' => $finalStatus,
                        'processing_completed_at' => now()
                    ]);

                    Log::info('Batch processing completed from single job', [
                        'batch_id' => $batch->id,
                        'final_status' => $finalStatus
                    ]);
                }
            } finally {
                $lock->release();
            }
        }
    }
}