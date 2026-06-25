import sys
import json
import re
import argparse
import concurrent.futures
import mimetypes
import inspect
from typing import Dict, Any, Optional, List
from pathlib import Path
import time
import os
import tempfile
from time import sleep

_paddle_ocr_instance = None
_kiri_ocr_instance = None


def _read_env_value_from_dotenv(dotenv_path: str, key: str) -> Optional[str]:
    """Fallback loader for .env values when process environment is not exported."""
    if not os.path.exists(dotenv_path):
        return None

    try:
        with open(dotenv_path, 'r', encoding='utf-8') as env_file:
            for raw_line in env_file:
                line = raw_line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                current_key, current_value = line.split('=', 1)
                if current_key.strip() == key:
                    return current_value.strip().strip('"').strip("'")
    except Exception:
        return None

    return None


def _get_google_config() -> Dict[str, str]:
    base_path = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
    dotenv_path = os.path.join(base_path, '.env')

    def get_config_value(key: str, default: Optional[str] = None) -> Optional[str]:
        return os.getenv(key) or _read_env_value_from_dotenv(dotenv_path, key) or default

    project_id = get_config_value('GOOGLE_CLOUD_PROJECT_ID')
    location = get_config_value('GOOGLE_CLOUD_LOCATION', 'us')
    processor_id = get_config_value('GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID')
    credentials_config = get_config_value('GOOGLE_APPLICATION_CREDENTIALS')

    if not project_id or not processor_id:
        raise ValueError('Missing GOOGLE_CLOUD_PROJECT_ID or GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID in environment configuration.')

    if not credentials_config:
        raise ValueError('Missing GOOGLE_APPLICATION_CREDENTIALS in environment configuration.')

    is_windows_absolute = re.match(r'^[A-Za-z]:[\\/]', credentials_config) is not None
    is_unix_absolute = credentials_config.startswith('/')
    credentials_path = credentials_config if (is_windows_absolute or is_unix_absolute) else os.path.join(base_path, 'storage', credentials_config)

    return {
        'project_id': project_id,
        'location': location,
        'processor_id': processor_id,
        'credentials_path': credentials_path,
    }


def _get_paddle_config() -> Dict[str, Any]:
    """Load PaddleOCR configuration from environment."""
    base_path = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
    dotenv_path = os.path.join(base_path, '.env')

    def get_config_value(key: str, default: Optional[str] = None) -> Optional[str]:
        return os.getenv(key) or _read_env_value_from_dotenv(dotenv_path, key) or default

    enabled = get_config_value('PADDLE_OCR_ENABLED', 'false').lower() in ('true', '1', 'yes')
    language = get_config_value('PADDLE_OCR_LANGUAGE', 'ch')
    confidence_threshold = float(get_config_value('PADDLE_OCR_CONFIDENCE_THRESHOLD', '0.85'))
    device = (get_config_value('PADDLE_OCR_DEVICE', 'auto') or 'auto').strip().lower()
    gpu_id = (get_config_value('PADDLE_OCR_GPU_ID', '0') or '0').strip()

    return {
        'enabled': enabled,
        'language': language,
        'confidence_threshold': confidence_threshold,
        # Device selection:
        # - auto: try GPU first (if Paddle is CUDA-enabled + GPU present), else CPU
        # - gpu:  try GPU first, else CPU
        # - cpu:  force CPU
        'device': device,
        'gpu_id': gpu_id,
    }


def _select_paddle_device(paddle_config: Dict[str, Any]) -> str:
    device = str(paddle_config.get('device', 'auto') or 'auto').strip().lower()
    gpu_id = str(paddle_config.get('gpu_id', '0') or '0').strip()
    gpu_device = f'gpu:{gpu_id}' if gpu_id.isdigit() else 'gpu:0'

    if device == 'cpu':
        return 'cpu'
    if device in {'gpu', 'cuda', 'auto'}:
        return gpu_device
    # Unknown value — default to CPU for safety
    print(f'DEBUG: Unknown PADDLE_OCR_DEVICE value "{device}", defaulting to CPU', file=sys.stderr)
    return 'cpu'


def _get_kiri_config() -> Dict[str, Any]:
    """Load Kiri OCR configuration from environment."""
    base_path = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
    dotenv_path = os.path.join(base_path, '.env')

    def get_config_value(key: str, default: Optional[str] = None) -> Optional[str]:
        return os.getenv(key) or _read_env_value_from_dotenv(dotenv_path, key) or default

    enabled = get_config_value('KIRI_OCR_ENABLED', 'false').lower() in ('true', '1', 'yes')
    decode_method = get_config_value('KIRI_OCR_DECODE_METHOD', 'accurate')
    confidence_threshold = float(get_config_value('KIRI_OCR_CONFIDENCE_THRESHOLD', '0.7'))
    device = get_config_value('KIRI_OCR_DEVICE', 'auto').strip().lower()

    return {
        'enabled': enabled,
        'decode_method': decode_method,
        'confidence_threshold': confidence_threshold,
        'device': device,
    }


def _get_ollama_config() -> Dict[str, Any]:
    """Load Ollama LLM configuration from environment."""
    base_path = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
    dotenv_path = os.path.join(base_path, '.env')

    def get_config_value(key: str, default: Optional[str] = None) -> Optional[str]:
        return os.getenv(key) or _read_env_value_from_dotenv(dotenv_path, key) or default

    enabled = get_config_value('OLLAMA_ENABLED', 'false').lower() in ('true', '1', 'yes')
    host = get_config_value('OLLAMA_HOST', 'http://ollama:11434')
    model = get_config_value('OLLAMA_MODEL', 'phi3:mini')
    timeout = int(get_config_value('OLLAMA_TIMEOUT', '60'))

    return {
        'enabled': enabled,
        'host': host.rstrip('/'),
        'model': model,
        'timeout': timeout,
    }


# ═══════════════════════════════════════════════════════════════════════════════
# Anchor-based hospital detection & corrupted-label mappings
# ═══════════════════════════════════════════════════════════════════════════════

# Known hospital anchors — when detected, bypass OCR for header fields
_HOSPITAL_ANCHORS: Dict[str, Dict[str, str]] = {
    'KV Hospital': {
        'hospital_name': 'KV Hospital',
        'hospital_address': 'Phum Tuol Vihear, Khum Chirou Ti 2, Srok Tbong Khmum',
        'hospital_slogan': '',
    },
}

# Fuzzy label mapping: corrupted OCR labels → canonical field names
_FUZZY_LABEL_MAP: Dict[str, str] = {
    'in:/name':       'patient_name',
    'ឈ្មោះ/name':     'patient_name',
    'nu/age':         'age',
    'អាយុ/agge':      'age',
    'អាយុ/age':       'age',
    'ing/gender':     'gender',
    'ភេទ/gender':     'gender',
    'paatient id':    'patient_id',
    'laab id':        'lab_id',
}


def _detect_hospital_anchor(raw_text: str) -> Optional[str]:
    """Detect which known hospital the OCR text belongs to.

    Returns the anchor key (e.g. 'KV Hospital') or None.
    """
    text_lower = raw_text.lower()
    for anchor_key in _HOSPITAL_ANCHORS:
        # Match flexible OCR typos: 'KV Hospittall', 'KV Hospital', etc.
        if anchor_key.lower().replace(' ', '') in text_lower.replace(' ', ''):
            return anchor_key
        # Also check for common OCR variants
        variants = [anchor_key.lower(), anchor_key.lower().replace('hospital', 'hospittal'),
                    anchor_key.lower().replace('hospital', 'hospittall')]
        for v in variants:
            if v in text_lower:
                return anchor_key
    return None


def _apply_hospital_anchor_overrides(result: Dict[str, Any], anchor_key: str) -> Dict[str, Any]:
    """Override hospital header fields with known-good values."""
    overrides = _HOSPITAL_ANCHORS.get(anchor_key, {})
    if not overrides:
        return result

    # For lab reports: patch labInfo or top-level fields
    lab_info = result.get('labInfo', {})
    patient_info = result.get('patientInfo', {})

    # Set hospital name wherever the schema expects it
    if 'hospital_name' in overrides:
        result['hospital_name'] = overrides['hospital_name']
        if lab_info:
            lab_info['hospital_name'] = overrides['hospital_name']

    print(f'DEBUG: Applied anchor overrides for "{anchor_key}"', file=sys.stderr)
    return result


def _build_llm_system_prompt(template: Dict[str, Any]) -> str:
    """Build the system prompt for the LLM extraction task."""
    schema = template.get('schema', {})
    
    # Support both old flat schema and new nested schema
    lab_schema = schema.get('laboratory_fields', schema)
    
    flag_enum = lab_schema.get('flag_enum', ['H', 'L'])
    categories = lab_schema.get('test_categories', [])
    hospital_phones = schema.get('hospital_phones_to_exclude', [])

    return (
        "You are a medical lab report data extractor. "
        "Extract structured JSON from raw OCR text of Cambodian hospital lab reports.\n\n"
        "STRICT RULES:\n"
        f"1. Flag values MUST be exactly one of {flag_enum} or null. "
        "NEVER output 'NEGATIVE', 'POSITIVE', 'Normal', or any other string as a flag. "
        "If the text says NEGATIVE, POSITIVE, POSITIVE (++), or has no flag, output null for the flag.\n"
        "2. All dates must be in DD/MM/YYYY HH:MM format exactly as they appear.\n"
        "3. Patient ID format: PT followed by digits (e.g. PT00139). Fix OCR typos like 'Paatient' → 'Patient'.\n"
        "4. Lab ID format: LT followed by digits (e.g. LT00001). Fix OCR typos like 'Laab' → 'Lab'.\n"
        "5. Patient name must be in English uppercase (e.g. HENG VANNAT).\n"
        "6. Gender must be exactly 'Male' or 'Female'. Fix OCR typos like 'Feemale' → 'Female'.\n"
        f"7. Test categories must be one of: {categories}\n"
        f"8. Ignore hospital phone numbers: {hospital_phones}\n"
        "\nFUZZY LABEL MAPPING (OCR frequently corrupts Khmer labels):\n"
        "- Treat 'ឈ្មោះ/Name' or 'in:/Name' as Patient Name\n"
        "- Treat 'អាយុ/Agge' or 'nu/Age' as Age\n"
        "- Treat 'ភេទ/Gender' or 'Ing/Gender' as Gender\n"
        "- Treat 'Paatient ID' as Patient ID\n"
        "- Treat 'Laab ID' as Lab ID\n"
        "- Treat 'Coollected Date' as Collected Date\n"
        "- Extract the clean English value immediately after each label's colon.\n"
        "\nENGLISH PRIORITY RULE:\n"
        "- For physician names (Requested By, Validated By, Lab Technician), "
        "ALWAYS extract the English text and ignore any garbled Khmer text nearby.\n"
        "- Example: 'Requested By : Dr.. LEANG Choou' → extract 'Dr. LEANG Choeu' (fix double dots).\n"
        "\nUNIT NORMALIZATION:\n"
        "- Fix corrupted units: '응' → '%', '៖៖' → '%', 'dlL' → 'dL', 'dLL' → 'dL', "
        "'dal' → 'dL', 'U/ZL' → 'U/L', 'U/zz' → 'U/L', '97ddL' → 'g/dL'.\n"
        "- Preserve exact numeric values — do NOT round or recalculate.\n"
        "\nADDITIONAL RULES:\n"
        "9. The OCR text may contain garbled Khmer Unicode characters before English labels. "
        "Extract the English value after the label.\n"
        "10. If a value cannot be determined from the text, use null.\n"
        "11. For test results, extract the numeric value or NEGATIVE/POSITIVE as the result string, "
        "but the FLAG must only be H, L, or null.\n"
        "12. 'BIOCHIMISTRY' is a misspelling of 'BIOCHEMISTRY' — normalize to 'BIOCHEMISTRY'.\n"
        "13. 'ENNZYMOLOGY' is a misspelling of 'ENZYMOLOGY' — normalize to 'ENZYMOLOGY'.\n"
        "14. The OCR text may contain multiple test categories (e.g., BIOCHEMISTRY, HEMATOLOGY, ENZYMOLOGY). Pay close attention to the headers in the text and categorize each test accordingly. Extract ALL tests from the entire document.\n"
        "15. Preserve test names exactly as they appear in the original text, including typos and variations (e.g. 'Cholesterole Total', 'Tryglyceride', 'Uric acide'). Do NOT fix spelling in test names.\n"
    )


