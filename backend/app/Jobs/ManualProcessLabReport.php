<?php

namespace App\Jobs;

use App\Models\LabReport;
use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use App\Services\ReportParserService;
use App\Models\ExtractedData;

class ManualProcessLabReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public LabReport $labReport
    ) {
        // This is how we pass the uploaded lab report into the job
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing lab report ID: {$this->labReport->id}");
        $this->labReport->update(['status' => 'processing']);

        $pdfPath = $this->labReport->getFullPath();
        $pythonScriptPath = base_path('scripts/python/ocr_processor.py');

        $result = Process::run("python \"{$pythonScriptPath}\" \"{$pdfPath}\"");

        if ($result->successful()) {
            $extractedText = $result->output();
            $parser = new ReportParserService();
            $structuredData = $parser->parse($extractedText);

            // Debugging logs
            Log::info("Raw OCR text for report ID: {$this->labReport->id}", ['ocr_text' => substr($extractedText, 0, 1000)]);
            Log::info("Parsed structured data for report ID: {$this->labReport->id}", $structuredData);

            $patientInfo = $structuredData['patient_info'];
            $patient = null;

            if (!empty($patientInfo['patient_id'])) {
                $patient = Patient::updateOrCreate(
                    ['patient_id' => $patientInfo['patient_id']],
                    [
                        'name' => $patientInfo['name'] ?? null,
                        'age' => $patientInfo['age'] ?? null,
                        'gender' => $patientInfo['gender'] ?? null,
                        'phone' => $patientInfo['phone'] ?? null,
                    ]
                );

                // Link the lab report to the patient
                $this->labReport->update(['patient_id' => $patient->id]);

                Log::info("Patient created/updated for report ID: {$this->labReport->id}", ['patient_id' => $patient->patient_id, 'patient_db_id' => $patient->id]);
            }

            // Save lab info to extracted_data
            $labData = [
                'lab_id' => $patientInfo['lab_id'] ?? null,
                'collected_date' => $patientInfo['collected_date'] ?? null,
                'analysis_date' => $patientInfo['analysis_date'] ?? null,
                'requested_by' => $patientInfo['requested_by'] ?? null,
            ];

            ExtractedData::create([
                'lab_report_id' => $this->labReport->id,
                'section' => 'lab_info',
                'field_name' => 'lab_data',
                'value' => json_encode($labData, JSON_UNESCAPED_UNICODE),
            ]);

            // 🔥 ADD THIS: Save patient_info to extracted_data
            if (!empty($structuredData['patient_info'])) {
                ExtractedData::create([
                    'lab_report_id' => $this->labReport->id,
                    'section' => 'patient_info',
                    'field_name' => 'patient_data',
                    'value' => json_encode($structuredData['patient_info'], JSON_UNESCAPED_UNICODE),
                ]);
                
                Log::info("Saved patient_info for report ID: {$this->labReport->id}", [
                    'patient_info_fields' => array_keys($structuredData['patient_info'])
                ]);
            }

            // Save the test results data as JSON objects per section
            if (!empty($structuredData['test_results'])) {
                foreach ($structuredData['test_results'] as $sectionName => $results) {
                    if (!empty($results)) {
                        ExtractedData::create([
                            'lab_report_id' => $this->labReport->id,
                            'section' => $sectionName, // e.g., 'biochemistry', 'hematology'
                            'field_name' => 'test_results',
                            'value' => json_encode($results, JSON_UNESCAPED_UNICODE),
                        ]);

                        Log::info("Saved {$sectionName} test results for report ID: {$this->labReport->id}", ['test_count' => count($results)]);
                    }
                }
            }

            $this->labReport->update(['status' => 'processed']);
        } else {
            $errorOutput = $result->errorOutput();
            Log::error("Failed to process report ID: {$this->labReport->id}", [
                'error' => $errorOutput
            ]);
            $this->labReport->update([
                'status' => 'failed',
                'processing_error' => $errorOutput
            ]);
        }

        Log::info("Finished processing job for lab report ID: {$this->labReport->id}");
    }
}
