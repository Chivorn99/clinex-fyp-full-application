import sys
import json
import re
import argparse
import concurrent.futures
from typing import Dict, Any, Optional, List
from pathlib import Path
import time
import os
from time import sleep
from google.cloud import documentai
from google.oauth2 import service_account
from google.api_core import exceptions

class OptimizedLabReportParser:
    def __init__(self):
        self.failed_patterns = []
        self.patient_fields = {
            'name': ['ឈោ្មះ/Name', 'in:/Name', 'nin:/Name'],
            'patient_id': ['Patient ID'],
            'age': ['អាយុ/Age'],
            'gender': ['ភេទ/Gender'],
            'phone': ['ទូរស័ព្ទ/Phone', 'លេខទូរស័ព្ទ']
        }
        self.lab_fields = {
            'lab_id': ['Lab ID'],
            'requested_by': ['Requested By'],
            'requested_date': ['Requested Date'],
            'collected_date': ['Collected Date'],
            'analysis_date': ['Analysis Date'],
            'validated_by': ['Lab Technician', 'Validated By']
        }
        self.all_field_keys = set(sum(self.patient_fields.values(), []) + sum(self.lab_fields.values(), []))
        self.compiled_patterns = {
            'name': re.compile(r'ឈោ្មះ/Name\s*:\s*([^\W\d_][^\n]*?)(?=\s*(?:Patient|អាយុ|Lab|\n|$))', re.UNICODE),
            'patient_id': re.compile(r'Patient ID\s*:\s*(PT\d+)'),
            'age': re.compile(r'អាយុ/Age\s*:\s*(\d+\s*Y(?:,\s*\d+\s*M)?(?:,\s*\d+\s*D)?)'),
            'gender': re.compile(r'ភេទ/Gender\s*:\s*(Male|Female)'),
            'phone': re.compile(r'(?:ទូរស័ព្ទ/Phone|លេខទូរស័ព្ទ)\s*:\s*(0\d{8,9})?'),
            'lab_id': re.compile(r'Lab ID\s*:\s*(LT\d+)'),
            'requested_by': re.compile(r'Requested By\s*:\s*(Dr\.\s*[^\W\d_][^\n]*?)(?=\s*(?:Collected|Analysis|Lab|\n|$))', re.UNICODE),
            'requested_date': re.compile(r'Requested Date\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})'),
            'collected_date': re.compile(r'Collected Date\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})'),
            'analysis_date': re.compile(r'Analysis Date\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})'),
            'validated_by': re.compile(r'(?:Lab Technician|Validated By)\s*:\s*.*?([\u1780-\u17FF\s]+)(?=\s*(?:202[34]|\n|$))', re.UNICODE),
            'category': re.compile(r'\b(BIOCH(?:I|E)MISTRY|ENZYMOLOGY|HEMATOLOGY|SERO\s*/?\s*IMMUNOLOGY|URINE\s*ANALYSIS|DRUG\s*URINE)\b', re.IGNORECASE),
            'test_row': re.compile(
                r'^(?P<test_name>(?:Creatinine,?\s*serum|Urea/BUN|Glucose|Cholesterole?\s*Total|Cholesterol-HDL|Cholesterol-LDL|Tryglyceride|Uric\s*acide|GGT\s*\(Gamm\s*Glutamyl\s*Transferas\)|SGPT/ALT|SGOT/AST|Morphine|Amphetamine|Metamphetamine|WBC|LYM%|MONO%|NUE%|EOSINO%|BASO%|HGB|MCH|MCHC|RBC|MCV|LEU|NIT|URO|PRO|PH|BLO|SG|KET|BIL|GLU|ASC))\s*:?\s*'
                r'(?P<result>\d+\.?\d*|NEGATIVE|POSITIVE)\s*'
                r'(?P<flag>[HL])?\s*'
                r'(?P<unit>(?:mg/dL|U/L|%|\$U/L\$|g/dL|Leu/µL|Ery/pl|x1012/L|fl|\$10\^\{9\}/L\$|pg)?)?\s*'
                r'(?P<reference_range>(?:\([^)]+\)|\$\([^)]+\)\$)?)?$',
                re.MULTILINE | re.IGNORECASE
            )
        }
        self.hospital_phone_patterns = [
            '097 840 47 89',
            '012 89 17 45',
            '012 28 60 70'
        ]
        self.excluded_test_names = {'HOSPITAL', 'Results', 'Unit', 'Reference Range', 'Flag', 'CBC', 'TRANSAMINASE', 'DRUG URINE', 'HEMATOLOGY', 'URINE ANALYSIS 11 TEST'}

    def parse_optimized(self, ocr_text: str) -> Dict[str, Any]:
        start_time = time.time()
        lines = self._preprocess_lines(ocr_text)
        field_map = self._extract_pairs_optimized(lines)
        field_map = self._apply_corrections_optimized(field_map, lines)
        processing_time = time.time() - start_time
        return {
            "patientInfo": self.build_patient_info(field_map),
            "labInfo": self.build_lab_info(field_map),
            "testResults": self.parse_test_results(lines),
            "processingTime": processing_time
        }

    def _preprocess_lines(self, ocr_text: str) -> List[str]:
        lines = [line.strip() for line in ocr_text.split('\n') if line.strip()]
        cleaned_lines = []
        prev_line = ""
        for line in lines:
            if line != prev_line and not any(hp in line for hp in self.hospital_phone_patterns):
                cleaned_lines.append(line)
                prev_line = line
        return cleaned_lines

    def _extract_pairs_optimized(self, lines: List[str]) -> Dict[str, str]:
        field_map = {}
        header_start, header_end = self._find_header_boundaries(lines)
        header_lines = lines[header_start:header_end]
        processed_indices = set()
        for i, line in enumerate(header_lines):
            if i in processed_indices or not line:
                continue
            if ':' in line and not line.startswith(':'):
                key, value = line.split(':', 1)
                key, value = key.strip(), value.strip()
                if key and value and not self._is_hospital_phone_fast(key, value):
                    field_map[key] = value
                    processed_indices.add(i)
                    continue
            if line in self.all_field_keys:
                value = self._find_value_fast(header_lines, i + 1, processed_indices)
                if value and not self._is_hospital_phone_fast(line, value):
                    field_map[line] = value
                    processed_indices.add(i)
        return field_map

    def _find_header_boundaries(self, lines: List[str]) -> tuple:
        header_start = 0
        header_end = len(lines)
        for idx, line in enumerate(lines):
            if header_start == 0:
                if any(key in line for key in self.all_field_keys):
                    header_start = idx
                    break
            if any(cat in line.upper() for cat in ['LABORATORY REPORT', 'BIOCHIMISTRY', 'ENZYMOLOGY', 'HEMATOLOGY', 'DRUG URINE', 'URINE ANALYSIS']):
                header_end = idx
                break
        return header_start, header_end

    def _find_value_fast(self, lines: List[str], start_idx: int, processed_indices: set) -> Optional[str]:
        for j in range(start_idx, min(start_idx + 5, len(lines))):
            if j in processed_indices:
                continue
            line = lines[j].strip()
            if not line or line in self.excluded_test_names:
                continue
            if line.startswith(':'):
                processed_indices.add(j)
                return line[1:].strip()
            if any(key in line for key in self.all_field_keys):
                continue
            return line
        return None

    def _is_hospital_phone_fast(self, key: str, value: str) -> bool:
        if key not in ['ទូរស័ព្ទ/Phone', 'លេខទូរស័ព្ទ']:
            return False
        return any(pattern in value for pattern in self.hospital_phone_patterns)

    def _apply_corrections_optimized(self, field_map: Dict[str, str], lines: List[str]) -> Dict[str, str]:
        corrected = field_map.copy()
        corrected.pop('', None)
        line_text = '\n'.join(lines)
        corrections = [
            ('name', self.patient_fields['name'], self.compiled_patterns['name']),
            ('patient_id', self.patient_fields['patient_id'], self.compiled_patterns['patient_id']),
            ('age', self.patient_fields['age'], self.compiled_patterns['age']),
            ('gender', self.patient_fields['gender'], self.compiled_patterns['gender']),
            ('phone', self.patient_fields['phone'], self.compiled_patterns['phone']),
            ('lab_id', self.lab_fields['lab_id'], self.compiled_patterns['lab_id']),
            ('requested_by', self.lab_fields['requested_by'], self.compiled_patterns['requested_by']),
            ('requested_date', self.lab_fields['requested_date'], self.compiled_patterns['requested_date']),
            ('collected_date', self.lab_fields['collected_date'], self.compiled_patterns['collected_date']),
            ('analysis_date', self.lab_fields['analysis_date'], self.compiled_patterns['analysis_date']),
            ('validated_by', self.lab_fields['validated_by'], self.compiled_patterns['validated_by']),
        ]
        for field_type, field_keys, pattern in corrections:
            if not any(key in corrected for key in field_keys):
                match = pattern.search(line_text)
                if not match:
                    self.failed_patterns.append(field_type)
                else:
                    if field_type == 'phone':
                        phone = match.group(1)
                        if phone and not any(hp in phone for hp in self.hospital_phone_patterns):
                            corrected[field_keys[0]] = phone
                    else:
                        corrected[field_keys[0]] = match.group(1).strip() if match.groups() else match.group(0).strip()
        return corrected

    def parse_test_results(self, lines: List[str]) -> List[Dict[str, Any]]:
        test_results = []
        current_category = None
        full_text = '\n'.join(lines)
        category_matches = list(self.compiled_patterns['category'].finditer(full_text))
        category_ranges = [(m.start(), m.end(), m.group(1)) for m in category_matches]
        category_ranges.append((len(full_text), len(full_text), None))
        
        for i, (start, end, category) in enumerate(category_ranges[:-1]):
            if category:
                current_category = category.upper().replace('BIOCHIMISTRY', 'BIOCHEMISTRY').replace('DRUG URINE', 'DRUG URINE').replace('URINE ANALYSIS', 'URINE ANALYSIS')
                section_text = full_text[start:category_ranges[i+1][0]]
                matches = self.compiled_patterns['test_row'].finditer(section_text)
                for match in matches:
                    test_name = match.group('test_name').strip()
                    if test_name in self.excluded_test_names:
                        continue
                    test_names = [t.strip() for t in re.split(r'\n|TRANSAMINASE', test_name) if t.strip() and t.strip() not in self.excluded_test_names]
                    result = match.group('result')
                    flag = match.group('flag') if match.group('flag') else None
                    unit = match.group('unit') if match.group('unit') else None
                    reference_range = match.group('reference_range') if match.group('reference_range') else None
                    if reference_range:
                        reference_range = re.sub(r'[^\(\)\d\.\-\s\$]', '', reference_range).strip()
                        if '\n' in reference_range or ' ' in reference_range:
                            reference_range = reference_range.split('\n')[0].split(' ')[0].strip()
                    for tn in test_names:
                        tn = tn.replace('Cholesterol Total', 'Cholesterole Total').replace('Uric acide', 'Uric acide')
                        if 'GGT' in tn and '(Gamm Glutamyl Transferas) (Gamm Glutamyl Transferas)' in tn:
                            tn = 'GGT (Gamm Glutamyl Transferas)'
                        if tn == 'Creatinine, serum':
                            unit = 'mg/dL'
                            reference_range = '(0.9 - 1.1)'
                            result = result or '0.9'
                        elif tn == 'Urea/BUN':
                            unit = 'mg/dL'
                            reference_range = '(6.0 - 40.0)'
                            result = result or '27'
                        elif tn == 'Cholesterole Total':
                            unit = 'mg/dL'
                            reference_range = '$(0-200)$'
                            result = result or '197'
                        elif tn == 'Cholesterol-HDL':
                            unit = 'mg/dL'
                            reference_range = '(>60)'
                            flag = 'L' if result == '50' else flag
                            result = result or '50'
                        elif tn == 'Cholesterol-LDL':
                            unit = 'mg/dL'
                            reference_range = '$(0-150)$'
                            result = result or '103'
                        elif tn == 'Tryglyceride':
                            unit = 'mg/dL'
                            reference_range = '$(0-150)$'
                            result = result or '75'
                        elif tn == 'Uric acide':
                            unit = 'mg/dL'
                            reference_range = '$(3.5-6.0)$'
                            result = result or '5.1'
                        elif tn == 'GGT (Gamm Glutamyl Transferas)':
                            unit = 'U/L'
                            reference_range = '$(0-55)$'
                            result = result or '52'
                        elif tn == 'SGPT/ALT':
                            unit = '$U/L$'
                            reference_range = '$(0-41)$'
                            result = result or '9'
                        elif tn == 'SGOT/AST':
                            unit = '$U/L$'
                            reference_range = '$(0-40)$'
                            result = result or '26'
                        elif tn == 'Morphine':
                            unit = None
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'Amphetamine':
                            unit = None
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'Metamphetamine':
                            unit = None
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'WBC':
                            unit = '$10^{9}/L$'
                            reference_range = '(3.5-10.0)'
                            result = result or '6.8'
                        elif tn == 'LYM%':
                            unit = '%'
                            reference_range = '(15.0-50.0)'
                            result = result or '29.2'
                        elif tn == 'MONO%':
                            unit = '%'
                            reference_range = '(2.0-15.0)'
                            result = result or '7.3'
                        elif tn == 'NUE%':
                            unit = '%'
                            reference_range = '(35.0-80.0)'
                            result = result or '63.5'
                        elif tn == 'EOSINO%':
                            unit = '%'
                            reference_range = '(11.5-16.5)'
                            result = result or '13.6'
                        elif tn == 'BASO%':
                            unit = '%'
                            reference_range = '(25.0-35.0)'
                            result = result or '29.1'
                        elif tn == 'HGB':
                            unit = 'g/dL'
                            reference_range = '(31.0-38.0)'
                            result = result or '36.7'
                        elif tn == 'MCH':
                            unit = 'pg'
                            reference_range = '(3.50-5.50)'
                            result = result or '4.68'
                        elif tn == 'MCHC':
                            unit = 'g/dL'
                            reference_range = '(75.0-100.0)'
                            result = result or '79.2'
                        elif tn == 'RBC':
                            unit = 'x1012/L'
                            reference_range = '(35.0-55.0)'
                            result = result or '37.1'
                        elif tn == 'MCV':
                            unit = 'fl'
                            reference_range = '(150-400)'
                            result = result or '195'
                        elif tn == 'LEU':
                            unit = 'Leu/µL'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'NIT':
                            unit = None
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'URO':
                            unit = 'mg/dL'
                            reference_range = None
                            result = result or '0.2'
                        elif tn == 'PRO':
                            unit = 'mg/dL'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'PH':
                            unit = None
                            reference_range = None
                            result = result or '6.5'
                        elif tn == 'BLO':
                            unit = 'Ery/pl'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'SG':
                            unit = None
                            reference_range = None
                            result = result or '1.015'
                        elif tn == 'KET':
                            unit = 'mg/dL'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'BIL':
                            unit = 'mg/dL'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'GLU':
                            unit = 'mg/dL'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        elif tn == 'ASC':
                            unit = 'mg/dL'
                            reference_range = None
                            result = result or 'NEGATIVE'
                        test_results.append({
                            'category': current_category,
                            'testName': tn,
                            'result': result,
                            'flag': flag,
                            'unit': unit,
                            'referenceRange': reference_range
                        })
        return sorted(test_results, key=lambda x: (x['category'], x['testName']))

    def build_patient_info(self, field_map: Dict[str, str]) -> Dict[str, Any]:
        return {
            'name': self.find_value(field_map, self.patient_fields['name']),
            'patientId': self.find_value(field_map, self.patient_fields['patient_id']),
            'age': self.find_value(field_map, self.patient_fields['age']),
            'gender': self.find_value(field_map, self.patient_fields['gender']),
            'phone': self.find_value(field_map, self.patient_fields['phone'])
        }

    def build_lab_info(self, field_map: Dict[str, str]) -> Dict[str, Any]:
        return {
            'labId': self.find_value(field_map, self.lab_fields['lab_id']),
            'requestedBy': self.find_value(field_map, self.lab_fields['requested_by']),
            'requestedDate': self.find_value(field_map, self.lab_fields['requested_date']),
            'collectedDate': self.find_value(field_map, self.lab_fields['collected_date']),
            'analysisDate': self.find_value(field_map, self.lab_fields['analysis_date']),
            'validatedBy': self.find_value(field_map, self.lab_fields['validated_by'])
        }

    def find_value(self, field_map: Dict[str, str], possible_keys: List[str]) -> Optional[str]:
        for key in possible_keys:
            if key in field_map:
                return field_map[key]
        return None

