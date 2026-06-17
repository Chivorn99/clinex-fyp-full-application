<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\Document;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DocumentAiService
{
    protected $client;
    protected $processorName;

    /**
     * Resolve credential path from config; supports relative path under storage.
     */
    private function resolveCredentialsPath(?string $configuredPath): ?string
    {
        if (!$configuredPath) {
            return null;
        }

        $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
        $isUnixAbsolute = str_starts_with($configuredPath, '/');

        if ($isWindowsAbsolute || $isUnixAbsolute) {
            return $configuredPath;
        }

        return storage_path($configuredPath);
    }

    /**
     * Create a new service instance.
     */
    public function __construct()
    {
        try {
            $projectId = config('services.google.project_id');
            $location = config('services.google.location');
            $processorId = config('services.google.processor_id');
            $configuredCredentialsPath = config('services.google.credentials');
            $credentialsPath = $this->resolveCredentialsPath($configuredCredentialsPath);

            if (!$projectId || !$location || !$processorId) {
                Log::warning('DocumentAiService: Google Cloud not configured — Document AI features will be unavailable.');
                $this->client = null;
                $this->processorName = null;
                return;
            }

            if (!$credentialsPath || !file_exists($credentialsPath)) {
                Log::warning("DocumentAiService: Credentials file not found at: {$credentialsPath} — Document AI features will be unavailable.");
                $this->client = null;
                $this->processorName = null;
                return;
            }

            $clientOptions = [
                'credentials' => $credentialsPath,
            ];

            // Non-default regions require explicit regional endpoint.
            if (!in_array($location, ['us', 'eu'], true)) {
                $clientOptions['apiEndpoint'] = sprintf('%s-documentai.googleapis.com', $location);
            }

            $this->client = new DocumentProcessorServiceClient([
                ...$clientOptions,
            ]);

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

    /**
     * Process a document and return the raw Document AI response object.
     */
    public function processDocument(string $filePath, ?string $processorId = null): ?Document
    {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("Document file not found: {$filePath}");
            }

            $documentContent = file_get_contents($filePath);
            if ($documentContent === false) {
                throw new \Exception("Unable to read document file: {$filePath}");
            }

            $rawDocument = new RawDocument([
                'content' => $documentContent,
                'mime_type' => $this->detectMimeType($filePath),
            ]);

            $request = (new ProcessRequest())
                ->setName($processorId ? $this->buildProcessorName($processorId) : $this->processorName)
                ->setRawDocument($rawDocument);

            $result = $this->client->processDocument($request);
            return $result->getDocument();
        } catch (\Exception $e) {
            Log::error('Document AI processDocument failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Compatibility wrapper used by template zoning flow.
     */
    public function processDocumentEnhanced(
        string $filePath,
        string $processorType = 'ocr',
        string $mimeType = 'application/pdf',
        bool $includeTables = true,
        bool $includeBlocks = true
    ): ?array {
        $document = $this->processDocument($filePath);
        if (!$document) {
            return null;
        }

        return [
            'text' => $document->getText(),
            'tables' => [],
            'blocks' => [],
            'processorType' => $processorType,
            'mimeType' => $mimeType,
            'includeTables' => $includeTables,
            'includeBlocks' => $includeBlocks,
        ];
    }

    private function buildProcessorName(string $processorId): string
    {
        $projectId = config('services.google.project_id');
        $location = config('services.google.location');

        if (!$projectId || !$location) {
            throw new \Exception('Google project_id and location must be configured.');
        }

        return $this->client->processorName($projectId, $location, $processorId);
    }

    /**
     * Detect supported mime type from file extension.
     */
    private function detectMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            default => 'application/pdf',
        };
    }

    private function extractOCRText(string $filePath): ?string
    {
        $documentContent = file_get_contents($filePath);
        if ($documentContent === false) {
            return null;
        }

        $rawDocument = new RawDocument([
            'content' => $documentContent,
            'mime_type' => $this->detectMimeType($filePath),
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