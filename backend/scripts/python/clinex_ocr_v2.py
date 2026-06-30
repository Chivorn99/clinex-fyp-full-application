"""
ClineX v2 OCR Extraction Engine
================================
Precision text-based PDF extraction with layout classification.
Handles two document types:
  - Layout A: Patient Consultation Information
  - Layout B: Laboratory Report

This module uses pdfplumber for direct text extraction from text-based PDFs.
For scanned/image PDFs, it falls back to the existing OCR engines (PaddleOCR/KiriOCR).
"""

import sys
import json
import re
import os
import argparse
import logging
from typing import Dict, Any, Optional, List, Tuple
from pathlib import Path

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.WARNING,
    format='[%(levelname)s] %(message)s',
    stream=sys.stderr,
)
log = logging.getLogger('clinex_extract')
log.setLevel(logging.INFO)

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------
SCRIPT_DIR = Path(__file__).resolve().parent
REFERENCE_DATA_PATH = SCRIPT_DIR / 'test_reference_data.json'

# Known category headers as they appear in PDF text
_CATEGORY_HEADERS = [
    'BIOCHIMISTRY', 'BIOCHEMISTRY', 'ENZYMOLOGY', 'HEMATOLOGY',
    'DRUG URINE', 'URINE ANALYSIS', 'ABO BLOOD GROUP', 'SERO/IMMUNOLOGY',
    'SERO/ IMMUNOLOGY',
]

# Sub-headers to skip (not test names)
_SKIP_HEADERS = {
    'CBC', 'TRANSAMINASE', 'URINE ANALYSIS 11 TEST',
    'Results', 'Unit', 'Reference Range', 'Flag',
    'Results Unit Reference Range Flag',
}


# ═══════════════════════════════════════════════════════════════════════════
#  TEXT EXTRACTION
# ═══════════════════════════════════════════════════════════════════════════

def extract_text_from_pdf(pdf_path: str) -> str:
    """Extract raw text from a PDF using pdfplumber (primary) or PyMuPDF (fallback)."""
    text = _try_pdfplumber(pdf_path)
    if text and len(text.strip()) > 50:
        return _resolve_khmer_cid(text)

    text = _try_pymupdf(pdf_path)
    if text and len(text.strip()) > 50:
        return _resolve_khmer_cid(text)

    text = _try_pypdf2(pdf_path)
    if text and len(text.strip()) > 50:
        return _resolve_khmer_cid(text)

    raise RuntimeError(f'Could not extract text from {pdf_path}')


# ---------------------------------------------------------------------------
# CID-to-Unicode resolution for KhmerOSbattambang subset fonts
# ---------------------------------------------------------------------------
# These PDFs use a subset of KhmerOSbattambang where some glyphs (Khmer
# consonant clusters / ligatures) are not mapped in the font's ToUnicode
# table.  pdfplumber outputs them as literal "(cid:XX)" strings.
#
# This mapping was built by cross-referencing (cid:XX) occurrences from
# test documents against the known Khmer Unicode text.
# ---------------------------------------------------------------------------
_KHMER_CID_MAP: Dict[int, str] = {
    2:   '\u1788',    # ឈ (Chhor)
    3:   '\u17D2\u1798\u17C4',  # ្មោ (subscript Mo + vowel O — part of ឈ្មោះ)
    5:   '\u17A2\u17B6',  # អា (Or + aa — part of អាយុ = age)
    10:  '\u1786\u17D2\u1793\u17B6',  # ឆ្នា (Chor cluster — part of ឆ្នាំ = year)
    11:  '',          # Combining part (already handled by cid:10)
    36:  '\u179F',    # ស (Sor)
    52:  '\u1794',    # ប (Bor)
    53:  '\u17D2\u179A',  # ្រ (subscript Ro — part of ប្រុស = male)
    54:  '\u17BB',    # ុ (vowel u)
    55:  '\u17BB',    # ុ (vowel u) — variant CID
    56:  '\u17D2\u179A\u17BB',  # ្រុ (subscript Ro + u)
    57:  '\u17D2\u179A\u17BB',  # ្រុ (subscript Ro + u) — variant CID
    58:  '\u17D2\u179A\u17BB',  # ្រុ (subscript Ro + u) — variant CID
    60:  '\u1789\u17D2\u1789\u17B6',  # ញ្ញា (Nyo cluster — part of សញ្ញា = sign)
    61:  '\u1789\u17D2\u1789\u17B6',  # ញ្ញា — variant CID
    62:  '\u1789\u17D2\u1789\u17B6',  # ញ្ញា — variant CID
    63:  '',          # Combining part
    70:  '\u179F\u17D2\u179A',  # ស្រ (Sor + subscript Ro)
    71:  '\u17C4',    # ោ (vowel O)
    73:  '\u17B6\u178F\u17CB',  # ាត់ — part of ស្រោាត់ (blood pressure)
    85:  '\u1785\u17D2\u179A',  # ច្រ (Chor + subscript Ro)
    86:  '\u17C2',    # ែ (vowel Ae)
    91:  '\u1784\u17CB',  # ង់ (Ngor + Bantoc)
    95:  '\u17D2\u178F\u17BB',  # ្តុ (subscript Tor + u)
    107: '\u17D2\u179B',  # ្ល (subscript Lo)
    108: '\u17B6\u1793',  # ាន (aa + Nor)
}

def _resolve_khmer_cid(text: str) -> str:
    """Replace (cid:XX) placeholders with Khmer Unicode characters."""
    def _replace_cid(m):
        cid_num = int(m.group(1))
        return _KHMER_CID_MAP.get(cid_num, m.group(0))
    
    return re.sub(r'\(cid:(\d+)\)', _replace_cid, text)


