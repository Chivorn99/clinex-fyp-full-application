#!/usr/bin/env python3
"""Compare KiriOCR-only vs Google Document AI parsing accuracy."""
import sys, json, os, glob
sys.path.insert(0, '/var/www/html/scripts/python')

from document_ocr import process_with_kiri_ocr, process_with_google_document_ai, OptimizedLabReportParser

# Find all test files
base = '/var/www/html/storage/app/private/lab_reports'
files = []
for ext in ['*.jpg', '*.png', '*.pdf']:
    files.extend(glob.glob(os.path.join(base, '**', ext), recursive=True))

files = sorted(files)[:5]  # first 5 files
parser = OptimizedLabReportParser()

print('=' * 80)
print('KIRI OCR vs GOOGLE DOCUMENT AI - ACCURACY COMPARISON')
print('=' * 80)

for f in files:
    fname = os.path.basename(f)
    print('\n' + '-' * 60)
    print('FILE:', fname)
    print('-' * 60)

    # Test KiriOCR
    try:
        kiri_result = process_with_kiri_ocr(f)
        kiri_text = kiri_result['text']
        kiri_conf = kiri_result['confidence']
        kiri_parsed = parser.parse_optimized(kiri_text)
        kiri_pi = kiri_parsed.get('patientInfo', {})
        kiri_li = kiri_parsed.get('labInfo', {})
        kiri_tr = kiri_parsed.get('testResults', [])
    except Exception as e:
        print('  KIRI: FAILED -', str(e))
        kiri_pi, kiri_li, kiri_tr = {}, {}, []
        kiri_conf = 0

    # Test Google
    try:
        google_text = process_with_google_document_ai(f)
        google_parsed = parser.parse_optimized(google_text)
        google_pi = google_parsed.get('patientInfo', {})
        google_li = google_parsed.get('labInfo', {})
        google_tr = google_parsed.get('testResults', [])
    except Exception as e:
        print('  GOOGLE: FAILED -', str(e))
        google_pi, google_li, google_tr = {}, {}, []

    # Compare
    fields = ['name', 'age', 'gender', 'phone']
    print('\n  PATIENT INFO:')
    print('  {:<12} {:<30} {:<30}'.format('Field', 'KiriOCR', 'Google'))
    for field in fields:
        kv = kiri_pi.get(field, '-')
        gv = google_pi.get(field, '-')
        match = 'OK' if kv == gv else 'DIFF'
        print('  {:<12} {:<30} {:<30} {}'.format(field, str(kv)[:28], str(gv)[:28], match))

    print('\n  LAB INFO:')
    lab_fields = ['labId', 'requestedBy', 'requestedDate', 'validatedBy']
    print('  {:<16} {:<30} {:<30}'.format('Field', 'KiriOCR', 'Google'))
    for field in lab_fields:
        kv = kiri_li.get(field, '-')
        gv = google_li.get(field, '-')
        match = 'OK' if kv == gv else 'DIFF'
        print('  {:<16} {:<30} {:<30} {}'.format(field, str(kv)[:28], str(gv)[:28], match))

    print('\n  TEST RESULTS: Kiri={} tests, Google={} tests'.format(len(kiri_tr), len(google_tr)))
    print('  Kiri confidence: {:.3f}'.format(kiri_conf))

    # Compare test results
    kiri_tests = {t.get('testName', ''): t for t in kiri_tr}
    google_tests = {t.get('testName', ''): t for t in google_tr}
    all_test_names = sorted(set(list(kiri_tests.keys()) + list(google_tests.keys())))

    if all_test_names:
        print('  {:<28} {:<12} {:<12} {:<8} {:<8}'.format('Test', 'Kiri Val', 'Google Val', 'K-Flag', 'G-Flag'))
        for name in all_test_names[:10]:
            kt = kiri_tests.get(name, {})
            gt = google_tests.get(name, {})
            kr = kt.get('result', '-')
            gr = gt.get('result', '-')
            kf = kt.get('flag', '-')
            gf = gt.get('flag', '-')
            rmatch = 'OK' if kr == gr else 'DIFF'
            print('  {:<28} {:<12} {:<12} {:<8} {:<8} {}'.format(
                name[:26], str(kr)[:10], str(gr)[:10], str(kf), str(gf), rmatch))

print('\n' + '=' * 80)
print('COMPARISON COMPLETE')
print('=' * 80)
