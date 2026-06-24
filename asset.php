<?php
require __DIR__ . '/lib.php';

$projectId = $_GET['project'] ?? '';
$filename = basename($_GET['file'] ?? '');
$download = isset($_GET['download']);

if (!$projectId || !$filename || !projectConfigById($projectId)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

$full = safeAssetFile($projectId, $filename);
if (!$full || !is_readable($full)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('File not found');
}

$mime = mimeType($filename);
$size = filesize($full);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

$asciiName = preg_replace('/[^\x20-\x7E]/', '_', $filename);
if ($download) {
    header('Content-Disposition: attachment; filename="' . $asciiName . '"');
} else {
    header('Content-Disposition: inline; filename="' . $asciiName . '"');
}

readfile($full);
exit;
