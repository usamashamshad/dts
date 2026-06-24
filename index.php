<?php
require __DIR__ . '/lib.php';

$config = cfg();
$projectId = $_GET['project'] ?? '';
$pickId = $_GET['pick'] ?? '';
$search = trim($_GET['q'] ?? '');

// Phase update via GET
if ($projectId && isset($_GET['phase'])) {
    updateProjectMeta($projectId, ['activePhase' => $_GET['phase']]);
}

if ($projectId) {
    $folder = $_GET['folder'] ?? '';
    $filePath = $_GET['file'] ?? '';
    $q = trim($_GET['q'] ?? '');
    $panel = $_GET['panel'] ?? '';

    $project = loadProject($projectId, projectScanMode($folder, $panel, $projectId));
    if (!$project) {
        header('Location: index.php');
        exit;
    }

    if ($folder === '' && $panel === '') {
        $folder = resolveDefaultWorkspaceFolder($projectId, $project);
    }

    if ($panel === '' && $folder !== '' && isGdriveFolderPath($folder) && empty($project['files'][$folder] ?? [])) {
        foreach ($project['folders'] as $candidate) {
            if (isGdriveFolderPath($candidate) && !empty($project['files'][$candidate])) {
                $redirect = ['project' => $projectId, 'folder' => $candidate];
                if ($filePath !== '') {
                    $redirect['file'] = $filePath;
                }
                if ($q !== '') {
                    $redirect['q'] = $q;
                }
                header('Location: ' . url($redirect));
                exit;
            }
        }
    }

    $fileList = $project['files'][$folder] ?? [];
    if ($q !== '') {
        $lq = mb_strtolower($q);
        $fileList = array_values(array_filter($fileList, function ($f) use ($lq) {
            return str_contains(mb_strtolower($f['name'] . ' ' . $f['kind']), $lq);
        }));
    }

    $selectedFile = null;
    if ($filePath !== '') {
        foreach ($project['files'][$folder] ?? [] as $f) {
            if ($f['path'] === $filePath) {
                $selectedFile = $f;
                break;
            }
        }
        if (!$selectedFile) {
            foreach ($project['files'] as $list) {
                foreach ($list as $f) {
                    if ($f['path'] === $filePath) {
                        $selectedFile = $f;
                        break 2;
                    }
                }
            }
        }
    }

    $selectedCvMember = null;
    $selectedCvFile = null;
    if ($panel === 'cvs') {
        $cvMemberId = $_GET['cv'] ?? '';
        foreach ($project['cvMembers'] as $m) {
            if ($cvMemberId !== '' && ($m['id'] ?? '') === $cvMemberId) {
                $selectedCvMember = $m;
                $selectedCvFile = $m['cvFile'] ?? null;
                break;
            }
        }
    }

    $pageClass = 'workspace';
    // $projectId already set from GET
    include __DIR__ . '/includes/header.php';
    include __DIR__ . '/includes/workspace.php';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Board
$projects = loadAllProjects();
$selected = null;
$selIndex = 0;
if ($pickId) {
    foreach ($projects as $i => $p) {
        if ($p['id'] === $pickId) {
            $selected = $p;
            $selIndex = $i;
            break;
        }
    }
} elseif (count($projects) > 0) {
    $selected = $projects[0];
    $selIndex = 0;
}

$pageClass = 'board';
$boardSyncSignature = boardSyncSignature($projects);
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/board.php';
include __DIR__ . '/includes/footer.php';
