<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class ReportTemplateSeeder extends Seeder
{
    /**
     * Seed the default report template for the primary hospital.
     *
     * This template contains:
     * - The expected data schema (fields, categories, flag enum)
     * - 2 complete few-shot examples (real OCR text → structured JSON)
     * - The LLM model to use (phi3:mini for 6GB VRAM GPU)
     */
    public function run(): void
    {
        ReportTemplate::updateOrCreate(
            ['hospital_code' => 'PPGH'],
            [
                'name' => 'Phnom Penh General Hospital',
                'llm_model' => 'phi3:mini',
                'is_active' => true,
                'schema' => $this->getSchema(),
                'few_shot_examples' => $this->getFewShotExamples(),
            ]
        );
    }

    private function getSchema(): array
    {
        return [
            'patient_fields' => [
                'name' => ['type' => 'string', 'description' => 'Patient full name in English (e.g. HENG VANNAT)'],
                'patientId' => ['type' => 'string', 'pattern' => 'PT followed by digits (e.g. PT00139)'],
                'age' => ['type' => 'string', 'pattern' => 'e.g. 38 Y, 0 M, 0 D'],
                'gender' => ['type' => 'string', 'enum' => ['Male', 'Female']],
                'phone' => ['type' => 'string', 'pattern' => '0 followed by 8-9 digits (patient mobile, NOT hospital phone)'],
            ],
            'lab_fields' => [
                'labId' => ['type' => 'string', 'pattern' => 'LT followed by digits (e.g. LT00001)'],
                'requestedBy' => ['type' => 'string', 'description' => 'Doctor name (e.g. Dr. Sok Chheng)'],
                'requestedDate' => ['type' => 'string', 'pattern' => 'DD/MM/YYYY HH:MM'],
                'collectedDate' => ['type' => 'string', 'pattern' => 'DD/MM/YYYY HH:MM'],
                'analysisDate' => ['type' => 'string', 'pattern' => 'DD/MM/YYYY HH:MM'],
                'validatedBy' => ['type' => 'string', 'description' => 'Lab technician name'],
            ],
            'test_categories' => [
                'BIOCHEMISTRY',
                'ENZYMOLOGY',
                'HEMATOLOGY',
                'SERO/IMMUNOLOGY',
                'URINE ANALYSIS',
                'DRUG URINE',
                'BLOOD GROUP',
            ],
            'flag_enum' => ['H', 'L'],
            'test_result_schema' => [
                'testName' => 'string (exact test name)',
                'result' => 'string (numeric value or NEGATIVE/POSITIVE)',
                'unit' => 'string or null',
                'referenceRange' => 'string or null (e.g. "(0.9 - 1.1)")',
                'flag' => 'H, L, or null — H means High (above range), L means Low (below range)',
                'category' => 'string (one of test_categories)',
            ],
            'hospital_phones_to_exclude' => [
                '097 840 47 89',
                '012 89 17 45',
                '012 28 60 70',
            ],
        ];
    }

    private function getFewShotExamples(): array
    {
        return [
            [
                'input' => "មន្ទី រព្រទ្យទួលកក\nPHNOM PENH GENERAL HOSPITAL\n097 840 47 89    012 89 17 45    012 28 60 70\nកម់:/Name\n: HENG VANNAT    Patient ID    : PT00139\nអាយ:/Age\n: 38 Y, 0 M, 0 D     isEmpty:/Gender    : Male\nទូរស:/Phone\n: 098765432\nLab ID    : LT00001    Requested By    : Dr. Sok Chheng\nRequested Date    : 15/03/2025 08:30    Collected Date    : 15/03/2025 09:00\nAnalysis Date    : 15/03/2025 10:30\nLab Technician\nSophal Meas\nLABORATORY REPORT\nBIOCHIMISTRY\nResults    Unit    Reference Range    Flag\nCreatinine, serum    0.9    mg/dL    (0.9 - 1.1)\nUrea/BUN    27    mg/dL    (6.0 - 40.0)\nGlucose    110    mg/dL    (70 - 100)    H\nCholesterole Total    197    mg/dL    (0-200)\nCholesterol-HDL    50    mg/dL    (>60)    L",
                'output' => [
                    'patientInfo' => [
                        'name' => 'HENG VANNAT',
                        'patientId' => 'PT00139',
                        'age' => '38 Y, 0 M, 0 D',
                        'gender' => 'Male',
                        'phone' => '098765432',
                    ],
                    'labInfo' => [
                        'labId' => 'LT00001',
                        'requestedBy' => 'Dr. Sok Chheng',
                        'requestedDate' => '15/03/2025 08:30',
                        'collectedDate' => '15/03/2025 09:00',
                        'analysisDate' => '15/03/2025 10:30',
                        'validatedBy' => 'Sophal Meas',
                    ],
                    'testResults' => [
                        ['testName' => 'Creatinine, serum', 'result' => '0.9', 'unit' => 'mg/dL', 'referenceRange' => '(0.9 - 1.1)', 'flag' => null, 'category' => 'BIOCHEMISTRY'],
                        ['testName' => 'Urea/BUN', 'result' => '27', 'unit' => 'mg/dL', 'referenceRange' => '(6.0 - 40.0)', 'flag' => null, 'category' => 'BIOCHEMISTRY'],
                        ['testName' => 'Glucose', 'result' => '110', 'unit' => 'mg/dL', 'referenceRange' => '(70 - 100)', 'flag' => 'H', 'category' => 'BIOCHEMISTRY'],
                        ['testName' => 'Cholesterole Total', 'result' => '197', 'unit' => 'mg/dL', 'referenceRange' => '(0-200)', 'flag' => null, 'category' => 'BIOCHEMISTRY'],
                        ['testName' => 'Cholesterol-HDL', 'result' => '50', 'unit' => 'mg/dL', 'referenceRange' => '(>60)', 'flag' => 'L', 'category' => 'BIOCHEMISTRY'],
                    ],
                ],
            ],
            [
                'input' => "PHNOM PENH GENERAL HOSPITAL\n/Name    : CHAN DARA    Patient ID    : PT00245\n/Age    : 25 Y, 0 M, 0 D    /Gender    : Female\n/Phone    : 012345678\nLab ID    : LT00078    Requested By    : Dr. Kim Sothea\nRequested Date    : 20/04/2025 07:45    Collected Date    : 20/04/2025 08:15\nAnalysis Date    : 20/04/2025 09:30\nValidated By\nChea Vanny\nLABORATORY REPORT\nHEMATOLOGY\nResults    Unit    Reference Range    Flag\nWBC    11.5    10^9/L    (3.5-10.0)    H\nRBC    4.68    x1012/L    (3.50-5.50)\nHGB    13.6    g/dL    (11.5-16.5)\nDRUG URINE\nMorphine    NEGATIVE\nAmphetamine    NEGATIVE\nMetamphetamine    NEGATIVE",
                'output' => [
                    'patientInfo' => [
                        'name' => 'CHAN DARA',
                        'patientId' => 'PT00245',
                        'age' => '25 Y, 0 M, 0 D',
                        'gender' => 'Female',
                        'phone' => '012345678',
                    ],
                    'labInfo' => [
                        'labId' => 'LT00078',
                        'requestedBy' => 'Dr. Kim Sothea',
                        'requestedDate' => '20/04/2025 07:45',
                        'collectedDate' => '20/04/2025 08:15',
                        'analysisDate' => '20/04/2025 09:30',
                        'validatedBy' => 'Chea Vanny',
                    ],
                    'testResults' => [
                        ['testName' => 'WBC', 'result' => '11.5', 'unit' => '10^9/L', 'referenceRange' => '(3.5-10.0)', 'flag' => 'H', 'category' => 'HEMATOLOGY'],
                        ['testName' => 'RBC', 'result' => '4.68', 'unit' => 'x1012/L', 'referenceRange' => '(3.50-5.50)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'HGB', 'result' => '13.6', 'unit' => 'g/dL', 'referenceRange' => '(11.5-16.5)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'Morphine', 'result' => 'NEGATIVE', 'unit' => null, 'referenceRange' => null, 'flag' => null, 'category' => 'DRUG URINE'],
                        ['testName' => 'Amphetamine', 'result' => 'NEGATIVE', 'unit' => null, 'referenceRange' => null, 'flag' => null, 'category' => 'DRUG URINE'],
                        ['testName' => 'Metamphetamine', 'result' => 'NEGATIVE', 'unit' => null, 'referenceRange' => null, 'flag' => null, 'category' => 'DRUG URINE'],
                    ],
                ],
            ],
        ];
    }
}
