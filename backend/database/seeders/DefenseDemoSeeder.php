<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Patient;
use App\Models\LabReport;
use App\Models\ReportBatch;
use App\Models\ExtractedData;
use App\Models\ExtractedLabInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DefenseDemoSeeder extends Seeder
{
    /**
     * Realistic lab test definitions by category.
     */
    private array $labTests = [
        'BIOCHEMISTRY' => [
            ['name' => 'Glucose', 'unit' => 'mg/dL', 'ref' => '(70 - 100)', 'min' => 65, 'max' => 180],
            ['name' => 'Creatinine, serum', 'unit' => 'mg/dL', 'ref' => '(0.9 - 1.1)', 'min' => 0.5, 'max' => 2.0],
            ['name' => 'Urea/BUN', 'unit' => 'mg/dL', 'ref' => '(6.0 - 40.0)', 'min' => 8, 'max' => 55],
            ['name' => 'Cholesterol Total', 'unit' => 'mg/dL', 'ref' => '(0 - 200)', 'min' => 140, 'max' => 280],
            ['name' => 'Cholesterol-HDL', 'unit' => 'mg/dL', 'ref' => '(> 60)', 'min' => 30, 'max' => 85],
            ['name' => 'Triglycerides', 'unit' => 'mg/dL', 'ref' => '(0 - 150)', 'min' => 70, 'max' => 300],
            ['name' => 'Total Bilirubin', 'unit' => 'mg/dL', 'ref' => '(0.0 - 1.0)', 'min' => 0.2, 'max' => 2.0],
            ['name' => 'AST (SGOT)', 'unit' => 'U/L', 'ref' => '(0 - 40)', 'min' => 12, 'max' => 85],
            ['name' => 'ALT (SGPT)', 'unit' => 'U/L', 'ref' => '(0 - 41)', 'min' => 10, 'max' => 90],
            ['name' => 'Uric Acid', 'unit' => 'mg/dL', 'ref' => '(3.5 - 7.2)', 'min' => 3.0, 'max' => 9.5],
        ],
        'HEMATOLOGY' => [
            ['name' => 'WBC', 'unit' => '10^9/L', 'ref' => '(3.5 - 10.0)', 'min' => 3.0, 'max' => 15.0],
            ['name' => 'RBC', 'unit' => 'x10^12/L', 'ref' => '(3.50 - 5.50)', 'min' => 3.2, 'max' => 6.0],
            ['name' => 'HGB', 'unit' => 'g/dL', 'ref' => '(11.5 - 16.5)', 'min' => 9.5, 'max' => 18.0],
            ['name' => 'HCT', 'unit' => '%', 'ref' => '(36 - 54)', 'min' => 30, 'max' => 58],
            ['name' => 'PLT', 'unit' => '10^9/L', 'ref' => '(150 - 400)', 'min' => 100, 'max' => 450],
            ['name' => 'MCV', 'unit' => 'fL', 'ref' => '(75.0 - 100.0)', 'min' => 68, 'max' => 105],
            ['name' => 'MCH', 'unit' => 'pg', 'ref' => '(27 - 33)', 'min' => 24, 'max' => 36],
            ['name' => 'LYM%', 'unit' => '%', 'ref' => '(15.0 - 50.0)', 'min' => 12, 'max' => 55],
            ['name' => 'MONO%', 'unit' => '%', 'ref' => '(2.0 - 15.0)', 'min' => 1.5, 'max' => 18],
        ],
        'SERO/IMMUNOLOGY' => [
            ['name' => 'HBsAg', 'unit' => '', 'ref' => 'Non-Reactive', 'min' => 0, 'max' => 1, 'qualitative' => true],
            ['name' => 'Anti-HCV', 'unit' => '', 'ref' => 'Non-Reactive', 'min' => 0, 'max' => 1, 'qualitative' => true],
            ['name' => 'HIV 1/2', 'unit' => '', 'ref' => 'Non-Reactive', 'min' => 0, 'max' => 1, 'qualitative' => true],
        ],
        'URINE ANALYSIS' => [
            ['name' => 'pH', 'unit' => '', 'ref' => '(5.0 - 8.0)', 'min' => 4.5, 'max' => 8.5],
            ['name' => 'Specific Gravity', 'unit' => '', 'ref' => '(1.005 - 1.030)', 'min' => 1.003, 'max' => 1.035],
            ['name' => 'Protein', 'unit' => '', 'ref' => 'Negative', 'min' => 0, 'max' => 1, 'qualitative' => true],
            ['name' => 'Glucose (Urine)', 'unit' => '', 'ref' => 'Negative', 'min' => 0, 'max' => 1, 'qualitative' => true],
        ],
    ];

    /**
     * Khmer-inspired patient data.
     */
    private array $patients = [
        ['id' => 'PT00101', 'name' => 'HENG VANNAT', 'age' => '38 Y, 0 M, 0 D', 'gender' => 'Male', 'phone' => '098-765-432'],
        ['id' => 'PT00102', 'name' => 'CHEA SOKUNTHEA', 'age' => '45 Y, 3 M, 12 D', 'gender' => 'Female', 'phone' => '097-234-567'],
        ['id' => 'PT00103', 'name' => 'SOK DARA', 'age' => '52 Y, 7 M, 5 D', 'gender' => 'Male', 'phone' => '096-345-678'],
        ['id' => 'PT00104', 'name' => 'PICH SREYMOM', 'age' => '28 Y, 1 M, 20 D', 'gender' => 'Female', 'phone' => '095-456-789'],
        ['id' => 'PT00105', 'name' => 'KIM SOPHEAP', 'age' => '61 Y, 11 M, 3 D', 'gender' => 'Male', 'phone' => '099-567-890'],
        ['id' => 'PT00106', 'name' => 'CHAN BOPHA', 'age' => '34 Y, 5 M, 18 D', 'gender' => 'Female', 'phone' => '098-678-901'],
        ['id' => 'PT00107', 'name' => 'LENG SOVANN', 'age' => '72 Y, 2 M, 9 D', 'gender' => 'Male', 'phone' => '097-789-012'],
        ['id' => 'PT00108', 'name' => 'NHEM CHANNARY', 'age' => '41 Y, 8 M, 25 D', 'gender' => 'Female', 'phone' => '096-890-123'],
        ['id' => 'PT00109', 'name' => 'TITH PISETH', 'age' => '55 Y, 0 M, 14 D', 'gender' => 'Male', 'phone' => '095-901-234'],
        ['id' => 'PT00110', 'name' => 'YIM SOTHEAVY', 'age' => '29 Y, 6 M, 7 D', 'gender' => 'Female', 'phone' => '099-012-345'],
        ['id' => 'PT00111', 'name' => 'CHHUN RATHANA', 'age' => '67 Y, 4 M, 1 D', 'gender' => 'Male', 'phone' => '098-123-456'],
        ['id' => 'PT00112', 'name' => 'MEAS SOKHA', 'age' => '23 Y, 9 M, 28 D', 'gender' => 'Female', 'phone' => '097-234-568'],
        ['id' => 'PT00113', 'name' => 'KHIEU VICHEKA', 'age' => '48 Y, 2 M, 16 D', 'gender' => 'Male', 'phone' => '096-345-679'],
        ['id' => 'PT00114', 'name' => 'PRUM THEARY', 'age' => '36 Y, 10 M, 4 D', 'gender' => 'Female', 'phone' => '095-456-790'],
        ['id' => 'PT00115', 'name' => 'ROS SAMNANG', 'age' => '59 Y, 1 M, 22 D', 'gender' => 'Male', 'phone' => '099-567-891'],
        ['id' => 'PT00116', 'name' => 'KHAT MEALEA', 'age' => '31 Y, 7 M, 11 D', 'gender' => 'Female', 'phone' => '098-678-902'],
        ['id' => 'PT00117', 'name' => 'SUON VIBOL', 'age' => '44 Y, 3 M, 30 D', 'gender' => 'Male', 'phone' => '097-789-013'],
        ['id' => 'PT00118', 'name' => 'OUK RACHANA', 'age' => '26 Y, 5 M, 8 D', 'gender' => 'Female', 'phone' => '096-890-124'],
    ];

    private array $doctors = [
        'Dr. Sok Chheng', 'Dr. Chea Vanny', 'Dr. Mao Bunthoeun',
        'Dr. Keo Sophal', 'Dr. Hem Sarath', 'Dr. Pich Sreypov',
    ];

    private array $validators = [
        'Sophal Meas', 'Sothea Khem', 'Vuthy Chan', 'Panha Lim',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->command->info('Starting Defense Demo Seeder...');

            // 1. Create users
            $users = $this->createUsers();

            // 2. Create patients
            $patients = $this->createPatients();

            // 3. Create batches and reports
            $this->createBatchesAndReports($users, $patients);

            $this->command->info('Defense Demo Seeder complete!');
            $this->command->info("   - Users: {$users['all']->count()}");
            $this->command->info("   - Patients: " . count($patients));
            $this->command->info("   - Batches: " . ReportBatch::count());
            $this->command->info("   - Lab Reports: " . LabReport::count());
            $this->command->info("   - Extracted Data Records: " . ExtractedData::count());
        });
    }

    private function createUsers(): array
    {
        $this->command->info('Creating users...');

        $admin = User::firstOrCreate(
            ['email' => 'admin@clinex.com'],
            [
                'name' => 'ClineX Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'permissions' => ['manage_users', 'manage_templates', 'manage_reports', 'view_analytics', 'view_system_health', 'export_data'],
                'email_verified_at' => now(),
            ]
        );

        $tech1 = User::firstOrCreate(
            ['email' => 'nhoungnchivorn99@gmail.com'],
            [
                'name' => 'Chivorn Nhoung',
                'password' => Hash::make('password'),
                'role' => 'lab_technician',
                'email_verified_at' => now(),
            ]
        );

        $tech2 = User::firstOrCreate(
            ['email' => 'sopheak@clinex.demo'],
            [
                'name' => 'Sopheak Meas',
                'password' => Hash::make('password'),
                'role' => 'lab_technician',
                'email_verified_at' => now(),
            ]
        );

        $tech3 = User::firstOrCreate(
            ['email' => 'dara@clinex.demo'],
            [
                'name' => 'Dara Keo',
                'password' => Hash::make('password'),
                'role' => 'lab_technician',
                'email_verified_at' => now(),
            ]
        );

        $techs = collect([$tech1, $tech2, $tech3]);
        $all = collect([$admin, $tech1, $tech2, $tech3]);

        return ['admin' => $admin, 'techs' => $techs, 'all' => $all];
    }

    private function createPatients(): array
    {
        $this->command->info('Creating patients...');

        $created = [];
        foreach ($this->patients as $p) {
            $created[] = Patient::firstOrCreate(
                ['patient_id' => $p['id']],
                [
                    'name' => $p['name'],
                    'age' => $p['age'],
                    'gender' => $p['gender'],
                    'phone' => $p['phone'],
                ]
            );
        }

        return $created;
    }

    private function createBatchesAndReports(array $users, array $patients): void
    {
        $this->command->info('Creating batches and reports...');

        $techs = $users['techs'];
        $admin = $users['admin'];
        $now = Carbon::now();

        $batches = [
            [
                'name' => 'June 15 Morning Labs',
                'uploader' => $techs[0],
                'date' => $now->copy()->subDays(2),
                'report_count' => 8,
                'status' => 'completed',
                'patient_indices' => [0, 1, 2, 3, 4, 5, 6, 7],
                'report_status' => 'verified',
            ],
            [
                'name' => 'June 10 Afternoon Labs',
                'uploader' => $techs[1],
                'date' => $now->copy()->subDays(7),
                'report_count' => 6,
                'status' => 'completed',
                'patient_indices' => [8, 9, 10, 11, 12, 13],
                'report_status' => 'verified',
            ],
            [
                'name' => 'June 5 Routine Screening',
                'uploader' => $techs[2],
                'date' => $now->copy()->subDays(12),
                'report_count' => 5,
                'status' => 'completed',
                'patient_indices' => [14, 15, 16, 17, 0],
                'report_status' => 'verified',
            ],
            [
                'name' => 'May 28 Emergency Panel',
                'uploader' => $techs[0],
                'date' => $now->copy()->subDays(20),
                'report_count' => 4,
                'status' => 'completed',
                'patient_indices' => [1, 5, 9, 13],
                'report_status' => 'processed',
            ],
            [
                'name' => 'May 20 Pre-Surgery Screening',
                'uploader' => $techs[1],
                'date' => $now->copy()->subDays(28),
                'report_count' => 5,
                'status' => 'completed',
                'patient_indices' => [2, 6, 10, 14, 3],
                'report_status' => 'verified',
            ],
            [
                'name' => 'June 16 Pending Review',
                'uploader' => $techs[2],
                'date' => $now->copy()->subDays(1),
                'report_count' => 3,
                'status' => 'processing',
                'patient_indices' => [4, 7, 11],
                'report_status' => 'processing',
            ],
        ];

        foreach ($batches as $batchDef) {
            $this->command->info("  Batch: {$batchDef['name']}");

            $batchDate = $batchDef['date'];
            $reportCount = $batchDef['report_count'];

            $verifiedCount = 0;
            $processedCount = 0;

            $batch = ReportBatch::create([
                'name' => $batchDef['name'],
                'uploaded_by' => $batchDef['uploader']->id,
                'total_reports' => $reportCount,
                'status' => $batchDef['status'],
                'processing_started_at' => $batchDate->copy()->addMinutes(2),
                'processing_completed_at' => $batchDef['status'] === 'completed'
                    ? $batchDate->copy()->addMinutes(15)
                    : null,
            ]);

            for ($i = 0; $i < $reportCount; $i++) {
                $patient = $patients[$batchDef['patient_indices'][$i]];
                $reportDate = $batchDate->copy()->addMinutes($i * 3);

                // Determine individual report status
                $reportStatus = $batchDef['report_status'];
                if ($batchDef['status'] === 'processing') {
                    $statuses = ['processing', 'uploaded', 'processing'];
                    $reportStatus = $statuses[$i % 3];
                }

                $processingTime = rand(5, 30);
                $isProcessed = in_array($reportStatus, ['processed', 'verified']);
                $isVerified = $reportStatus === 'verified';

                $filename = "LAB_{$patient->patient_id}_{$batchDate->format('Ymd')}.pdf";

                // Build extracted data JSON
                $extractedJson = null;
                if ($isProcessed) {
                    $extractedJson = $this->buildExtractedDataJson($patient, $reportDate);
                }

                $report = LabReport::create([
                    'batch_id' => $batch->id,
                    'patient_id' => $patient->id,
                    'uploaded_by' => $batchDef['uploader']->id,
                    'verified_by' => $isVerified ? $admin->id : null,
                    'original_filename' => $filename,
                    'stored_filename' => "stored_{$filename}",
                    'storage_path' => "reports/batch_{$batch->id}/{$filename}",
                    'file_size' => rand(200000, 800000),
                    'mime_type' => 'application/pdf',
                    'file_hash' => md5("demo_{$batch->id}_{$i}_{$patient->patient_id}_" . uniqid()),
                    'report_date' => $reportDate->toDateString(),
                    'document_type' => 'lab_report',
                    'status' => $reportStatus,
                    'uploaded_at' => $reportDate,
                    'processing_started_at' => $isProcessed ? $reportDate->copy()->addSeconds(2) : null,
                    'processing_completed_at' => $isProcessed ? $reportDate->copy()->addSeconds(2 + $processingTime) : null,
                    'processed_at' => $isProcessed ? $reportDate->copy()->addSeconds(2 + $processingTime) : null,
                    'processing_time' => $isProcessed ? $processingTime : null,
                    'verified_at' => $isVerified ? $reportDate->copy()->addHours(1) : null,
                    'extracted_data' => $extractedJson,
                    'raw_ocr_text' => $isProcessed ? "Simulated OCR text for {$patient->name} report" : null,
                    'created_at' => $reportDate,
                    'updated_at' => $isVerified ? $reportDate->copy()->addHours(1) : $reportDate,
                ]);

                // Create extracted data records for processed/verified reports
                if ($isProcessed) {
                    $this->createExtractedDataRecords($report, $isVerified);
                    $this->createExtractedLabInfo($report, $reportDate);
                }

                if ($isVerified) $verifiedCount++;
                if ($isProcessed && !$isVerified) $processedCount++;
            }

            // Update batch counts
            $batch->update([
                'processed_reports' => $verifiedCount + $processedCount,
                'verified_reports' => $verifiedCount,
                'failed_reports' => 0,
            ]);
        }
    }

    private function buildExtractedDataJson(Patient $patient, Carbon $date): array
    {
        $doctor = $this->doctors[array_rand($this->doctors)];
        $validator = $this->validators[array_rand($this->validators)];

        $testResults = [];
        $categories = array_rand($this->labTests, min(3, count($this->labTests)));
        if (!is_array($categories)) $categories = [$categories];

        foreach ($categories as $catKey) {
            $tests = $this->labTests[$catKey];
            $selectedTests = array_slice($tests, 0, rand(3, min(5, count($tests))));

            foreach ($selectedTests as $test) {
                $result = $this->generateTestResult($test);
                $testResults[] = [
                    'testName' => $test['name'],
                    'result' => $result['value'],
                    'unit' => $test['unit'],
                    'referenceRange' => $test['ref'],
                    'flag' => $result['flag'],
                    'category' => $catKey,
                ];
            }
        }

        return [
            'patientInfo' => [
                'name' => $patient->name,
                'patientId' => $patient->patient_id,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
            ],
            'labInfo' => [
                'labId' => 'LT' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT),
                'requestedBy' => $doctor,
                'requestedDate' => $date->format('d/m/Y H:i'),
                'collectedDate' => $date->copy()->addMinutes(30)->format('d/m/Y H:i'),
                'analysisDate' => $date->copy()->addHours(1)->format('d/m/Y H:i'),
                'validatedBy' => $validator,
            ],
            'testResults' => $testResults,
        ];
    }

    private function createExtractedDataRecords(LabReport $report, bool $isVerified): void
    {
        $categories = array_rand($this->labTests, min(3, count($this->labTests)));
        if (!is_array($categories)) $categories = [$categories];

        foreach ($categories as $catKey) {
            $tests = $this->labTests[$catKey];
            $selectedTests = array_slice($tests, 0, rand(3, min(5, count($tests))));

            foreach ($selectedTests as $test) {
                $result = $this->generateTestResult($test);

                ExtractedData::create([
                    'lab_report_id' => $report->id,
                    'category' => $catKey,
                    'test_name' => $test['name'],
                    'result' => $result['value'],
                    'unit' => $test['unit'],
                    'reference' => $test['ref'],
                    'flag' => $result['flag'],
                    'confidence_score' => $isVerified ? 1.0 : round(rand(85, 99) / 100, 2),
                    'is_verified' => $isVerified,
                ]);
            }
        }
    }

    private function createExtractedLabInfo(LabReport $report, Carbon $date): void
    {
        $doctor = $this->doctors[array_rand($this->doctors)];
        $validator = $this->validators[array_rand($this->validators)];

        ExtractedLabInfo::create([
            'lab_report_id' => $report->id,
            'lab_id' => 'LT' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT),
            'requested_by' => $doctor,
            'requested_date' => $date->format('d/m/Y H:i'),
            'collected_date' => $date->copy()->addMinutes(30)->format('d/m/Y H:i'),
            'analysis_date' => $date->copy()->addHours(1)->format('d/m/Y H:i'),
            'validated_by' => $validator,
        ]);
    }

    private function generateTestResult(array $test): array
    {
        if (isset($test['qualitative']) && $test['qualitative']) {
            $isPositive = rand(1, 10) <= 2;
            return [
                'value' => $isPositive ? 'Reactive' : 'Non-Reactive',
                'flag' => $isPositive ? 'H' : null,
            ];
        }

        $value = round($test['min'] + (mt_rand() / mt_getrandmax()) * ($test['max'] - $test['min']), 1);

        $flag = null;
        if (preg_match('/\((\d+\.?\d*)\s*-\s*(\d+\.?\d*)\)/', $test['ref'], $matches)) {
            $refMin = (float) $matches[1];
            $refMax = (float) $matches[2];
            if ($value > $refMax) $flag = 'H';
            elseif ($value < $refMin) $flag = 'L';
        } elseif (preg_match('/>\s*(\d+\.?\d*)/', $test['ref'], $matches)) {
            if ($value < (float) $matches[1]) $flag = 'L';
        }

        return ['value' => (string) $value, 'flag' => $flag];
    }
}
