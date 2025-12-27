import sys
import os
import pytesseract
from pdf2image import convert_from_path

pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

def extract_text_from_pdf(pdf_path):
    """
    Extracts text from all pages of a PDF file and returns it as a single string.
    """
    absolute_pdf_path = os.path.abspath(pdf_path)
    # --- NEW DEBUG LOGGING ---
    print(f"Python Log: Script started. Attempting to process '{absolute_pdf_path}'.", file=sys.stderr)

    try:
        # This points to your Poppler installation
        images = convert_from_path(absolute_pdf_path, poppler_path=r'C:\poppler\Library\bin') 

        # --- NEW DEBUG LOGGING ---
        print(f"Python Log: PDF successfully converted into {len(images)} image(s).", file=sys.stderr)

        full_text = ""

        # Loop through each page image and perform OCR
        for i, image in enumerate(images):
            # --- NEW DEBUG LOGGING ---
            print(f"Python Log: Processing page {i + 1} with OCR...", file=sys.stderr)
            
            # You can add configuration options here if needed, for example:
            # custom_config = r'--oem 3 --psm 6'
            # text = pytesseract.image_to_string(image, config=custom_config)
            
            text = pytesseract.image_to_string(image)
            full_text += text + "\n--- PAGE BREAK ---\n"

        # --- NEW DEBUG LOGGING ---
        print("Python Log: OCR complete. Returning text to Laravel.", file=sys.stderr)
        return full_text

    except Exception as e:
        print(f"Python Error: {e}", file=sys.stderr)
        return None

if __name__ == "__main__":
    if len(sys.argv) > 1:
        pdf_file_path = sys.argv[1]
        extracted_text = extract_text_from_pdf(pdf_file_path)
        if extracted_text:
            print(extracted_text)
    else:
        print("Python Error: No PDF file path provided.", file=sys.stderr)