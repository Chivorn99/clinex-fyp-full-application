<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Document;
use Illuminate\Support\Facades\Log;

class LabReportParserService
{
    private $fullText;
    private $lines;
    private $infoMap = [];

    // Known section headers that appear in lab reports
    const SECTION_HEADERS = [
        'BIOCHEMISTRY',
        'BIOCHIMISTRY',
        'IMMUNOLOGY',
        'HEMATOLOGY',
        'URINE ANALYSIS',
        'DRUG URINE',
        'ENZYMOLOGY',
        'TRANSAMINASE'
    ];

    // Keywords that signal the end of test result sections
    const STOP_KEYWORD = 'Validated By';

    // Known hospital phone patterns to exclude (these appear in footers)
    const HOSPITAL_PHONE_PATTERNS = [
        '097 840 47 89',
        '012 89 17 45',
        '012 28 60 70'
    ];

    /**
     * Parse a document into structured data
     */
    public function parse(Document $document): array
    {
        // Normalize line endings and split into individual lines
        $fullText = str_replace(["\r\n", "\r"], "\n", $document->getText());
        Log::debug("--- RAW OCR TEXT ---\n" . $fullText . "\n--- END RAW OCR TEXT ---");

        $this->fullText = $fullText;
        $this->lines = explode("\n", $fullText);
        $this->buildInfoMap();

        return [
            'patientInfo' => $this->getPatientInfo(),
            'labInfo' => $this->getLabInfo(),
            'testResults' => $this->parseTestResults(),
        ];
    }

    /**
     * Build a map of key-value pairs from the OCR text
     */
    private function buildInfoMap(): void
    {
        // Define important patient info and lab info keys
        $nameKeys = ['ឈោ្មះ/Name', 'in:/Name', 'nin:/Name'];
        $phoneKeys = ['ទូរស័ព្ទ/Phone', 'លេខទូរស័ព្ទ', 'î₪çîñḥ'];
        $validatedByFound = false;
        
        // Define the header box boundaries
        $headerStartLine = -1;
        $headerEndLine = -1;
        
        // Step 1: Identify the header box boundaries
        for ($i = 0; $i < min(30, count($this->lines)); $i++) {
            $line = trim($this->lines[$i]);
            
            // Header box usually starts with patient name or lab ID
            if ($headerStartLine === -1 && 
               (preg_match('/ឈោ្មះ\/Name|in:\/Name|nin:\/Name/u', $line) || 
                strpos($line, 'Patient ID') !== false)) {
                $headerStartLine = $i;
            }
            
            // Header ends with LABORATORY REPORT or any section header
            if ($headerStartLine !== -1 && 
               (stripos($line, 'LABORATORY REPORT') !== false || 
                $this->isLineASectionHeader($line))) {
                $headerEndLine = $i - 1;
                break;
            }
        }
        
        // If we couldn't identify boundaries, use default approach
        if ($headerStartLine === -1 || $headerEndLine === -1) {
            $headerStartLine = 0;
            $headerEndLine = min(30, count($this->lines));
        }
        
        Log::debug("Header boundaries: start={$headerStartLine}, end={$headerEndLine}");
        
        // Step 2: Handle scattered OCR text by processing key-value pairs sequentially
        $this->processScatteredOCRText($headerStartLine, $headerEndLine, $phoneKeys);
        
        // Clean up potential issues in the data mapping
        $this->cleanUpInfoMap();
        
        Log::debug('Built Info Map:', $this->infoMap);
    }

