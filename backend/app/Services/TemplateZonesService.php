<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateZones;
use Illuminate\Support\Facades\Log;

class TemplateZonesService
{
    protected DocumentAiService $documentAiService;
    
    public function __construct(DocumentAiService $documentAiService)
    {
        $this->documentAiService = $documentAiService;
    }
    
    /**
     * Process a document using a template with defined zones
     */
    public function processWithTemplate(string $filePath, Template $template)
    {
        // First, extract all text using Document AI
        $fullExtraction = $this->documentAiService->processDocumentEnhanced(
            $filePath,
            'ocr',
            'application/pdf',
            true,
            true
        );
        
        if (!$fullExtraction) {
            return null;
        }
        
        // Get zones for this template
        $zones = $template->zones;
        
        // Extract data from each zone
        $extractedData = [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'extracted_fields' => [],
            'extracted_tables' => []
        ];
        
        foreach ($zones as $zone) {
            $zoneData = $this->extractDataFromZone($fullExtraction, $zone);
            
            if ($zone->type === 'field') {
                $extractedData['extracted_fields'][$zone->field_name] = $zoneData;
            } elseif ($zone->type === 'table') {
                $extractedData['extracted_tables'][$zone->field_name] = $zoneData;
            }
        }
        
        return $extractedData;
    }
    
    /**
     * Extract data from a specific zone
     */
    private function extractDataFromZone(array $fullExtraction, TemplateZones $zone)
    {
        // For fields, find text within the zone coordinates
        if ($zone->type === 'field') {
            return $this->extractFieldFromZone($fullExtraction, $zone);
        }
        
        // For tables, identify and structure table data within zone
        if ($zone->type === 'table') {
            return $this->extractTableFromZone($fullExtraction, $zone);
        }
        
        return null;
    }
    
    /**
     * Extract a single field value from a zone
     */
    private function extractFieldFromZone(array $fullExtraction, TemplateZones $zone)
    {
        // Get text blocks that fall within this zone
        $textBlocks = $this->getTextBlocksInZone($fullExtraction, $zone);
        
        // For a field, we typically just concatenate all text found
        $fieldText = '';
        foreach ($textBlocks as $block) {
            $fieldText .= $block['text'] . ' ';
        }
        
        return trim($fieldText);
    }
    
    /**
     * Extract a table from a zone
     */
    private function extractTableFromZone(array $fullExtraction, TemplateZones $zone)
    {
        // Look for tables in the full extraction that overlap with this zone
        foreach ($fullExtraction['tables'] as $table) {
            // Check if this table is within our zone
            // This would require calculating table bounds from its cells
            // For now, we'll just return the first table if it exists
            return $table;
        }
        
        // If no predefined table is found, try to identify rows and columns
        // based on text block positioning within the zone
        $textBlocks = $this->getTextBlocksInZone($fullExtraction, $zone);
        
        // Group blocks by approximate y-coordinate to identify rows
        $rows = $this->groupBlocksByRows($textBlocks);
        
        // Sort blocks within each row by x-coordinate to get proper column order
        $structuredTable = $this->arrangeRowsIntoTable($rows);
        
        return $structuredTable;
    }
    
    /**
     * Get text blocks that fall within a specific zone
     */
    private function getTextBlocksInZone(array $fullExtraction, TemplateZone $zone)
    {
        // For this we need the full page layout with text block coordinates
        // We'll simulate this for now
        $zoneBlocks = [];
        
        // In a real implementation, you would use the coordinates from Document AI
        // and check if they fall within the zone's coordinates
        
        return $zoneBlocks;
    }
    
    /**
     * Group text blocks into rows based on their y-coordinates
     */
    private function groupBlocksByRows(array $textBlocks)
    {
        $rows = [];
        $rowTolerance = 10; // pixels tolerance for considering blocks on the same row
        
        // Group blocks by similar y-coordinates
        foreach ($textBlocks as $block) {
            $yCenter = $block['y'] + ($block['height'] / 2);
            
            // Find a row this block belongs to
            $rowFound = false;
            foreach ($rows as $rowIndex => $rowBlocks) {
                $rowYCenter = 0;
                foreach ($rowBlocks as $rowBlock) {
                    $rowYCenter += $rowBlock['y'] + ($rowBlock['height'] / 2);
                }
                $rowYCenter /= count($rowBlocks);
                
                if (abs($yCenter - $rowYCenter) <= $rowTolerance) {
                    $rows[$rowIndex][] = $block;
                    $rowFound = true;
                    break;
                }
            }
            
            // If no suitable row found, create a new one
            if (!$rowFound) {
                $rows[] = [$block];
            }
        }
        
        return $rows;
    }
    
    /**
     * Arrange rows of text blocks into a structured table
     */
    private function arrangeRowsIntoTable(array $rows)
    {
        $table = [];
        
        // Sort rows by y-coordinate
        usort($rows, function($a, $b) {
            $aY = $a[0]['y'];
            $bY = $b[0]['y'];
            return $aY - $bY;
        });
        
        // Process each row
        foreach ($rows as $row) {
            // Sort blocks in this row by x-coordinate
            usort($row, function($a, $b) {
                return $a['x'] - $b['x'];
            });
            
            // Extract text from each block
            $tableRow = array_map(function($block) {
                return $block['text'];
            }, $row);
            
            $table[] = $tableRow;
        }
        
        return $table;
    }
}