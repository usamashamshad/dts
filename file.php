<?php
require __DIR__ . '/lib.php';

$projectId = $_GET['project'] ?? '';
$rel = $_GET['path'] ?? '';
$download = isset($_GET['download']);

$conf = projectConfigById($projectId);
if (!$conf) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Project not found');
}

$rel = str_replace('\\', '/', urldecode($rel));

if (preg_match('#^__gdrive__/([^/]+)/(.+)$#', $rel, $m)) {
    gdriveStreamFile($projectId, $m[1], $download, basename($m[2]));
}

$rootPath = projectFolderPath($projectId);
$full = safeFile($rootPath, $rel);
if (!$full || !is_readable($full)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('File not found: ' . $rel);
}

$name = basename($full);
$mime = mimeType($name);
$size = filesize($full);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

$asciiName = preg_replace('/[^\x20-\x7E]/', '_', $name);
if ($download) {
    header('Content-Disposition: attachment; filename="' . $asciiName . '"');
} else {
    header('Content-Disposition: inline; filename="' . $asciiName . '"');
}

$fp = fopen($full, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
} else {
    readfile($full);
}
exit;
