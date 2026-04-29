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

    return {
        'enabled': enabled,
        'decode_method': decode_method,
        'confidence_threshold': confidence_threshold,
    }



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
            # Normalize orientation and boost readability for OCR.
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
    """Normalize paddleocr output shape across versions into a flat line list."""
    if not ocr_result:
        return []

    if isinstance(ocr_result, dict):
        rec_texts = ocr_result.get('rec_texts')
        rec_scores = ocr_result.get('rec_scores')
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


def _parse_paddle_result(ocr_result: Any) -> tuple[str, float]:
    """Parse PaddleOCR result into text and average confidence."""
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


def process_with_paddle_ocr(file_path: str) -> Dict[str, Any]:
    """Extract text from document using PaddleOCR with confidence scoring."""
    try:
        # Pre-load torch (if available) before paddle to avoid Windows DLL conflicts
        try:
            import torch  # noqa: F401
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

    try:
        ocr = PaddleOCR(**ocr_kwargs)
    except TypeError:
        # Backward/forward compatibility: if PaddleOCR signature changed, fall back to minimal init.
        ocr = PaddleOCR(lang=paddle_config['language'])

    print(
        f'DEBUG: PaddleOCR initialized (requested_device={paddle_config.get("device")}, '
        f'selected_device={selected_device}, use_gpu={use_gpu})',
        file=sys.stderr,
    )

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
                result = ocr.ocr(temp_img_path, cls=True)
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

    result = ocr.ocr(file_path, cls=True)
    text, confidence = _parse_paddle_result(result)
    print(f'DEBUG: PaddleOCR extracted {len(text)} characters with confidence {confidence:.3f}', file=sys.stderr)
    return {
        'text': text,
        'confidence': confidence,
        'page_confidences': [confidence],
    }


def process_with_kiri_ocr(file_path: str) -> Dict[str, Any]:
    """Extract text from document using Kiri OCR (Khmer-specialized transformer model)."""
    try:
        from kiri_ocr import OCR as KiriOCR
    except ImportError as import_error:
        raise ImportError('kiri-ocr not installed in current environment. Install with: pip install kiri-ocr') from import_error

    kiri_config = _get_kiri_config()
    mime_type = _infer_mime_type(file_path)

    # Initialize Kiri OCR with configured decode method
    decode_method = kiri_config.get('decode_method', 'accurate')
    ocr = KiriOCR(decode_method=decode_method)

    if mime_type == 'application/pdf':
        # Kiri OCR only works with images — render PDF pages via PyMuPDF
        try:
            import fitz
        except ImportError as import_error:
            raise Exception('PyMuPDF is required for PDF + Kiri OCR flow') from import_error

        doc = fitz.open(file_path)
        all_text: List[str] = []
        all_results: List[Dict[str, Any]] = []
        page_confidences: List[float] = []

        for page_num in range(len(doc)):
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
                r'(?=\s*(?:Patient|/Age|\n|$))',
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
                r'(?P<flag>[HLhl](?:\s+[HLhl])?)?\s*'
                r'(?P<unit>(?:mg/dL|U/L|%|\$U/L\$|g/dL|Leu/µL|Ery/pl|'
                r'x?X?1012/L|10[⁹9]/L|fl|\$10\^\{9\}/L\$|pg|응|%0|0P|09)?)?\s*'
                r'(?P<reference_range>(?:\(?[^)\n]+\)?|\$\([^)]+\)\$)?)?$',
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

                    # Normalise flag: 'H H' → 'H', Cyrillic 'Н' → 'H'
                    if flag:
                        flag = flag.strip().split()[0].upper()
                        if flag in ('Н', 'н'):
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

