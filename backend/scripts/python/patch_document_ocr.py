import sys
import re

with open('document_ocr.py', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update _build_llm_system_prompt
old_build_llm_prompt = """    return (
        "You are a medical lab report data extractor. "
        "Extract structured JSON from raw OCR text of Cambodian hospital lab reports.\n\n"
        "STRICT RULES:\n"
        f"1. Flag values MUST be exactly one of {flag_enum} or null. No other values allowed.\n"
        "2. All dates must be in DD/MM/YYYY HH:MM format exactly as they appear.\n"
        "3. Patient ID format: PT followed by digits (e.g. PT00139).\n"
        "4. Lab ID format: LT followed by digits (e.g. LT00001).\n"
        "5. Patient name must be in English uppercase (e.g. HENG VANNAT).\n"
        "6. Gender must be exactly 'Male' or 'Female'.\n"
        f"7. Test categories must be one of: {categories}\n"
        f"8. Ignore hospital phone numbers: {hospital_phones}\n"
        "9. The OCR text may contain garbled Khmer Unicode characters before English labels "
        "(e.g. 'កម់:/Name' means 'Name'). Extract the English value after the label.\n"
        "10. If a value cannot be determined from the text, use null.\n"
        "11. For test results, extract the numeric value or NEGATIVE/POSITIVE exactly as shown.\n"
        "12. 'BIOCHIMISTRY' is a misspelling of 'BIOCHEMISTRY' — normalize to 'BIOCHEMISTRY'.\n"
    )"""

new_build_llm_prompt = """    return (
        "You are a medical lab report data extractor. "
        "Extract structured JSON from raw OCR text of Cambodian hospital lab reports.\n\n"
        "STRICT RULES:\n"
        f"1. Flag values MUST be exactly one of {flag_enum} or null. No other values allowed.\n"
        "2. All dates must be in DD/MM/YYYY HH:MM format exactly as they appear.\n"
        "3. Patient ID format: PT followed by digits (e.g. PT00139).\n"
        "4. Lab ID format: LT followed by digits (e.g. LT00001).\n"
        "5. Patient name must be in English uppercase (e.g. HENG VANNAT).\n"
        "6. Gender must be exactly 'Male' or 'Female'.\n"
        f"7. Test results MUST be grouped under 'test_results' by department panel (e.g., biochemistry, hematology).\n"
        f"8. Ignore hospital phone numbers: {hospital_phones}\n"
        "9. The OCR text may contain garbled Khmer Unicode characters before English labels.\n"
        "10. If a value cannot be determined from the text, use null.\n"
        "11. For test results, extract the numeric value or NEGATIVE/POSITIVE exactly as shown.\n"
        "12. Normalize garbled unit characters (e.g., '응' -> '%').\n"
        "13. Ensure multi-page reports are combined into the same JSON structure.\n"
    )"""

content = content.replace(old_build_llm_prompt, new_build_llm_prompt)

# 2. Update _get_ollama_json_schema
old_schema = """            "testResults": {
                "type": "array",
                "items": test_result_schema
            }
        },
        "required": ["patientInfo", "labInfo", "testResults"]"""

new_schema = """            "test_results": {
                "type": "object",
                "properties": {
                    "biochemistry": {"type": "array", "items": test_result_schema},
                    "enzymology": {"type": "array", "items": test_result_schema},
                    "hematology": {"type": "array", "items": test_result_schema},
                    "urine_analysis": {"type": "array", "items": test_result_schema},
                    "drug_urine": {"type": "array", "items": test_result_schema},
                    "blood_group": {"type": "array", "items": test_result_schema}
                }
            }
        },
        "required": ["patientInfo", "labInfo", "test_results"]"""

content = content.replace(old_schema, new_schema)

# 3. Add _build_consultation_system_prompt and _get_consultation_json_schema and detect_document_type
consultation_funcs = """def detect_document_type(ocr_text: str) -> str:
    \"\"\"Detect whether OCR text is a consultation form or lab report.\"\"\"
    text_upper = ocr_text.upper()
    consultation_markers = ['PATIENT CONSULTATION', 'VITAL SIGNS', 'CHIEF COMPLAINT',
                           'TREATMENT PLAN', 'PRESCRIPTION', 'TENSION ARTERIELLE']
    lab_markers = ['LABORATORY REPORT', 'HEMATOLOGY', 'BIOCHEMISTRY', 'BIOCHIMISTRY',
                   'ENZYMOLOGY', 'URINE ANALYSIS', 'DRUG URINE', 'Lab ID']
    
    consult_score = sum(1 for m in consultation_markers if m in text_upper)
    lab_score = sum(1 for m in lab_markers if m in text_upper)
    
    return 'consultation' if consult_score > lab_score else 'laboratory'

def _build_consultation_system_prompt(template: Dict[str, Any]) -> str:
    return (
        "You are a medical data extractor for hospital consultation forms.\\n\\n"
        "STRICT RULES:\\n"
        "1. Extract patient demographics, normalizing age to years/months/days integers.\\n"
        "2. Strip LaTeX notation from vital signs (e.g. $36,5^{\\\\circ}C$ -> 36.5, $80/mn$ -> 80).\\n"
        "3. Parse blood pressure into systolic and diastolic numbers.\\n"
        "4. If a value cannot be determined, use null.\\n"
    )

def _get_consultation_json_schema() -> Dict[str, Any]:
    return {
        "type": "object",
        "properties": {
            "hospital_name": {"type": "string"},
            "document_type": {"type": "string"},
            "physician": {"type": ["string", "null"]},
            "evaluation_date": {"type": ["string", "null"]},
            "patient_demographics": {
                "type": "object",
                "properties": {
                    "name_khmer": {"type": ["string", "null"]},
                    "gender": {"type": ["string", "null"]},
                    "payment_type": {"type": ["string", "null"]},
                    "age": {
                        "type": "object",
                        "properties": {
                            "years": {"type": ["integer", "null"]},
                            "months": {"type": ["integer", "null"]},
                            "days": {"type": ["integer", "null"]}
                        }
                    }
                }
            },
            "vital_signs": {
                "type": "object",
                "properties": {
                    "systolic_mmhg": {"type": ["number", "null"]},
                    "diastolic_mmhg": {"type": ["number", "null"]},
                    "pulse_bpm": {"type": ["number", "null"]},
                    "respiratory_rate_per_mn": {"type": ["number", "null"]},
                    "temperature_celsius": {"type": ["number", "null"]},
                    "oxygen_saturation_percentage": {"type": ["number", "null"]},
                    "height_cm": {"type": ["number", "null"]},
                    "weight_kg": {"type": ["number", "null"]}
                }
            },
            "clinical_notes": {
                "type": "object",
                "properties": {
                    "chief_complaint": {"type": ["string", "null"]},
                    "current_medications": {"type": ["string", "null"]}
                }
            },
            "treatment_plan": {
                "type": "object",
                "properties": {
                    "prescription_id": {"type": ["string", "null"]},
                    "laboratory_id": {"type": ["string", "null"]}
                }
            }
        },
        "required": ["hospital_name", "document_type", "patient_demographics", "vital_signs"]
    }
"""

content = content.replace("def _get_ollama_json_schema() -> Dict[str, Any]:", consultation_funcs + "\ndef _get_ollama_json_schema() -> Dict[str, Any]:")

# 4. Modify extract_with_llm
old_extract_llm = """    system_prompt = _build_llm_system_prompt(template)
    user_prompt = _build_llm_user_prompt(raw_text, template)

    payload = {
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "format": _get_ollama_json_schema(),"""

new_extract_llm = """    doc_type = detect_document_type(raw_text)
    if doc_type == 'consultation':
        system_prompt = _build_consultation_system_prompt(template)
        json_schema = _get_consultation_json_schema()
    else:
        system_prompt = _build_llm_system_prompt(template)
        json_schema = _get_ollama_json_schema()

    user_prompt = _build_llm_user_prompt(raw_text, template)

    payload = {
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "format": json_schema,"""

content = content.replace(old_extract_llm, new_extract_llm)

# 5. Modify validate_extraction for format 2
old_validate = """    # Flag normalization — force to enum
    for test in result.get('testResults', []):
        flag = test.get('flag')
        if flag is not None:
            flag_upper = str(flag).strip().upper()
            if flag_upper in ('H', 'L'):
                test['flag'] = flag_upper
            else:
                test['flag'] = None"""

new_validate = """    # Normalize garbled units and reference range LaTeX
    test_results = result.get('test_results', {})
    if isinstance(test_results, dict):
        all_tests = []
        for panel, tests in test_results.items():
            if isinstance(tests, list):
                all_tests.extend(tests)
    else:
        all_tests = result.get('testResults', [])

    for test in all_tests:
        # Flag normalization
        flag = test.get('flag')
        if flag is not None:
            flag_upper = str(flag).strip().upper()
            if flag_upper in ('H', 'L'):
                test['flag'] = flag_upper
            else:
                test['flag'] = None
        
        # Unit normalization
        unit = test.get('unit')
        if unit:
            unit = unit.replace('응', '%').replace('%0', '%').replace('0P', '%').replace('09', '%')
            test['unit'] = unit
            
        # Reference range cleanup
        ref = test.get('referenceRange')
        if ref:
            # Strip $()$ LaTeX wrappers
            ref = re.sub(r'\\$\\(', '(', ref)
            ref = re.sub(r'\\)\\$', ')', ref)
            test['referenceRange'] = ref"""

content = content.replace(old_validate, new_validate)

# 6. Add validate_consultation_extraction
val_consult = """def validate_consultation_extraction(result: Dict[str, Any]) -> Dict[str, Any]:
    vital_signs = result.get('vital_signs', {})
    if vital_signs:
        for k, v in vital_signs.items():
            if isinstance(v, str):
                # strip latex
                cleaned = re.sub(r'\\$([^$]+)\\$', lambda m: m.group(1).replace('{\\\\circ}', '°').replace(',', '.'), v)
                vital_signs[k] = cleaned

    demo = result.get('patient_demographics', {})
    # Simple age fallback if age is string
    age = demo.get('age')
    if isinstance(age, str):
        m = re.search(r'(\\d+)\\s*(?:ឆ្នាំ|Y).*?(\\d+)\\s*(?:ខែ|M).*?(\\d+)\\s*(?:ថ្ងៃ|D)', age)
        if m:
            demo['age'] = {'years': int(m.group(1)), 'months': int(m.group(2)), 'days': int(m.group(3))}
            
    return result

"""
content = content.replace("def validate_extraction(result: Dict[str, Any]) -> Dict[str, Any]:", val_consult + "def validate_extraction(result: Dict[str, Any]) -> Dict[str, Any]:")


# 7. Add ConsultationReportParser and modify process_single_file
consult_parser = """class ConsultationReportParser:
    def parse_optimized(self, ocr_text: str) -> Dict[str, Any]:
        # Minimal regex fallback since LLM will do most of the work for consultation
        text = re.sub(r'\\$([^$]+)\\$', lambda m: m.group(1).replace('{\\\\circ}', '°').replace(',', '.'), ocr_text)
        
        systolic = None
        diastolic = None
        bp_match = re.search(r'(\\d{2,3})/(\\d{2,3})', text)
        if bp_match:
            systolic = int(bp_match.group(1))
            diastolic = int(bp_match.group(2))
            
        return {
            "hospital_name": "KV Hospital",
            "document_type": "consultation",
            "vital_signs": {
                "systolic_mmhg": systolic,
                "diastolic_mmhg": diastolic
            }
        }

"""
content = content.replace("class OptimizedLabReportParser:", consult_parser + "class OptimizedLabReportParser:")

old_process = """        # Fallback to regex parser if LLM failed or no template
        if result is None:
            parser = OptimizedLabReportParser()
            result = parser.parse_optimized(ocr_text)
            extraction_method = 'regex'
            print(f'DEBUG: Using regex parser (fallback)', file=sys.stderr)"""

new_process = """        # Fallback to regex parser if LLM failed or no template
        if result is None:
            doc_type = detect_document_type(ocr_text)
            if doc_type == 'consultation':
                parser = ConsultationReportParser()
                result = parser.parse_optimized(ocr_text)
                result = validate_consultation_extraction(result)
            else:
                parser = OptimizedLabReportParser()
                result = parser.parse_optimized(ocr_text)
            extraction_method = 'regex'
            print(f'DEBUG: Using {doc_type} regex parser (fallback)', file=sys.stderr)
        elif template:
            # Re-run correct validator for LLM output
            doc_type = detect_document_type(ocr_text)
            if doc_type == 'consultation':
                result = validate_consultation_extraction(result)"""

content = content.replace(old_process, new_process)

with open('document_ocr.py', 'w', encoding='utf-8') as f:
    f.write(content)
print("document_ocr.py updated successfully!")
