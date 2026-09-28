<?php

return [

    /*
    |--------------------------------------------------------------------------
    | pdftk binary
    |--------------------------------------------------------------------------
    |
    | Path to the pdftk executable used to read, merge and bookmark PDFs.
    |
    */

    'pdftk' => env('PDFTK_BINARY', 'pdftk'),

    /*
    |--------------------------------------------------------------------------
    | Conversion binaries
    |--------------------------------------------------------------------------
    |
    | External tools used by the Optimize and Convert-to-PDF tools
    | (apt: ghostscript qpdf ocrmypdf tesseract-ocr-eng img2pdf libreoffice-*).
    |
    */

    'binaries' => [
        'gs' => env('GHOSTSCRIPT_BINARY', 'gs'),
        'qpdf' => env('QPDF_BINARY', 'qpdf'),
        'ocrmypdf' => env('OCRMYPDF_BINARY', 'ocrmypdf'),
        'img2pdf' => env('IMG2PDF_BINARY', 'img2pdf'),
        'soffice' => env('SOFFICE_BINARY', 'soffice'),
        // Python 3 with PyMuPDF, openpyxl and python-docx (apt: python3-fitz
        // python3-openpyxl python3-docx), used by resources/python/pdfops.py.
        'python' => env('PDF_PYTHON_BINARY', 'python3'),
        'pdftoppm' => env('PDFTOPPM_BINARY', 'pdftoppm'),
        'pdfimages' => env('PDFIMAGES_BINARY', 'pdfimages'),
    ],

    'ocr_language' => env('PDF_OCR_LANGUAGE', 'eng'),

    /*
    |--------------------------------------------------------------------------
    | AI tools
    |--------------------------------------------------------------------------
    |
    | AI Summarizer and Translate PDF call the Claude API and show as
    | unavailable until ANTHROPIC_API_KEY is set. Every run is billed to
    | that key's Anthropic account.
    |
    */

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('PDF_AI_MODEL', 'claude-opus-5'),
        // Largest PDF sent to Claude as-is; bigger files are sent as extracted text.
        'max_document_mb' => 20,
    ],

    // Seconds a single conversion may run before it is killed.
    'timeout' => (int) env('PDF_TOOL_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | The per-file size limit is also applied to Livewire's temporary upload
    | rule in config/livewire.php, so keep PHP's upload_max_filesize and
    | post_max_size at least this large.
    |
    */

    'max_files' => (int) env('PDF_MAX_FILES', 20),

    'max_file_mb' => (int) env('PDF_MAX_FILE_MB', 25),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Working files live on this disk under "{directory}/{workspace}" and are
    | deleted by the pdf:prune command once they are older than the retention
    | period.
    |
    */

    'disk' => env('PDF_DISK', 'local'),

    'directory' => 'pdf-workspaces',

    'retention_minutes' => (int) env('PDF_RETENTION_MINUTES', 60),

];
