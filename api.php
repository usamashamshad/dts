<?php
require __DIR__ . '/lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $body['action'] ?? '';

if ($action === 'save_project') {
    $id = $body['id'] ?? '';
    if (!$id || !projectConfigById($id)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid project']);
        exit;
    }
    $allowed = [
        'title', 'subtitle', 'status', 'progress', 'introduction', 'executiveSummary', 'summary',
        'location', 'client', 'startDate', 'closingDate', 'sponsor', 'sponsorLogoUrl', 'clientLogoUrl',
        'locationMapUrl', 'panoramaUrl', 'disclaimer', 'projectSummarySheetUrl', 'projectSummarySheetName', 'budget', 'budgetSource', 'pm', 'consultants', 'activePhase',
        'gdriveFolderUrl',
    ];
    $fields = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $body)) {
            $fields[$key] = $body[$key];
        }
    }

    // Prevent wiping existing metadata by saving empty values.
    // Users often edit one field and hit save; keep other fields unchanged unless set to non-empty.
    foreach (['panoramaUrl', 'disclaimer', 'sponsorLogoUrl', 'clientLogoUrl', 'locationMapUrl', 'projectSummarySheetUrl', 'projectSummarySheetName', 'consultants'] as $k) {
        if (array_key_exists($k, $fields) && trim((string)$fields[$k]) === '') {
            unset($fields[$k]);
        }
    }

    if (isset($body['progress'])) {
        $fields['progress'] = max(0, min(100, (int)$body['progress']));
    }
    if (array_key_exists('gdriveFolderUrl', $body)) {
        $newUrl = trim((string)$body['gdriveFolderUrl']);
        $fields['gdriveFolderUrl'] = $newUrl;
        gdriveClearCache($id);
    }
    if (array_key_exists('localFolderPath', $body)) {
        $localPath = trim((string)$body['localFolderPath']);
        if ($localPath === '') {
            clearFolderOverride($id);
        } else {
            $resolved = resolvePath($localPath);
            if (!$resolved || !is_dir($resolved)) {
                echo json_encode(['ok' => false, 'error' => 'Local folder does not exist: ' . $localPath]);
                exit;
            }
            if (!saveFolderOverride($id, $localPath)) {
                echo json_encode(['ok' => false, 'error' => 'Could not save local folder path']);
                exit;
            }
        }
    }
    if (array_key_exists('cvMembers', $body)) {
        $fields['cvMembers'] = sanitizeCvMembers($body['cvMembers']);
    }
    if (array_key_exists('timesheet', $body)) {
        $fields['timesheet'] = sanitizeTimesheet($body['timesheet']);
    }
    echo json_encode(['ok' => updateProjectMeta($id, $fields)]);
    exit;
}

if ($action === 'test_gdrive') {
    $url = trim($body['url'] ?? '');
    $projectId = trim($body['id'] ?? $body['project_id'] ?? '');
    if ($url === '') {
        echo json_encode(['ok' => false, 'error' => 'Enter a Google Drive folder link']);
        exit;
    }
    $folderId = parseGdriveFolderId($url);
    if (!$folderId) {
        echo json_encode(['ok' => false, 'error' => 'Invalid Google Drive folder link']);
        exit;
    }
    $scan = scanGdriveFolder($url, true, $projectId !== '' ? $projectId : null);
    echo json_encode([
        'ok' => !empty($scan['ok']),
        'error' => $scan['error'] ?? null,
        'filesCount' => (int)($scan['files_count'] ?? 0),
        'foldersCount' => (int)($scan['folders_count'] ?? 0),
        'folderName' => $scan['folder_name'] ?? 'Google Drive',
        'folderId' => $folderId,
    ]);
    exit;
}

if ($action === 'test_local_folder') {
    $path = trim($body['path'] ?? '');
    if ($path === '') {
        echo json_encode(['ok' => false, 'error' => 'Enter a local folder path']);
        exit;
    }
    $resolved = resolvePath($path);
    if (!$resolved || !is_dir($resolved)) {
        echo json_encode(['ok' => false, 'error' => 'Folder does not exist: ' . $path]);
        exit;
    }
    $scan = scanProject($path);
    echo json_encode([
        'ok' => !empty($scan['ok']),
        'error' => $scan['error'] ?? null,
        'filesCount' => (int)($scan['files_count'] ?? 0),
        'foldersCount' => (int)($scan['folders_count'] ?? 0),
        'folderName' => $scan['folder_name'] ?? basename($resolved),
        'resolvedPath' => $resolved,
    ]);
    exit;
}

if ($action === 'delete_project') {
    $id = trim($body['id'] ?? '');
    echo json_encode(deleteProject($id));
    exit;
}

if ($action === 'create_project') {
    $name = trim($body['name'] ?? '');
    $id = trim($body['id'] ?? '');
    if ($id === '' && $name !== '') {
        $id = slugifyProjectId($name);
    }
    $path = trim($body['path'] ?? '');
    $cloneFrom = trim($body['clone_from'] ?? '') ?: null;
    $result = createProject($id, $name, $path, $cloneFrom);
    echo json_encode($result);
    exit;
}

if ($action === 'save_folder') {
    $id = $body['id'] ?? $body['project_id'] ?? '';
    $path = trim($body['path'] ?? '');
    if (!$id || !projectConfigById($id)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid project']);
        exit;
    }
    if ($path === '') {
        echo json_encode(['ok' => false, 'error' => 'Enter a folder path']);
        exit;
    }
    $resolved = resolvePath($path);
    if (!$resolved || !is_dir($resolved)) {
        echo json_encode(['ok' => false, 'error' => 'Folder does not exist: ' . $path]);
        exit;
    }
    echo json_encode(['ok' => saveFolderOverride($id, $path)]);
    exit;
}

if ($action === 'set_phase') {
    $id = $body['id'] ?? '';
    $phase = $body['phase'] ?? '';
    if (!$id || !projectConfigById($id)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    echo json_encode(['ok' => updateProjectMeta($id, ['activePhase' => $phase])]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