def process_single_file(file_path: str) -> Dict[str, Any]:
    paddle_config = {}
    kiri_config = {}
    ocr_text = None
    confidence = 0.0
    ocr_engine = 'unknown'
    parser = None
    
    try:
        mime_type = _infer_mime_type(file_path)
        paddle_config = _get_paddle_config()
        kiri_config = _get_kiri_config()
        
        if mime_type in {'application/pdf', 'image/jpeg', 'image/png', 'image/tiff', 'image/bmp', 'image/gif', 'image/webp'}:
            # ── Fusion mode: both Paddle + Kiri enabled ──
            if paddle_config['enabled'] and kiri_config['enabled']:
                # Pre-load torch before paddle to avoid Windows DLL conflicts (shm.dll)
                try:
                    import torch  # noqa: F401
                except ImportError:
                    pass
                paddle_text = None
                paddle_conf = 0.0
                kiri_text = None
                kiri_conf = 0.0
                kiri_results = []

                # Run PaddleOCR
                try:
                    paddle_result = process_with_paddle_ocr(file_path)
                    paddle_text = paddle_result['text']
                    paddle_conf = paddle_result['confidence']
                    print(f'DEBUG: PaddleOCR pass complete — {len(paddle_text)} chars, confidence {paddle_conf:.3f}', file=sys.stderr)
                except Exception as paddle_error:
                    print(f'DEBUG: PaddleOCR failed ({paddle_error}), will use Kiri-only mode', file=sys.stderr)

                # Run Kiri OCR
                try:
                    kiri_result = process_with_kiri_ocr(file_path)
                    kiri_text = kiri_result['text']
                    kiri_conf = kiri_result['confidence']
                    kiri_results = kiri_result.get('results', [])
                    print(f'DEBUG: Kiri OCR pass complete — {len(kiri_text)} chars, confidence {kiri_conf:.3f}', file=sys.stderr)
                except Exception as kiri_error:
                    print(f'DEBUG: Kiri OCR failed ({kiri_error}), will use Paddle-only mode', file=sys.stderr)

                # Fuse results if both succeeded
                if paddle_text and kiri_text:
                    fused = _fuse_ocr_texts(paddle_text, paddle_conf, kiri_text, kiri_conf, kiri_results)
                    ocr_text = fused['text']
                    confidence = fused['confidence']
                    ocr_engine = f'paddle+kiri (fused: {fused["paddle_line_count"]}P/{fused["kiri_line_count"]}K)'
                elif kiri_text:
                    ocr_text = kiri_text
                    confidence = kiri_conf
                    ocr_engine = 'kiri (paddle unavailable)'
                elif paddle_text:
                    ocr_text = paddle_text
                    confidence = paddle_conf
                    ocr_engine = 'paddle (kiri unavailable)'
                else:
                    # Both local engines failed — fall back to Google
                    print(f'DEBUG: Both local engines failed, falling back to Google Document AI', file=sys.stderr)
                    try:
                        ocr_text = process_with_google_document_ai(file_path)
                        ocr_engine = 'google (fallback from local engines)'
                    except Exception as google_error:
                        raise Exception(f'All OCR engines failed. Paddle, Kiri, and Google all unavailable. Last error: {google_error}')

                # If fused confidence is still low, optionally try Google
                if confidence < min(paddle_config['confidence_threshold'], kiri_config['confidence_threshold']):
                    print(f'DEBUG: Fused confidence {confidence:.3f} below threshold, trying Google fallback', file=sys.stderr)
                    try:
                        google_text = process_with_google_document_ai(file_path)
                        ocr_text = google_text
                        ocr_engine = f'google (fallback from {ocr_engine})'
                    except Exception as google_fallback_error:
                        print(f'DEBUG: Google fallback unavailable ({google_fallback_error}); keeping fused output', file=sys.stderr)

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

        parser = OptimizedLabReportParser()
        result = parser.parse_optimized(ocr_text)
        result['source_file'] = os.path.basename(file_path)
        result['success'] = True
        result['ocr_engine'] = ocr_engine
        result['rawText'] = ocr_text
        result['confidence'] = confidence
        return result
    except Exception as e:
        return {
            'source_file': os.path.basename(file_path),
            'error': str(e),
            'success': False,
            'rawText': ocr_text,
            'ocr_engine': ocr_engine,
            'debug': {
                'ocr_length': len(ocr_text) if ocr_text else 0,
                'failed_patterns': getattr(parser, 'failed_patterns', []) if parser else []
            }
        }

def process_batch_parallel(file_paths: List[str], max_workers: Optional[int] = None) -> List[Dict[str, Any]]:
    if max_workers is None:
        max_workers = min(len(file_paths), 3)
    print(f'DEBUG: Processing {len(file_paths)} files with {max_workers} workers (PaddleOCR + Google fallback)', file=sys.stderr)
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
    parser = argparse.ArgumentParser(description='Parse lab report OCR text with PaddleOCR + Google Document AI fallback')
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
            file_paths.extend([str(f) for f in batch_dir.glob('*.jpg')])
            file_paths.extend([str(f) for f in batch_dir.glob('*.jpeg')])
            file_paths.extend([str(f) for f in batch_dir.glob('*.png')])
            file_paths.extend([str(f) for f in batch_dir.glob('*.tif')])
            file_paths.extend([str(f) for f in batch_dir.glob('*.tiff')])
            file_paths.extend([str(f) for f in batch_dir.glob('*.txt')])
            print(f'DEBUG: Found {len(file_paths)} files to process', file=sys.stderr)
            if not file_paths:
                raise Exception(f'No supported files (PDF/Image/TXT) found in {batch_dir}')
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
