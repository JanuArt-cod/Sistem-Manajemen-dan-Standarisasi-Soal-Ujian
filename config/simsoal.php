<?php

return [
    'max_upload_mb' => (int) env('DOCUMENT_MAX_UPLOAD_MB', 30),
    'max_batch_files' => (int) env('DOCUMENT_MAX_BATCH_FILES', 20),
    'queue_timeout' => (int) env('DOCUMENT_QUEUE_TIMEOUT', 300),
    'python_binary' => env('DOCUMENT_ENGINE_PYTHON', 'python'),
    'engine_script' => env('DOCUMENT_ENGINE_SCRIPT', 'tools/document_engine/process_docx.py'),
    'libreoffice_binary' => env('LIBREOFFICE_BINARY', 'C:\\Program Files\\LibreOffice\\program\\soffice.exe'),
];