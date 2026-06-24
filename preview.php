<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/includes/preview-render.php';

header('Content-Type: application/json; charset=utf-8');

$projectId = $_GET['project'] ?? '';
$path = $_GET['path'] ?? '';

if (!$projectId || !projectConfigById($projectId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Project not found']);
    exit;
}

$file = $path !== '' ? resolveProjectFile($projectId, $path) : null;
if (!$file) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'File not found']);
    exit;
}

$project = projectPreviewContext($projectId);

echo json_encode([
    'ok' => true,
    'html' => renderPreviewPanel($project, $file),
    'file' => ['path' => $file['path'], 'name' => $file['name']],
]);