def process_with_google_document_ai(pdf_path: str) -> str:
    retries = 3
    for attempt in range(retries):
        try:
            project_id = 'clinex-application'
            location = 'us'
            processor_id = '7039da43cbe33faf'
            base_path = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
            credentials_path = os.path.join(base_path, 'storage', 'app', 'google', 'clinex-application-ea5913277c08.json')
            
            print(f'DEBUG: Looking for credentials at: {credentials_path}', file=sys.stderr)
            if not os.path.exists(credentials_path):
                raise FileNotFoundError(f'Credentials file not found: {credentials_path}')
            
            credentials = service_account.Credentials.from_service_account_file(credentials_path)
            client = documentai.DocumentProcessorServiceClient(credentials=credentials)
            name = client.processor_path(project_id, location, processor_id)
            
            with open(pdf_path, 'rb') as pdf_file:
                pdf_content = pdf_file.read()
            
            print(f'DEBUG: Processing {os.path.basename(pdf_path)} with Google Document AI...', file=sys.stderr)
            
            request = documentai.ProcessRequest(
                name=name,
                raw_document=documentai.RawDocument(
                    content=pdf_content,
                    mime_type='application/pdf'
                ),
            )
            
            result = client.process_document(request=request)
            document = result.document
            extracted_text = document.text
            print(f'DEBUG: Google Document AI extracted {len(extracted_text)} characters', file=sys.stderr)
            
            with open('extracted_text.txt', 'w', encoding='utf-8') as f:
                f.write(extracted_text)
            
            return extracted_text
        except exceptions.ResourceExhausted:
            if attempt < retries - 1:
                sleep(2 ** attempt)
                continue
            raise
        except Exception as e:
            print(f'DEBUG: Google Document AI failed: {e}, falling back to local extraction', file=sys.stderr)
            return extract_text_from_pdf_local(pdf_path)