def _build_llm_user_prompt(raw_text: str, template: Dict[str, Any]) -> str:
    """Build the user prompt with raw OCR text and few-shot examples."""
    doc_type = detect_document_type(raw_text)
    examples = template.get('few_shot_examples', [])

    # Filter examples to match document type so lab examples don't confuse consultation extraction
    if examples:
        filtered = []
        for ex in examples:
            output = ex.get('output', {})
            # Consultation examples have 'vital_signs' or 'patient_demographics' keys
            is_consultation_example = 'vital_signs' in output or 'patient_demographics' in output
            # Lab examples have 'testResults' or 'labInfo' keys
            is_lab_example = 'testResults' in output or 'labInfo' in output

            if doc_type == 'consultation' and is_consultation_example:
                filtered.append(ex)
            elif doc_type != 'consultation' and is_lab_example:
                filtered.append(ex)
        examples = filtered

    if doc_type == 'consultation':
        doc_label = 'patient consultation form'
    else:
        doc_label = 'lab report'

    prompt_parts = [f"Extract all structured data from this {doc_label} OCR text.\n"]

    if examples:
        # Prioritize the most recent (verified) examples to adapt to current formats
        selected_examples = examples[-4:] if len(examples) > 4 else examples
        prompt_parts.append("=== FEW-SHOT EXAMPLES ===\n")
        for i, ex in enumerate(selected_examples, 1):
            prompt_parts.append(f"--- Example {i} Input ---\n{ex['input']}\n")
            prompt_parts.append(f"--- Example {i} Output ---\n{json.dumps(ex['output'], ensure_ascii=False)}\n")

    prompt_parts.append(f"=== ACTUAL {doc_label.upper()} TO EXTRACT ===\n")
    prompt_parts.append(raw_text)

    return "\n".join(prompt_parts)


def detect_document_type(ocr_text: str) -> str:
    """Detect whether OCR text is a consultation form or lab report."""
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
        "You are a medical data extractor for Cambodian hospital Patient Consultation forms.\n"
        "Extract structured JSON from raw OCR text.\n\n"
        "STRICT RULES:\n"
        "1. hospital_name: Look for 'KV Hospital' or similar hospital name in the header. Default to 'KV Hospital' if the text contains 'KV' or 'HOSPITAL'.\n"
        "2. document_type: Always output 'Patient Consultation Information'.\n"
        "3. physician: Extract the doctor name (e.g. 'Dr. LEANG Choeu'). It may appear after 'Physician :' or at the bottom as 'Physician's Name'.\n"
        "4. evaluation_date: Convert from DD/MM/YYYY HH:MM format to YYYY-MM-DD HH:MM:SS. Example: '31/03/2024 13:34' -> '2024-03-31 13:34:00'.\n"
        "5. patient_demographics.name_khmer: Extract the Khmer patient name after 'Patient :' or 'ឈ្មោះ'. The name may contain garbled Unicode — extract the best text available.\n"
        "6. patient_demographics.gender: Output 'Male' or 'Female'. Map 'M' to 'Male', 'F' to 'Female'.\n"
        "7. patient_demographics.payment_type: Extract text after 'Payment Type :' (e.g. 'ប.ស.ស', 'GIS', 'Insurance').\n"
        "8. patient_demographics.age: Parse into integer years, months, days. "
        "The OCR may format age as '51 ñ/year, 11 18/month, 14 iŃj/day' — extract the FIRST number for years, months, days respectively. "
        "Garbled Khmer text between numbers should be ignored.\n"
        "9. vital_signs: The OCR text shows vital signs with labels like '(Systolic)', '(Diastolic)', '(Pulse)', '(RR)', '(Temperature)', '(O2sat)', '(Height)', '(Weight)'. "
        "Extract the numeric values. Strip units like /mmHg, /mn, °C, %, cm, kg. "
        "Example: '107/mmHg' -> systolic_mmhg: 107, '75/mmHg' -> diastolic_mmhg: 75, '80 /mn' -> pulse_bpm: 80, '36,5 °C' -> temperature_celsius: 36.5.\n"
        "10. If vital signs section is empty or missing, output an object with all null values, NOT an empty array.\n"
        "11. clinical_notes.chief_complaint: Extract text after '(Chief complain)' or 'Chief Complaint'. May be in French (e.g. 'douleur de la pièd droite').\n"
        "12. clinical_notes.current_medications: Extract text after '(Current medications)'.\n"
        "13. treatment_plan: Extract prescription_id (after 'Prescription') and laboratory_id (after 'Laboratory'). These are codes like 'PRE001226', 'PAR004366'.\n"
        "14. Ignore hospital phone numbers: 097 840 47 89, 012 89 17 45, 012 28 60 70.\n"
        "15. The OCR text contains garbled Khmer Unicode characters — focus on extracting English values and numbers.\n"
        "16. If a value cannot be determined, use null — NEVER make up data.\n"
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
                        },
                        "required": ["years", "months", "days"]
                    }
                },
                "required": ["name_khmer", "gender", "payment_type", "age"]
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
                },
                "required": ["systolic_mmhg", "diastolic_mmhg", "pulse_bpm", "respiratory_rate_per_mn",
                             "temperature_celsius", "oxygen_saturation_percentage", "height_cm", "weight_kg"]
            },
            "clinical_notes": {
                "type": "object",
                "properties": {
                    "chief_complaint": {"type": ["string", "null"]},
                    "current_medications": {"type": ["string", "null"]}
                },
                "required": ["chief_complaint", "current_medications"]
            },
            "treatment_plan": {
                "type": "object",
                "properties": {
                    "prescription_id": {"type": ["string", "null"]},
                    "laboratory_id": {"type": ["string", "null"]}
                },
                "required": ["prescription_id", "laboratory_id"]
            }
        },
        "required": ["hospital_name", "document_type", "physician", "evaluation_date",
                     "patient_demographics", "vital_signs", "clinical_notes", "treatment_plan"]
    }

def _get_ollama_json_schema(template: Dict[str, Any]) -> Dict[str, Any]:
    """Return the JSON schema for Ollama structured output."""
    schema = template.get('schema', {})
    lab_schema = schema.get('laboratory_fields', schema)
    categories = lab_schema.get('test_categories', [])
    
    test_result_schema = {
        "type": "object",
        "properties": {
            "testName": {"type": "string"},
            "result": {"type": "string"},
            "unit": {"type": ["string", "null"]},
            "referenceRange": {"type": ["string", "null"]},
            "flag": {"type": ["string", "null"], "enum": ["H", "L", None]},
            "category": {"type": "string"}
        },
        "required": ["testName", "result", "unit", "referenceRange", "flag", "category"]
    }
    
    if categories:
        test_result_schema["properties"]["category"]["enum"] = categories
    
    return {
        "type": "object",
        "properties": {
            "patientInfo": {
                "type": "object",
                "properties": {
                    "name": {"type": ["string", "null"]},
                    "patientId": {"type": ["string", "null"]},
                    "age": {"type": ["string", "null"]},
                    "gender": {"type": ["string", "null"]},
                    "phone": {"type": ["string", "null"]}
                },
                "required": ["name", "patientId", "age", "gender", "phone"]
            },
            "labInfo": {
                "type": "object",
                "properties": {
                    "labId": {"type": ["string", "null"]},
                    "requestedBy": {"type": ["string", "null"]},
                    "requestedDate": {"type": ["string", "null"]},
                    "collectedDate": {"type": ["string", "null"]},
                    "analysisDate": {"type": ["string", "null"]},
                    "validatedBy": {"type": ["string", "null"]}
                },
                "required": ["labId", "requestedBy"]
            },
            "testResults": {
                "type": "array",
                "items": test_result_schema
            }
        },
        "required": ["patientInfo", "labInfo", "testResults"]
    }


