<?php

namespace App\Jobs;

use App\Services\DocumentAiService;
use App\Events\PdfProcessingProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPdfForExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public string $pdfPath,
        public string $sessionId,
        public int $userId
    ) {}

    public function handle(): void
    {
        try {
            // Step 1: Starting
            broadcast(new PdfProcessingProgress($this->sessionId, 'Starting PDF processing...', 10))->toOthers();

            // Step 2: File validation
            if (!file_exists($this->pdfPath)) {
                throw new \Exception("PDF file not found at path: {$this->pdfPath}");
            }
            broadcast(new PdfProcessingProgress($this->sessionId, 'File validated, processing with Document AI...', 30))->toOthers();

            // Step 3: Document AI processing
            $aiService = new DocumentAiService();
            $document = $aiService->processDocument($this->pdfPath, 'a2439f686e4b0f79');

            if (!$document) {
                throw new \Exception("Failed to process document with Document AI");
            }
            broadcast(new PdfProcessingProgress($this->sessionId, 'Document AI processing complete, extracting data...', 60))->toOthers();

            // Step 4: Extract structure data
            $extractedData = $this->transformDocumentToStructureData($document);
            broadcast(new PdfProcessingProgress($this->sessionId, 'Structuring extracted data...', 80))->toOthers();

            // Step 5: Transform for frontend
            $transformedData = $this->transformStructureDataForTemplate($extractedData);
            broadcast(new PdfProcessingProgress($this->sessionId, 'Data extraction completed!', 100, true, $transformedData))->toOthers();

            // Clean up the temporary file
            if (file_exists($this->pdfPath)) {
                unlink($this->pdfPath);
                Log::info("Cleaned up temporary file: " . $this->pdfPath);
            }

        } catch (\Exception $e) {
            Log::error('PDF extraction job failed: ' . $e->getMessage());
            broadcast(new PdfProcessingProgress($this->sessionId, 'Extraction failed', 0, true, null, $e->getMessage()))->toOthers();
            
            // Clean up on error
            if (file_exists($this->pdfPath)) {
                unlink($this->pdfPath);
            }
        }
    }

    private function transformDocumentToStructureData($document): array
    {
        $structureData = [
            'entities' => [],
            'tables' => []
        ];

        try {
            $fullText = $this->sanitizeText($document->getText());
            
            // Process entities
            $entities = $document->getEntities();
            foreach ($entities as $entity) {
                $structureData['entities'][] = [
                    'type' => $this->sanitizeText($entity->getType()),
                    'mention_text' => $this->sanitizeText($entity->getMentionText()),
                    'confidence' => $entity->getConfidence(),
                    'normalized_value' => $entity->getNormalizedValue() ? 
                        $this->sanitizeText($entity->getNormalizedValue()->getText()) : 
                        $this->sanitizeText($entity->getMentionText())
                ];
            }

            // Process tables
            $pages = $document->getPages();
            foreach ($pages as $page) {
                $tables = $page->getTables();
                foreach ($tables as $tableIndex => $table) {
                    $headers = [];
                    $rows = [];

                    // Extract headers
                    $headerRows = $table->getHeaderRows();
                    if ($headerRows && count($headerRows) > 0) {
                        $headerRow = $headerRows[0];
                        $cells = $headerRow->getCells();
                        foreach ($cells as $cell) {
                            $headers[] = $this->extractTextFromDocumentAICell($cell, $fullText);
                        }
                    }

                    // Extract data rows
                    $bodyRows = $table->getBodyRows();
                    foreach ($bodyRows as $row) {
                        $rowData = [];
                        $cells = $row->getCells();
                        foreach ($cells as $cell) {
                            $rowData[] = $this->extractTextFromDocumentAICell($cell, $fullText);
                        }
                        if (!empty(array_filter($rowData))) {
                            $rows[] = $rowData;
                        }
                    }

                    if (!empty($headers) || !empty($rows)) {
                        $structureData['tables'][] = [
                            'name' => "Table " . ($tableIndex + 1),
                            'headers' => $headers,
                            'rows' => $rows
                        ];
                    }
                }
            }

            return $structureData;

        } catch (\Exception $e) {
            Log::error('Error transforming Document AI response: ' . $e->getMessage());
            return ['entities' => [], 'tables' => []];
        }
    }

    private function transformStructureDataForTemplate(array $structureData): array
    {
        $aiData = ['entities' => [], 'tables' => []];

        // Process entities
        foreach ($structureData['entities'] as $entity) {
            $aiData['entities'][$entity['type']] = $entity['mention_text'];
        }

        // Process tables
        foreach ($structureData['tables'] as $index => $table) {
            if (empty($table['headers']) && empty($table['rows'])) {
                continue;
            }

            $aiData['tables'][] = [
                'index' => $index,
                'name' => $table['name'] ?? "Test Results " . ($index + 1),
                'headers' => $table['headers'],
                'rows' => $table['rows']
            ];
        }

        return $aiData;
    }

    private function extractTextFromDocumentAICell($cell, $fullText): string
    {
        try {
            $layout = $cell->getLayout();
            if (!$layout) return '';

            $textAnchor = $layout->getTextAnchor();
            if (!$textAnchor) return '';

            $textSegments = $textAnchor->getTextSegments();
            $text = '';

            foreach ($textSegments as $segment) {
                $startIndex = $segment->getStartIndex();
                $endIndex = $segment->getEndIndex();
                
                if ($startIndex !== null && $endIndex !== null) {
                    $text .= substr($fullText, $startIndex, $endIndex - $startIndex);
                }
            }

            return $this->sanitizeText(trim($text));

        } catch (\Exception $e) {
            return '';
        }
    }

    private function sanitizeText(?string $text): string
    {
        if (empty($text)) return '';

        if (!mb_check_encoding($text, 'UTF-8')) {
            $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
            if ($encoding !== false) {
                $text = mb_convert_encoding($text, 'UTF-8', $encoding);
            } else {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/[^\P{C}\t\r\n]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
}