def _try_pdfplumber(pdf_path: str) -> Optional[str]:
    try:
        import pdfplumber
        text = ''
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + '\n'
        return text
    except Exception as e:
        log.debug(f'pdfplumber failed: {e}')
        return None


def _try_pymupdf(pdf_path: str) -> Optional[str]:
    try:
        import fitz
        doc = fitz.open(pdf_path)
        text = ''
        for page in doc:
            text += page.get_text() + '\n'
        doc.close()
        return text
    except Exception as e:
        log.debug(f'PyMuPDF failed: {e}')
        return None


def _try_pypdf2(pdf_path: str) -> Optional[str]:
    try:
        import PyPDF2
        with open(pdf_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            text = ''
            for page in reader.pages:
                text += page.extract_text() + '\n'
        return text
    except Exception as e:
        log.debug(f'PyPDF2 failed: {e}')
        return None


def is_text_based_pdf(pdf_path: str) -> bool:
    """Check if the PDF has extractable embedded text (not a scanned image)."""
    text = _try_pdfplumber(pdf_path)
    if text and len(text.strip()) > 100:
        # Check there are enough alphabetic chars (not just dots and spaces)
        alpha_chars = sum(1 for c in text if c.isalpha())
        return alpha_chars > 30
    return False


# ═══════════════════════════════════════════════════════════════════════════
#  TEXT CLEANING
# ═══════════════════════════════════════════════════════════════════════════

def _clean_dotted_value(raw: str) -> str:
    """Clean the dot-separated values from PDF text extraction.

    Example input:  '.0...7................m.g./.d.L.........(.0...9. .-. .1...1.).........L'
    We need to remove the filler dots/spaces between characters.
    """
    if not raw:
        return raw
    # Remove leading/trailing dots and spaces
    cleaned = raw.strip('. ')
    # Collapse sequences of dots (with optional spaces) into nothing,
    # but preserve dots that are part of decimal numbers
    # Strategy: remove dots that are surrounded by non-digit characters,
    # or sequences of 2+ dots
    cleaned = re.sub(r'\.{2,}', ' ', cleaned)  # 2+ dots -> space
    cleaned = re.sub(r'\s{2,}', ' ', cleaned)   # collapse whitespace
    return cleaned.strip()


def _clean_dotted_line(line: str) -> str:
    """Clean an entire dotted lab result line.

    Input:  'Creatinine, serum ............:. .0...7................m.g./.d.L.........(.0...9. .-. .1...1.).........L'
    Output: 'Creatinine, serum : 0.7 mg/dL (0.9 - 1.1) L'
    """
    if ':' not in line:
        return line

    # Split at the FIRST colon-like separator
    # The pattern is typically: 'TestName .....:.  .values.....'
    # Find the colon that separates test name from values
    match = re.match(r'^(.+?)\s*[.:]+\s*[.:]?\s*(.*)$', line)
    if not match:
        return line

    test_part = match.group(1).strip().rstrip('.')
    value_part = match.group(2)

    # Clean value part: remove filler dots
    # Replace sequences of dots with single space
    cleaned_value = re.sub(r'\.{2,}', ' ', value_part)
    # Remove standalone dots that are clearly filler (not decimal points)
    # A filler dot is surrounded by spaces or other dots
    cleaned_value = re.sub(r'(?<=\s)\.(?=\s)', ' ', cleaned_value)
    # Remove dots at start/end
    cleaned_value = cleaned_value.strip('. ')
    # Collapse whitespace
    cleaned_value = re.sub(r'\s{2,}', ' ', cleaned_value)

    return f'{test_part} : {cleaned_value}'


def _deduplicate_chars(text: str) -> str:
    """Fix double-character encoding artifacts.

    Example: 'DDrr.. LLEEAANNGG CChhooeeuu' -> 'Dr. LEANG Choeu'
    Example: 'VViittaall ssiiggnnss' -> 'Vital signs'
    """
    result = []
    i = 0
    while i < len(text):
        if i + 1 < len(text) and text[i] == text[i + 1]:
            result.append(text[i])
            i += 2
        else:
            result.append(text[i])
            i += 1
    return ''.join(result)


def _remove_cid_codes(text: str) -> str:
    """Remove (cid:XX) codes from text, replacing with empty string."""
    return re.sub(r'\(cid:\d+\)', '', text)


def _clean_text_full(raw_text: str) -> str:
    """Apply all text cleaning to raw PDF-extracted text."""
    text = _remove_cid_codes(raw_text)
    # Don't apply dedup globally — only on known double-char lines
    return text


# ═══════════════════════════════════════════════════════════════════════════
#  DOCUMENT CLASSIFICATION
# ═══════════════════════════════════════════════════════════════════════════

def classify_document(text: str) -> str:
    """Classify document as 'Consultation_Form' or 'Laboratory_Report'.

    Rules:
    - Look for 'Patient Consultation Information' header → Consultation_Form
    - Look for 'LABORATORY REPORT' header → Laboratory_Report
    """
    upper = text.upper()
    has_consultation = 'PATIENT CONSULTATION INFORMATION' in upper
    has_lab_report = 'LABORATORY REPORT' in upper

    if has_consultation and not has_lab_report:
        return 'Consultation_Form'
    elif has_lab_report and not has_consultation:
        return 'Laboratory_Report'
    elif has_consultation and has_lab_report:
        # Both present — check which comes first
        consult_pos = upper.index('PATIENT CONSULTATION INFORMATION')
        lab_pos = upper.index('LABORATORY REPORT')
        return 'Consultation_Form' if consult_pos < lab_pos else 'Laboratory_Report'
    else:
        # Fallback heuristics
        if any(cat in upper for cat in ['BIOCHIMISTRY', 'BIOCHEMISTRY', 'HEMATOLOGY', 'ENZYMOLOGY']):
            return 'Laboratory_Report'
        return 'Consultation_Form'


# ═══════════════════════════════════════════════════════════════════════════
#  REFERENCE DATA
# ═══════════════════════════════════════════════════════════════════════════

_reference_data_cache: Optional[Dict] = None


def _load_reference_data() -> Dict[str, Any]:
    global _reference_data_cache
    if _reference_data_cache is not None:
        return _reference_data_cache

    if REFERENCE_DATA_PATH.exists():
        with open(REFERENCE_DATA_PATH, 'r', encoding='utf-8') as f:
            _reference_data_cache = json.load(f)
    else:
        log.warning(f'Reference data file not found: {REFERENCE_DATA_PATH}')
        _reference_data_cache = {}

    return _reference_data_cache


def _get_category_key(raw_category: str) -> str:
    """Map raw category header to normalized key."""
    ref = _load_reference_data()
    aliases = ref.get('category_aliases', {})
    upper = raw_category.upper().strip()
    return aliases.get(upper, upper.lower().replace(' ', '_').replace('/', '_'))


def _get_test_reference(category_key: str, test_name: str) -> Dict[str, Any]:
    """Look up canonical unit and reference range for a test."""
    ref = _load_reference_data()
    category_data = ref.get(category_key, {})

    # Direct match
    if test_name in category_data:
        return category_data[test_name]

    # Check aliases
    for canonical_name, info in category_data.items():
        if isinstance(info, dict) and test_name in info.get('aliases', []):
            return info

    return {}


# ═══════════════════════════════════════════════════════════════════════════
#  CONSULTATION FORM PARSER (Layout A)
# ═══════════════════════════════════════════════════════════════════════════

def _parse_consultation_form(text: str, file_source: str) -> Dict[str, Any]:
    """Parse a Patient Consultation Information form."""
    lines = [l.strip() for l in text.split('\n') if l.strip()]

    result = {
        'document_classification': 'Consultation_Form',
        'file_source': file_source,
        'patient_header': {
            'patient_name_khmer': None,
            'payment_type': None,
            'age_string_raw': None,
            'gender_raw': None,
            'attending_physician': None,
            'evaluation_timestamp': None,
        },
        'vital_signs': {
            'systolic_bp': None,
            'diastolic_bp': None,
            'pulse': None,
            'respiratory_rate': None,
            'temperature_celsius': None,
            'o2_saturation_percentage': None,
            'height_cm': None,
            'weight_kg': None,
        },
        'clinical_records': {
            'chief_complaint_raw': None,
            'current_medications': None,
            'evaluation_summary': None,
        },
        'treatment_routing': [],
    }

    full_text = '\n'.join(lines)
    cleaned_text = _remove_cid_codes(full_text)

    # --- Patient Header ---
    _parse_consultation_header(cleaned_text, lines, result)

    # --- Vital Signs ---
    _parse_vital_signs(cleaned_text, result)

    # --- Clinical Records ---
    _parse_clinical_records(cleaned_text, lines, result)

    # --- Treatment Plan ---
    _parse_treatment_routing(lines, result)

    return result


def _parse_consultation_header(text: str, lines: List[str], result: Dict):
    """Extract patient header fields from consultation form."""
    header = result['patient_header']

    # Patient name — after 'Patient :' but before 'Payment Type'
    m = re.search(r'Patient\s*:\s*(.+?)\s*Payment\s*Type', text, re.IGNORECASE)
    if m:
        name = m.group(1).strip()
        # Clean up any remaining CID artifacts
        name = re.sub(r'\s+', ' ', name).strip()
        header['patient_name_khmer'] = name

    # Payment Type — after 'Payment Type :'
    m = re.search(r'Payment\s*Type\s*:\s*(\S+)', text, re.IGNORECASE)
    if m:
        header['payment_type'] = m.group(1).strip()

    # Age — complex pattern with Khmer/English mix
    # Pattern: 'Age : 51 (cid:10)(cid:11)ំ/year, 11 ែខ/month, 14 ៃថ(cid:3)/day'
    # After CID removal: 'Age : 51 ំ/year, 11 ែខ/month, 14 ៃថ/day'
    # We need to extract the numbers and construct the age string
    m = re.search(
        r'Age\s*:\s*(\d+)\s*[^\d,/]*/?year[,\s]*(\d+)\s*[^\d,/]*/?month[,\s]*(\d+)',
        text, re.IGNORECASE
    )
    if m:
        years, months, days = m.group(1), m.group(2), m.group(3)
        header['age_string_raw'] = f'{years} ឆ្នាំ/year, {months} ខែ/month, {days} ថ្ងៃ/day'
    else:
        # Fallback: try simpler pattern
        m = re.search(r'Age\s*:\s*(.+?)(?:Gender|Physician|\n)', text, re.IGNORECASE)
        if m:
            age_raw = m.group(1).strip().rstrip(',').strip()
            age_raw = re.sub(r'\s+', ' ', age_raw)
            header['age_string_raw'] = age_raw

    # Gender — after 'Gender :' look for M/F
    m = re.search(r'Gender\s*:\s*([^\n]*?)(M|F|Male|Female)\b', text, re.IGNORECASE)
    if m:
        gender = m.group(2).strip()
        prefix = m.group(1).strip().rstrip('/')
        if gender.upper() in ('M', 'MALE'):
            header['gender_raw'] = f'{prefix}/M' if prefix else 'M'
        elif gender.upper() in ('F', 'FEMALE'):
            header['gender_raw'] = f'{prefix}/F' if prefix else 'F'

    # Physician
    m = re.search(r'Physician\s*:\s*(Dr\.?\s*[A-Za-z\s.]+?)(?:\s{2,}|Evaluate)', text, re.IGNORECASE)
    if m:
        header['attending_physician'] = re.sub(r'\s+', ' ', m.group(1)).strip()

    # Evaluate at timestamp
    m = re.search(r'Evaluate\s*at\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})', text, re.IGNORECASE)
    if m:
        header['evaluation_timestamp'] = m.group(1)


def _parse_vital_signs(text: str, result: Dict):
    """Extract vital signs from consultation form."""
    vs = {}

    # Systolic
    m = re.search(r'(?:Systolic|systolic)\)?[\s]*(\d+)\s*/?\s*mmHg', text, re.IGNORECASE)
    if not m:
        m = re.search(r'Systolic\)\s*(\d+)\s*/mmHg', text, re.IGNORECASE)
    if m:
        vs['systolic_bp'] = f'{m.group(1)}/mmHg'

    # Diastolic
    m = re.search(r'(?:Diastolic|diastolic)\)?[\s]*(\d+)\s*/?\s*mmHg', text, re.IGNORECASE)
    if not m:
        m = re.search(r'Diastolic\)\s*(\d+)\s*/mmHg', text, re.IGNORECASE)
    if m:
        vs['diastolic_bp'] = f'{m.group(1)}/mmHg'

    # Pulse
    m = re.search(r'(?:Pulse|pulse)\)?[\s]*(\d+)\s*/mn', text, re.IGNORECASE)
    if m:
        vs['pulse'] = f'{m.group(1)}/mn'

    # Respiratory Rate (RR)
    m = re.search(r'(?:RR|respiratory)\)?[\s]*(\d+)\s*/mn', text, re.IGNORECASE)
    if m:
        vs['respiratory_rate'] = f'{m.group(1)}/mn'

    # Temperature — may have comma as decimal separator (36,5)
    m = re.search(r'(?:Temperature|Temp)\)?[\s]*([\d]+[,.][\d]+|[\d]+)', text, re.IGNORECASE)
    if m:
        temp = m.group(1).replace(',', '.')
        vs['temperature_celsius'] = f'{temp}°C'

    # O2 saturation
    m = re.search(r'(?:O2sat|SpO2|O2)\)?[\s]*(\d+)\s*%', text, re.IGNORECASE)
    if m:
        vs['o2_saturation_percentage'] = f'{m.group(1)}%'

    # Height
    m = re.search(r'(?:Height|Taille)\)?[\s]*(\d+)\s*cm', text, re.IGNORECASE)
    if m:
        vs['height_cm'] = f'{m.group(1)} cm'

    # Weight
    m = re.search(r'(?:Weight|Poids)\)?[\s]*(\d+)\s*kg', text, re.IGNORECASE)
    if m:
        vs['weight_kg'] = f'{m.group(1)} kg'

    result['vital_signs'] = vs


def _parse_clinical_records(text: str, lines: List[str], result: Dict):
    """Extract clinical notes from consultation form."""
    cr = result['clinical_records']

    # Chief complaint — after '(Chief complain)' or 'Chief complain'
    m = re.search(
        r'(?:Chief\s*complain(?:t)?)\)?\s*(.+?)(?:\n|$)',
        text, re.IGNORECASE
    )
    if m:
        complaint = m.group(1).strip()
        # Remove leading parenthesis if present
        complaint = re.sub(r'^\)\s*', '', complaint)
        if complaint:
            cr['chief_complaint_raw'] = complaint

    # Current medications — after '(Current medications)' or 'Current medications'
    m = re.search(
        r'(?:Current\s*medications?)\)?\s*(.+?)(?:\n|$)',
        text, re.IGNORECASE
    )
    if m:
        meds = m.group(1).strip()
        meds = re.sub(r'^\)\s*', '', meds)
        if meds:
            cr['current_medications'] = meds

    # Evaluation Summary
    m = re.search(
        r'Evaluation\s*Summary\s*(.+?)(?:\n|$)',
        text, re.IGNORECASE
    )
    if m:
        summary = m.group(1).strip()
        if summary:
            cr['evaluation_summary'] = summary


def _parse_treatment_routing(lines: List[str], result: Dict):
    """Extract treatment plan references (PRE/PAR codes) as array of {type, code}."""
    routing = []

    # Canonical display names
    type_display = {
        'prescription': 'Prescription',
        'laboratory': 'Laboratory',
        'echography': 'Echography',
        'xray': 'Xray',
        'ecg': 'ECG',
        'ent endoscopy': 'ENT Endoscopy',
    }

    for line in lines:
        line_clean = line.strip()
        # Match patterns like: 'Prescription PRE001226' or 'Laboratory PAR004366'
        m = re.match(
            r'^(Prescription|Laboratory|Echography|Xray|ECG|ENT\s*Endoscopy)\s+'
            r'((?:PRE|PAR)\d+)',
            line_clean, re.IGNORECASE
        )
        if m:
            treat_type = m.group(1).strip().lower()
            code = m.group(2).strip()
            display = type_display.get(treat_type, m.group(1).strip())
            routing.append({'type': display, 'code': code})

    result['treatment_routing'] = routing


# ═══════════════════════════════════════════════════════════════════════════
#  LABORATORY REPORT PARSER (Layout B)
# ═══════════════════════════════════════════════════════════════════════════

def _parse_lab_report(text: str, file_source: str) -> Dict[str, Any]:
    """Parse a Laboratory Report."""
    lines = [l for l in text.split('\n')]

    result = {
        'document_classification': 'Laboratory_Report',
        'file_source': file_source,
        'patient_header': {
            'patient_name': None,
            'patient_id': None,
            'age_string': None,
            'gender': None,
            'requested_by': None,
            'requested_date': None,
        },
        'report_metadata': {
            'lab_id': None,
            'collected_timestamp': None,
            'analysis_timestamp': None,
            'validation_timestamp': None,
            'technician_name_khmer': None,
        },
        'panels': {},
    }

    cleaned_text = _remove_cid_codes(text)

    # --- Patient Header (appears on first page / repeated on every page) ---
    _parse_lab_header(cleaned_text, result)

    # --- Report Metadata ---
    _parse_lab_metadata(cleaned_text, lines, result)

    # --- Panel Results ---
    _parse_lab_panels(cleaned_text, result)

    return result


def _parse_lab_header(text: str, result: Dict):
    """Extract patient header from lab report."""
    header = result['patient_header']

    # Name — after '/Name :' (Khmer label before it may be garbled)
    # Name ends at: (cid:X), Khmer chars, /Age, or 2+ spaces
    m = re.search(r'/Name\s*:\s*([A-Za-z][A-Za-z\s.]+?)(?:\s*\(cid:|\s*[\u1780-\u17FF]|\s*/Age|\s{2,}|$)', text, re.MULTILINE)
    if m:
        header['patient_name'] = re.sub(r'\s+', ' ', m.group(1)).strip()

    # Age — after '/Age :'
    m = re.search(r'/Age\s*:\s*([\d]+\s*Y(?:[,.\s]+\d+\s*M)?(?:[,.\s]+\d+\s*D)?)', text)
    if m:
        header['age_string'] = re.sub(r'\s+', ' ', m.group(1)).strip()

    # Gender — after '/Gender :'
    m = re.search(r'/Gender\s*:\s*(Male|Female)', text, re.IGNORECASE)
    if m:
        header['gender'] = m.group(1).capitalize()

    # Patient ID
    m = re.search(r'Patient\s*ID\s*:\s*(PT\d+)', text)
    if m:
        header['patient_id'] = m.group(1)

    # Requested Date
    m = re.search(r'Requested\s*Date\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})', text)
    if m:
        header['requested_date'] = m.group(1)

    # Requested By
    m = re.search(r'Requested\s*By\s*:\s*(Dr\.?\s*[A-Za-z\s.]+?)(?:\s{2,}|\n|$)', text, re.MULTILINE)
    if m:
        header['requested_by'] = re.sub(r'\s+', ' ', m.group(1)).strip()


def _parse_lab_metadata(text: str, lines: List[str], result: Dict):
    """Extract report metadata from lab report."""
    meta = result['report_metadata']

    # Lab ID
    m = re.search(r'Lab\s*ID\s*:\s*(LT\d+)', text)
    if m:
        meta['lab_id'] = m.group(1)

    # Collected Date
    m = re.search(r'Collected\s*Date\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})', text)
    if m:
        meta['collected_timestamp'] = m.group(1)

    # Analysis Date
    m = re.search(r'Analysis\s*Date\s*:\s*(\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2})', text)
    if m:
        meta['analysis_timestamp'] = m.group(1)

    # Validated By timestamp — appears as 'Validated By :' followed by a date on the same or next line
    m = re.search(r'Validated\s*By\s*:.*?\n?\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})', text)
    if m:
        meta['validation_timestamp'] = m.group(1)

    # Lab Technician name — appears after 'Lab Technician:' but could be Khmer
    # In the PDF, it shows as 'Lab Technician:' followed by a timestamp, but
    # the actual Khmer name needs to come from the Khmer text in the footer
    # For text-based PDFs, the Khmer text may be garbled via CID codes
    # We'll look for Khmer Unicode characters near 'Lab Technician'
    _extract_technician_name(text, lines, meta)


def _extract_technician_name(text: str, raw_lines: List[str], meta: Dict):
    """Try to extract technician name (often in Khmer) from the footer area."""
    # Look for Khmer text near 'Validated By' or 'Lab Technician'
    for line in raw_lines:
        stripped = line.strip()
        # Check if line contains Khmer characters (U+1780-U+17FF range)
        khmer_chars = [c for c in stripped if '\u1780' <= c <= '\u17FF']
        if len(khmer_chars) >= 3:
            # This might be the technician name — check it's near the footer
            if 'Page' not in stripped and 'Patient' not in stripped and '/Name' not in stripped:
                # Filter out known Khmer label text (Age, Gender etc.)
                if not re.search(r'/(?:Name|Age|Gender|Phone)', stripped):
                    meta['technician_name_khmer'] = stripped.strip()
                    break


def _parse_lab_panels(text: str, result: Dict):
    """Extract test results organized by diagnostic panels."""
    panels = {}

    # Split text into pages/sections by finding category headers
    # Each page repeats the header, so we need to handle that
    lines = text.split('\n')

    current_category = None
    current_category_key = None

    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue

        # Check if this line is a category header
        upper = stripped.upper()
        matched_category = None
        for cat in _CATEGORY_HEADERS:
            if cat in upper and 'TEST' not in upper and upper not in _SKIP_HEADERS:
                matched_category = cat
                break

        if matched_category:
            current_category = matched_category
            current_category_key = _get_category_key(matched_category)
            if current_category_key not in panels:
                panels[current_category_key] = []
            continue

        # Skip non-test lines
        if current_category is None:
            continue
        if upper in {s.upper() for s in _SKIP_HEADERS}:
            continue
        if upper.startswith('RESULTS') or upper.startswith('PAGE '):
            continue
        if 'LABORATORY REPORT' in upper:
            continue
        if '/NAME' in upper or 'PATIENT ID' in upper or 'COLLECTED DATE' in upper:
            current_category = None  # We've hit a new page header
            continue
        if 'VALIDATED BY' in upper or 'LAB TECHNICIAN' in upper:
            current_category = None
            continue

        # Try to parse this line as a test result
        test_result = _parse_test_line(stripped, current_category_key)
        if test_result:
            # Avoid duplicates
            existing_names = {t['test_name'] for t in panels.get(current_category_key, [])}
            if test_result['test_name'] not in existing_names:
                panels.setdefault(current_category_key, []).append(test_result)

    result['panels'] = panels


def _strip_dot_encoding(s: str) -> str:
    """Strip the dot-separated character encoding from PDF text.

    In these PDFs, dots are used as column-fill characters. Every real character
    (letter, digit, symbol) is surrounded by dots. There are NO real decimal
    points in the PDF text — they were all rendered as dots too.

    Example input:  '.0...7................m.g./.d.L.........(.0...9. .-. .1...1.).........L'
    After stripping: '07 mg/dL (09 - 11) L'
    
    Strategy: Remove ALL dots, collapse whitespace.
    Decimal reconstruction happens later using test-specific context.
    """
    if not s:
        return s
    # Remove ALL dots
    result = s.replace('.', ' ')
    # Collapse whitespace
    result = re.sub(r'\s{2,}', ' ', result).strip()
    return result


def _parse_test_line(line: str, category_key: str) -> Optional[Dict[str, Any]]:
    """Parse a single test result line.

    Handles two formats:
    1. Dotted: 'Creatinine, serum ............:. .0...7......m.g./.d.L....(.0...9. .-. .1...1.)...L'
    2. Clean:  'GGT (Gamm Glutamyl Transferas): 254 U/L ( 0 - 55 ) H'
    """
    if not line or len(line) < 3:
        return None

    upper = line.upper().strip()
    if upper in {s.upper() for s in _SKIP_HEADERS}:
        return None

    # Detect if line is "dotted format" (has long runs of dots)
    is_dotted = bool(re.search(r'\.{3,}', line))

    # Find the colon separator
    colon_match = re.search(r'\s*\.{0,50}\s*:\s*\.?\s*', line)
    if not colon_match:
        return None

    test_name_raw = line[:colon_match.start()].strip().rstrip('. ')
    value_raw = line[colon_match.end():]

    # Clean test name
    test_name = re.sub(r'\.{2,}', '', test_name_raw).strip()
    if not test_name or test_name.upper() in {s.upper() for s in _SKIP_HEADERS}:
        return None

    # Normalize GGT
    if 'GGT' in test_name and 'Glutamyl' in test_name:
        test_name = 'GGT'

    # Get reference data
    ref_info = _get_test_reference(category_key, test_name)
    canonical_unit = ref_info.get('unit')
    canonical_range = ref_info.get('reference_range')

    if is_dotted:
        return _parse_dotted_value(test_name, value_raw, category_key, canonical_unit, canonical_range)
    else:
        return _parse_clean_value(test_name, value_raw, category_key, canonical_unit, canonical_range)


def _parse_dotted_value(test_name: str, value_raw: str, category_key: str,
                        canonical_unit: Optional[str], canonical_range: Optional[str]) -> Optional[Dict]:
    """Parse a dotted-format value line.
    
    After removing dots, every character is space-separated:
      'N E G A T I V E L e u / µ L'  (NEGATIVE Leu/µL)
      '0 7 m g / d L ( 0 9 - 1 1 ) L'  (0.7 mg/dL (0.9-1.1) L)
    
    Strategy:
      1. Remove dots → space-separated chars
      2. Strip unit patterns (space-separated form)
      3. Strip reference range parens
      4. Reconstitute words by joining single chars
      5. Check for qualitative (NEGATIVE/POSITIVE) or numeric values
    """
    # Step 1: Remove ALL dots, collapse spaces
    cleaned = value_raw.replace('.', ' ')
    cleaned = re.sub(r'\s{2,}', ' ', cleaned).strip()
    
    # Step 2: Strip known unit text patterns FIRST (before flag detection)
    # This prevents the trailing 'L' in 'L e u / µ L' from being mistaken as a flag
    unit_strip_patterns = [
        r'\s*m\s*g\s*/\s*d\s*L',               # mg/dL
        r'\s*U\s*/\s*L',                         # U/L
        r'\s*g\s*/\s*d\s*L',                     # g/dL
        r'\s*1\s*0\s*[⁹9]\s*/\s*L',             # 10⁹/L
        r'\s*x?\s*1\s*0\s*1\s*2\s*/\s*L',       # x1012/L
        r'\s*L\s*e\s*u\s*/\s*[µu]\s*L',         # Leu/µL
        r'\s*E\s*r\s*y\s*/\s*[µu]\s*[lL]',      # Ery/µl
        r'\s*p\s*g(?:\s|$)',                      # pg
        r'\s*f\s*l(?:\s|$)',                      # fl
        r'\s*%(?:\s|$)',                           # %
    ]
    for pattern in unit_strip_patterns:
        cleaned = re.sub(pattern, ' ', cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r'\s{2,}', ' ', cleaned).strip()

    # Step 3: Extract trailing flag (H or L — now safe after unit stripping)
    flag = None
    flag_match = re.search(r'\s+([HL])\s*$', cleaned)
    if flag_match:
        flag = flag_match.group(1)
        cleaned = cleaned[:flag_match.start()].strip()

    # Step 4: Remove reference range in parentheses
    # After dot strip: '( 0 9 - 1 1 )' or '( + + )'
    # Save any qualifier like (++) before removing
    qualifier_match = re.search(r'\(\s*((?:\+\s*)+)\)', cleaned)
    qualifier = None
    if qualifier_match:
        plus_count = qualifier_match.group(1).count('+')
        qualifier = f'({"+" * plus_count})'
        cleaned = cleaned[:qualifier_match.start()] + cleaned[qualifier_match.end():]
    
    # Remove numeric reference ranges in parens
    cleaned = re.sub(r'\([^)]*\)', '', cleaned).strip()
    cleaned = re.sub(r'\s{2,}', ' ', cleaned).strip()

    # Step 5: Reconstitute words from space-separated single characters
    # 'N E G A T I V E' → 'NEGATIVE'
    # '0 7' stays as '0 7' (digits)
    reconstituted = _reconstitute_text(cleaned)

    # Step 6: Check for qualitative results
    qual_match = re.match(r'^(NEGATIVE|POSITIVE)\b', reconstituted, re.IGNORECASE)
    if qual_match:
        value = qual_match.group(1).upper()
        if qualifier:
            value = f'{value} {qualifier}'
        return {
            'test_name': test_name,
            'value': value,
            'unit': canonical_unit,
            'reference_range': canonical_range,
            'flag': flag,
        }

    # Step 7: Extract numeric value
    # Remove all remaining non-digit characters
    numeric_only = re.sub(r'[^\d\s]', '', reconstituted).strip()
    numeric_only = re.sub(r'\s{2,}', ' ', numeric_only).strip()

    if not numeric_only:
        return None

    value = _reconstruct_value(numeric_only, test_name, category_key)

    if not value:
        return None

    return {
        'test_name': test_name,
        'value': value,
        'unit': canonical_unit,
        'reference_range': canonical_range,
        'flag': flag,
    }


def _reconstitute_text(spaced: str) -> str:
    """Reconstitute words from space-separated single characters.
    
    'N E G A T I V E' → 'NEGATIVE'
    'P O S I T I V E' → 'POSITIVE'
    '0 7' → '07'  (all single-char tokens → join)
    '254' → '254' (already a word → keep)
    """
    if not spaced:
        return spaced
    
    tokens = spaced.split()
    
    # If all tokens are single characters, join them all
    if all(len(t) == 1 for t in tokens):
        return ''.join(tokens)
    
    # Mixed: some single chars, some multi-char tokens
    # Group consecutive single-char tokens and join them
    result_parts = []
    current_singles = []
    
    for token in tokens:
        if len(token) == 1:
            current_singles.append(token)
        else:
            if current_singles:
                result_parts.append(''.join(current_singles))
                current_singles = []
            result_parts.append(token)
    
    if current_singles:
        result_parts.append(''.join(current_singles))
    
    return ' '.join(result_parts)


def _parse_clean_value(test_name: str, value_raw: str, category_key: str,
                       canonical_unit: Optional[str], canonical_range: Optional[str]) -> Optional[Dict]:
    """Parse a clean-format value line (e.g., 'GGT: 254 U/L ( 0 - 55 ) H')."""
    value_clean = value_raw.strip()
    
    flag = None
    # 1. Trailing flag
    flag_match = re.search(r'\s+([HL])\s*$', value_clean)
    if flag_match:
        flag = flag_match.group(1).upper()
        value_clean = value_clean[:flag_match.start()].strip()

    # 2. Reference range in parentheses
    range_match = re.search(r'\(\s*(.+?)\s*\)\s*$', value_clean)
    extracted_range = canonical_range
    if range_match:
        extracted_range = range_match.group(1).strip()
        value_clean = value_clean[:range_match.start()].strip()

    # 3. Strip unit from end
    unit_patterns = [
        (r'\s+mg/dL\s*$', 'mg/dL'),
        (r'\s+U/L\s*$', 'U/L'),
        (r'\s+g/dL\s*$', 'g/dL'),
        (r'\s+pg\s*$', 'pg'),
        (r'\s+fl\s*$', 'fl'),
        (r'\s+%\s*$', '%'),
    ]
    extracted_unit = canonical_unit
    for pattern, unit_name in unit_patterns:
        um = re.search(pattern, value_clean, re.IGNORECASE)
        if um:
            extracted_unit = unit_name
            value_clean = value_clean[:um.start()].strip()
            break

    value = value_clean.strip()
    if not value:
        return None

    return {
        'test_name': test_name,
        'value': value,
        'unit': extracted_unit,
        'reference_range': extracted_range,
        'flag': flag,
    }


def _reconstruct_value(raw: str, test_name: str, category_key: str) -> str:
    """Reconstruct a numeric value that may have lost its decimal point.

    Input is space-separated digits from dot-stripping: '0 7', '2 6', '1 4 6'
    After joining: '07', '26', '146'
    Then applies context-aware decimal placement.
    """
    if not raw:
        return raw

    # Qualitative results — return as-is
    if re.match(r'^[A-Za-z]', raw):
        raw = re.sub(r'POSITIVE\s*\(', 'POSITIVE (', raw)
        return raw

    # Join space-separated digits into a single string
    # '0 7' → '07', '1 4 6' → '146', '5 7 1 0' → '5710'
    tokens = raw.split()
    if all(t.isdigit() for t in tokens):
        digits = ''.join(tokens)
    else:
        return raw

    if not digits:
        return raw

    # ═══ Context-aware decimal placement ═══
    # Tests whose results are always whole integers
    integer_tests = {
        'Urea/BUN', 'Glucose', 'Cholesterole Total', 'Cholesterol-HDL',
        'Cholesterol-LDL', 'Tryglyceride', 'MCV',
        'GGT', 'SGPT/ALT', 'SGOT/AST',
        'Morphine', 'Amphetamine', 'Metamphetamine',
    }
    if test_name in integer_tests:
        return digits

    # SG (specific gravity): '1010' → '1.010'
    if test_name == 'SG' and len(digits) == 4 and digits[0] == '1':
        return f'{digits[0]}.{digits[1:]}'

    # Tests with known decimal format: {test: integer_part_length}
    decimal_format = {
        'Creatinine, serum': 1,  # 0.7, 0.9, 1.0
        'Uric acide': -1,       # variable: 4.5, 10.2
        'WBC': -1,              # variable: 5.7, 9.4
        'LYM%': 2, 'MONO%': -1, 'NUE%': 2,
        'EOSINO%': 2, 'BASO%': 2,
        'HGB': 2,               # 36.7
        'MCH': -1,              # 4.56, 3.95
        'MCHC': 2,              # 69.2
        'RBC': 2,               # 31.5
        'URO': 1,               # 0.2
        'PH': 1,                # 6.5, 6.0
    }

    if test_name in decimal_format:
        int_len = decimal_format[test_name]
        if int_len > 0 and len(digits) > int_len:
            return f'{digits[:int_len]}.{digits[int_len:]}'
        elif int_len == -1:
            # Variable — smart heuristic
            if digits[0] == '0' and len(digits) > 1:
                return f'0.{digits[1:]}'
            # For values like '57' (WBC=5.7), '45' (Uric=4.5)
            if len(digits) == 2:
                return f'{digits[0]}.{digits[1]}'
            if len(digits) == 3:
                # Could be XX.X (e.g., MCH=4.56 → '456' → need 3 digits)
                # or X.XX
                if test_name == 'MCH':
                    return f'{digits[0]}.{digits[1:]}'
                return f'{digits[:2]}.{digits[2:]}'
            if len(digits) == 4:
                # e.g., Uric acide '102' comes as 3 digits, but '1002' doesn't happen
                return f'{digits[:2]}.{digits[2:]}'
        return digits

    # Default: leading zero means 0.X
    if digits[0] == '0' and len(digits) > 1:
        return f'0.{digits[1:]}'

    return digits



# ═══════════════════════════════════════════════════════════════════════════
#  MAIN PROCESSING ENTRY POINT
# ═══════════════════════════════════════════════════════════════════════════

def process_file(file_path: str) -> Dict[str, Any]:
    """Process a single PDF file and return structured extraction."""
    file_source = os.path.basename(file_path)
    log.info(f'Processing: {file_source}')

    # Step 1: Extract text
    raw_text = extract_text_from_pdf(file_path)
    log.info(f'Extracted {len(raw_text)} chars')

    # Step 2: Classify document
    doc_type = classify_document(raw_text)
    log.info(f'Classified as: {doc_type}')

    # Step 3: Parse based on classification
    if doc_type == 'Consultation_Form':
        result = _parse_consultation_form(raw_text, file_source)
    else:
        result = _parse_lab_report(raw_text, file_source)

    # Attach raw text for debugging
    result['raw_text'] = raw_text
    result['rawText'] = raw_text

    return result


def process_batch(file_paths: List[str], stream: bool = False) -> List[Dict[str, Any]]:
    """Process multiple PDF files."""
    results = []
    for fp in file_paths:
        try:
            result = process_file(fp)
            if stream:
                print(json.dumps(result, ensure_ascii=False), flush=True)
            else:
                results.append(result)
        except Exception as e:
            log.error(f'Failed to process {fp}: {e}')
            err = {
                'document_classification': 'Unknown',
                'file_source': os.path.basename(fp),
                'error': str(e),
                'success': False,
            }
            if stream:
                print(json.dumps(err, ensure_ascii=False), flush=True)
            else:
                results.append(err)
    return results


# ═══════════════════════════════════════════════════════════════════════════
#  CLI
# ═══════════════════════════════════════════════════════════════════════════

def main():
    parser = argparse.ArgumentParser(
        description='ClineX v2 OCR Extraction — Layout-Aware Document Parser'
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--file', help='Single PDF file to process')
    group.add_argument('--batch', help='Directory containing PDF files')
    group.add_argument('--file-list', help='Text file containing list of file paths')
    parser.add_argument('--output-format', choices=['json', 'pretty'], default='json')
    parser.add_argument('--output-file', help='Output file (default: stdout)')
    parser.add_argument('--stream', action='store_true', help='Stream results as JSON lines')
    # Backward-compatibility flags (accepted but ignored — v2 auto-detects layout)
    parser.add_argument('--document-type', help='(Ignored) Layout is auto-detected')
    parser.add_argument('--template', help='(Ignored) v2 uses built-in extraction rules')
    args = parser.parse_args()

    results = []

    if args.file:
        result = process_file(args.file)
        results = [result]
    elif args.batch:
        batch_dir = Path(args.batch)
        file_paths = sorted(str(f) for f in batch_dir.glob('*.pdf'))
        if not file_paths:
            log.error(f'No PDF files found in {batch_dir}')
            sys.exit(1)
        results = process_batch(file_paths, args.stream)
    elif args.file_list:
        with open(args.file_list, 'r') as f:
            file_paths = [line.strip() for line in f if line.strip()]
        results = process_batch(file_paths, args.stream)

    if not args.stream:
        if args.output_format == 'pretty':
            output = json.dumps(results, ensure_ascii=False, indent=2)
        else:
            output = json.dumps(results, ensure_ascii=False)

        if args.output_file:
            with open(args.output_file, 'w', encoding='utf-8') as f:
                f.write(output)
        else:
            sys.stdout.buffer.write(output.encode('utf-8'))


if __name__ == '__main__':
    main()
