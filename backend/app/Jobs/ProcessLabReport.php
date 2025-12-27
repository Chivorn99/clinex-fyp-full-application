<?php

namespace App\Jobs;

use App\Models\LabReport;
use App\Models\Template;
use App\Services\DocumentAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLabReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public LabReport $labReport
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentAiService $documentAiService): void
    {
        // --- 1. Load the Template from the Database ---
        $template = Template::find($this->labReport->template_id);
        if (!$template) {
            $this->labReport->update(['status' => 'failed', 'processing_error' => "Template ID {$this->labReport->template_id} not found."]);
            Log::error("Template ID {$this->labReport->template_id} not found for Lab Report {$this->labReport->id}.");
            return;
        }

        Log::info("Processing lab report ID: {$this->labReport->id} with Template: '{$template->name}'");
        $this->labReport->update(['status' => 'processing']);

        try {
            // --- 2. Get Processor ID from the Template ---
            $pdfPath = $this->labReport->getFullPath();
            $processorId = $template->processor_id;

            // Add file existence check
            if (!file_exists($pdfPath)) {
                throw new \Exception("PDF file not found at path: {$pdfPath}");
            }

            $document = $documentAiService->processDocument($pdfPath, $processorId);

            if ($document !== null) {
                Log::info("Successfully processed report ID: {$this->labReport->id}.");

                // --- 3. Safely extract and sanitize text ---
                $extractedText = $this->sanitizeText($document->getText());
                
                // Log only first 500 characters to avoid log overflow
                Log::info("Extracted Text: " . substr($extractedText, 0, 500) . "...");
                
                // --- 4. (Future Step) Apply Mappings ---
                // $parser = new DocumentAiParserService();
                // $structuredData = $parser->parse($document, $template->mappings);
                // $this->saveData($structuredData);

                $this->labReport->update([
                    'status' => 'processed',
                    'processing_error' => null
                ]);

            } else {
                throw new \Exception('Document AI returned null response');
            }

        } catch (\Exception $e) {
            Log::error("Error processing lab report ID {$this->labReport->id}: " . $e->getMessage());
            
            $this->labReport->update([
                'status' => 'failed',
                'processing_error' => 'Processing failed: ' . $e->getMessage()
            ]);
            
            // Re-throw to trigger job retry mechanism
            throw $e;
        }

        Log::info("Finished processing job for lab report ID: {$this->labReport->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job permanently failed for lab report ID {$this->labReport->id}: " . $exception->getMessage());
        
        $this->labReport->update([
            'status' => 'failed',
            'processing_error' => 'Job failed after retries: ' . $exception->getMessage()
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Wait 30s, then 60s, then 120s between retries
    }

    /**
     * Sanitize text to handle UTF-8 encoding issues
     */
    private function sanitizeText(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Convert to UTF-8 and handle malformed sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove non-printable characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $text);
        
        // Remove BOM if present
        $text = preg_replace('/\xEF\xBB\xBF/', '', $text);
        
        return $text;
    }
}