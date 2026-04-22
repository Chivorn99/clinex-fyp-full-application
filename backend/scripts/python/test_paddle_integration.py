#!/usr/bin/env python3
"""
Quick test script for PaddleOCR integration
Run this once the venv setup completes
"""

import sys
import json
from pathlib import Path

# Test imports
def test_imports():
    print("Testing imports...")
    try:
        from paddleocr import PaddleOCR
        print("✓ PaddleOCR imported successfully")
    except ImportError as e:
        print(f"✗ PaddleOCR import failed: {e}")
        return False
    
    try:
        from google.cloud import documentai
        print("✓ Google Document AI SDK imported successfully")
    except ImportError as e:
        print(f"✗ Google Document AI import failed: {e}")
        return False
    
    return True


def test_paddle_config():
    print("\nTesting PaddleOCR configuration...")
    sys.path.insert(0, str(Path(__file__).parent))
    
    from document_ocr import _get_paddle_config
    
    try:
        config = _get_paddle_config()
        print(f"✓ PaddleOCR config loaded:")
        print(f"  - Enabled: {config['enabled']}")
        print(f"  - Language: {config['language']}")
        print(f"  - Threshold: {config['confidence_threshold']}")
        return True
    except Exception as e:
        print(f"✗ Config loading failed: {e}")
        return False


def test_paddle_ocr_on_sample():
    print("\nTesting PaddleOCR on sample image...")
    sys.path.insert(0, str(Path(__file__).parent))
    
    from document_ocr import process_with_paddle_ocr
    
    sample_file = Path(__file__).parent.parent / "test_file" / "report1.jpg"
    if not sample_file.exists():
        print(f"✗ Test file not found: {sample_file}")
        return False
    
    try:
        result = process_with_paddle_ocr(str(sample_file))
        print(f"✓ PaddleOCR processing succeeded:")
        print(f"  - Text extracted: {len(result['text'])} characters")
        print(f"  - Confidence: {result['confidence']:.3f}")
        print(f"  - Page confidences: {result['page_confidences']}")
        return True
    except Exception as e:
        print(f"✗ PaddleOCR processing failed: {e}")
        import traceback
        traceback.print_exc()
        return False


def test_hybrid_routing():
    print("\nTesting hybrid fallback routing...")
    sys.path.insert(0, str(Path(__file__).parent))
    
    from document_ocr import process_single_file
    
    sample_file = Path(__file__).parent.parent / "test_file" / "report1.jpg"
    if not sample_file.exists():
        print(f"✗ Test file not found: {sample_file}")
        return False
    
    try:
        result = process_single_file(str(sample_file))
        print(f"✓ Hybrid processing succeeded:")
        print(f"  - Success: {result.get('success')}")
        print(f"  - OCR Engine: {result.get('ocr_engine')}")
        print(f"  - Confidence: {result.get('confidence')}")
        print(f"  - Patient Info: {len(result.get('patientInfo', {}))} fields")
        print(f"  - Lab Info: {len(result.get('labInfo', {}))} fields")
        print(f"  - Test Results: {len(result.get('testResults', []))} tests")
        return result.get('success', False)
    except Exception as e:
        print(f"✗ Hybrid processing failed: {e}")
        import traceback
        traceback.print_exc()
        return False


if __name__ == '__main__':
    print("=" * 60)
    print("PaddleOCR Integration Test Suite")
    print("=" * 60)
    
    results = []
    results.append(("Imports", test_imports()))
    results.append(("Config", test_paddle_config()))
    results.append(("PaddleOCR", test_paddle_ocr_on_sample()))
    results.append(("Hybrid Routing", test_hybrid_routing()))
    
    print("\n" + "=" * 60)
    print("Test Summary")
    print("=" * 60)
    for test_name, passed in results:
        status = "✓ PASSED" if passed else "✗ FAILED"
        print(f"{test_name:.<40} {status}")
    
    all_passed = all(r[1] for r in results)
    print("\nOverall: " + ("✓ ALL TESTS PASSED" if all_passed else "✗ SOME TESTS FAILED"))
    sys.exit(0 if all_passed else 1)
