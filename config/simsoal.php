<?php

$defaultPythonBinary = 'python';
$windowsVenvPython = base_path('engine/.venv/Scripts/python.exe');$unixVenvPython = base_path('engine/.venv/bin/python');

if (is_file($windowsVenvPython)) {
    $defaultPythonBinary =$windowsVenvPython;
} elseif (is_file($unixVenvPython)) {
    $defaultPythonBinary =$unixVenvPython;
}

return [
    'max_upload_mb' => (int) env('DOCUMENT_MAX_UPLOAD_MB', 30),
    'max_batch_files' => (int) env('DOCUMENT_MAX_BATCH_FILES', 20),
    'queue_timeout' => (int) env('DOCUMENT_QUEUE_TIMEOUT', 300),
    'process_timeout' => (int) env('DOCUMENT_PROCESS_TIMEOUT', 120),
    'python_binary' => env('DOCUMENT_ENGINE_PYTHON') ?: $defaultPythonBinary,
    'engine_script' => env('DOCUMENT_ENGINE_SCRIPT', 'engine/simsoal_docx/cli.py'),
    'libreoffice_binary' => env('LIBREOFFICE_BINARY', 'C:\\Program Files\\LibreOffice\\program\\soffice.exe'),
];