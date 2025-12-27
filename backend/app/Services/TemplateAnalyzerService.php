<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Document;
use Google\Cloud\DocumentAI\V1\Document\Entity;
use Google\Cloud\DocumentAI\V1\Document\Page\Table;
use Google\Cloud\DocumentAI\V1\Document\TextAnchor;

class TemplateAnalyzerService
{
    /**
     * Parses the Google Document AI response into a simple, clean array for the frontend.
     */
    public function parse(Document $document): array
    {
        $fullText = $document->getText();

        return [
            'entities' => $this->parseEntities($document->getEntities(), $fullText),
            'tables' => $this->parseTables($document->getPages(), $fullText),
        ];
    }

    /**
     * Extracts key-value pairs.
     */
    private function parseEntities($entities, string $fullText): array
    {
        $data = [];
        foreach ($entities as $entity) {
            /** @var Entity $entity */
            $label = trim($entity->getType());
            $value = trim($entity->getMentionText());
            // Ensure we don't have empty labels and we get unique values
            if (!empty($label) && !isset($data[$label])) {
                $data[$label] = $value;
            }
        }
        return $data;
    }

    /**
     * Extracts tables and their content.
     */
    private function parseTables($pages, string $fullText): array
    {
        $tablesData = [];
        $tableCounter = 0;

        foreach ($pages as $pageIndex => $page) {
            /** @var \Google\Cloud\DocumentAI\V1\Document\Page $page */
            foreach ($page->getTables() as $tableIndex => $table) {
                /** @var Table $table */
                $headerRow = $table->getHeaderRows()[0] ?? null;
                $bodyRows = $table->getBodyRows();

                $headers = $headerRow ? $this->getCellsFromRow($headerRow, $fullText) : [];

                // Only process tables that have headers
                if (empty($headers))
                    continue;

                $rows = [];
                foreach ($bodyRows as $bodyRow) {
                    $rows[] = $this->getCellsFromRow($bodyRow, $fullText);
                }

                $tablesData[] = [
                    'id' => "table_{$tableCounter}",
                    'name' => "Detected Table " . ($tableCounter + 1),
                    'headers' => $headers,
                    'rows' => array_slice($rows, 0, 3), // Only send first 3 rows for preview
                ];
                $tableCounter++;
            }
        }
        return $tablesData;
    }

    /**
     * Helper to get cell text from a table row.
     */
    private function getCellsFromRow($row, string $fullText): array
    {
        $cells = [];
        foreach ($row->getCells() as $cell) {
            $cells[] = $this->getTextFromLayout($cell->getLayout(), $fullText);
        }
        return $cells;
    }

    /**
     * Helper to extract text based on TextAnchor segments.
     */
    private function getTextFromLayout($layout, string $fullText): string
    {
        if (!$layout || !$layout->getTextAnchor() || !$layout->getTextAnchor()->getTextSegments()) {
            return '';
        }

        $text = '';
        foreach ($layout->getTextAnchor()->getTextSegments() as $segment) {
            /** @var TextAnchor\TextSegment $segment */
            $startIndex = $segment->getStartIndex();
            $endIndex = $segment->getEndIndex();
            $text .= substr($fullText, $startIndex, $endIndex - $startIndex);
        }
        return trim(str_replace("\n", ' ', $text));
    }
}