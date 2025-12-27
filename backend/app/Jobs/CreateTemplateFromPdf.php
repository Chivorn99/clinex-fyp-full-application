<?php

namespace App\Jobs;

use App\Models\Template;
use App\Services\DocumentAiService;
use App\Services\TemplateAnalyzerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateTemplateFromPdf implements ShouldQueue
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
        public string $pdfPath,
        public string $templateName,
        public string $processorId,
        public int $userId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(TemplateAnalyzerService $templateAnalyzer): void
    {
        Log::info("Creating template '{$this->templateName}' from PDF: {$this->pdfPath}");

        try {
            // Check if PDF file exists with detailed logging
            if (!file_exists($this->pdfPath)) {
                Log::error("PDF file not found. Path: {$this->pdfPath}");
                Log::error("Current working directory: " . getcwd());
                Log::error("Storage path: " . storage_path());
                Log::error("App path: " . storage_path('app'));
                
                // List files in temp directory for debugging
                $tempDir = dirname($this->pdfPath);
                if (is_dir($tempDir)) {
                    $files = scandir($tempDir);
                    Log::error("Files in temp directory: " . implode(', ', $files));
                } else {
                    Log::error("Temp directory doesn't exist: " . $tempDir);
                }
                
                throw new \Exception("PDF file not found at path: {$this->pdfPath}");
            }

            // Check file permissions
            if (!is_readable($this->pdfPath)) {
                throw new \Exception("PDF file is not readable: {$this->pdfPath}");
            }

            $fileSize = filesize($this->pdfPath);
            Log::info("Processing PDF file. Size: {$fileSize} bytes");

            // Analyze PDF structure using TemplateAnalyzerService
            $structureData = $templateAnalyzer->analyzePdfStructure($this->pdfPath, $this->processorId);

            // Generate field mappings from structure data
            $fieldMappings = $this->generateFieldMappings($structureData);

            // Create template with extracted structure
            $template = Template::create([
                'name' => $this->templateName,
                'processor_id' => $this->processorId,
                'mappings' => $fieldMappings, // Add this line - it was missing!
                'is_active' => true,
            ]);

            Log::info("Successfully created template ID: {$template->id} with name: '{$this->templateName}'");

            // Clean up temporary PDF file if needed
            if (str_contains($this->pdfPath, 'temp_pdfs')) {
                unlink($this->pdfPath);
                Log::info("Cleaned up temporary PDF file: {$this->pdfPath}");
            }

        } catch (\Exception $e) {
            Log::error("Error creating template '{$this->templateName}': " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Generate initial field mappings from structure data
     */
    private function generateFieldMappings(array $structureData): array
    {
        return [
            'header' => [
                'patient_info' => [],
                'lab_info' => []
            ],
            'test_sections' => array_map(function($table) {
                return [
                    'section_name' => $table['name'] ?? 'Unknown Section',
                    'page' => $table['page'] ?? 0,
                    'headers' => $table['headers'] ?? [],
                    'category' => 'general'
                ];
            }, $structureData['tables'] ?? []),
            'footer' => []
        ];
    }

    /**
     * Guess field name based on entity type and text
     */
    private function guessFieldName(string $entityType, string $mentionText): ?string
    {
        $text = strtolower($mentionText);
        
        // Common lab report fields
        if (str_contains($text, 'patient') || str_contains($text, 'name')) {
            return 'patient_name';
        }
        if (str_contains($text, 'date') || str_contains($text, 'collected')) {
            return 'collection_date';
        }
        if (str_contains($text, 'doctor') || str_contains($text, 'physician')) {
            return 'doctor_name';
        }
        if (str_contains($text, 'lab') || str_contains($text, 'laboratory')) {
            return 'lab_name';
        }
        if (str_contains($text, 'reference') || str_contains($text, 'ref')) {
            return 'reference_number';
        }
        
        return null;
    }

    // Add category guessing method
    private function guessCategory(string $tableName): string
    {
        $name = strtolower($tableName);
        
        if (str_contains($name, 'biochemistry')) return 'biochemistry';
        if (str_contains($name, 'hematology')) return 'hematology';
        if (str_contains($name, 'serology')) return 'serology';
        if (str_contains($name, 'immunology')) return 'immunology';
        
        return 'other';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Template creation job failed for '{$this->templateName}': " . $exception->getMessage());
        
        // Clean up temporary PDF file on failure
        if (file_exists($this->pdfPath) && str_contains($this->pdfPath, 'temp_pdfs')) {
            unlink($this->pdfPath);
        }
    }
}