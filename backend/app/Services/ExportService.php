<?php

namespace App\Services;

use App\Models\LabReport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    // ── Brand colours ───────────────────────────────────────────
    private const HEADER_BG    = '1E293B'; // slate-800
    private const HEADER_FG    = 'FFFFFF';
    private const ACCENT_BG    = 'F1F5F9'; // slate-100 (alternating rows)
    private const FLAG_HIGH_BG = 'FEE2E2'; // red-100
    private const FLAG_LOW_BG  = 'DBEAFE'; // blue-100

    // ── Single Report ───────────────────────────────────────────

    /**
     * Export a single lab report as a styled XLSX file.
     *
     * Sheet 1 — "Report Summary": key-value metadata
     * Sheet 2 — "Test Results":   extracted data table
     */
    public function exportSingleReport(LabReport $report): StreamedResponse
    {
        $report->load(['patient', 'batch', 'extractedData', 'extractedLabInfo', 'verifier', 'uploader']);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('ClineX Lab Report #' . $report->id)
            ->setCreator('ClineX')
            ->setDescription('Exported lab report');

        // ── Sheet 1: Report Summary ──
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Report Summary');
        $this->buildSummarySheet($summary, $report);

        // ── Sheet 2: Test Results ──
        $results = $spreadsheet->createSheet();
        $results->setTitle('Test Results');
        $this->buildTestResultsSheet($results, collect([$report]));

        $filename = $this->singleFilename($report);

        return $this->streamXlsx($spreadsheet, $filename);
    }

    // ── Bulk Reports ────────────────────────────────────────────

    /**
     * Export multiple lab reports as a styled XLSX file.
     *
     * Sheet 1 — "Reports":      one row per report with metadata
     * Sheet 2 — "Test Results":  all test results linked by Report ID
     */
    public function exportBulkReports(Collection $reports): StreamedResponse
    {
        $reports->load(['patient', 'batch', 'extractedData', 'extractedLabInfo', 'verifier', 'uploader']);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('ClineX Lab Reports Export')
            ->setCreator('ClineX')
            ->setDescription('Bulk export of lab reports');

        // ── Sheet 1: Reports ──
        $reportsSheet = $spreadsheet->getActiveSheet();
        $reportsSheet->setTitle('Reports');
        $this->buildReportsSheet($reportsSheet, $reports);

        // ── Sheet 2: Test Results ──
        $resultsSheet = $spreadsheet->createSheet();
        $resultsSheet->setTitle('Test Results');
        $this->buildTestResultsSheet($resultsSheet, $reports);

        $filename = 'clinex_reports_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return $this->streamXlsx($spreadsheet, $filename);
    }

    // ── Sheet Builders ──────────────────────────────────────────

    /**
     * Key-value summary sheet for a single report.
     */
    private function buildSummarySheet($sheet, LabReport $report): void
    {
        $patient = $report->patient;
        $labInfo = $report->extractedLabInfo;
        $verifier = $report->verifier;
        $uploader = $report->uploader;

        $rows = [
            ['REPORT INFORMATION', ''],
            ['Report ID',        $report->id],
            ['Filename',         $report->original_filename],
            ['Batch',            $report->batch->name ?? '—'],
            ['Status',           ucfirst($report->status)],
            ['Uploaded By',      $uploader->name ?? '—'],
            ['Created At',       $report->created_at?->format('Y-m-d H:i:s') ?? '—'],
            ['', ''],
            ['PATIENT INFORMATION', ''],
            ['Patient ID',       $patient->patient_id ?? '—'],
            ['Name',             $patient->name ?? '—'],
            ['Age',              $patient->age ?? '—'],
            ['Gender',           $patient->gender ?? '—'],
            ['Phone',            $patient->phone ?? '—'],
            ['', ''],
            ['LAB INFORMATION', ''],
            ['Lab ID',           $labInfo->lab_id ?? '—'],
            ['Requested By',     $labInfo->requested_by ?? '—'],
            ['Requested Date',   $labInfo->requested_date ?? '—'],
            ['Collected Date',   $labInfo->collected_date ?? '—'],
            ['Analysis Date',    $labInfo->analysis_date ?? '—'],
            ['Validated By',     $labInfo->validated_by ?? '—'],
            ['', ''],
            ['VERIFICATION', ''],
            ['Verified By',      $verifier->name ?? '—'],
            ['Verified At',      $report->verified_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Notes',            $report->notes ?? '—'],
        ];

        $row = 1;
        foreach ($rows as $data) {
            $sheet->setCellValue("A{$row}", $data[0]);
            $sheet->setCellValue("B{$row}", $data[1]);

            // Section headers
            if (in_array($data[0], ['REPORT INFORMATION', 'PATIENT INFORMATION', 'LAB INFORMATION', 'VERIFICATION'])) {
                $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF' . self::HEADER_FG]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::HEADER_BG]],
                ]);
            } else {
                // Label column bold
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setColor(new Color('475569'));
            }

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(40);
    }

    /**
     * Bulk reports overview sheet (one row per report).
     */
    private function buildReportsSheet($sheet, Collection $reports): void
    {
        $headers = [
            'Report ID', 'Filename', 'Batch', 'Status',
            'Patient ID', 'Patient Name', 'Age', 'Gender',
            'Lab ID', 'Requested By', 'Requested Date', 'Collected Date',
            'Analysis Date', 'Validated By',
            'Verified By', 'Verified At', 'Uploaded By', 'Notes',
        ];

        // Write headers
        $this->writeHeaderRow($sheet, $headers);

        // Write data rows
        $row = 2;
        foreach ($reports as $report) {
            $patient = $report->patient;
            $labInfo = $report->extractedLabInfo;

            $data = [
                $report->id,
                $report->original_filename,
                $report->batch->name ?? '—',
                ucfirst($report->status),
                $patient->patient_id ?? '—',
                $patient->name ?? '—',
                $patient->age ?? '—',
                $patient->gender ?? '—',
                $labInfo->lab_id ?? '—',
                $labInfo->requested_by ?? '—',
                $labInfo->requested_date ?? '—',
                $labInfo->collected_date ?? '—',
                $labInfo->analysis_date ?? '—',
                $labInfo->validated_by ?? '—',
                $report->verifier->name ?? '—',
                $report->verified_at?->format('Y-m-d H:i:s') ?? '—',
                $report->uploader->name ?? '—',
                $report->notes ?? '',
            ];

            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue("{$col}{$row}", $value);
                $col++;
            }

            // Zebra striping
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:R{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(self::ACCENT_BG);
            }

            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'R');
    }

    /**
     * Test results sheet (one row per test, linked by Report ID).
     */
    private function buildTestResultsSheet($sheet, Collection $reports): void
    {
        $headers = [
            'Report ID', 'Patient Name', 'Category',
            'Test Name', 'Result', 'Unit', 'Reference Range', 'Flag',
        ];

        $this->writeHeaderRow($sheet, $headers);

        $row = 2;
        foreach ($reports as $report) {
            $patientName = $report->patient->name ?? '—';

            if ($report->extractedData->isEmpty()) {
                $sheet->setCellValue("A{$row}", $report->id);
                $sheet->setCellValue("B{$row}", $patientName);
                $sheet->setCellValue("C{$row}", '—');
                $sheet->setCellValue("D{$row}", 'No test results extracted');
                $row++;
                continue;
            }

            foreach ($report->extractedData as $test) {
                $data = [
                    $report->id,
                    $patientName,
                    $test->category ?? '—',
                    $test->test_name,
                    $test->result,
                    $test->unit ?? '',
                    $test->reference ?? '',
                    $test->flag ?? '',
                ];

                $col = 'A';
                foreach ($data as $value) {
                    $sheet->setCellValue("{$col}{$row}", $value);
                    $col++;
                }

                // Colour-code flagged results
                if ($test->flag === 'H') {
                    $sheet->getStyle("A{$row}:H{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB(self::FLAG_HIGH_BG);
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true)->setColor(new Color('DC2626'));
                } elseif ($test->flag === 'L') {
                    $sheet->getStyle("A{$row}:H{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB(self::FLAG_LOW_BG);
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true)->setColor(new Color('2563EB'));
                } elseif ($row % 2 === 0) {
                    // Zebra stripe for non-flagged rows
                    $sheet->getStyle("A{$row}:H{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB(self::ACCENT_BG);
                }

                $row++;
            }
        }

        $this->autoSizeColumns($sheet, 'A', 'H');
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function writeHeaderRow($sheet, array $headers): void
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}1", $header);
            $col++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 11,
                'color' => ['argb' => 'FF' . self::HEADER_FG],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF' . self::HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FF0F172A'],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->setAutoFilter("A1:{$lastCol}1");
    }

    private function autoSizeColumns($sheet, string $from, string $to): void
    {
        $fromOrd = ord($from);
        $toOrd   = ord($to);
        for ($i = $fromOrd; $i <= $toOrd; $i++) {
            $sheet->getColumnDimension(chr($i))->setAutoSize(true);
        }
    }

    private function singleFilename(LabReport $report): string
    {
        $parts = ['clinex_report', $report->id];

        if ($report->patient?->name) {
            $parts[] = preg_replace('/[^a-zA-Z0-9]/', '_', $report->patient->name);
        }

        $parts[] = now()->format('Y-m-d');

        return implode('_', $parts) . '.xlsx';
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
            'Pragma'              => 'public',
        ]);
    }
}
