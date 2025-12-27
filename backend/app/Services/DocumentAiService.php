<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DocumentAiService
{
    protected $client;
    protected $processorName;

    /**
     * Create a new service instance.
     */
    public function __construct()
    {
        try {
            // --- THIS IS THE BULLETPROOF FIX FOR YOUR CREDENTIALS ---
            // It directly points to the credentials file, bypassing .env lookup issues.
            $credentialsPath = storage_path('app/google/clinex-application-ea5913277c08.json');

            if (!file_exists($credentialsPath)) {
                // If the file doesn't exist, this provides a clear, actionable error.
                throw new \Exception("Google Cloud credentials file not found at: {$credentialsPath}");
            }

            $this->client = new DocumentProcessorServiceClient([
                'credentials' => $credentialsPath
            ]);
            // --- END OF FIX ---

            // Load configuration from config/services.php
            $projectId = config('services.google.project_id');
            $location = config('services.google.location');
            $processorId = config('services.google.processor_id');

            if (!$projectId || !$location || !$processorId) {
                throw new \Exception('Google Cloud project_id, location, and processor_id must be configured in config/services.php');
            }

            // Construct the full processor name required by the API
            $this->processorName = $this->client->processorName($projectId, $location, $processorId);

        } catch (\Exception $e) {
            Log::error("FATAL: Failed to initialize Document AI client: " . $e->getMessage());
            // Fail fast if the service can't start.
            throw $e;
        }
    }

    /**
     * The single public method to process a lab report PDF.
     */
    public function processLabReport(string $filePath): ?array
    {
        try {
            // Step 1: Extract raw OCR text using Google Document AI
            $ocrText = $this->extractOCRText($filePath);
            
            if (!$ocrText) {
                Log::error("Failed to extract OCR text from: {$filePath}");
                return null;
            }

            Log::debug("--- RAW OCR TEXT ---\n" . $ocrText . "\n--- END RAW OCR TEXT ---");

            // Step 2: Use Python script to parse the OCR text intelligently
            return $this->parseWithPython($ocrText);

        } catch (\Exception $e) {
            Log::error('Document processing failed: ' . $e->getMessage());
            return null;
        }
    }

    private function extractOCRText(string $filePath): ?string
    {
        $documentContent = file_get_contents($filePath);
        $rawDocument = new RawDocument([
            'content' => $documentContent,
            'mime_type' => 'application/pdf',
        ]);

        $request = (new ProcessRequest())
            ->setName($this->processorName)
            ->setRawDocument($rawDocument);

        $result = $this->client->processDocument($request);
        $document = $result->getDocument();

        return $document ? $document->getText() : null;
    }

    private function parseWithPython(string $ocrText): ?array
    {
        // Create temporary file with OCR text
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr_text_');
        file_put_contents($tempFile, $ocrText, LOCK_EX);

        // Path to your Python script
        $pythonScript = base_path('scripts/python/document_ocr.py');
        
        // Check if Python script exists
        if (!file_exists($pythonScript)) {
            Log::error("Python script not found at: {$pythonScript}");
            unlink($tempFile);
            return null;
        }

        try {
            // Run Python script with explicit UTF-8 encoding
            $result = Process::run([
                'python', 
                '-u',  // Force unbuffered output
                $pythonScript, 
                $tempFile,
                '--output-format', 'json'
            ]);

            // Clean up temp file
            unlink($tempFile);

            if ($result->successful()) {
                $output = trim($result->output());
                Log::debug("Python parser raw output length: " . strlen($output));
                Log::debug("Python parser output: " . $output);
                
                // Handle potential encoding issues
                if (!mb_check_encoding($output, 'UTF-8')) {
                    $output = utf8_encode($output);
                }
                
                $parsed = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                } else {
                    Log::error("Failed to parse Python output as JSON: " . json_last_error_msg());
                    Log::error("Raw output: " . $output);
                    return null;
                }
            } else {
                $errorOutput = $result->errorOutput();
                Log::error("Python script failed with error: " . $errorOutput);
                
                // Check if there was actually valid output despite stderr messages
                $stdOutput = trim($result->output());
                if (!empty($stdOutput)) {
                    Log::info("Attempting to parse output despite stderr messages");
                    $parsed = json_decode($stdOutput, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $parsed;
                    }
                }
                
                return null;
            }

        } catch (\Exception $e) {
            // Clean up temp file on error
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            Log::error("Error running Python parser: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cleanly close the client connection.
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client->close();
        }
    }
}