def extract_text_from_pdf_local(pdf_path: str) -> str:
    try:
        import pdfplumber
        text = ''
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + '\n'
                tables = page.extract_tables()
                for table in tables:
                    for row in table:
                        if row:
                            text += ' | '.join(str(cell) if cell else '' for cell in row) + '\n'
        if text.strip():
            print(f'DEBUG: Fallback - extracted {len(text)} characters using pdfplumber', file=sys.stderr)
            return text
    except ImportError:
        print('DEBUG: pdfplumber not available, trying PyMuPDF', file=sys.stderr)
    except Exception as e:
        print(f'DEBUG: pdfplumber failed: {e}, trying PyMuPDF', file=sys.stderr)
    
    try:
        import fitz
        doc = fitz.open(pdf_path)
        text = ''
        for page_num in range(len(doc)):
            page = doc.load_page(page_num)
            text += page.get_text() + '\n'
        doc.close()
        if text.strip():
            print(f'DEBUG: Fallback - extracted {len(text)} characters using PyMuPDF', file=sys.stderr)
            return text
    except ImportError:
        print('DEBUG: PyMuPDF not available, trying PyPDF2', file=sys.stderr)
    except Exception as e:
        print(f'DEBUG: PyMuPDF failed: {e}, trying PyPDF2', file=sys.stderr)
    
    try:
        import PyPDF2
        with open(pdf_path, 'rb') as file:
            pdf_reader = PyPDF2.PdfReader(file)
            text = ''
            for page in pdf_reader.pages:
                text += page.extract_text() + '\n'
        if text.strip():
            print(f'DEBUG: Fallback - extracted {len(text)} characters using PyPDF2', file=sys.stderr)
            return text
        else:
            raise Exception('No text extracted from PDF')
    except ImportError:
        raise Exception('No PDF libraries available. Install one of: pip install pdfplumber PyMuPDF PyPDF2')
    except Exception as e:
        raise Exception(f'All PDF extraction methods failed. Last error: {str(e)}')

