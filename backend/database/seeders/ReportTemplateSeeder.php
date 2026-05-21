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
                'is_active' => false,
                'schema' => $this->getSchema(),
                'few_shot_examples' => $this->getFewShotExamples(),
            ]
        );

        ReportTemplate::updateOrCreate(
            ['hospital_code' => 'KVHOSP'],
            [
                'name' => 'KV Hospital',
                'llm_model' => 'phi3:mini',
                'is_active' => true,
                'schema' => $this->getKvHospitalSchema(),
                'few_shot_examples' => $this->getKvHospitalFewShotExamples(),
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

    private function getKvHospitalSchema(): array
    {
        return [
            'document_types' => ['consultation', 'laboratory'],
            'consultation_fields' => [
                'hospital_name' => ['type' => 'string'],
                'document_type' => ['type' => 'string', 'enum' => ['Patient Consultation Information']],
                'physician' => ['type' => 'string', 'description' => 'Doctor name (e.g. Dr. LEANG Choeu)'],
                'evaluation_date' => ['type' => 'string', 'pattern' => 'YYYY-MM-DD HH:MM:SS'],
                'patient_demographics' => [
                    'name_khmer' => ['type' => 'string'],
                    'gender' => ['type' => 'string', 'enum' => ['Male', 'Female']],
                    'payment_type' => ['type' => 'string'],
                    'age' => [
                        'years' => ['type' => 'integer'],
                        'months' => ['type' => 'integer'],
                        'days' => ['type' => 'integer'],
                    ],
                ],
                'vital_signs' => [
                    'systolic_mmhg' => ['type' => 'number'],
                    'diastolic_mmhg' => ['type' => 'number'],
                    'pulse_bpm' => ['type' => 'number'],
                    'respiratory_rate_per_mn' => ['type' => 'number'],
                    'temperature_celsius' => ['type' => 'number'],
                    'oxygen_saturation_percentage' => ['type' => 'number'],
                    'height_cm' => ['type' => 'number'],
                    'weight_kg' => ['type' => 'number'],
                ],
                'clinical_notes' => [
                    'chief_complaint' => ['type' => 'string'],
                    'current_medications' => ['type' => 'string'],
                ],
                'treatment_plan' => [
                    'prescription_id' => ['type' => 'string'],
                    'laboratory_id' => ['type' => 'string'],
                ],
            ],
            'laboratory_fields' => [
                'lab_metadata' => [
                    'lab_id' => ['type' => 'string', 'pattern' => 'LT followed by digits'],
                    'patient_id' => ['type' => 'string', 'pattern' => 'PT followed by digits'],
                    'patient_name' => ['type' => 'string'],
                    'gender' => ['type' => 'string', 'enum' => ['Male', 'Female']],
                    'age' => ['type' => 'string'],
                    'requested_by' => ['type' => 'string'],
                    'collected_date' => ['type' => 'string', 'pattern' => 'DD/MM/YYYY HH:MM'],
                    'analysis_date' => ['type' => 'string', 'pattern' => 'DD/MM/YYYY HH:MM'],
                    'validated_by_technician' => ['type' => 'string'],
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
            ],
            'hospital_phones_to_exclude' => [
                '097 840 47 89',
                '012 89 17 45',
                '012 28 60 70',
            ],
        ];
    }

    private function getKvHospitalFewShotExamples(): array
    {
        return [
            // Consultation example
            [
                'input' => "KV Hospital\nPatient Consultation Information\nPhysician: Dr. LEANG Choeu\nDate: 01/04/2025 16:47:02\nPatient: សុខ សារី\nGender: Female   Payment: Insurance\nAge: 26ឆ្នាំ 3ខែ 0ថ្ងៃ\nVital Signs:\nTension Arterielle: \$120/80\$ mmHg\nPouls: \$80/mn\$\nFR: \$18/mn\$\nTemp: \$36,5^{\\circ}C\$\nSpO2: 98%\nTaille: 165 cm   Poids: 55 kg\nChief Complaint: Headache and dizziness for 3 days\nCurrent Medications: Paracetamol 500mg\nPrescription ID: RX001234\nLaboratory ID: LT001336",
                'output' => [
                    'hospital_name' => 'KV Hospital',
                    'document_type' => 'Patient Consultation Information',
                    'physician' => 'Dr. LEANG Choeu',
                    'evaluation_date' => '2025-04-01 16:47:02',
                    'patient_demographics' => [
                        'name_khmer' => 'សុខ សារី',
                        'gender' => 'Female',
                        'payment_type' => 'Insurance',
                        'age' => ['years' => 26, 'months' => 3, 'days' => 0],
                    ],
                    'vital_signs' => [
                        'systolic_mmhg' => 120,
                        'diastolic_mmhg' => 80,
                        'pulse_bpm' => 80,
                        'respiratory_rate_per_mn' => 18,
                        'temperature_celsius' => 36.5,
                        'oxygen_saturation_percentage' => 98,
                        'height_cm' => 165,
                        'weight_kg' => 55,
                    ],
                    'clinical_notes' => [
                        'chief_complaint' => 'Headache and dizziness for 3 days',
                        'current_medications' => 'Paracetamol 500mg',
                    ],
                    'treatment_plan' => [
                        'prescription_id' => 'RX001234',
                        'laboratory_id' => 'LT001336',
                    ],
                ],
            ],
            // Laboratory example
            [
                'input' => "KV Hospital\nLABORATORY REPORT\n/Name : HONG HOEN    Patient ID : PT001876\n/Age : 38 Y    /Gender : Female\n/Phone : 015624037\nLab ID : LT001239    Requested By : Dr. ENG Hunchhay\nRequested Date : 18/03/2024 08:08    Collected Date : 18/03/2024 08:44\nAnalysis Date : 18/03/2024 08:44\nLab Technician\nSophal Meas\nHEMATOLOGY\nWBC : 9.4    10^9/L    (3.5-10.0)\nLYM% : 39.4    %    (15.0-50.0)\nMONO% : 6.8    %    (2.0-15.0)\nHGB : 12.9    g/dL    (11.5-16.5)\nRBC : 4.83    x1012/L    (3.50-5.50)\nMCV : 75.0    fl    (75.0-100.0)",
                'output' => [
                    'patientInfo' => [
                        'name' => 'HONG HOEN',
                        'patientId' => 'PT001876',
                        'age' => '38 Y',
                        'gender' => 'Female',
                        'phone' => '015624037',
                    ],
                    'labInfo' => [
                        'labId' => 'LT001239',
                        'requestedBy' => 'Dr. ENG Hunchhay',
                        'requestedDate' => '18/03/2024 08:08',
                        'collectedDate' => '18/03/2024 08:44',
                        'analysisDate' => '18/03/2024 08:44',
                        'validatedBy' => 'Sophal Meas',
                    ],
                    'testResults' => [
                        ['testName' => 'WBC', 'result' => '9.4', 'unit' => '10^9/L', 'referenceRange' => '(3.5-10.0)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'LYM%', 'result' => '39.4', 'unit' => '%', 'referenceRange' => '(15.0-50.0)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'MONO%', 'result' => '6.8', 'unit' => '%', 'referenceRange' => '(2.0-15.0)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'HGB', 'result' => '12.9', 'unit' => 'g/dL', 'referenceRange' => '(11.5-16.5)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'RBC', 'result' => '4.83', 'unit' => 'x1012/L', 'referenceRange' => '(3.50-5.50)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                        ['testName' => 'MCV', 'result' => '75.0', 'unit' => 'fl', 'referenceRange' => '(75.0-100.0)', 'flag' => null, 'category' => 'HEMATOLOGY'],
                    ],
                ],
            ],
        ];
    }
}
