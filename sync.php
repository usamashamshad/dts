<?php
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!empty($_GET['board'])) {
    $projects = loadAllProjects();
    echo json_encode([
        'ok' => true,
        'signature' => boardSyncSignature($projects),
        'projects' => array_map(static function ($p) {
            return [
                'id' => $p['id'],
                'filesCount' => (int)($p['filesCount'] ?? 0),
                'foldersCount' => (int)($p['foldersCount'] ?? 0),
                'scanOk' => !empty($p['scanOk']),
                'signature' => $p['syncSignature'] ?? '',
            ];
        }, $projects),
        'syncedAt' => time(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$projectId = $_GET['project'] ?? '';
if (!$projectId || !projectConfigById($projectId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid project']);
    exit;
}

$mode = $_GET['source'] ?? 'local';
if (!in_array($mode, ['local', 'gdrive', 'all'], true)) {
    $mode = 'local';
}

if (!empty($_GET['warm'])) {
    $store = loadMetaStore();
    $gdriveUrl = trim((string)($store[$projectId]['gdriveFolderUrl'] ?? ''));
    if ($gdriveUrl !== '') {
        scanGdriveFolder($gdriveUrl, false, $projectId);
    }
    echo json_encode(['ok' => true, 'warmed' => true, 'syncedAt' => time()], JSON_UNESCAPED_UNICODE);
    exit;
}

$scan = scanProjectForId($projectId, false, $mode);
echo json_encode([
    'ok' => true,
    'signature' => syncSignatureFromScan($scan),
    'filesCount' => (int)($scan['files_count'] ?? 0),
    'foldersCount' => (int)($scan['folders_count'] ?? 0),
    'folderName' => $scan['folder_name'] ?? '',
    'scanOk' => !empty($scan['ok']),
    'sources' => $scan['sources'] ?? [],
    'source' => $mode,
    'gdriveScanOk' => !empty($scan['gdriveScan']['ok']),
    'gdriveScanError' => $scan['gdriveScan']['error'] ?? null,
    'syncedAt' => time(),
], JSON_UNESCAPED_UNICODE);