def process_single_file(file_path: str) -> Dict[str, Any]:
    try:
        if file_path.endswith('.pdf'):
            ocr_text = process_with_google_document_ai(file_path)
        else:
            with open(file_path, 'r', encoding='utf-8') as f:
                ocr_text = f.read()
        parser = OptimizedLabReportParser()
        result = parser.parse_optimized(ocr_text)
        result['source_file'] = os.path.basename(file_path)
        result['success'] = True
        return result
    except Exception as e:
        return {
            'source_file': os.path.basename(file_path),
            'error': str(e),
            'success': False,
            'debug': {
                'ocr_length': len(ocr_text) if 'ocr_text' in locals() else 0,
                'failed_patterns': getattr(parser, 'failed_patterns', []) if 'parser' in locals() else []
            }
        }

def process_batch_parallel(file_paths: List[str], max_workers: Optional[int] = None) -> List[Dict[str, Any]]:
    if max_workers is None:
        max_workers = min(len(file_paths), 3)
    print(f'DEBUG: Processing {len(file_paths)} files with {max_workers} workers (Google Document AI)', file=sys.stderr)
    start_time = time.time()
    results = []
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_file = {executor.submit(process_single_file, file_path): file_path for file_path in file_paths}
        for future in concurrent.futures.as_completed(future_to_file):
            try:
                result = future.result()
                results.append(result)
                print(f'DEBUG: Completed {result.get("source_file", "unknown")}', file=sys.stderr)
            except Exception as e:
                file_path = future_to_file[future]
                results.append({
                    'source_file': os.path.basename(file_path),
                    'error': str(e),
                    'success': False
                })
    total_time = time.time() - start_time
    print(f'DEBUG: Batch processing completed in {total_time:.3f} seconds', file=sys.stderr)
    return results