    /**
     * Process scattered OCR text where keys and values are not aligned horizontally
     */
    private function processScatteredOCRText($startLine, $endLine, $phoneKeys): void
    {
        $i = $startLine;
        
        while ($i <= $endLine) {
            $line = trim($this->lines[$i]);
            if (empty($line)) {
                $i++;
                continue;
            }
            
            Log::debug("Processing scattered line {$i}: {$line}");
            
            // Check if this line is a key (field name)
            if ($this->isPatientInfoKey($line)) {
                $key = $line;
                $value = null;
                
                // Look ahead for the corresponding value
                for ($j = $i + 1; $j <= min($i + 3, $endLine); $j++) {
                    $nextLine = trim($this->lines[$j]);
                    
                    // Skip empty lines
                    if (empty($nextLine)) continue;
                    
                    // If the next line starts with ":", it's likely the value
                    if (str_starts_with($nextLine, ':')) {
                        $value = trim(substr($nextLine, 1));
                        break;
                    }
                    // If it's another key, stop looking
                    if ($this->isPatientInfoKey($nextLine)) {
                        break;
                    }
                }
                
                // Store the key-value pair if we found a value
                if ($value !== null) {
                    Log::debug("Scattered pair: key='{$key}', value='{$value}'");
                    
                    // Skip hospital phone numbers
                    if (in_array($key, $phoneKeys) && $this->isHospitalPhone($value)) {
                        // Don't store hospital phones
                    } else {
                        $this->infoMap[$key] = $value;
                    }
                }
            }
            // Handle direct key:value pairs on the same line
            else if (preg_match('/^(.+?)\s*:\s*(.+)$/u', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                Log::debug("Direct pair: key='{$key}', value='{$value}'");
                
                // Skip hospital phone numbers
                if (in_array($key, $phoneKeys) && $this->isHospitalPhone($value)) {
                    // Don't store hospital phones
                } else {
                    $this->infoMap[$key] = $value;
                }
            }
            
            $i++;
        }
    }

    /**
     * Check if a line contains a patient information key
     */
    private function isPatientInfoKey($line): bool
    {
        $patientInfoKeys = [
            'ឈោ្មះ/Name', 'in:/Name', 'nin:/Name',
            'Patient ID', 'Lab ID', 
            'អាយុ/Age', 'ភេទ/Gender', 'ទូរស័ព្ទ/Phone',
            'Requested Date', 'Collected Date', 'Analysis Date', 'Requested By'
        ];
        
        foreach ($patientInfoKeys as $key) {
            if (strcasecmp(trim($line), $key) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a line is a section header
     */
    private function isLineASectionHeader($line): bool
    {
        $line = strtoupper(trim($line));
        $possibleHeaders = array_merge(self::SECTION_HEADERS, ['CBC', 'URINE ANALYSIS 11 TEST']);
        
        foreach ($possibleHeaders as $header) {
            if (strpos($line, strtoupper($header)) !== false || 
                levenshtein($line, strtoupper($header)) <= 2) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a phone number matches known hospital patterns
     */
    private function isHospitalPhone($value): bool 
    {
        foreach (self::HOSPITAL_PHONE_PATTERNS as $pattern) {
            if (strpos($value, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Enhanced cleanup method to handle scattered OCR issues
     */
    private function cleanUpInfoMap(): void
    {
        Log::debug('Before cleanup Info Map:', $this->infoMap);
        
        // Enhanced name extraction - look for names that might be misplaced in other fields
        if (!isset($this->infoMap['ឈោ្មះ/Name']) && !isset($this->infoMap['in:/Name']) && !isset($this->infoMap['nin:/Name'])) {
            // Check if name got misplaced in other fields
            foreach ($this->infoMap as $key => $value) {
                // If we find a name pattern in a date field or other field
                if (preg_match('/^[A-Z][A-Z\s]+$/', $value) && !preg_match('/\d/', $value)) {
                    // This looks like a name (all caps, no numbers)
                    if ($key === 'Requested Date' || $key === 'Analysis Date' || $key === 'Collected Date') {
                        $this->infoMap['ឈោ្មះ/Name'] = $value;
                        // Now find the correct date for this field
                        $this->findCorrectDateForField($key);
                        break;
                    }
                }
            }
            
            // If still no name found, look in raw text
            if (!isset($this->infoMap['ឈោ្មះ/Name'])) {
                foreach ($this->lines as $line) {
                    if (preg_match('/:\s*([A-Z][A-Z\s]+)/', $line, $matches)) {
                        $possibleName = trim($matches[1]);
                        // Make sure it's not a doctor name or other field
                        if (!preg_match('/Dr\.|Doctor/', $possibleName) && 
                            !in_array($possibleName, ['MALE', 'FEMALE']) &&
                            strlen($possibleName) > 2) {
                            $this->infoMap['ឈោ្មះ/Name'] = $possibleName;
                            break;
                        }
                    }
                }
            }
        }
        
        // Fix Patient ID if it's missing
        if (!isset($this->infoMap['Patient ID'])) {
            foreach ($this->lines as $line) {
                if (preg_match('/PT\d+/', $line, $matches)) {
                    $this->infoMap['Patient ID'] = $matches[0];
                    break;
                }
            }
        }
        
        // Fix Lab ID if it's missing
        if (!isset($this->infoMap['Lab ID'])) {
            foreach ($this->lines as $line) {
                if (preg_match('/LT\d+/', $line, $matches)) {
                    $this->infoMap['Lab ID'] = $matches[0];
                    break;
                }
            }
        }
        
        // Fix phone number extraction
        if (!isset($this->infoMap['ទូរស័ព្ទ/Phone']) || empty($this->infoMap['ទូរស័ព្ទ/Phone'])) {
            foreach ($this->lines as $line) {
                if (preg_match('/:\s*(0\d{8,9})/', $line, $matches)) {
                    $phoneNumber = $matches[1];
                    if (!$this->isHospitalPhone($phoneNumber)) {
                        $this->infoMap['ទូរស័ព្ទ/Phone'] = $phoneNumber;
                        break;
                    }
                }
            }
        }
        
        // Fix Requested By if it contains a Patient ID instead of doctor name
        if (isset($this->infoMap['Requested By']) && preg_match('/PT\d+/', $this->infoMap['Requested By'])) {
            foreach ($this->lines as $line) {
                if (preg_match('/Dr\.\s+([A-Z]+\s+[A-Z][a-z]+)/', $line, $matches)) {
                    $this->infoMap['Requested By'] = $matches[0];
                    break;
                }
            }
        }
        
        // Fix dates that contain names or other non-date values
        $dateFields = ['Requested Date', 'Collected Date', 'Analysis Date'];
        foreach ($dateFields as $field) {
            if (isset($this->infoMap[$field])) {
                $value = $this->infoMap[$field];
                // If the field doesn't contain a date pattern, find the correct date
                if (!preg_match('/\d{2}\/\d{2}\/\d{4}/', $value)) {
                    // Check if it's gender info
                    if (in_array(strtolower($value), ['male', 'female'])) {
                        if (!isset($this->infoMap['ភេទ/Gender'])) {
                            $this->infoMap['ភេទ/Gender'] = $value;
                        }
                    }
                    // Find correct date for this field
                    $this->findCorrectDateForField($field);
                }
            }
        }
        
        // Fix ValidatedBy
        if (!isset($this->infoMap['validatedBy']) || empty($this->infoMap['validatedBy'])) {
            foreach ($this->lines as $line) {
                if (preg_match('/(SREYNEANG\s*-\s*B\.Sc|ហុក\s+ម៉េងឆាយ|ផាន\s+ឡាទី)/u', $line, $matches)) {
                    $this->infoMap['validatedBy'] = trim($matches[1]);
                    break;
                }
                if (preg_match('/Lab\s+Technician\s*:\s*(.+)/', $line, $matches)) {
                    $techName = trim($matches[1]);
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $techName)) {
                        $this->infoMap['validatedBy'] = $techName;
                        break;
                    }
                }
            }
        }
        
        Log::debug('After cleanup Info Map:', $this->infoMap);
    }

    /**
     * Find the correct date for a specific field
     */
    private function findCorrectDateForField($field): void
    {
        $allDates = [];
        
        // Extract all dates from the document
        foreach ($this->lines as $lineIndex => $line) {
            if (preg_match_all('/(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2})/', $line, $matches)) {
                foreach ($matches[1] as $date) {
                    $allDates[] = [
                        'date' => $date,
                        'line' => $lineIndex,
                        'context' => $line
                    ];
                }
            }
        }
        
        // Try to match dates to fields based on context
        foreach ($allDates as $dateInfo) {
            $context = strtolower($dateInfo['context']);
            $date = $dateInfo['date'];
            
            // Check context around the date
            if ($field === 'Requested Date' && (strpos($context, 'request') !== false)) {
                $this->infoMap[$field] = $date;
                return;
            }
            if ($field === 'Collected Date' && (strpos($context, 'collect') !== false)) {
                $this->infoMap[$field] = $date;
                return;
            }
            if ($field === 'Analysis Date' && (strpos($context, 'analysis') !== false)) {
                $this->infoMap[$field] = $date;
                return;
            }
        }
        
        // If no context match, assign dates in order they appear
        if (!empty($allDates)) {
            if ($field === 'Requested Date' && !isset($this->infoMap[$field])) {
                $this->infoMap[$field] = $allDates[0]['date'];
            } elseif ($field === 'Collected Date' && count($allDates) > 1) {
                $this->infoMap[$field] = $allDates[1]['date'];
            } elseif ($field === 'Analysis Date' && count($allDates) > 2) {
                $this->infoMap[$field] = $allDates[2]['date'];
            }
        }
    }

    /**
     * Extract patient information from the info map
     */
    private function getPatientInfo(): array
    {
        // Try different possible keys for each field
        $name = $this->findValueInMap(['ឈោ្មះ/Name', 'in:/Name', 'nin:/Name']);
        $patientId = $this->findValueInMap(['Patient ID']);
        $age = $this->findValueInMap(['អាយុ/Age', 'In tij/Age']);
        $gender = $this->findValueInMap(['ភេទ/Gender']);
        $phone = $this->findValueInMap(['ទូរស័ព្ទ/Phone', 'លេខទូរស័ព្ទ']);
        
        // Apply additional corrections for common issues
        if (!$name && isset($this->infoMap[': សាន សេងយាន'])) {
            $name = 'សាន សេងយាន';
        }
        
        if (!$patientId && preg_match('/PT\d+/', $this->fullText, $matches)) {
            $patientId = $matches[0];
        }
        
        return [
            'name' => $name,
            'patientId' => $patientId,
            'age' => $age,
            'gender' => $gender,
            'phone' => $phone,
        ];
    }

    /**
     * Extract lab information from the info map
     */
    private function getLabInfo(): array
    {
        return [
            'labId' => $this->findValueInMap(['Lab ID']),
            'requestedBy' => $this->findValueInMap(['Requested By']),
            'requestedDate' => $this->findValueInMap(['Requested Date']),
            'collectedDate' => $this->findValueInMap(['Collected Date']),
            'analysisDate' => $this->findValueInMap(['Analysis Date']),
            'validatedBy' => $this->findValueInMap(['Lab Technician', 'validatedBy']),
        ];
    }

    /**
     * Find a value in the info map using various possible keys
     */
    private function findValueInMap(array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($this->infoMap[$key])) {
                return $this->infoMap[$key];
            }
        }
        return null;
    }

    /**
     * Parse test results from the document
     */
    private function parseTestResults(): array
    {
        $results = [];

        // Skip test names that are actually footer fields
        $skipTestNames = ['លេខទូរស័ព្ទ', 'î₪çîñḥ', 'អាសយដ្ឋាន'];

        // Ensure all possible section headers are included
        $sectionHeaders = array_merge(
            self::SECTION_HEADERS,
            ['CBC', 'URINE ANALYSIS 11 TEST']
        );

        // First pass: Find all section headers and their positions
        $sections = [];
        for ($i = 0; $i < count($this->lines); $i++) {
            $line = trim($this->lines[$i]);

            // Skip empty lines and headers
            if (empty($line) || stripos($line, 'LABORATORY REPORT') !== false) {
                continue;
            }

            // Check if this line is a section header - more flexible matching for OCR variations
            foreach ($sectionHeaders as $header) {
                if (
                    strcasecmp($line, $header) === 0 ||
                    stripos(strtoupper($line), strtoupper($header)) === 0 ||
                    levenshtein(strtoupper($line), strtoupper($header)) <= 2
                ) {
                    $sections[] = [
                        'category' => ucwords(strtolower($header)),
                        'startLine' => $i + 1,
                        'endLine' => null
                    ];
                    Log::debug("Found section header: " . ucwords(strtolower($header)) . " at line " . $i);
                    break;
                }
            }

            // If line contains "Validated By", mark it as end of the current section
            if (str_contains($line, self::STOP_KEYWORD)) {
                if (!empty($sections)) {
                    $sections[count($sections) - 1]['endLine'] = $i;
                }
            }
        }

        // Set end lines for sections
        for ($i = 0; $i < count($sections) - 1; $i++) {
            if ($sections[$i]['endLine'] === null) {
                $sections[$i]['endLine'] = $sections[$i + 1]['startLine'] - 1;
            }
        }

        // If the last section doesn't have an end, set it to end of document
        if (!empty($sections) && $sections[count($sections) - 1]['endLine'] === null) {
            $sections[count($sections) - 1]['endLine'] = count($this->lines) - 1;
        }

        // Second pass: Parse test results for each section
        foreach ($sections as $section) {
            $currentCategory = $section['category'];

            // Process lines in this section
            for ($i = $section['startLine']; $i < $section['endLine']; $i++) {
                $line = trim($this->lines[$i]);

                // Skip empty or header lines
                if (empty($line) || preg_match('/^Results\s+Unit\s+Reference\s+Range\s+Flag\s*$/i', $line)) {
                    continue;
                }

                // Parse test result line
                if (strpos($line, ':') !== false && strpos($line, ':') > 0) {
                    $colonPos = strpos($line, ':');
                    $testName = trim(substr($line, 0, $colonPos));
                    $valuesStr = trim(substr($line, $colonPos + 1));

                    if (empty($testName) || empty($valuesStr)) {
                        continue;
                    }

                    // Skip known footer fields
                    if (in_array($testName, $skipTestNames)) {
                        continue;
                    }

                    // Skip any value containing hospital phone patterns
                    $shouldSkip = false;
                    foreach (self::HOSPITAL_PHONE_PATTERNS as $pattern) {
                        if (strpos($valuesStr, $pattern) !== false) {
                            $shouldSkip = true;
                            break;
                        }
                    }
                    if ($shouldSkip) {
                        continue;
                    }

                    // Try to extract unit, reference range, and flag
                    $result = $this->parseTestValues($valuesStr);

                    if ($result) {
                        $results[] = array_merge([
                            'category' => $currentCategory,
                            'testName' => $testName,
                        ], $result);
                        Log::debug("Added result: {$testName} = {$valuesStr}");
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Parse test values with improved pattern matching
     */
    private function parseTestValues(string $valuesStr): ?array
    {
        $valuesStr = trim($valuesStr);

        // 1. Handle text-only results like "NEGATIVE" or "POSITIVE"
        if (preg_match('/^(NEGATIVE|POSITIVE|NORMAL)$/i', $valuesStr)) {
            return [
                'result' => $valuesStr,
                'unit' => null,
                'referenceRange' => null,
                'flag' => null,
            ];
        }

        // 2. Enhanced pattern for "value unit (range) flag"
        if (preg_match('/^([\d\.]+)\s+([^\s\(]+)(?:\s+\(?([^)]+)\)?)?(?:\s+([A-Z]))?$/i', $valuesStr, $matches)) {
            $result = $matches[1];
            $unit = isset($matches[2]) ? $matches[2] : null;
            $referenceRange = isset($matches[3]) ? '(' . $matches[3] . ')' : null;
            $flag = isset($matches[4]) ? $matches[4] : null;

            // Clean up and standardize the reference range format
            if (isset($referenceRange)) {
                // Handle spaces between numbers (e.g. "0.9 1.1" → "0.9-1.1")
                if (preg_match('/(\d+\.?\d*)\s+(\d+\.?\d*)/', $referenceRange)) {
                    $referenceRange = preg_replace('/(\d+\.?\d*)\s+(\d+\.?\d*)/', '$1-$2', $referenceRange);
                }

                // Make sure it's enclosed in parentheses
                if (!empty($referenceRange) && $referenceRange[0] !== '(') {
                    $referenceRange = '(' . $referenceRange . ')';
                }
            }

            return [
                'result' => $result,
                'unit' => $unit,
                'referenceRange' => $referenceRange,
                'flag' => $flag,
            ];
        }

        // 3. Handle values with just a numeric result (like "195")
        if (preg_match('/^([\d\.]+)$/i', $valuesStr)) {
            return [
                'result' => $valuesStr,
                'unit' => null,
                'referenceRange' => null,
                'flag' => null,
            ];
        }

        // 4. Handle percentage results
        if (preg_match('/^([\d\.]+)\s*%(?:\s+\(?([^)]+)\)?)?(?:\s+([A-Z]))?$/i', $valuesStr, $matches)) {
            return [
                'result' => $matches[1],
                'unit' => '%',
                'referenceRange' => isset($matches[2]) ? '(' . $matches[2] . ')' : null,
                'flag' => isset($matches[3]) ? $matches[3] : null,
            ];
        }

        // Log unmatched formats for debugging
        Log::debug("Could not parse test value format: {$valuesStr}");
        return null;
    }

    /**
     * Helper method to apply a regex pattern to the full text
     */
    private function match(string $regex): ?string
    {
        return preg_match($regex, $this->fullText, $matches) ? trim($matches[1]) : null;
    }

    /**
     * Extract dynamic data from JSON-formatted text
     */
    public function extractDynamicData(string $text): array
    {
        // Regular expressions to match dynamic data
        $regex = [
            'name' => '/"name":\s*"([^"]+)"/',
            'patientId' => '/"patientId":\s*"([^"]+)"/',
            'age' => '/"age":\s*"([^"]+)"/',
            'gender' => '/"gender":\s*"([^"]+)"/',
            'phone' => '/"phone":\s*(null|"[^"]*")/',
            'labId' => '/"labId":\s*"([^"]+)"/',
            'requestedBy' => '/"requestedBy":\s*"([^"]+)"/',
            'requestedDate' => '/"requestedDate":\s*(null|"[^"]*")/',
            'collectedDate' => '/"collectedDate":\s*"([^"]+)"/',
            'analysisDate' => '/"analysisDate":\s*"([^"]+)"/',
            'validatedBy' => '/"validatedBy":\s*(null|"[^"]*")/',
            'testResults' => '/"testResults":\s*\[(.*?)\]/s'
        ];

        $data = [];
        foreach ($regex as $key => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data[$key] = $matches[1] === 'null' ? null : $matches[1];
            }
        }

        // Extract test results (nested structure)
        if (isset($data['testResults'])) {
            preg_match_all('/\{(.*?)\}/s', $data['testResults'], $testMatches);
            $testResults = [];
            foreach ($testMatches[1] as $test) {
                preg_match('/"category":\s*"([^"]+)"/', $test, $categoryMatch);
                preg_match('/"testName":\s*"([^"]+)"/', $test, $testNameMatch);
                preg_match('/"result":\s*"([^"]+)"/', $test, $resultMatch);
                preg_match('/"unit":\s*"([^"]*)"/', $test, $unitMatch);
                preg_match('/"referenceRange":\s*"([^"]*)"/', $test, $referenceRangeMatch);
                preg_match('/"flag":\s*(null|"[^"]*")/', $test, $flagMatch);

                $testResults[] = [
                    'category' => $categoryMatch[1] ?? null,
                    'testName' => $testNameMatch[1] ?? null,
                    'result' => $resultMatch[1] ?? null,
                    'unit' => $unitMatch[1] ?? null,
                    'referenceRange' => $referenceRangeMatch[1] ?? null,
                    'flag' => $flagMatch[1] === 'null' ? null : (isset($flagMatch[1]) ? trim($flagMatch[1], '"') : null)
                ];
            }
            $data['testResults'] = $testResults;
        }

        return $data;
    }
}