def extract_with_llm(raw_text: str, template: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    """Send raw OCR text to local Ollama LLM for structured extraction.

    Returns parsed result dict on success, or None on failure (so caller
    can fall back to regex parser).
    """
    try:
        import requests as http_requests
    except ImportError:
        print('DEBUG: requests library not installed, cannot call Ollama', file=sys.stderr)
        return None

    ollama_config = _get_ollama_config()
    if not ollama_config['enabled']:
        print('DEBUG: Ollama LLM disabled in config', file=sys.stderr)
        return None

    model = template.get('llm_model', ollama_config['model'])
    api_url = f"{ollama_config['host']}/api/chat"

    doc_type = detect_document_type(raw_text)
    if doc_type == 'consultation':
        system_prompt = _build_consultation_system_prompt(template)
        json_schema = _get_consultation_json_schema()
    else:
        system_prompt = _build_llm_system_prompt(template)
        json_schema = _get_ollama_json_schema(template)

    user_prompt = _build_llm_user_prompt(raw_text, template)

    payload = {
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "format": json_schema,
        "stream": False,
        "options": {
            "temperature": 0,
            "num_ctx": 32768,
            "num_predict": 4096,
        }
    }

    max_retries = 3
    for attempt in range(1, max_retries + 1):
        try:
            print(f'DEBUG: Calling Ollama ({model}) at {api_url} (attempt {attempt}/{max_retries})...', file=sys.stderr)
            start = time.time()
            resp = http_requests.post(
                api_url,
                json=payload,
                timeout=ollama_config['timeout']
            )
            elapsed = time.time() - start
            print(f'DEBUG: Ollama responded in {elapsed:.1f}s (status {resp.status_code})', file=sys.stderr)

            if resp.status_code != 200:
                print(f'DEBUG: Ollama error (attempt {attempt}): {resp.text[:500]}', file=sys.stderr)
                if attempt < max_retries:
                    sleep(2 ** attempt)
                    continue
                return None

            response_data = resp.json()
            content = response_data.get('message', {}).get('content', '')

            if not content:
                print(f'DEBUG: Ollama returned empty content (attempt {attempt})', file=sys.stderr)
                if attempt < max_retries:
                    sleep(2 ** attempt)
                    continue
                return None

            # Parse the JSON content from the LLM response
            result = json.loads(content) if isinstance(content, str) else content
            print(f'DEBUG: Ollama extracted {len(result.get("testResults", []))} test results', file=sys.stderr)

            # ── Anchor-based post-processing ──
            anchor_key = _detect_hospital_anchor(raw_text)
            if anchor_key:
                result = _apply_hospital_anchor_overrides(result, anchor_key)

            return result

        except http_requests.exceptions.ConnectionError:
            print(f'DEBUG: Ollama unreachable (attempt {attempt}/{max_retries})', file=sys.stderr)
            if attempt < max_retries:
                sleep(2 ** attempt)
                continue
            print('DEBUG: Ollama unreachable after all retries — falling back to regex', file=sys.stderr)
            return None
        except http_requests.exceptions.Timeout:
            print(f'DEBUG: Ollama timed out (attempt {attempt}/{max_retries})', file=sys.stderr)
            if attempt < max_retries:
                sleep(2 ** attempt)
                continue
            print(f'DEBUG: Ollama timed out after all retries — falling back to regex', file=sys.stderr)
            return None
        except (json.JSONDecodeError, KeyError, TypeError) as e:
            print(f'DEBUG: Failed to parse Ollama response (attempt {attempt}): {e}', file=sys.stderr)
            if attempt < max_retries:
                sleep(2 ** attempt)
                continue
            return None
        except Exception as e:
            print(f'DEBUG: Ollama call failed unexpectedly: {e}', file=sys.stderr)
            return None


def validate_consultation_extraction(result: Dict[str, Any]) -> Dict[str, Any]:
    vital_signs = result.get('vital_signs', {})
    if vital_signs:
        for k, v in list(vital_signs.items()):
            if isinstance(v, str):
                # strip latex
                cleaned = re.sub(r'\$([^$]+)\$', lambda m: m.group(1).replace('{\\circ}', '°').replace(',', '.'), v)
                # Parse numeric values
                if k in ['systolic_mmhg', 'diastolic_mmhg', 'pulse_bpm', 'respiratory_rate_per_mn', 
                         'temperature_celsius', 'oxygen_saturation_percentage', 'height_cm', 'weight_kg']:
                    # Special case for BP if it wasn't split correctly
                    if k in ['systolic_mmhg', 'diastolic_mmhg'] and '/' in cleaned:
                        m = re.search(r'(\d{2,3})\s*/\s*(\d{2,3})', cleaned)
                        if m:
                            vital_signs['systolic_mmhg'] = float(m.group(1))
                            vital_signs['diastolic_mmhg'] = float(m.group(2))
                            continue
                    
                    # Extract first number
                    num_match = re.search(r'(\d+\.?\d*)', cleaned.replace(',', '.'))
                    if num_match:
                        vital_signs[k] = float(num_match.group(1))
                    else:
                        vital_signs[k] = None
                else:
                    vital_signs[k] = cleaned

    demo = result.get('patient_demographics', {})
    if demo:
        # Simple age fallback if age is string
        age = demo.get('age')
        if isinstance(age, str):
            m = re.search(r'(\d+)\s*(?:ឆ្នាំ|Y).*?(\d+)\s*(?:ខែ|M).*?(\d+)\s*(?:ថ្ងៃ|D)', age)
            if m:
                demo['age'] = {'years': int(m.group(1)), 'months': int(m.group(2)), 'days': int(m.group(3))}
            else:
                m_simple = re.search(r'(\d+)\s*(?:ឆ្នាំ|Y)', age)
                if m_simple:
                    demo['age'] = {'years': int(m_simple.group(1)), 'months': 0, 'days': 0}
            
    return result

def validate_extraction(result: Dict[str, Any]) -> Dict[str, Any]:
    """Post-LLM validation to catch hallucinations and normalize values.

    This is a critical safety net for medical data — never trust raw LLM
    output without validation.
    """
    # Normalize garbled units and reference range LaTeX
    test_results = result.get('test_results', {})
    if isinstance(test_results, dict):
        all_tests = []
        for panel, tests in test_results.items():
            if isinstance(tests, list):
                all_tests.extend(tests)
    else:
        all_tests = result.get('testResults', [])

    for test in all_tests:
        # ── Strict Flag normalization (Task 4) ──
        flag = test.get('flag')
        if flag is not None:
            flag_str = str(flag).strip().upper()
            if flag_str in ('H', 'L'):
                test['flag'] = flag_str
            else:
                # NEGATIVE, POSITIVE, POSITIVE (++), Normal, empty, z, etc. → null
                test['flag'] = None
        
        # ── Unit normalization (Task 4) ──
        unit = test.get('unit')
        if unit:
            unit = (unit
                    .replace('응', '%')
                    .replace('៖៖', '%')
                    .replace('៖', '%')
                    .replace('%0', '%')
                    .replace('0P', '%')
                    .replace('09', '%')
                    )
            # Fix common OCR unit corruption
            unit = re.sub(r'dl[Ll]', 'dL', unit)
            unit = re.sub(r'dLL', 'dL', unit)
            unit = re.sub(r'dal', 'dL', unit, flags=re.IGNORECASE)
            unit = re.sub(r'U/[Zz][Ll]', 'U/L', unit)
            unit = re.sub(r'U/zz', 'U/L', unit)
            unit = re.sub(r'97ddL', 'g/dL', unit)
            unit = re.sub(r'ddL', 'dL', unit)
            test['unit'] = unit

        # ── Result normalization: strip OCR artifacts from numeric values ──
        result_val = test.get('result')
        if result_val and isinstance(result_val, str):
            # Collapse errant spaces within decimal numbers: '0 . 7' → '0.7'
            result_val = re.sub(r'(\d)\s*\.\s*(\d)', r'\1.\2', result_val)
            # Collapse double dots: '31//03' stays as-is (date) but '0..7' → '0.7'
            result_val = re.sub(r'(\d)\.{2,}(\d)', r'\1.\2', result_val)
            test['result'] = result_val.strip()
            
        # ── Reference range cleanup ──
        ref = test.get('referenceRange')
        if ref:
            # Strip $()$ LaTeX wrappers
            ref = re.sub(r'\$\(', '(', ref)
            ref = re.sub(r'\)\$', ')', ref)
            # Collapse OCR double-dot artifacts: '40..0' → '40.0'
            ref = re.sub(r'(\d)\.{2,}(\d)', r'\1.\2', ref)
            # Collapse errant spaces: '0 - 200' is fine, '0 . 7' → '0.7'
            ref = re.sub(r'(\d)\s*\.\s*(\d)', r'\1.\2', ref)
            test['referenceRange'] = ref

        # ── Test name cleanup: fix OCR double-char typos ──
        test_name = test.get('testName', '')
        if test_name:
            # Common OCR typos in test names
            test_name = re.sub(r'Chollesterr?ole?', 'Cholesterol', test_name)
            test_name = re.sub(r'Tryglyceride', 'Triglyceride', test_name)
            test_name = re.sub(r'Creatinine,\s*,', 'Creatinine,', test_name)
            test_name = re.sub(r'Uriic\s+accide', 'Uric Acid', test_name, flags=re.IGNORECASE)
            test['testName'] = test_name.strip()

    # Patient ID format validation
    patient_info = result.get('patientInfo', {})
    pid = patient_info.get('patientId', '')
    if pid and not re.match(r'^PT\d+$', str(pid)):
        patient_info['patientId'] = None

    # Lab ID format validation
    lab_info = result.get('labInfo', {})
    lid = lab_info.get('labId', '')
    if lid and not re.match(r'^LT\d+$', str(lid)):
        lab_info['labId'] = None

    # Gender normalization — handle OCR typos like 'Feemale'
    gender = patient_info.get('gender', '')
    if gender:
        gender_lower = str(gender).strip().lower()
        if gender_lower in ('male', 'm', 'maale', 'malle'):
            patient_info['gender'] = 'Male'
        elif gender_lower in ('female', 'f', 'feemale', 'femaale', 'femalle'):
            patient_info['gender'] = 'Female'
        else:
            patient_info['gender'] = None

    # ── Physician name cleanup: English priority (Task 3) ──
    for field in ['requestedBy', 'validatedBy']:
        name_val = lab_info.get(field, '')
        if name_val and isinstance(name_val, str):
            # Fix OCR double-dot: 'Dr..' → 'Dr.'
            name_val = re.sub(r'\.{2,}', '.', name_val)
            # Fix OCR double-letter typos in common titles
            name_val = re.sub(r'Choou', 'Choeu', name_val)
            # Strip any non-ASCII (garbled Khmer) from physician names
            name_ascii = re.sub(r'[^\x00-\x7F]+', '', name_val).strip()
            if name_ascii and len(name_ascii) > 3:
                lab_info[field] = name_ascii
            else:
                lab_info[field] = name_val.strip()

    # Category normalization
    for test in result.get('testResults', []):
        cat = test.get('category', '')
        if cat:
            cat_upper = str(cat).upper().strip()
            cat_upper = cat_upper.replace('BIOCHIMISTRY', 'BIOCHEMISTRY')
            cat_upper = cat_upper.replace('ENNZYMOLOGY', 'ENZYMOLOGY')
            test['category'] = cat_upper

    # Strip empty string values → null
    for section in [patient_info, lab_info]:
        for k, v in section.items():
            if isinstance(v, str) and not v.strip():
                section[k] = None

    return result


def _infer_mime_type(file_path: str) -> str:
    extension = Path(file_path).suffix.lower()
    explicit_map = {
        '.pdf': 'application/pdf',
        '.jpg': 'image/jpeg',
        '.jpeg': 'image/jpeg',
        '.png': 'image/png',
        '.tif': 'image/tiff',
        '.tiff': 'image/tiff',
        '.bmp': 'image/bmp',
        '.gif': 'image/gif',
        '.webp': 'image/webp',
    }
    if extension in explicit_map:
        return explicit_map[extension]

    guessed, _ = mimetypes.guess_type(file_path)
    return guessed or 'application/octet-stream'


def _preprocess_image_for_ocr(file_path: str) -> bytes:
    """
    Optional preprocessing pipeline for scanned images.
    Falls back to original bytes if Pillow is unavailable.
    """
    mime_type = _infer_mime_type(file_path)
    if not mime_type.startswith('image/'):
        with open(file_path, 'rb') as source:
            return source.read()

    try:
        from PIL import Image, ImageFilter, ImageOps

        with Image.open(file_path) as image:
            processed = ImageOps.exif_transpose(image)
            processed = processed.convert('L')
            processed = ImageOps.autocontrast(processed, cutoff=2)
            processed = processed.filter(ImageFilter.SHARPEN)

            output_format = 'JPEG' if mime_type == 'image/jpeg' else 'PNG'
            with tempfile.NamedTemporaryFile(suffix='.' + output_format.lower(), delete=False) as temp_file:
                temp_path = temp_file.name

            try:
                processed.save(temp_path, format=output_format, optimize=True)
                with open(temp_path, 'rb') as temp_input:
                    return temp_input.read()
            finally:
                if os.path.exists(temp_path):
                    os.remove(temp_path)
    except Exception as preprocessing_error:
        print(f'DEBUG: Image preprocessing unavailable or failed ({preprocessing_error}); using original image bytes', file=sys.stderr)
        with open(file_path, 'rb') as source:
            return source.read()


def _flatten_paddle_lines(ocr_result: Any) -> List[Any]:
    """Normalize paddleocr output shape across versions into a flat line list.

    Handles:
    - v2.x: list of [box, (text, score)] tuples
    - v3.5: OCRResult objects with rec_texts / rec_scores attributes
    - dict: {'rec_texts': [...], 'rec_scores': [...]}
    """
    if not ocr_result:
        return []

    # v3.5 OCRResult or plain dict with rec_texts / rec_scores
    rec_texts = None
    rec_scores = None
    if isinstance(ocr_result, dict):
        rec_texts = ocr_result.get('rec_texts')
        rec_scores = ocr_result.get('rec_scores')
    elif hasattr(ocr_result, 'rec_texts'):
        rec_texts = getattr(ocr_result, 'rec_texts', None)
        rec_scores = getattr(ocr_result, 'rec_scores', None)
    elif hasattr(ocr_result, '__getitem__') and not isinstance(ocr_result, (list, tuple)):
        try:
            rec_texts = ocr_result['rec_texts']
            rec_scores = ocr_result['rec_scores']
        except (KeyError, TypeError, IndexError):
            pass

    if rec_texts and isinstance(rec_texts, list):
        flattened: List[Any] = []
        for idx, text in enumerate(rec_texts):
            score = 0.0
            if isinstance(rec_scores, list) and idx < len(rec_scores):
                score = float(rec_scores[idx])
            flattened.append((None, (str(text), score)))
        return flattened

    if isinstance(ocr_result, list):
        if ocr_result and isinstance(ocr_result[0], list) and ocr_result[0] and isinstance(ocr_result[0][0], (list, tuple)):
            return ocr_result[0]
        return ocr_result

    return []


def _reconstruct_lines_from_polys(ocr_result: Any) -> tuple[str, float]:
    """Reconstruct reading-order lines from PaddleOCR v3.5 spatial data.

    PaddleOCR v3.5 returns individual text fragments with bounding polygons.
    This function groups fragments that share the same vertical position
    (same visual line) and joins them left-to-right to produce properly
    structured lines for the parser.
    """
    import numpy as np

    dt_polys = None
    rec_texts = None
    rec_scores = None

    for attr in ['dt_polys', 'rec_texts', 'rec_scores']:
        val = None
        if isinstance(ocr_result, dict):
            val = ocr_result.get(attr)
        elif hasattr(ocr_result, attr):
            val = getattr(ocr_result, attr, None)
        elif hasattr(ocr_result, '__getitem__') and not isinstance(ocr_result, (list, tuple)):
            try:
                val = ocr_result[attr]
            except (KeyError, TypeError, IndexError):
                pass
        if attr == 'dt_polys':
            dt_polys = val
        elif attr == 'rec_texts':
            rec_texts = val
        elif attr == 'rec_scores':
            rec_scores = val

    if not rec_texts or not dt_polys:
        return '', 0.0

    if not rec_scores:
        rec_scores = [0.0] * len(rec_texts)

    fragments = []
    for text, score, poly in zip(rec_texts, rec_scores, dt_polys):
        text = str(text).strip()
        if not text:
            continue
        poly_arr = np.array(poly)
        y_mid = float(poly_arr[:, 1].mean())
        x_left = float(poly_arr[:, 0].min())
        fragments.append((y_mid, x_left, text, float(score)))

    if not fragments:
        return '', 0.0

    fragments.sort(key=lambda f: (f[0], f[1]))

    LINE_Y_TOLERANCE = 15
    lines = []
    current_line = [fragments[0]]
    for frag in fragments[1:]:
        if abs(frag[0] - current_line[0][0]) < LINE_Y_TOLERANCE:
            current_line.append(frag)
        else:
            current_line.sort(key=lambda f: f[1])
            lines.append(current_line)
            current_line = [frag]
    if current_line:
        current_line.sort(key=lambda f: f[1])
        lines.append(current_line)

    text_lines = []
    all_scores = []
    for line in lines:
        joined = '    '.join(f[2] for f in line)
        text_lines.append(joined)
        all_scores.extend(f[3] for f in line)

    extracted_text = '\n'.join(text_lines)
    avg_confidence = sum(all_scores) / len(all_scores) if all_scores else 0.0
    return extracted_text, avg_confidence


def _parse_paddle_result(ocr_result: Any) -> tuple[str, float]:
    """Parse PaddleOCR result into text and average confidence.

    For v3.5 OCRResult with dt_polys, uses spatial reconstruction.
    For v2.x list results, falls back to sequential parsing.
    """
    has_polys = False
    if isinstance(ocr_result, dict):
        has_polys = bool(ocr_result.get('dt_polys'))
    elif hasattr(ocr_result, 'dt_polys'):
        has_polys = bool(getattr(ocr_result, 'dt_polys', None))
    elif hasattr(ocr_result, '__getitem__') and not isinstance(ocr_result, (list, tuple)):
        try:
            has_polys = bool(ocr_result['dt_polys'])
        except (KeyError, TypeError, IndexError):
            pass

    if has_polys:
        try:
            return _reconstruct_lines_from_polys(ocr_result)
        except Exception as e:
            print(f'DEBUG: Spatial reconstruction failed ({e}), falling back to sequential parse', file=sys.stderr)

    text_lines: List[str] = []
    confidences: List[float] = []

    for line in _flatten_paddle_lines(ocr_result):
        if not isinstance(line, (list, tuple)) or len(line) < 2:
            continue

        payload = line[1]
        if isinstance(payload, (list, tuple)) and len(payload) >= 2:
            text = str(payload[0]).strip()
            try:
                confidence = float(payload[1])
            except (TypeError, ValueError):
                confidence = 0.0
        else:
            text = str(payload).strip()
            confidence = 0.0

        if text:
            text_lines.append(text)
            confidences.append(confidence)

    extracted_text = '\n'.join(text_lines)
    average_confidence = sum(confidences) / len(confidences) if confidences else 0.0
    return extracted_text, average_confidence


def _call_paddle_ocr(ocr: Any, file_path: str) -> Any:
    """Call PaddleOCR with API-version compatibility.

    v3.5+: ocr.predict(file_path) -> iterator of OCRResult
    v2.x:  ocr.ocr(file_path, cls=True) -> list of results
    """
    if hasattr(ocr, 'predict'):
        try:
            results = list(ocr.predict(file_path))
            if results:
                return results[0] 
        except TypeError:
            pass

    if hasattr(ocr, 'ocr'):
        try:
            return ocr.ocr(file_path, cls=True)
        except TypeError:
            return ocr.ocr(file_path)

    raise RuntimeError('PaddleOCR instance has neither predict() nor ocr() method')


def process_with_paddle_ocr(file_path: str) -> Dict[str, Any]:
    """Extract text from document using PaddleOCR with confidence scoring."""
    try:
        try:
            import torch
        except ImportError:
            pass
        import paddle
        from paddleocr import PaddleOCR
    except ImportError as import_error:
        raise ImportError('paddleocr/paddlepaddle not installed in current environment') from import_error

    paddle_config = _get_paddle_config()
    mime_type = _infer_mime_type(file_path)

    requested_device = _select_paddle_device(paddle_config)
    selected_device = 'cpu'
    use_gpu = False

    try:
        if requested_device.startswith('gpu'):
            compiled_with_cuda = False
            try:
                compiled_with_cuda = bool(paddle.is_compiled_with_cuda())
            except Exception:
                compiled_with_cuda = False

            device_count = 0
            try:
                device_count = int(paddle.device.cuda.device_count())
            except Exception:
                device_count = 0

            if compiled_with_cuda and device_count > 0:
                try:
                    paddle.set_device(requested_device)
                    selected_device = requested_device
                    use_gpu = True
                except Exception as device_error:
                    print(
                        f'DEBUG: Failed to set Paddle device to {requested_device} ({device_error}); falling back to CPU',
                        file=sys.stderr,
                    )
                    paddle.set_device('cpu')
            else:
                print(
                    f'DEBUG: Paddle GPU requested but unavailable '
                    f'(compiled_with_cuda={compiled_with_cuda}, device_count={device_count}); using CPU',
                    file=sys.stderr,
                )
                paddle.set_device('cpu')
        else:
            paddle.set_device('cpu')
    except Exception as device_setup_error:
        print(f'DEBUG: Paddle device setup failed ({device_setup_error}); using CPU', file=sys.stderr)
        try:
            paddle.set_device('cpu')
        except Exception:
            pass
        selected_device = 'cpu'
        use_gpu = False

    ocr_kwargs: Dict[str, Any] = {
        'lang': paddle_config['language'],
    }
    try:
        signature = inspect.signature(PaddleOCR)
        if 'use_gpu' in signature.parameters:
            ocr_kwargs['use_gpu'] = use_gpu
        elif 'use_cuda' in signature.parameters:
            ocr_kwargs['use_cuda'] = use_gpu
        elif 'device' in signature.parameters:
            ocr_kwargs['device'] = selected_device
    except (TypeError, ValueError):
        pass

    global _paddle_ocr_instance
    if _paddle_ocr_instance is None:
        try:
            _paddle_ocr_instance = PaddleOCR(**ocr_kwargs)
        except TypeError:
            # Backward/forward compatibility: if PaddleOCR signature changed, fall back to minimal init.
            _paddle_ocr_instance = PaddleOCR(lang=paddle_config['language'])

        print(
            f'DEBUG: PaddleOCR initialized (requested_device={paddle_config.get("device")}, '
            f'selected_device={selected_device}, use_gpu={use_gpu})',
            file=sys.stderr,
        )
    ocr = _paddle_ocr_instance

    try:
        if mime_type == 'application/pdf':
            try:
                import fitz
            except ImportError as import_error:
                raise Exception('PyMuPDF is required for PDF + PaddleOCR flow') from import_error

            doc = fitz.open(file_path)
            all_text: List[str] = []
            page_confidences: List[float] = []

            for page_num in range(len(doc)):
                page = doc.load_page(page_num)
                pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
                temp_img_path = tempfile.NamedTemporaryFile(suffix='.png', delete=False).name
                pix.save(temp_img_path)

                try:
                    result = _call_paddle_ocr(ocr, temp_img_path)
                    page_text, page_confidence = _parse_paddle_result(result)
                    if page_text:
                        all_text.append(page_text)
                    page_confidences.append(page_confidence)
                    print(f'DEBUG: PaddleOCR processed page {page_num + 1} with confidence {page_confidence:.3f}', file=sys.stderr)
                finally:
                    if os.path.exists(temp_img_path):
                        os.remove(temp_img_path)

            doc.close()
            avg_confidence = sum(page_confidences) / len(page_confidences) if page_confidences else 0.0
            joined_text = '\n'.join(all_text)
            print(f'DEBUG: PaddleOCR extracted {len(joined_text)} characters with avg confidence {avg_confidence:.3f}', file=sys.stderr)
            return {
                'text': joined_text,
                'confidence': avg_confidence,
                'page_confidences': page_confidences,
            }

        result = _call_paddle_ocr(ocr, file_path)
        text, confidence = _parse_paddle_result(result)
        print(f'DEBUG: PaddleOCR extracted {len(text)} characters with confidence {confidence:.3f}', file=sys.stderr)
        return {
            'text': text,
            'confidence': confidence,
            'page_confidences': [confidence],
        }
    finally:
        try:
            del ocr
            import gc
            gc.collect()
            import paddle
            paddle.device.cuda.empty_cache()
            print('DEBUG: PaddlePaddle GPU memory cleared', file=sys.stderr)
        except Exception as e:
            print(f'DEBUG: Error clearing PaddlePaddle GPU memory: {e}', file=sys.stderr)


def process_with_kiri_ocr(file_path: str, max_pages: Optional[int] = None) -> Dict[str, Any]:
    """Extract text from document using Kiri OCR (Khmer-specialized transformer model).

    Args:
        file_path: Path to the document file.
        max_pages: If set, only process the first N pages of a PDF.
                   Use max_pages=1 for lab reports (demographics are on page 1).
                   Use None for consultation docs (Khmer text throughout).
    """
    try:
        from kiri_ocr import OCR as KiriOCR
    except ImportError as import_error:
        raise ImportError('kiri-ocr not installed in current environment. Install with: pip install kiri-ocr') from import_error

    kiri_config = _get_kiri_config()
    mime_type = _infer_mime_type(file_path)

    # Force Kiri OCR to CPU if configured (reserve GPU for Ollama LLM)
    kiri_device = kiri_config.get('device', 'auto')
    if kiri_device == 'cpu':
        import torch
        if torch.cuda.is_available():
            print('DEBUG: Kiri OCR forced to CPU mode (KIRI_OCR_DEVICE=cpu) — GPU reserved for LLM', file=sys.stderr)
        # Force PyTorch to use CPU by setting device before model loads
        os.environ['CUDA_VISIBLE_DEVICES'] = ''

    global _kiri_ocr_instance
    if _kiri_ocr_instance is None:
        decode_method = kiri_config.get('decode_method', 'accurate')
        _kiri_ocr_instance = KiriOCR(decode_method=decode_method)
        # Restore CUDA_VISIBLE_DEVICES after model is loaded on CPU
        if kiri_device == 'cpu' and 'CUDA_VISIBLE_DEVICES' in os.environ:
            if os.environ['CUDA_VISIBLE_DEVICES'] == '':
                del os.environ['CUDA_VISIBLE_DEVICES']
    
    ocr = _kiri_ocr_instance

    try:
        if mime_type == 'application/pdf':
            # Kiri OCR only works with images — render PDF pages via PyMuPDF
            try:
                import fitz
            except ImportError as import_error:
                raise Exception('PyMuPDF is required for PDF + Kiri OCR flow') from import_error

            doc = fitz.open(file_path)
            total_pages = len(doc)
            pages_to_process = min(total_pages, max_pages) if max_pages else total_pages
            all_text: List[str] = []
            all_results: List[Dict[str, Any]] = []
            page_confidences: List[float] = []

            if max_pages and max_pages < total_pages:
                print(f'DEBUG: Kiri OCR processing {pages_to_process}/{total_pages} pages (first-page-only mode)', file=sys.stderr)

            for page_num in range(pages_to_process):
                page = doc.load_page(page_num)
                pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
                temp_img_path = tempfile.NamedTemporaryFile(suffix='.png', delete=False).name
                pix.save(temp_img_path)

                try:
                    text, results = ocr.extract_text(temp_img_path)
                    if text:
                        all_text.append(text)
                    # Compute average confidence for this page
                    if results:
                        page_conf = sum(r.get('confidence', 0.0) for r in results) / len(results)
                        all_results.extend(results)
                    else:
                        page_conf = 0.0
                    page_confidences.append(page_conf)
                    print(f'DEBUG: Kiri OCR processed page {page_num + 1} with confidence {page_conf:.3f}', file=sys.stderr)
                finally:
                    if os.path.exists(temp_img_path):
                        os.remove(temp_img_path)

            doc.close()
            avg_confidence = sum(page_confidences) / len(page_confidences) if page_confidences else 0.0
            joined_text = '\n'.join(all_text)
            print(f'DEBUG: Kiri OCR extracted {len(joined_text)} characters with avg confidence {avg_confidence:.3f}', file=sys.stderr)
            return {
                'text': joined_text,
                'confidence': avg_confidence,
                'results': all_results,
                'page_confidences': page_confidences,
            }

        # Image file — process directly
        text, results = ocr.extract_text(file_path)
        avg_confidence = 0.0
        if results:
            avg_confidence = sum(r.get('confidence', 0.0) for r in results) / len(results)
        print(f'DEBUG: Kiri OCR extracted {len(text)} characters with confidence {avg_confidence:.3f}', file=sys.stderr)
        return {
            'text': text,
            'confidence': avg_confidence,
            'results': results,
            'page_confidences': [avg_confidence],
        }
    finally:
        # Keep _kiri_ocr_instance alive for reuse across files in a batch.
        # The global singleton pattern already prevents re-loading.
        pass


def _detect_line_script(text: str) -> str:
    """Detect the dominant script of a text line.

    Returns 'khmer' if > 30% of alpha chars are Khmer,
    'english' if predominantly Latin, or 'mixed' otherwise.
    """
    if not text or not text.strip():
        return 'empty'

    khmer_count = 0
    latin_count = 0
    digit_count = 0

    for ch in text:
        code = ord(ch)
        if 0x1780 <= code <= 0x17FF or 0x19E0 <= code <= 0x19FF:
            khmer_count += 1
        elif (0x0041 <= code <= 0x005A) or (0x0061 <= code <= 0x007A):
            latin_count += 1
        elif 0x0030 <= code <= 0x0039:
            digit_count += 1

    total_alpha = khmer_count + latin_count
    if total_alpha == 0:
        return 'numeric' if digit_count > 0 else 'empty'

    khmer_ratio = khmer_count / total_alpha
    if khmer_ratio > 0.30:
        return 'khmer'
    elif khmer_ratio < 0.05:
        return 'english'
    else:
        return 'mixed'


def _fuse_ocr_texts(paddle_text: str, paddle_confidence: float,
                    kiri_text: str, kiri_confidence: float,
                    kiri_results: List[Dict[str, Any]]) -> Dict[str, Any]:
    """Fuse PaddleOCR and Kiri OCR outputs by selecting the best engine per line.

    Strategy:
    - Lines with Khmer script: prefer Kiri OCR (purpose-built for Khmer)
    - Lines with English/numeric: prefer PaddleOCR (strong at structured data)
    - Mixed lines: use the engine with higher overall confidence
    """
    paddle_lines = paddle_text.split('\n') if paddle_text else []
    kiri_lines = kiri_text.split('\n') if kiri_text else []

    # Build per-line confidence map from Kiri results
    kiri_line_confidences: Dict[int, float] = {}
    if kiri_results:
        for r in kiri_results:
            line_num = r.get('line_number', 0)
            if isinstance(line_num, int) and line_num > 0:
                kiri_line_confidences[line_num - 1] = r.get('confidence', 0.0)

    fused_lines: List[str] = []
    engine_choices: List[str] = []
    max_lines = max(len(paddle_lines), len(kiri_lines))

    for i in range(max_lines):
        paddle_line = paddle_lines[i].strip() if i < len(paddle_lines) else ''
        kiri_line = kiri_lines[i].strip() if i < len(kiri_lines) else ''

        # If one engine produced nothing for this line, use the other
        if not paddle_line and kiri_line:
            fused_lines.append(kiri_line)
            engine_choices.append('kiri')
            continue
        if paddle_line and not kiri_line:
            fused_lines.append(paddle_line)
            engine_choices.append('paddle')
            continue
        if not paddle_line and not kiri_line:
            continue

        # Both engines produced output — decide based on script type
        kiri_script = _detect_line_script(kiri_line)
        paddle_script = _detect_line_script(paddle_line)

        # For Khmer text, strongly prefer Kiri OCR
        if kiri_script == 'khmer' or paddle_script == 'khmer':
            fused_lines.append(kiri_line)
            engine_choices.append('kiri')
        # For English/numeric text, prefer PaddleOCR
        elif paddle_script in ('english', 'numeric'):
            fused_lines.append(paddle_line)
            engine_choices.append('paddle')
        # Mixed — use whichever engine has higher confidence
        elif kiri_script == 'mixed' or paddle_script == 'mixed':
            kiri_conf = kiri_line_confidences.get(i, kiri_confidence)
            if kiri_conf >= paddle_confidence:
                fused_lines.append(kiri_line)
                engine_choices.append('kiri')
            else:
                fused_lines.append(paddle_line)
                engine_choices.append('paddle')
        else:
            # Default: use PaddleOCR for structured lab data
            fused_lines.append(paddle_line)
            engine_choices.append('paddle')

    fused_text = '\n'.join(fused_lines)
    kiri_count = engine_choices.count('kiri')
    paddle_count = engine_choices.count('paddle')
    total = kiri_count + paddle_count

    # Weighted average confidence based on engine contribution
    if total > 0:
        fused_confidence = (kiri_confidence * kiri_count + paddle_confidence * paddle_count) / total
    else:
        fused_confidence = max(paddle_confidence, kiri_confidence)

    print(f'DEBUG: Fusion result — {kiri_count} Kiri lines, {paddle_count} Paddle lines, '
          f'fused confidence {fused_confidence:.3f}', file=sys.stderr)

    return {
        'text': fused_text,
        'confidence': fused_confidence,
        'engine_choices': engine_choices,
        'kiri_line_count': kiri_count,
        'paddle_line_count': paddle_count,
    }


class ConsultationReportParser:
    """Regex fallback parser for KV Hospital Patient Consultation forms.

    Extracts: hospital_name, physician, evaluation_date, patient_demographics,
    vital_signs (with LaTeX stripping), clinical_notes, treatment_plan.
    """

    # Regex for stripping LaTeX notation: $36,5^{\circ}C$ → 36.5
    _LATEX_RE = re.compile(r'\$([^$]+)\$')

    def _strip_latex(self, text: str) -> str:
        """Remove LaTeX wrappers and normalize notation."""
        def _clean(m):
            inner = m.group(1)
            inner = inner.replace('{\\circ}', '°')
            inner = inner.replace('^{\\circ}', '°')
            inner = inner.replace(',', '.')
            inner = re.sub(r'[°CcFf]', '', inner).strip()
            return inner
        return self._LATEX_RE.sub(_clean, text)

    def _parse_number(self, text: str) -> Optional[float]:
        """Extract first number from text, handling commas as decimal separators."""
        if not text:
            return None
        cleaned = self._strip_latex(str(text))
        cleaned = cleaned.replace(',', '.')
        m = re.search(r'(\d+\.?\d*)', cleaned)
        return float(m.group(1)) if m else None

    def parse_optimized(self, ocr_text: str) -> Dict[str, Any]:
        text = ocr_text
        text_stripped = self._strip_latex(text)

        # ── Hospital name ──
        hospital_name = 'KV Hospital'
        for marker in ['KV Hospital', 'KV HOSPITAL']:
            if marker.upper() in text.upper():
                hospital_name = 'KV Hospital'
                break

        # ── Physician ──
        physician = None
        phys_match = re.search(
            r'(?:Physician|Doctor|Médecin|Dr\.?\s*:)\s*:?\s*(Dr\.?\s*[A-Za-z\s.]+)',
            text, re.IGNORECASE
        )
        if phys_match:
            physician = phys_match.group(1).strip()

        # ── Evaluation date → standardize to YYYY-MM-DD HH:MM:SS ──
        evaluation_date = None
        date_match = re.search(
            r'(?:Date|Evaluation)\s*:?\s*(\d{2})/(\d{2})/(\d{4})\s+(\d{2}:\d{2}(?::\d{2})?)',
            text, re.IGNORECASE
        )
        if date_match:
            d, mo, y, t = date_match.group(1), date_match.group(2), date_match.group(3), date_match.group(4)
            if len(t) == 5:
                t += ':00'
            evaluation_date = f'{y}-{mo}-{d} {t}'

        # ── Patient demographics ──
        name_khmer = None
        # Match Khmer script name (sequence of Khmer chars + spaces)
        khmer_name_match = re.search(
            r'(?:Patient|ឈ្មោះ|នាម)\s*:?\s*([\u1780-\u17FF\s]{2,})',
            text
        )
        if khmer_name_match:
            name_khmer = khmer_name_match.group(1).strip()

        gender = None
        gender_match = re.search(r'(?:Gender|ភេទ)\s*:?\s*(Male|Female|ប្រុស|ស្រី)', text, re.IGNORECASE)
        if gender_match:
            g = gender_match.group(1).strip()
            if g in ('ប្រុស', 'Male', 'male'):
                gender = 'Male'
            elif g in ('ស្រី', 'Female', 'female'):
                gender = 'Female'
            else:
                gender = g.capitalize()

        payment_type = None
        pay_match = re.search(r'(?:Payment|Paiement|ប្រភេទ)\s*:?\s*(\S+)', text, re.IGNORECASE)
        if pay_match:
            payment_type = pay_match.group(1).strip()

        # Age: "26ឆ្នាំ 3ខែ 0ថ្ងៃ" or "26 Y 3 M 0 D" or "26ឆ្នាំ/year"
        age = {'years': None, 'months': None, 'days': None}
        age_match = re.search(
            r'(\d+)\s*(?:ឆ្នាំ|Y(?:ear)?)\s*[\s,]*(\d+)\s*(?:ខែ|M(?:onth)?)\s*[\s,]*(\d+)\s*(?:ថ្ងៃ|D(?:ay)?)',
            text, re.IGNORECASE
        )
        if age_match:
            age = {
                'years': int(age_match.group(1)),
                'months': int(age_match.group(2)),
                'days': int(age_match.group(3))
            }
        else:
            # Simpler: just years
            age_simple = re.search(r'(\d+)\s*(?:ឆ្នាំ|Y)', text, re.IGNORECASE)
            if age_simple:
                age = {'years': int(age_simple.group(1)), 'months': 0, 'days': 0}

        # ── Vital signs ──
        systolic = None
        diastolic = None
        bp_match = re.search(r'(?:Tension|TA|BP)\s*[^:]*:?\s*\$?(\d{2,3})\s*/\s*(\d{2,3})\$?', text_stripped, re.IGNORECASE)
        if not bp_match:
            bp_match = re.search(r'(\d{2,3})\s*/\s*(\d{2,3})\s*(?:mmHg|mm)', text_stripped, re.IGNORECASE)
        if bp_match:
            systolic = int(bp_match.group(1))
            diastolic = int(bp_match.group(2))

        pulse = self._parse_number(
            self._find_vital(text, ['Pouls', 'Pulse', 'FC', 'Heart Rate'])
        )

        resp_rate = self._parse_number(
            self._find_vital(text, ['FR', 'Respiratory', 'Resp Rate'])
        )

        temp = None
        temp_match = re.search(
            r'(?:Temp|Temperature|T°)\s*[^:]*:?\s*\$?(\d{2}[,.]?\d*)\s*(?:\^?\{?\\?circ\}?)?[°]?\s*C?\$?',
            text, re.IGNORECASE
        )
        if temp_match:
            temp = float(temp_match.group(1).replace(',', '.'))

        spo2 = self._parse_number(
            self._find_vital(text, ['SpO2', 'O2', 'Saturation'])
        )

        height = self._parse_number(
            self._find_vital(text, ['Taille', 'Height', 'Ht'])
        )

        weight = self._parse_number(
            self._find_vital(text, ['Poids', 'Weight', 'Wt'])
        )

        # ── Clinical notes ──
        chief_complaint = None
        cc_match = re.search(
            r'(?:Chief\s*Complaint|Motif|CC)\s*:?\s*(.+?)(?=\n|Current|Medication|$)',
            text, re.IGNORECASE
        )
        if cc_match:
            chief_complaint = cc_match.group(1).strip()

        current_medications = None
        med_match = re.search(
            r'(?:Current\s*Medications?|Traitement|Médicaments)\s*:?\s*(.+?)(?=\n|Prescription|Laboratory|$)',
            text, re.IGNORECASE
        )
        if med_match:
            current_medications = med_match.group(1).strip()

        # ── Treatment plan ──
        prescription_id = None
        rx_match = re.search(r'(?:Prescription\s*ID|RX)\s*:?\s*(\S+)', text, re.IGNORECASE)
        if rx_match:
            prescription_id = rx_match.group(1).strip()

        laboratory_id = None
        lab_match = re.search(r'(?:Laboratory\s*ID|Lab\s*ID)\s*:?\s*(LT\d+|\S+)', text, re.IGNORECASE)
        if lab_match:
            laboratory_id = lab_match.group(1).strip()

        return {
            'hospital_name': hospital_name,
            'document_type': 'Patient Consultation Information',
            'physician': physician,
            'evaluation_date': evaluation_date,
            'patient_demographics': {
                'name_khmer': name_khmer,
                'gender': gender,
                'payment_type': payment_type,
                'age': age,
            },
            'vital_signs': {
                'systolic_mmhg': systolic,
                'diastolic_mmhg': diastolic,
                'pulse_bpm': int(pulse) if pulse else None,
                'respiratory_rate_per_mn': int(resp_rate) if resp_rate else None,
                'temperature_celsius': temp,
                'oxygen_saturation_percentage': int(spo2) if spo2 else None,
                'height_cm': int(height) if height else None,
                'weight_kg': int(weight) if weight else None,
            },
            'clinical_notes': {
                'chief_complaint': chief_complaint,
                'current_medications': current_medications,
            },
            'treatment_plan': {
                'prescription_id': prescription_id,
                'laboratory_id': laboratory_id,
            },
        }

    def _find_vital(self, text: str, keywords: List[str]) -> Optional[str]:
        """Find a vital sign value by trying multiple keyword labels."""
        for kw in keywords:
            match = re.search(
                rf'{re.escape(kw)}\s*[^:]*:?\s*\$?([^$\n]+)\$?',
                text, re.IGNORECASE
            )
            if match:
                return match.group(1).strip()
        return None


class OptimizedLabReportParser:
    def __init__(self):
        self.failed_patterns = []
        # Field keys — include English-only variants so garbled Khmer prefixes still match
        self.patient_fields = {
            'name': ['ឈោ្មះ/Name', '/Name', 'Name'],
            'patient_id': ['Patient ID'],
            'age': ['អាយុ/Age', '/Age', 'Age'],
            'gender': ['ភេទ/Gender', '/Gender', 'Gender'],
            'phone': ['ទូរស័ព្ទ/Phone', 'លេខទូរស័ព្ទ', '/Phone', 'Phone']
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

        # Map to normalise garbled keys like 'min:/Name' → '/Name'
        # Matches any line ending with /EnglishLabel
        self._english_suffix_re = re.compile(r'[/](Name|Age|Gender|Phone)$', re.IGNORECASE)

        self.compiled_patterns = {
            # --- Patient fields: use .*?/Name so garbled Khmer prefix is ignored ---
            'name': re.compile(
                r'(?:.*?/)Name\s*:?\s*\n?:?\s*([A-Z][A-Za-z\s.]+?)'
                r'(?=\s{3,}|\s*(?:Patient|/Age|\n|$))',
                re.UNICODE | re.MULTILINE
            ),
            'patient_id': re.compile(r'Patient\s*ID\s*:?\s*\n?:?\s*(PT\d+)'),
            'age': re.compile(
                r'(?:.*?/)Age\s*:?\s*\n?:?\s*(\d+\s*Y(?:[,.]?\s*\d+\s*M)?(?:[,.]?\s*\d+\s*D)?)',
                re.MULTILINE
            ),
            'gender': re.compile(
                r'(?:.*?/)Gender\s*:?\s*\n?:?\s*(Male|Female)',
                re.IGNORECASE | re.MULTILINE
            ),
            'phone': re.compile(
                r'(?:.*?/)Phone\s*:?\s*\n?:?\s*(0\d{8,9})',
                re.MULTILINE
            ),
            # --- Lab fields (English-only, already mostly working) ---
            'lab_id': re.compile(r'Lab\s*ID\s*:?\s*\n?:?\s*(LT\d+)'),
            'requested_by': re.compile(
                r'Requested\s*By\s*:?\s*\n?:?\s*(Dr\.?\s*[A-Za-z\s.]+?)'
                r'(?=\s*(?:Collected|Analysis|Lab|LABORATORY|\n|$))',
                re.UNICODE | re.MULTILINE
            ),
            'requested_date': re.compile(
                r'Requested\s*Date\s*:?\s*\n?:?\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})'
            ),
            'collected_date': re.compile(
                r'Collected\s*Date\s*:?\s*\n?:?\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})'
            ),
            'analysis_date': re.compile(
                r'Analysis\s*Date\s*:?\s*\n?:?\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})'
            ),
            # Validated By — try to grab the name on same or next line
            'validated_by': re.compile(
                r'(?:Lab\s*Technician|Validated\s*By)\s*:?\s*\n?'
                r'([\u1780-\u17FF\sA-Za-z.]+?)'
                r'(?=\s*(?:202[3-9]|\n|$))',
                re.UNICODE | re.MULTILINE
            ),
            'category': re.compile(
                r'\b(BIOCH(?:I|E)MISTRY|ENZYMOLOGY|HEMATOLOGY|'
                r'SERO\s*/?\s*IMMUNOLOGY|URINE\s*ANALYSIS|DRUG\s*URINE|'
                r'ABO\s*Blood\s*Group)\b',
                re.IGNORECASE
            ),
            'test_row': re.compile(
                r'^(?P<test_name>(?:Creatinine,?\s*serum|Urea/BUN|Glucose|'
                r'Cholesterole?\s*Total|Cholesterol-HDL|Cholesterol-LDL|'
                r'Tryglyceride|Uric\s*acide|'
                r'GGT\s*\(Gamm\s*Glutamyl\s*Transferas\)|SGPT/ALT|SGOT/AST|'
                r'Morphine|Amphetamine|Metamphetamine|'
                r'WBC|LYM%|MONO%|NUE%|EOSINO%|BASO%|HGB|MCH|MCHC|RBC|MCV|'
                r'LEU|NIT|URO|PRO|PH|BLO|SG|KET|BIL|GLU|ASC|'
                r'Group|Rhesus))'
                r'\s*:?\s*'
                r'(?P<result>\d+\.?\d*|NEGATIVE|POSITIVE|[ABO]{1,2}|○)\s*'
                r'(?P<unit>(?:mg/dL|U/L|%|\$U/L\$|g/dL|Leu/µL|Ery/pl|'
                r'x?X?1012/L|10[⁹9]/L|fl|\$10\^{9}/L\$|pg|응|%0|0P|09)?)?\s*'
                r'(?P<reference_range>(?:\(?[^)\n]+\)?|\$\([^)]+\)\$)?)?\s*'
                r'(?P<flag>[HLhl1](?:\s+[HLhl1])?)?\s*$',
                re.MULTILINE | re.IGNORECASE
            )
        }
        self.hospital_phone_patterns = [
            '097 840 47 89',
            '012 89 17 45',
            '012 28 60 70'
        ]
        self.excluded_test_names = {
            'HOSPITAL', 'Results', 'Unit', 'Reference Range', 'Flag',
            'CBC', 'TRANSAMINASE', 'DRUG URINE', 'HEMATOLOGY',
            'URINE ANALYSIS 11 TEST'
        }

    @staticmethod
    def _normalize_key(raw_key: str) -> str:
        """Normalise a garbled OCR key to its English field name.

        Examples:
            'min:/Name'   → '/Name'
            'In 9/Gender' → '/Gender'
            'Patient ID'  → 'Patient ID'  (unchanged)
        """
        # If the key ends with /EnglishLabel, extract that suffix
        m = re.search(r'[/](Name|Age|Gender|Phone)$', raw_key, re.IGNORECASE)
        if m:
            return '/' + m.group(1)
        return raw_key.strip()

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

            # Normalise garbled keys like 'min:/Name' → '/Name'
            normalised = self._normalize_key(line)

            # Case 1: key-on-one-line, value-starts-with-colon on next line
            #   e.g.  line[i]   = 'min:/Name'
            #         line[i+1] = ': HENG VANNAT'
            if normalised in self.all_field_keys or normalised != line:
                lookup_key = normalised if normalised in self.all_field_keys else line
                value = self._find_value_fast(header_lines, i + 1, processed_indices)
                if value and not self._is_hospital_phone_fast(lookup_key, value):
                    field_map[normalised] = value
                    processed_indices.add(i)
                    continue

            # Case 2: 'Key : Value' on a single line (but NOT garbled /Name lines)
            if ':' in line and not line.startswith(':'):
                key, value = line.split(':', 1)
                key, value = key.strip(), value.strip()
                norm_key = self._normalize_key(key)
                if key and value and not self._is_hospital_phone_fast(key, value):
                    field_map[norm_key if norm_key in self.all_field_keys else key] = value
                    processed_indices.add(i)
                    continue

            # Case 3: exact match in all_field_keys (English-only keys)
            if line in self.all_field_keys:
                value = self._find_value_fast(header_lines, i + 1, processed_indices)
                if value and not self._is_hospital_phone_fast(line, value):
                    field_map[line] = value
                    processed_indices.add(i)
        return field_map

    def _find_header_boundaries(self, lines: List[str]) -> tuple:
        header_start = 0
        header_end = len(lines)
        # Detect header start: look for any known field key OR garbled /Name, /Age etc.
        header_keywords_re = re.compile(
            r'(?:/Name|/Age|/Gender|/Phone|Patient\s*ID|Lab\s*ID|Requested)',
            re.IGNORECASE
        )
        category_keywords = [
            'LABORATORY REPORT', 'BIOCHIMISTRY', 'BIOCHEMISTRY',
            'ENZYMOLOGY', 'HEMATOLOGY', 'DRUG URINE', 'URINE ANALYSIS',
            'ABO BLOOD GROUP'
        ]
        for idx, line in enumerate(lines):
            if header_start == 0 and header_keywords_re.search(line):
                header_start = idx
            upper = line.upper()
            if header_start > 0 and any(cat in upper for cat in category_keywords):
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
        phone_keys = {'ទូរស័ព្ទ/Phone', 'លេខទូរស័ព្ទ', '/Phone', 'Phone'}
        if key not in phone_keys:
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
                    elif field_type == 'validated_by':
                        val = match.group(1).strip() if match.groups() else ''
                        # Filter out single-char garbage from signature areas
                        if len(val) >= 2:
                            corrected[field_keys[0]] = val
                    else:
                        corrected[field_keys[0]] = match.group(1).strip() if match.groups() else match.group(0).strip()
        return corrected

    def parse_test_results(self, lines: List[str]) -> List[Dict[str, Any]]:
        test_results = []
        current_category = None
        full_text = '\n'.join(lines)

        # Pre-merge multi-line test names:
        # 'Creatinine,\nserum' → 'Creatinine, serum'
        full_text = re.sub(r'Creatinine,\s*\n\s*serum', 'Creatinine, serum', full_text)

        category_matches = list(self.compiled_patterns['category'].finditer(full_text))
        category_ranges = [(m.start(), m.end(), m.group(1)) for m in category_matches]
        category_ranges.append((len(full_text), len(full_text), None))

        # Track seen test names to avoid duplicates
        seen_tests = set()
        
        for i, (start, end, category) in enumerate(category_ranges[:-1]):
            if category:
                # Normalise category name
                cat_upper = category.upper()
                current_category = cat_upper \
                    .replace('BIOCHIMISTRY', 'BIOCHEMISTRY') \
                    .replace('ABO BLOOD GROUP', 'BLOOD GROUP')
                section_text = full_text[start:category_ranges[i+1][0]]

                # Extract flag lines that appear after results (e.g., 'H', 'L', 'H H')
                # Build a map of line positions → flags from the section
                section_lines = section_text.split('\n')
                line_flags = {}
                for li, sl in enumerate(section_lines):
                    stripped = sl.strip()
                    # Standalone flag lines: 'H', 'L', 'H H', 'Н' (Cyrillic H)
                    if re.match(r'^[HLhlНн](?:\s+[HLhlНн])?$', stripped):
                        line_flags[li] = stripped[0].upper()
                        if stripped[0] in 'Нн':  # Cyrillic H → Latin H
                            line_flags[li] = 'H'

                matches = list(self.compiled_patterns['test_row'].finditer(section_text))
                for match in matches:
                    test_name = match.group('test_name').strip()
                    if test_name in self.excluded_test_names:
                        continue
                    # Skip spurious 'serum' entry
                    if test_name.lower() == 'serum':
                        continue

                    test_names = [t.strip() for t in re.split(r'\n|TRANSAMINASE', test_name)
                                  if t.strip() and t.strip() not in self.excluded_test_names]
                    result = match.group('result')
                    flag = match.group('flag') if match.group('flag') else None
                    unit = match.group('unit') if match.group('unit') else None
                    reference_range = match.group('reference_range') if match.group('reference_range') else None

                    # Normalise flag: 'H H' → 'H', Cyrillic 'Н' → 'H', OCR '1' → 'H'
                    if flag:
                        flag = flag.strip().split()[0].upper()
                        if flag in ('Н', 'н', '1'):
                            flag = 'H'

                    # If no flag from inline regex, check if there's a flag line nearby
                    if not flag:
                        # Find the line index AFTER this match's end
                        match_end_pos = match.end()
                        match_end_line = section_text[:match_end_pos].count('\n')
                        # Check lines after the match end (up to 6 lines)
                        for offset in range(0, 7):
                            check_idx = match_end_line + offset
                            if check_idx in line_flags:
                                flag = line_flags[check_idx]
                                break

                    if reference_range:
                        reference_range = re.sub(r'[^\(\)\d\.\-\s\$\>]', '', reference_range).strip()

                    for tn in test_names:
                        tn = tn.replace('Cholesterol Total', 'Cholesterole Total').replace('Uric acide', 'Uric acide')
                        if 'GGT' in tn and '(Gamm Glutamyl Transferas) (Gamm Glutamyl Transferas)' in tn:
                            tn = 'GGT (Gamm Glutamyl Transferas)'
                        
                        # Skip duplicates
                        test_key = f"{current_category}:{tn}"
                        if test_key in seen_tests:
                            continue
                        seen_tests.add(test_key)

                        if tn == 'Creatinine, serum':
                            unit = 'mg/dL'
                            reference_range = '(0.9 - 1.1)'
                        elif tn == 'Urea/BUN':
                            unit = 'mg/dL'
                            reference_range = '(6.0 - 40.0)'
                        elif tn == 'Cholesterole Total':
                            unit = 'mg/dL'
                            reference_range = '$(0-200)$'
                        elif tn == 'Cholesterol-HDL':
                            unit = 'mg/dL'
                            reference_range = '(>60)'
                        elif tn == 'Cholesterol-LDL':
                            unit = 'mg/dL'
                            reference_range = '$(0-150)$'
                        elif tn == 'Tryglyceride':
                            unit = 'mg/dL'
                            reference_range = '$(0-150)$'
                        elif tn == 'Uric acide':
                            unit = 'mg/dL'
                            reference_range = '$(3.5-6.0)$'
                        elif tn == 'GGT (Gamm Glutamyl Transferas)':
                            unit = 'U/L'
                            reference_range = '$(0-55)$'
                        elif tn == 'SGPT/ALT':
                            unit = '$U/L$'
                            reference_range = '$(0-41)$'
                        elif tn == 'SGOT/AST':
                            unit = '$U/L$'
                            reference_range = '$(0-40)$'
                        elif tn == 'Morphine':
                            unit = None
                            reference_range = None
                        elif tn == 'Amphetamine':
                            unit = None
                            reference_range = None
                        elif tn == 'Metamphetamine':
                            unit = None
                            reference_range = None
                        elif tn == 'WBC':
                            unit = '$10^{9}/L$'
                            reference_range = '(3.5-10.0)'
                        elif tn == 'LYM%':
                            unit = '%'
                            reference_range = '(15.0-50.0)'
                        elif tn == 'MONO%':
                            unit = '%'
                            reference_range = '(2.0-15.0)'
                        elif tn == 'NUE%':
                            unit = '%'
                            reference_range = '(35.0-80.0)'
                        elif tn == 'EOSINO%':
                            unit = '%'
                            reference_range = '(11.5-16.5)'
                        elif tn == 'BASO%':
                            unit = '%'
                            reference_range = '(25.0-35.0)'
                        elif tn == 'HGB':
                            unit = 'g/dL'
                            reference_range = '(31.0-38.0)'
                        elif tn == 'MCH':
                            unit = 'pg'
                            reference_range = '(3.50-5.50)'
                        elif tn == 'MCHC':
                            unit = 'g/dL'
                            reference_range = '(75.0-100.0)'
                        elif tn == 'RBC':
                            unit = 'x1012/L'
                            reference_range = '(35.0-55.0)'
                        elif tn == 'MCV':
                            unit = 'fl'
                            reference_range = '(150-400)'
                        elif tn == 'LEU':
                            unit = 'Leu/µL'
                            reference_range = None
                        elif tn == 'NIT':
                            unit = None
                            reference_range = None
                        elif tn == 'URO':
                            unit = 'mg/dL'
                            reference_range = None
                        elif tn == 'PRO':
                            unit = 'mg/dL'
                            reference_range = None
                        elif tn == 'PH':
                            unit = None
                            reference_range = None
                        elif tn == 'BLO':
                            unit = 'Ery/pl'
                            reference_range = None
                        elif tn == 'SG':
                            unit = None
                            reference_range = None
                        elif tn == 'KET':
                            unit = 'mg/dL'
                            reference_range = None
                        elif tn == 'BIL':
                            unit = 'mg/dL'
                            reference_range = None
                        elif tn == 'GLU':
                            unit = 'mg/dL'
                            reference_range = None
                        elif tn == 'ASC':
                            unit = 'mg/dL'
                            reference_range = None
                        elif tn == 'Group':
                            unit = None
                            reference_range = None
                        elif tn == 'Rhesus':
                            unit = None
                            reference_range = None
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

def process_with_google_document_ai(file_path: str) -> str:
    try:
        from google.cloud import documentai
        from google.oauth2 import service_account
        from google.api_core import exceptions
    except ImportError as import_error:
        raise RuntimeError(f'Google Document AI dependencies unavailable: {import_error}') from import_error

    retries = 3
    for attempt in range(retries):
        try:
            google_config = _get_google_config()
            project_id = google_config['project_id']
            location = google_config['location']
            processor_id = google_config['processor_id']
            credentials_path = google_config['credentials_path']
            
            print(f'DEBUG: Looking for credentials at: {credentials_path}', file=sys.stderr)
            if not os.path.exists(credentials_path):
                raise FileNotFoundError(f'Credentials file not found: {credentials_path}')
            
            credentials = service_account.Credentials.from_service_account_file(credentials_path)
            client_options = {'api_endpoint': f'{location}-documentai.googleapis.com'} if location not in ['us', 'eu'] else None
            client = documentai.DocumentProcessorServiceClient(credentials=credentials, client_options=client_options)
            name = client.processor_path(project_id, location, processor_id)
            
            mime_type = _infer_mime_type(file_path)
            if mime_type == 'application/octet-stream':
                raise ValueError(f'Unsupported file extension for OCR: {file_path}')

            doc_content = _preprocess_image_for_ocr(file_path)
            
            print(f'DEBUG: Processing {os.path.basename(file_path)} as {mime_type} with Google Document AI...', file=sys.stderr)
            
            request = documentai.ProcessRequest(
                name=name,
                raw_document=documentai.RawDocument(
                    content=doc_content,
                    mime_type=mime_type
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
            if _infer_mime_type(file_path) == 'application/pdf':
                return extract_text_from_pdf_local(file_path)
            raise

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


def _normalize_consultation_to_frontend(result: Dict[str, Any]) -> Dict[str, Any]:
    """Map consultation extraction schema into the patientInfo/testResults format
    that the frontend verification page expects.

    The frontend reads:
      - patientInfo: { name, patientId, age, gender, phone }
      - labInfo: { labId, requestedBy, requestedDate, ... }
      - testResults: [ { category, testName, result, unit, referenceRange, flag } ]

    Consultation forms extract:
      - patient_demographics: { name_khmer, gender, payment_type, age }
      - vital_signs: { systolic_mmhg, diastolic_mmhg, pulse_bpm, ... }
      - clinical_notes: { chief_complaint, current_medications }
      - treatment_plan: { prescription_id, laboratory_id }
    """
    demo = result.get('patient_demographics', {}) or {}
    vital = result.get('vital_signs', {}) or {}
    clinical = result.get('clinical_notes', {}) or {}
    treatment = result.get('treatment_plan', {}) or {}

    # ── Map patient_demographics → patientInfo ──
    age_obj = demo.get('age', {})
    if isinstance(age_obj, dict):
        age_parts = []
        if age_obj.get('years'):
            age_parts.append(f"{age_obj['years']} Y")
        if age_obj.get('months'):
            age_parts.append(f"{age_obj['months']} M")
        if age_obj.get('days'):
            age_parts.append(f"{age_obj['days']} D")
        age_str = ', '.join(age_parts) if age_parts else ''
    else:
        age_str = str(age_obj) if age_obj else ''

    gender_raw = demo.get('gender', '') or ''
    # Normalize 'Male'/'Female' to match frontend display
    gender_str = gender_raw

    result['patientInfo'] = {
        'name': demo.get('name_khmer', '') or result.get('physician', '') or '',
        'patientId': '',
        'age': age_str,
        'gender': gender_str,
        'phone': '',
    }

    # ── Map physician + evaluation_date → labInfo ──
    result['labInfo'] = {
        'labId': treatment.get('laboratory_id', '') or '',
        'requestedBy': result.get('physician', '') or '',
        'requestedDate': result.get('evaluation_date', '') or '',
        'collectedDate': '',
        'analysisDate': '',
        'validatedBy': result.get('physician', '') or '',
    }

    # ── Map vital_signs + clinical_notes + treatment_plan → testResults ──
    test_results = []

    # Vital signs as individual "test results"
    vital_sign_labels = {
        'systolic_mmhg': ('Systolic BP', 'mmHg', '90 - 140'),
        'diastolic_mmhg': ('Diastolic BP', 'mmHg', '60 - 90'),
        'pulse_bpm': ('Pulse', '/mn', '60 - 100'),
        'respiratory_rate_per_mn': ('Respiratory Rate', '/mn', '12 - 20'),
        'temperature_celsius': ('Temperature', '°C', '36.1 - 37.2'),
        'oxygen_saturation_percentage': ('O2 Saturation', '%', '95 - 100'),
        'height_cm': ('Height', 'cm', ''),
        'weight_kg': ('Weight', 'kg', ''),
    }

    for key, (label, unit, ref_range) in vital_sign_labels.items():
        value = vital.get(key)
        if value is not None:
            test_results.append({
                'category': 'VITAL SIGNS',
                'testName': label,
                'result': str(value),
                'unit': unit,
                'referenceRange': ref_range,
                'flag': None,
            })

    # Clinical notes as test results
    if clinical.get('chief_complaint'):
        test_results.append({
            'category': 'CLINICAL NOTES',
            'testName': 'Chief Complaint',
            'result': clinical['chief_complaint'],
            'unit': '',
            'referenceRange': '',
            'flag': None,
        })
    if clinical.get('current_medications'):
        test_results.append({
            'category': 'CLINICAL NOTES',
            'testName': 'Current Medications',
            'result': clinical['current_medications'],
            'unit': '',
            'referenceRange': '',
            'flag': None,
        })

    # Treatment plan items as test results
    if treatment.get('prescription_id'):
        test_results.append({
            'category': 'TREATMENT PLAN',
            'testName': 'Prescription',
            'result': treatment['prescription_id'],
            'unit': '',
            'referenceRange': '',
            'flag': None,
        })
    if treatment.get('laboratory_id'):
        test_results.append({
            'category': 'TREATMENT PLAN',
            'testName': 'Laboratory',
            'result': treatment['laboratory_id'],
            'unit': '',
            'referenceRange': '',
            'flag': None,
        })

    # Payment type and evaluation summary as info
    if demo.get('payment_type'):
        test_results.append({
            'category': 'PATIENT INFO',
            'testName': 'Payment Type',
            'result': demo['payment_type'],
            'unit': '',
            'referenceRange': '',
            'flag': None,
        })

    if result.get('evaluation_summary'):
        test_results.append({
            'category': 'CLINICAL NOTES',
            'testName': 'Evaluation Summary',
            'result': result['evaluation_summary'],
            'unit': '',
            'referenceRange': '',
            'flag': None,
        })

    result['testResults'] = test_results

    return result


def process_single_file(file_path: str, template: Optional[Dict[str, Any]] = None, document_type: Optional[str] = None) -> Dict[str, Any]:
    paddle_config = {}
    kiri_config = {}
    ocr_text = None
    confidence = 0.0
    ocr_engine = 'unknown'
    parser = None
    extraction_method = 'regex'
    
    try:
        mime_type = _infer_mime_type(file_path)
        paddle_config = _get_paddle_config()
        kiri_config = _get_kiri_config()
        
        if mime_type in {'application/pdf', 'image/jpeg', 'image/png', 'image/tiff', 'image/bmp', 'image/gif', 'image/webp'}:
            # ── Dual-engine mode: run BOTH Paddle + Kiri, fuse results ──
            if paddle_config['enabled'] and kiri_config['enabled']:
                paddle_result = None
                kiri_result = None

                # Run PaddleOCR (best for structured English/numeric lab data)
                try:
                    paddle_result = process_with_paddle_ocr(file_path)
                    print(f'DEBUG: PaddleOCR pass — {len(paddle_result["text"])} chars, confidence {paddle_result["confidence"]:.3f}', file=sys.stderr)
                except Exception as paddle_error:
                    print(f'DEBUG: PaddleOCR failed ({paddle_error})', file=sys.stderr)

                if document_type == 'consultation':
                    # Consultation docs: run Kiri OCR on ALL pages (Khmer text throughout)
                    try:
                        kiri_result = process_with_kiri_ocr(file_path)
                        print(f'DEBUG: Kiri OCR pass (full) — {len(kiri_result["text"])} chars, confidence {kiri_result["confidence"]:.3f}', file=sys.stderr)
                    except Exception as kiri_error:
                        print(f'DEBUG: Kiri OCR failed ({kiri_error})', file=sys.stderr)
                else:
                    # Lab reports: run Kiri OCR on FIRST PAGE ONLY (patient demographics/Khmer names)
                    try:
                        kiri_result = process_with_kiri_ocr(file_path, max_pages=1)
                        print(f'DEBUG: Kiri OCR pass (page 1 only) — {len(kiri_result["text"])} chars, confidence {kiri_result["confidence"]:.3f}', file=sys.stderr)
                    except Exception as kiri_error:
                        print(f'DEBUG: Kiri OCR failed ({kiri_error})', file=sys.stderr)

                # Fuse results from both engines
                if paddle_result and kiri_result:
                    fused = _fuse_ocr_texts(
                        paddle_result['text'], paddle_result['confidence'],
                        kiri_result['text'], kiri_result['confidence'],
                        kiri_result.get('results', []),
                    )
                    ocr_text = fused['text']
                    confidence = fused['confidence']
                    ocr_engine = f'fused (paddle={fused.get("paddle_line_count", 0)}, kiri={fused.get("kiri_line_count", 0)})'
                    print(f'DEBUG: Fused output — {len(ocr_text)} chars, confidence {confidence:.3f}', file=sys.stderr)
                elif paddle_result:
                    ocr_text = paddle_result['text']
                    confidence = paddle_result['confidence']
                    ocr_engine = 'paddle (kiri unavailable)'
                elif kiri_result:
                    ocr_text = kiri_result['text']
                    confidence = kiri_result['confidence']
                    ocr_engine = 'kiri (paddle unavailable)'
                else:
                    # Both failed — try Google as last resort
                    print(f'DEBUG: Both local OCR engines failed, trying Google', file=sys.stderr)
                    try:
                        ocr_text = process_with_google_document_ai(file_path)
                        ocr_engine = 'google (fallback from local engines)'
                    except Exception as google_error:
                        raise Exception(f'All OCR engines failed. Google: {google_error}')

                # If fused/paddle confidence is below threshold, try Google as enhancement
                if confidence < paddle_config['confidence_threshold'] and not ocr_engine.startswith('google'):
                    print(f'DEBUG: OCR confidence {confidence:.3f} below {paddle_config["confidence_threshold"]}, trying Google', file=sys.stderr)
                    try:
                        google_text = process_with_google_document_ai(file_path)
                        ocr_text = google_text
                        ocr_engine = f'google (fallback from low-confidence)'
                    except Exception as google_fallback_error:
                        print(f'DEBUG: Google fallback unavailable ({google_fallback_error}); keeping local OCR output', file=sys.stderr)

            # ── Paddle-only mode ──
            elif paddle_config['enabled']:
                try:
                    paddle_result = process_with_paddle_ocr(file_path)
                    ocr_text = paddle_result['text']
                    confidence = paddle_result['confidence']
                    ocr_engine = 'paddle'

                    if confidence < paddle_config['confidence_threshold']:
                        print(f'DEBUG: PaddleOCR confidence {confidence:.3f} below threshold {paddle_config["confidence_threshold"]}, falling back to Google Document AI', file=sys.stderr)
                        try:
                            ocr_text = process_with_google_document_ai(file_path)
                            ocr_engine = 'google (fallback from paddle)'
                        except Exception as google_fallback_error:
                            print(f'DEBUG: Google fallback unavailable ({google_fallback_error}); keeping low-confidence Paddle output', file=sys.stderr)
                            ocr_engine = 'paddle (low-confidence; google unavailable)'
                except Exception as paddle_error:
                    print(f'DEBUG: PaddleOCR failed ({paddle_error}), falling back to Google Document AI', file=sys.stderr)
                    ocr_text = process_with_google_document_ai(file_path)
                    ocr_engine = 'google (fallback from paddle)'

            # ── Kiri-only mode ──
            elif kiri_config['enabled']:
                try:
                    kiri_result = process_with_kiri_ocr(file_path)
                    ocr_text = kiri_result['text']
                    confidence = kiri_result['confidence']
                    ocr_engine = 'kiri'

                    if confidence < kiri_config['confidence_threshold']:
                        print(f'DEBUG: Kiri OCR confidence {confidence:.3f} below threshold, falling back to Google Document AI', file=sys.stderr)
                        try:
                            ocr_text = process_with_google_document_ai(file_path)
                            ocr_engine = 'google (fallback from kiri)'
                        except Exception as google_fallback_error:
                            print(f'DEBUG: Google fallback unavailable ({google_fallback_error}); keeping low-confidence Kiri output', file=sys.stderr)
                            ocr_engine = 'kiri (low-confidence; google unavailable)'
                except Exception as kiri_error:
                    print(f'DEBUG: Kiri OCR failed ({kiri_error}), falling back to Google Document AI', file=sys.stderr)
                    ocr_text = process_with_google_document_ai(file_path)
                    ocr_engine = 'google (fallback from kiri)'

            # ── No local engine — Google only ──
            else:
                ocr_text = process_with_google_document_ai(file_path)
                ocr_engine = 'google'
        else:
            with open(file_path, 'r', encoding='utf-8') as f:
                ocr_text = f.read()
            ocr_engine = 'text'

        # ── Intelligence Layer: LLM-first, regex-fallback ──
        result = None
        if template:
            llm_result = extract_with_llm(ocr_text, template)
            if llm_result:
                result = validate_extraction(llm_result)
                extraction_method = 'llm'
                print(f'DEBUG: LLM extraction succeeded — {len(result.get("testResults", []))} tests', file=sys.stderr)

        # Fallback to regex parser if LLM failed or no template
        if result is None:
            doc_type = document_type or detect_document_type(ocr_text)
            is_consult = doc_type in ('consultation', 'Patient Consultation Information') or (detect_document_type(ocr_text) == 'consultation')
            if is_consult:
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
            doc_type = document_type or detect_document_type(ocr_text)
            is_consult = doc_type in ('consultation', 'Patient Consultation Information') or (detect_document_type(ocr_text) == 'consultation')
            if is_consult:
                result = validate_consultation_extraction(result)

        result['source_file'] = os.path.basename(file_path)
        result['success'] = True
        result['ocr_engine'] = ocr_engine
        result['extraction_method'] = extraction_method
        result['rawText'] = ocr_text
        result['confidence'] = confidence

        # ── Normalize consultation data into the frontend-expected format ──
        # The frontend verification page reads patientInfo, labInfo, testResults
        # but consultation forms store data as patient_demographics, vital_signs, etc.
        doc_type_final = document_type or (result.get('document_type') if isinstance(result, dict) else None) or detect_document_type(ocr_text)
        is_consult_final = doc_type_final in ('consultation', 'Patient Consultation Information') or (detect_document_type(ocr_text) == 'consultation')
        if is_consult_final:
            result = _normalize_consultation_to_frontend(result)

        return result
    except Exception as e:
        return {
            'source_file': os.path.basename(file_path),
            'error': str(e),
            'success': False,
            'rawText': ocr_text,
            'ocr_engine': ocr_engine,
            'extraction_method': extraction_method,
            'debug': {
                'ocr_length': len(ocr_text) if ocr_text else 0,
                'failed_patterns': getattr(parser, 'failed_patterns', []) if parser else []
            }
        }

def process_batch_parallel(file_paths: List[str], max_workers: Optional[int] = None, template: Optional[Dict[str, Any]] = None, document_type: Optional[str] = None, stream: bool = False):
    # When using LLM, serialize to avoid GPU contention
    if template and max_workers is None:
        max_workers = 1
    elif max_workers is None:
        max_workers = min(len(file_paths), 3)
    engine_desc = 'LLM + PaddleOCR' if template else 'PaddleOCR + Google fallback'
    print(f'DEBUG: Processing {len(file_paths)} files with {max_workers} workers ({engine_desc})', file=sys.stderr)
    start_time = time.time()
    results = []
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_file = {executor.submit(process_single_file, fp, template, document_type): fp for fp in file_paths}
        for future in concurrent.futures.as_completed(future_to_file):
            file_path = future_to_file[future]
            try:
                result = future.result()
                if stream:
                    print(json.dumps([result], ensure_ascii=False), flush=True)
                else:
                    results.append(result)
                print(f'DEBUG: Completed {result.get("source_file", "unknown")} via {result.get("extraction_method", "unknown")}', file=sys.stderr)
            except Exception as e:
                err_result = {
                    'source_file': os.path.basename(file_path),
                    'error': str(e),
                    'success': False
                }
                if stream:
                    print(json.dumps([err_result], ensure_ascii=False), flush=True)
                else:
                    results.append(err_result)
    total_time = time.time() - start_time
    print(f'DEBUG: Batch processing completed in {total_time:.3f} seconds', file=sys.stderr)
    if not stream:
        return results

def main():
    parser = argparse.ArgumentParser(description='Smart OCR Intelligence Layer — PaddleOCR + Ollama LLM')
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--file', help='Single file to process')
    group.add_argument('--batch', help='Directory containing OCR files')
    group.add_argument('--file-list', help='Text file containing list of files to process')
    parser.add_argument('--output-format', choices=['json', 'pretty'], default='json')
    parser.add_argument('--workers', type=int, default=None, help='Parallel workers (default: 1 for LLM, 3 for regex)')
    parser.add_argument('--output-file', help='Output file (default: stdout)')
    parser.add_argument('--template', help='Path to JSON file with report template for LLM extraction')
    parser.add_argument('--document-type', choices=['lab_report', 'consultation'], help='Type of document to guide processing')
    parser.add_argument('--stream', action='store_true', help='Stream results as JSON lines immediately when finished')
    args = parser.parse_args()

    # Load template if provided
    template = None
    if args.template:
        try:
            with open(args.template, 'r', encoding='utf-8') as tf:
                template = json.load(tf)
            print(f'DEBUG: Loaded report template: {template.get("name", "unknown")}', file=sys.stderr)
        except Exception as e:
            print(f'DEBUG: Failed to load template ({e}), proceeding without LLM', file=sys.stderr)

    results = []
    try:
        if args.file:
            result = process_single_file(args.file, template, args.document_type)
            results = [result]
        elif args.batch:
            batch_dir = Path(args.batch)
            file_paths = []
            for ext in ['*.pdf', '*.jpg', '*.jpeg', '*.png', '*.tif', '*.tiff', '*.txt']:
                file_paths.extend([str(f) for f in batch_dir.glob(ext)])
            print(f'DEBUG: Found {len(file_paths)} files to process', file=sys.stderr)
            if not file_paths:
                raise Exception(f'No supported files (PDF/Image/TXT) found in {batch_dir}')
            results = process_batch_parallel(file_paths, args.workers, template, args.document_type, args.stream)
        elif args.file_list:
            with open(args.file_list, 'r') as f:
                file_paths = [line.strip() for line in f if line.strip()]
            results = process_batch_parallel(file_paths, args.workers, template, args.document_type, args.stream)
        
        if not args.stream:
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