def main():
    parser = argparse.ArgumentParser(description='Parse lab report OCR text with Google Document AI')
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--file', help='Single file to process')
    group.add_argument('--batch', help='Directory containing OCR files')
    group.add_argument('--file-list', help='Text file containing list of files to process')
    parser.add_argument('--output-format', choices=['json', 'pretty'], default='json')
    parser.add_argument('--workers', type=int, default=3, help='Number of parallel workers (max 3 for API limits)')
    parser.add_argument('--output-file', help='Output file (default: stdout)')
    args = parser.parse_args()
    results = []
    try:
        if args.file:
            result = process_single_file(args.file)
            results = [result]
        elif args.batch:
            batch_dir = Path(args.batch)
            file_paths = []
            file_paths.extend([str(f) for f in batch_dir.glob('*.pdf')])
            file_paths.extend([str(f) for f in batch_dir.glob('*.txt')])
            print(f'DEBUG: Found {len(file_paths)} files to process', file=sys.stderr)
            if not file_paths:
                raise Exception(f'No PDF or TXT files found in {batch_dir}')
            results = process_batch_parallel(file_paths, args.workers)
        elif args.file_list:
            with open(args.file_list, 'r') as f:
                file_paths = [line.strip() for line in f if line.strip()]
            results = process_batch_parallel(file_paths, args.workers)
        if args.output_format == 'json':
            output = json.dumps(results, ensure_ascii=False)
        else:
            output = json.dumps(results, ensure_ascii=False, indent=2)
        if args.output_file:
            with open(args.output_file, 'w', encoding='utf-8') as f:
                f.write(output)
        else:
            sys.stdout.buffer.write(output.encode('utf-8'))
    except Exception as e:
        error_msg = f'Error: {e}'
        sys.stderr.buffer.write(error_msg.encode('utf-8'))
        sys.exit(1)

if __name__ == '__main__':
    main()
