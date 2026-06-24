<?php

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/lib/gdrive.php';

function cfg(): array
{
    static $c;
    if (!$c) {
        $c = require __DIR__ . '/config.php';
    }
    return $c;
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Short label for tight UI boxes — full name stays in title attribute */
function shortDisplayName(string $name, int $max = 28): string
{
    if (strlen($name) <= $max) {
        return $name;
    }
    $dot = strrpos($name, '.');
    $ext = $dot !== false ? substr($name, $dot) : '';
    $base = $dot !== false ? substr($name, 0, $dot) : $name;
    $budget = $max - strlen($ext) - 1;
    if ($budget < 6) {
        return substr($name, 0, $max - 1) . '…';
    }
    $head = (int) ceil($budget * 0.6);
    $tail = max(0, $budget - $head);

    return substr($base, 0, $head) . '…' . ($tail > 0 ? substr($base, -$tail) : '') . $ext;
}

function resolvePath(string $path): string
{
    if (preg_match('#^[a-zA-Z]:[/\\\\]#', $path) || str_starts_with($path, '/')) {
        $r = realpath($path);
        return $r ? str_replace('\\', '/', $r) : '';
    }
    $r = realpath(__DIR__ . '/' . $path);
    return $r ? str_replace('\\', '/', $r) : '';
}

function projectsRegistryFile(): string
{
    return __DIR__ . '/data/projects-registry.json';
}

function loadProjectsRegistry(): array
{
    $file = projectsRegistryFile();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = trim((string)($row['id'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        if ($pid === '' || $name === '') {
            continue;
        }
        $out[] = [
            'id' => $pid,
            'name' => $name,
            'path' => trim((string)($row['path'] ?? '')),
        ];
    }
    return $out;
}

function saveProjectsRegistry(array $projects): bool
{
    $file = projectsRegistryFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return (bool)file_put_contents($file, json_encode(array_values($projects), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function hiddenProjectsFile(): string
{
    return __DIR__ . '/data/hidden-projects.json';
}

function loadHiddenProjects(): array
{
    $file = hiddenProjectsFile();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $data)));
}

function saveHiddenProjects(array $ids): bool
{
    $file = hiddenProjectsFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ids = array_values(array_unique(array_filter(array_map(static function ($id) {
        return preg_replace('/[^a-z0-9_-]/i', '', (string)$id);
    }, $ids))));
    return (bool)file_put_contents($file, json_encode($ids, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function isBuiltinProject(string $id): bool
{
    foreach (cfg()['projects'] as $p) {
        if (($p['id'] ?? '') === $id) {
            return true;
        }
    }
    return false;
}

function isRegistryProject(string $id): bool
{
    foreach (loadProjectsRegistry() as $p) {
        if (($p['id'] ?? '') === $id) {
            return true;
        }
    }
    return false;
}

/** Built-in config.php projects plus user-added registry entries (minus hidden) */
function allProjectConfigs(): array
{
    $hidden = array_flip(loadHiddenProjects());
    $list = [];
    $seen = [];
    foreach (cfg()['projects'] as $p) {
        $pid = $p['id'] ?? '';
        if ($pid === '' || isset($hidden[$pid]) || !empty($seen[$pid])) {
            continue;
        }
        $list[] = $p;
        $seen[$pid] = true;
    }
    foreach (loadProjectsRegistry() as $p) {
        $pid = $p['id'] ?? '';
        if ($pid === '' || isset($hidden[$pid]) || !empty($seen[$pid])) {
            continue;
        }
        $list[] = $p;
        $seen[$pid] = true;
    }
    return $list;
}

function deleteDirectory(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }
    $items = scandir($dir);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } elseif (!@unlink($path)) {
            return false;
        }
    }
    return @rmdir($dir);
}

/**
 * Remove a project from the app.
 * @return array{ok:bool,error?:string,redirect?:string}
 */
function deleteProject(string $id): array
{
    $id = preg_replace('/[^a-z0-9_-]/i', '', trim($id));
    if ($id === '' || !projectConfigById($id)) {
        return ['ok' => false, 'error' => 'Project not found'];
    }
    if (count(allProjectConfigs()) <= 1) {
        return ['ok' => false, 'error' => 'Cannot delete the only remaining project'];
    }

    $store = loadMetaStore();
    unset($store[$id]);
    if (!saveMetaStore($store)) {
        return ['ok' => false, 'error' => 'Could not update project metadata'];
    }

    $folders = loadFolderOverrides();
    if (isset($folders[$id])) {
        unset($folders[$id]);
        $folderFile = foldersConfigFile();
        if (!file_put_contents($folderFile, json_encode($folders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            return ['ok' => false, 'error' => 'Could not clear folder link'];
        }
    }

    if (isRegistryProject($id)) {
        $registry = array_values(array_filter(loadProjectsRegistry(), static function ($p) {
            return ($p['id'] ?? '') !== $id;
        }));
        if (!saveProjectsRegistry($registry)) {
            return ['ok' => false, 'error' => 'Could not remove project from registry'];
        }
    } elseif (isBuiltinProject($id)) {
        $hidden = loadHiddenProjects();
        if (!in_array($id, $hidden, true)) {
            $hidden[] = $id;
        }
        if (!saveHiddenProjects($hidden)) {
            return ['ok' => false, 'error' => 'Could not hide built-in project'];
        }
    }

    deleteDirectory(projectUploadDir($id));

    return ['ok' => true, 'redirect' => 'index.php'];
}

function slugifyProjectId(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'project';
}

function projectIdExists(string $id): bool
{
    return projectConfigById($id) !== null;
}

function projectConfigById(string $id): ?array
{
    foreach (allProjectConfigs() as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function metaFieldsCloneable(): array
{
    return [
        'subtitle', 'status', 'progress', 'introduction', 'executiveSummary', 'summary',
        'location', 'client', 'startDate', 'closingDate', 'sponsor', 'sponsorLogoUrl', 'clientLogoUrl',
        'locationMapUrl', 'panoramaUrl', 'disclaimer', 'budget', 'budgetSource', 'pm', 'consultants', 'phases', 'activePhase', 'cvMembers', 'timesheet',
    ];
}

/**
 * Register a new project (saved to data/projects-registry.json).
 * @return array{ok:bool,error?:string,id?:string}
 */
function createProject(string $id, string $name, string $path = '', ?string $cloneFromId = null): array
{
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9_-]/', '', str_replace(' ', '-', $id));
    $name = trim($name);
    $path = trim($path);

    if ($name === '') {
        return ['ok' => false, 'error' => 'Enter a project name'];
    }
    if ($id === '' || !preg_match('/^[a-z][a-z0-9_-]{1,48}$/', $id)) {
        return ['ok' => false, 'error' => 'Project ID must be 2–49 characters: lowercase letters, numbers, hyphens'];
    }
    if (projectIdExists($id)) {
        return ['ok' => false, 'error' => 'A project with this ID already exists'];
    }
    if ($path !== '') {
        $resolved = resolvePath($path);
        if (!$resolved || !is_dir($resolved)) {
            return ['ok' => false, 'error' => 'Folder does not exist: ' . $path];
        }
        $path = str_replace('\\', '/', $resolved);
    }

    $registry = loadProjectsRegistry();
    $registry[] = ['id' => $id, 'name' => $name, 'path' => $path];
    if (!saveProjectsRegistry($registry)) {
        return ['ok' => false, 'error' => 'Could not save project registry'];
    }

    $meta = defaultMeta($id, $name);
    if ($cloneFromId && $cloneFromId !== $id) {
        $store = loadMetaStore();
        $source = $store[$cloneFromId] ?? null;
        if (is_array($source)) {
            foreach (metaFieldsCloneable() as $key) {
                if (array_key_exists($key, $source)) {
                    $meta[$key] = $source[$key];
                }
            }
        }
    }
    $meta['title'] = $name;

    $store = loadMetaStore();
    $store[$id] = $meta;
    if (!saveMetaStore($store)) {
        return ['ok' => false, 'error' => 'Project registered but metadata could not be saved'];
    }

    return ['ok' => true, 'id' => $id];
}

/** @deprecated alias — use projectConfigById() */
function projectById(string $id): ?array
{
    return projectConfigById($id);
}

function foldersConfigFile(): string
{
    return __DIR__ . '/data/folders.json';
}

function loadFolderOverrides(): array
{
    $file = foldersConfigFile();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveFolderOverride(string $projectId, string $path): bool
{
    $file = foldersConfigFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $all = loadFolderOverrides();
    $all[$projectId] = trim($path);
    return (bool)file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function clearFolderOverride(string $projectId): bool
{
    $file = foldersConfigFile();
    $all = loadFolderOverrides();
    if (!isset($all[$projectId])) {
        return true;
    }
    unset($all[$projectId]);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return (bool)file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function projectFolderPath(string $id): string
{
    $overrides = loadFolderOverrides();
    if (!empty($overrides[$id])) {
        return $overrides[$id];
    }
    $conf = projectConfigById($id);
    return $conf['path'] ?? '';
}

function loadMetaStore(): array
{
    $file = cfg()['data_file'];
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveMetaStore(array $store): bool
{
    $file = cfg()['data_file'];
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return (bool)file_put_contents($file, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function defaultMeta(string $id, string $name): array
{
    return [
        'id' => $id,
        'name' => $name,
        'title' => $name,
        'subtitle' => '',
        'status' => 'Active',
        'progress' => 0,
        'introduction' => '',
        'executiveSummary' => '',
        'summary' => '',
        'location' => '',
        'client' => '',
        'startDate' => '',
        'closingDate' => '',
        'sponsor' => '',
        'sponsorLogoUrl' => '',
        'clientLogoUrl' => '',
        'locationMapUrl' => '',
        'panoramaUrl' => '',
        'disclaimer' => '',
        'projectSummarySheetUrl' => '',
        'projectSummarySheetName' => '',
        'budget' => '',
        'budgetSource' => '',
        'pm' => '',
        'consultants' => '',
        'phases' => ['Initiation', 'Planning', 'Design', 'Construction', 'Closeout'],
        'activePhase' => 'Initiation',
        'cvMembers' => [],
        'timesheet' => [],
        'gdriveFolderUrl' => '',
    ];
}

function loadProject(string $id, string $mode = 'all'): ?array
{
    $conf = projectConfigById($id);
    if (!$conf) {
        return null;
    }
    $store = loadMetaStore();
    $meta = array_merge(defaultMeta($id, $conf['name']), $store[$id] ?? []);
    $folderPath = projectFolderPath($id);
    $scan = scanProjectForId($id, false, $mode);
    $meta['path'] = $folderPath;
    $meta['linkedFolderName'] = $scan['ok'] ? $scan['folder_name'] : null;
    $meta['folders'] = $scan['folders'] ?? [];
    $meta['files'] = $scan['files'] ?? [];
    $meta['nav'] = $scan['nav'] ?? [];
    $meta['filesCount'] = $scan['files_count'] ?? 0;
    $meta['foldersCount'] = $scan['folders_count'] ?? 0;
    $meta['scanOk'] = $scan['ok'] ?? false;
    $meta['scanError'] = $scan['error'] ?? null;
    $meta['dataSources'] = $scan['sources'] ?? [];
    $meta['gdriveScanOk'] = !empty($scan['gdriveScan']['ok']);
    $meta['gdriveScanError'] = $scan['gdriveScan']['error'] ?? null;
    $meta['gdriveFilesCount'] = (int)($scan['gdriveScan']['files_count'] ?? 0);
    $meta['localScanOk'] = !empty($scan['localScan']['ok']);
    $meta['localScanError'] = $scan['localScan']['error'] ?? null;
    $meta['localFilesCount'] = (int)($scan['localScan']['files_count'] ?? 0);
    $meta['syncSignature'] = syncSignatureFromScan($scan);
    $meta['lastSyncedAt'] = time();
    foreach ($meta['cvMembers'] as &$member) {
        $member['cvFile'] = cvFileForMember($meta, $member);
        if (!empty($member['photoAsset'])) {
            $member['photoUrl'] = assetUrl($id, basename($member['photoAsset']));
        } else {
            $member['photoUrl'] = '';
        }
    }
    unset($member);
    if ($mode === 'local') {
        return $meta;
    }
    return enrichGdriveMetaFromCache($id, $meta);
}

/** Resolved CV file for preview — uploaded asset or project folder match */
function cvFileForMember(array $project, array $member): ?array
{
    if (!empty($member['cvAsset'])) {
        $fn = basename($member['cvAsset']);
        $size = 0;
        $full = safeAssetFile((string)($project['id'] ?? ''), $fn);
        if ($full && is_readable($full)) {
            $size = (int)filesize($full);
        }
        return [
            'name' => $fn,
            'path' => '__asset__/' . $fn,
            'kind' => fileKind($fn),
            'asset' => true,
            'size' => $size,
            'size_label' => formatSize($size),
        ];
    }
    return findCvFileForMember($project, $member);
}

function teamMemberPhotoUrl(string $projectId, array $member): string
{
    $asset = basename(trim((string)($member['photoAsset'] ?? '')));
    return $asset !== '' ? assetUrl($projectId, $asset) : '';
}

function teamAvatarHtml(string $projectId, array $member): string
{
    $initials = h($member['initials'] ?? '');
    $name = h($member['name'] ?? '');
    $photoUrl = teamMemberPhotoUrl($projectId, $member);
    $cls = 'dts-team-avatar' . ($photoUrl !== '' ? ' has-photo' : '');
    if ($photoUrl !== '') {
        return '<span class="' . $cls . '" title="' . $name . '"><img src="' . h($photoUrl) . '" alt="' . $name . '"></span>';
    }
    return '<span class="' . $cls . '" title="' . $name . '">' . $initials . '</span>';
}

function sanitizeCvMembers($rows): array
{
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $id = trim((string)($row['id'] ?? ''));
        if ($id === '') {
            $id = 'm' . ($i + 1);
        }
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: ('m' . ($i + 1));
        $initials = trim((string)($row['initials'] ?? ''));
        if ($initials === '') {
            $parts = preg_split('/\s+/', $name);
            $initials = '';
            foreach ($parts as $p) {
                if ($p !== '') {
                    $initials .= mb_strtoupper(mb_substr($p, 0, 1));
                }
            }
            $initials = mb_substr($initials, 0, 4);
        }
        $out[] = [
            'id' => $id,
            'initials' => mb_substr($initials, 0, 4),
            'name' => $name,
            'role' => trim((string)($row['role'] ?? '')),
            'experienceYears' => max(0, (int)($row['experienceYears'] ?? 0)),
            'group' => trim((string)($row['group'] ?? '')),
            'cvFilePath' => trim(str_replace('\\', '/', (string)($row['cvFilePath'] ?? ''))),
            'cvAsset' => basename(trim((string)($row['cvAsset'] ?? ''))),
            'photoAsset' => basename(trim((string)($row['photoAsset'] ?? ''))),
        ];
    }
    return $out;
}

function sanitizeTimesheet($rows): array
{
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $week = trim((string)($row['week'] ?? ''));
        if ($week === '') {
            continue;
        }
        $id = trim((string)($row['id'] ?? ''));
        if ($id === '') {
            $id = 't' . ($i + 1);
        }
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: ('t' . ($i + 1));
        $out[] = [
            'id' => $id,
            'week' => $week,
            'hours' => trim((string)($row['hours'] ?? '')),
            'phase' => trim((string)($row['phase'] ?? '')),
            'notes' => trim((string)($row['notes'] ?? '')),
        ];
    }
    return $out;
}

/** Find a CV document (PDF/Word) in the project folder for a team member */
function findCvFileForMember(array $project, array $member): ?array
{
    if (!empty($member['cvFilePath'])) {
        foreach ($project['files'] ?? [] as $list) {
            foreach ($list as $f) {
                if ($f['path'] === $member['cvFilePath']) {
                    return $f;
                }
            }
        }
    }

    $name = mb_strtolower(trim($member['name'] ?? ''));
    $initials = mb_strtolower(trim($member['initials'] ?? ''));
    $nameParts = array_values(array_filter(preg_split('/\s+/', $name), function ($p) {
        return strlen($p) > 2;
    }));
    $candidates = [];

    foreach ($project['files'] ?? [] as $list) {
        foreach ($list as $f) {
            $kind = $f['kind'] ?? fileKind($f['name']);
            if (!in_array($kind, ['PDF', 'Word'], true)) {
                continue;
            }
            $fn = mb_strtolower($f['name']);
            $pathLower = mb_strtolower($f['path']);
            $score = 0;
            if (preg_match('/\b(cv|cvs|resume|curriculum)\b/i', $pathLower . ' ' . $fn)) {
                $score += 12;
            }
            if (str_contains($pathLower, 'team') || str_contains($pathLower, 'people')) {
                $score += 6;
            }
            foreach ($nameParts as $part) {
                if (str_contains($fn, $part)) {
                    $score += 8;
                }
            }
            if ($initials !== '' && str_contains($fn, $initials)) {
                $score += 3;
            }
            if ($score > 0) {
                $candidates[] = ['file' => $f, 'score' => $score];
            }
        }
    }

    if (empty($candidates)) {
        return null;
    }
    usort($candidates, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    return $candidates[0]['file'];
}

/** Fingerprint of folder tree — used for auto-sync when files/folders change on disk */
function syncSignatureFromScan(array $scan): string
{
    if (empty($scan['ok'])) {
        return 'err:' . md5((string)($scan['error'] ?? 'missing'));
    }
    $maxUpdated = 0;
    foreach ($scan['files'] ?? [] as $list) {
        foreach ($list as $f) {
            $maxUpdated = max($maxUpdated, (int)($f['updated'] ?? 0));
        }
    }
    return md5(implode("\0", [
        (string)($scan['files_count'] ?? 0),
        (string)($scan['folders_count'] ?? 0),
        (string)$maxUpdated,
        implode('|', $scan['folders'] ?? []),
        implode(',', $scan['sources'] ?? []),
        (string)($scan['gdriveFolderId'] ?? ($scan['gdriveScan']['gdriveFolderId'] ?? '')),
    ]));
}

function boardSyncSignature(array $projects): string
{
    $parts = [];
    foreach ($projects as $p) {
        $parts[] = ($p['id'] ?? '') . ':' . ($p['syncSignature'] ?? '');
    }
    return md5(implode(';', $parts));
}

function loadAllProjects(): array
{
    $list = [];
    foreach (allProjectConfigs() as $conf) {
        $p = loadProject($conf['id'], 'board');
        if ($p) {
            $list[] = $p;
        }
    }
    return $list;
}

function updateProjectMeta(string $id, array $fields): bool
{
    $store = loadMetaStore();
    $conf = projectConfigById($id);
    if (!$conf) {
        return false;
    }
    $store[$id] = array_merge(defaultMeta($id, $conf['name']), $store[$id] ?? [], $fields);
    $store[$id]['id'] = $id;
    $store[$id]['name'] = $conf['name'];
    return saveMetaStore($store);
}

function safeFile(string $root, string $relative): ?string
{
    $root = resolvePath($root);
    if (!$root || !is_dir($root)) {
        return null;
    }
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $relative = urldecode(str_replace('\\', '/', $relative));
    $relative = ltrim($relative, '/');
    if ($relative === '' || str_contains($relative, '..')) {
        return null;
    }
    $candidate = $root . '/' . $relative;
    $full = realpath($candidate);
    if (!$full) {
        return null;
    }
    $fullNorm = str_replace('\\', '/', $full);
    if (!str_starts_with(strtolower($fullNorm), strtolower($root))) {
        return null;
    }
    return is_file($full) ? $fullNorm : null;
}

function fileKind(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return 'PDF';
        case 'doc':
        case 'docx':
            return 'Word';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return 'Excel';
        case 'ppt':
        case 'pptx':
            return 'PowerPoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
        case 'bmp':
        case 'svg':
        case 'tif':
        case 'tiff':
            return 'Images';
        case 'dwg':
        case 'dxf':
            return 'DWG/CAD';
        case 'kml':
        case 'kmz':
        case 'gpx':
        case 'geojson':
            return 'Maps/GIS';
        default:
            return 'Other';
    }
}

function mimeType(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return 'application/pdf';
        case 'docx':
            return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        case 'doc':
            return 'application/msword';
        case 'xlsx':
            return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        case 'xls':
            return 'application/vnd.ms-excel';
        case 'csv':
            return 'text/csv';
        case 'png':
            return 'image/png';
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'gif':
            return 'image/gif';
        case 'webp':
            return 'image/webp';
        case 'svg':
            return 'image/svg+xml';
        case 'txt':
            return 'text/plain';
        case 'json':
            return 'application/json';
        case 'dwg':
            return 'application/acad';
        default:
            return 'application/octet-stream';
    }
}

const PREVIEW_MAX_BYTES = 104857600; // 100 MB

function previewMaxBytes(): int
{
    return PREVIEW_MAX_BYTES;
}

function previewMaxSizeLabel(): string
{
    return formatSize(PREVIEW_MAX_BYTES);
}

function formatSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

function fileSizeBytes(array $file, ?string $projectId = null, ?string $projectPath = null): int
{
    if (isset($file['size']) && is_numeric($file['size'])) {
        return (int)$file['size'];
    }
    if (!empty($file['gdrive']) && isset($file['size'])) {
        return (int)$file['size'];
    }
    if (!empty($file['asset']) && $projectId) {
        $fn = basename((string)($file['path'] ?? $file['name'] ?? ''));
        $full = safeAssetFile($projectId, $fn);
        return ($full && is_readable($full)) ? (int)filesize($full) : 0;
    }
    if ($projectPath && !empty($file['path']) && !str_starts_with((string)$file['path'], '__asset__/') && !str_starts_with((string)$file['path'], '__gdrive__/')) {
        $full = safeFile($projectPath, (string)$file['path']);
        return ($full && is_readable($full)) ? (int)filesize($full) : 0;
    }
    return 0;
}

function isFileTooLargeForPreview(array $file, ?string $projectId = null, ?string $projectPath = null): bool
{
    $size = fileSizeBytes($file, $projectId, $projectPath);
    return $size > previewMaxBytes();
}

function kindClass(string $kind): string
{
    return 'kind-' . preg_replace('/[^a-z0-9]/i', '', strtolower($kind));
}

function scanProject(string $rootPath): array
{
    $root = resolvePath($rootPath);
    if (!$root || !is_dir($root)) {
        return ['ok' => false, 'error' => 'Folder not found: ' . $rootPath, 'folders' => [], 'files' => [], 'nav' => [], 'files_count' => 0, 'folders_count' => 0, 'folder_name' => ''];
    }

    $folders = [];
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($root))), '/');
        if ($rel === '' || str_starts_with(basename($rel), '.')) {
            continue;
        }
        if ($item->isDir()) {
            if (!in_array($rel, $folders, true)) {
                $folders[] = $rel;
            }
            if (!isset($files[$rel])) {
                $files[$rel] = [];
            }
        } else {
            $dir = dirname($rel);
            $category = ($dir === '.' || $dir === '') ? 'Files' : $dir;
            if (!isset($files[$category])) {
                $files[$category] = [];
            }
            $stat = $item->getSize();
            $files[$category][] = [
                'name' => $item->getFilename(),
                'path' => str_replace('\\', '/', $rel),
                'kind' => fileKind($item->getFilename()),
                'size' => $stat,
                'size_label' => formatSize($stat),
                'updated' => filemtime($item->getPathname()),
            ];
        }
    }

    sort($folders);
    foreach ($files as &$list) {
        usort($list, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
    }
    unset($list);

    if (!empty($files['Files']) && !in_array('Files', $folders, true)) {
        $folders[] = 'Files';
    }

    $total = 0;
    foreach ($files as $list) {
        $total += count($list);
    }

    return [
        'ok' => true,
        'root' => $root,
        'folder_name' => basename($root),
        'folders' => $folders,
        'files' => $files,
        'nav' => buildNav($folders, $files),
        'files_count' => $total,
        'folders_count' => count($folders),
    ];
}

function buildNav(array $folders, array $filesByFolder): array
{
    $top = [];
    foreach ($folders as $f) {
        $parts = explode('/', $f, 2);
        $top[$parts[0]] = true;
    }
    $icons = ['📄', '🗺️', '📷', '📊', '📁', '🔧', '📋'];
    $colors = ['#6366f1', '#0891b2', '#d946ef', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'];
    $nav = [];
    $i = 0;
    foreach (array_keys($top) as $title) {
        if ($title === 'Files') {
            continue;
        }
        $subs = [];
        foreach ($folders as $f) {
            if ($f === $title || str_starts_with($f, $title . '/')) {
                $label = $f === $title ? $title : basename($f);
                $subs[] = [
                    'label' => $label,
                    'folder' => $f,
                    'depth' => substr_count($f, '/'),
                    'count' => count($filesByFolder[$f] ?? []),
                ];
            }
        }
        if (!empty($subs)) {
            $minSlash = min(array_map(static fn($s) => (int)$s['depth'], $subs));
            foreach ($subs as &$sub) {
                $sub['depth'] = (int)$sub['depth'] - $minSlash;
            }
            unset($sub);
        }
        usort($subs, static function ($a, $b) {
            return strcasecmp($a['folder'], $b['folder']);
        });
        $icon = ($title === 'Google Drive') ? '☁️' : $icons[$i % count($icons)];
        $color = ($title === 'Google Drive') ? '#4285f4' : $colors[$i % count($colors)];
        $nav[] = [
            'title' => $title,
            'icon' => $icon,
            'color' => $color,
            'subs' => $subs,
            'gdrive' => ($title === 'Google Drive'),
        ];
        $i++;
    }
    if (!empty($filesByFolder['Files'])) {
        $nav[] = ['title' => 'Project Folder', 'icon' => '📁', 'color' => '#6366f1', 'subs' => [['label' => 'Files', 'folder' => 'Files', 'depth' => 0, 'count' => count($filesByFolder['Files'])]]];
    }
    return $nav;
}

function canPreview(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return 'pdf';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'], true)) {
        return 'image';
    }
    if (in_array($ext, ['tif', 'tiff'], true)) {
        return 'tiff';
    }
    if (in_array($ext, ['txt', 'csv', 'json', 'xml', 'md', 'log', 'yaml', 'yml', 'ini', 'cfg', 'html', 'htm', 'css', 'js', 'ts', 'sql', 'kml', 'gpx', 'geojson'], true)) {
        return 'text';
    }
    if ($ext === 'docx') {
        return 'docx';
    }
    if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
        return 'excel';
    }
    if ($ext === 'pptx') {
        return 'pptx';
    }
    if (in_array($ext, ['dwg', 'dxf'], true)) {
        return 'cad';
    }
    if (in_array($ext, ['doc', 'ppt'], true)) {
        return 'office-legacy';
    }
    return 'binary';
}

function previewIcon(string $mode): string
{
    switch ($mode) {
        case 'pdf':
            return '📕';
        case 'image':
        case 'tiff':
            return '🖼️';
        case 'text':
            return '📝';
        case 'docx':
            return '📘';
        case 'excel':
            return '📗';
        case 'pptx':
            return '📙';
        case 'cad':
            return '📐';
        case 'office-legacy':
            return '📄';
        default:
            return '📦';
    }
}

function statusClass(string $status): string
{
    switch ($status) {
        case 'In Review':
            return 'status-review';
        case 'Archived':
            return 'status-archived';
        default:
            return 'status-active';
    }
}

function formatDate(string $iso): string
{
    if ($iso === '') {
        return '—';
    }
    $t = strtotime($iso);
    return $t ? date('j M Y', $t) : $iso;
}

function daysBetween(string $start, string $end): ?int
{
    if ($start === '' || $end === '') {
        return null;
    }
    $s = strtotime($start);
    $e = strtotime($end);
    if (!$s || !$e) {
        return null;
    }
    return max(0, (int)round(($e - $s) / 86400));
}

function isGdriveFolderPath(string $folder): bool
{
    return $folder === 'Google Drive' || str_starts_with($folder, 'Google Drive/');
}

function isGdriveNavSection(array $section): bool
{
    $title = (string)($section['title'] ?? '');
    if ($title === 'Google Drive' || str_starts_with($title, 'Google Drive/')) {
        return true;
    }
    foreach ($section['subs'] ?? [] as $sub) {
        if (isGdriveFolderPath((string)($sub['folder'] ?? ''))) {
            return true;
        }
    }
    return false;
}

function filterNavForSource(array $nav, string $source): array
{
    return array_values(array_filter($nav, static function ($section) use ($source) {
        $isGdrive = isGdriveNavSection($section);
        return $source === 'gdrive' ? $isGdrive : !$isGdrive;
    }));
}

function firstFolderInNav(array $nav, string $source): string
{
    foreach (filterNavForSource($nav, $source) as $section) {
        foreach ($section['subs'] ?? [] as $sub) {
            $f = (string)($sub['folder'] ?? '');
            if ($f !== '') {
                return $f;
            }
        }
    }
    return $source === 'gdrive' ? 'Google Drive' : 'Files';
}

function navHasSource(array $nav, string $source): bool
{
    return count(filterNavForSource($nav, $source)) > 0;
}

function folderDataSource(string $folder): string
{
    return isGdriveFolderPath($folder) ? 'gdrive' : 'local';
}

/** Per-project workspace source prefs from browser cookie (set by app.js). */
function loadWorkspaceSourcePrefs(): array
{
    $raw = $_COOKIE['dts-workspace-source'] ?? '';
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = json_decode(urldecode($raw), true);
    }
    return is_array($data) ? $data : [];
}

/** @return array{source:string,folder?:string}|null */
function workspaceSourcePref(string $projectId): ?array
{
    $entry = loadWorkspaceSourcePrefs()[$projectId] ?? null;
    if (is_string($entry) && in_array($entry, ['local', 'gdrive'], true)) {
        return ['source' => $entry];
    }
    if (!is_array($entry)) {
        return null;
    }
    $source = $entry['source'] ?? null;
    if (!in_array($source, ['local', 'gdrive'], true)) {
        return null;
    }
    $out = ['source' => $source];
    $folder = trim((string)($entry['folder'] ?? ''));
    if ($folder !== '') {
        $out['folder'] = $folder;
    }
    return $out;
}

/** Default folder when opening a project without ?folder= (respects saved source). */
function resolveDefaultWorkspaceFolder(string $projectId, array $project): string
{
    $pref = workspaceSourcePref($projectId);
    $preferredFolder = trim((string)($pref['folder'] ?? ''));
    if ($preferredFolder !== '') {
        $src = folderDataSource($preferredFolder);
        if ($src === 'gdrive' && projectHasGdriveSource($projectId)) {
            return $preferredFolder;
        }
        if ($src === 'local' && projectHasLocalSource($projectId)) {
            return $preferredFolder;
        }
    }

    $preferredSource = $pref['source'] ?? null;
    if ($preferredSource === 'gdrive' && projectHasGdriveSource($projectId)) {
        return defaultGdriveEntryFolder($projectId);
    }

    $folder = firstFolderInNav($project['nav'], 'local');
    if (!isset($project['files'][$folder]) && projectHasGdriveSource($projectId)) {
        if ($preferredSource === null && !projectHasLocalSource($projectId)) {
            return defaultGdriveEntryFolder($projectId);
        }
        if ($preferredSource === 'gdrive') {
            return defaultGdriveEntryFolder($projectId);
        }
    }
    if (!isset($project['files'][$folder])) {
        $folder = $project['folders'][0] ?? 'Files';
    }
    return $folder;
}

/** Which scan to run for a workspace request (avoids slow GDrive API on local browsing). */
function projectScanMode(string $folder, string $panel = '', ?string $projectId = null): string
{
    if ($panel !== '') {
        return 'local';
    }
    if ($folder !== '') {
        return folderDataSource($folder);
    }
    if ($projectId) {
        $pref = workspaceSourcePref($projectId);
        if ($pref) {
            if ($pref['source'] === 'gdrive' && projectHasGdriveSource($projectId)) {
                return 'gdrive';
            }
            if ($pref['source'] === 'local' && projectHasLocalSource($projectId)) {
                return 'local';
            }
        }
        if (projectHasGdriveSource($projectId) && !projectHasLocalSource($projectId)) {
            return 'gdrive';
        }
    }
    return 'local';
}

function projectHasGdriveSource(string $projectId): bool
{
    $store = loadMetaStore();
    return trim((string)($store[$projectId]['gdriveFolderUrl'] ?? '')) !== '';
}

function projectHasLocalSource(string $projectId): bool
{
    $resolved = resolvePath(projectFolderPath($projectId));
    return $resolved !== '' && is_dir($resolved);
}

/** First folder link for the Local tab (fast disk scan). */
function firstLocalFolderForProject(string $projectId): string
{
    $scan = scanProject(projectFolderPath($projectId));
    if (empty($scan['ok'])) {
        return 'Files';
    }
    return firstFolderInNav($scan['nav'], 'local');
}

/** First folder link for the Google Drive tab (from cache or default). */
function defaultGdriveEntryFolder(string $projectId): string
{
    $cached = gdriveLoadCache($projectId, true);
    if ($cached && !empty($cached['scan']['ok'])) {
        $prefixed = prefixGdriveScan($cached['scan']);
        $folder = firstFolderInNav($prefixed['nav'], 'gdrive');
        if ($folder !== '' && $folder !== 'Files') {
            return $folder;
        }
        if (!empty($prefixed['folders'][0])) {
            return $prefixed['folders'][0];
        }
    }
    return 'Google Drive';
}

/** Attach GDrive status from cache when the active page scan skipped the API. */
function enrichGdriveMetaFromCache(string $projectId, array $meta): array
{
    if (trim((string)($meta['gdriveFolderUrl'] ?? '')) === '') {
        return $meta;
    }
    if (!empty($meta['gdriveScanOk']) || !empty($meta['gdriveScanError'])) {
        return $meta;
    }
    $cached = gdriveLoadCache($projectId, true);
    if ($cached && !empty($cached['scan'])) {
        $scan = $cached['scan'];
        $meta['gdriveScanOk'] = !empty($scan['ok']);
        $meta['gdriveScanError'] = $scan['error'] ?? null;
        $meta['gdriveFilesCount'] = (int)($scan['files_count'] ?? 0);
    }
    return $meta;
}

/** Fast file lookup for preview/viewer — no full folder scan. */
function resolveProjectFile(string $projectId, string $path): ?array
{
    $path = str_replace('\\', '/', urldecode($path));

    if (preg_match('#^__gdrive__/([^/]+)/(.+)$#', $path, $m)) {
        $meta = gdriveFileFromCache($projectId, $m[1]);
        if ($meta) {
            return $meta;
        }
        $name = basename($m[2]);
        return [
            'name' => $name,
            'path' => $path,
            'kind' => fileKind($name),
            'size' => 0,
            'size_label' => '—',
            'updated' => time(),
            'gdrive' => true,
            'gdriveId' => $m[1],
        ];
    }

    $full = safeFile(projectFolderPath($projectId), $path);
    if (!$full || !is_readable($full)) {
        return null;
    }
    $stat = (int)filesize($full);
    return [
        'name' => basename($full),
        'path' => $path,
        'kind' => fileKind(basename($full)),
        'size' => $stat,
        'size_label' => formatSize($stat),
        'updated' => (int)filemtime($full),
    ];
}

function projectPreviewContext(string $projectId): array
{
    return [
        'id' => $projectId,
        'path' => projectFolderPath($projectId),
    ];
}

function url(array $params = []): string
{
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

function fileUrl(string $projectId, string $path, bool $download = false): string
{
    $p = ['project' => $projectId, 'path' => $path];
    if ($download) {
        $p['download'] = '1';
    }
    return 'file.php?' . http_build_query($p, '', '&', PHP_QUERY_RFC3986);
}

function viewerUrl(string $projectId, string $path, bool $embed = true): string
{
    $q = ['project' => $projectId, 'path' => $path];
    if ($embed) {
        $q['embed'] = '1';
    }
    return 'viewer.php?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}

function kindShort(string $kind): string
{
    switch ($kind) {
        case 'PDF':
            return 'PDF';
        case 'Word':
            return 'DOC';
        case 'Excel':
            return 'XLS';
        case 'PowerPoint':
            return 'PPT';
        case 'Images':
            return 'IMG';
        case 'DWG/CAD':
            return 'CAD';
        case 'Maps/GIS':
            return 'GIS';
        default:
            return 'FILE';
    }
}

function appRoot(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    return rtrim($dir, '/') . '/';
}

function workspacePanels(): array
{
    return [
        ['id' => 'team', 'title' => 'Team & People', 'icon' => '👥', 'color' => '#8b5cf6', 'subs' => [
            ['id' => 'cvs', 'label' => 'All Team CVs', 'panel' => 'cvs'],
            ['id' => 'team-table', 'label' => 'Team Roster', 'panel' => 'team-table'],
        ]],
        ['id' => 'tracking', 'title' => 'Time & Progress', 'icon' => '⏱️', 'color' => '#f59e0b', 'subs' => [
            ['id' => 'timesheet', 'label' => 'Manager Timesheets', 'panel' => 'timesheet'],
            ['id' => 'timeline', 'label' => 'Project Timeline', 'panel' => 'timeline'],
        ]],
    ];
}

function detailTabs(): array
{
    return [
        ['id' => 'summary', 'label' => 'Executive Summary', 'icon' => '📋'],
        ['id' => 'summary-sheet', 'label' => 'Project Summary Sheet', 'icon' => '📄'],
        ['id' => 'cost', 'label' => 'Estimated Cost', 'icon' => '💰'],
        ['id' => 'client', 'label' => 'Client & Sponsor', 'icon' => '🤝'],
        ['id' => 'dates', 'label' => 'Starting & Closing Dates', 'icon' => '📅'],
        ['id' => 'team', 'label' => 'Project Team', 'icon' => '👥'],
        ['id' => 'location', 'label' => 'Location', 'icon' => '📍'],
    ];
}

function projectUploadDir(string $id): string
{
    $dir = __DIR__ . '/data/uploads/' . preg_replace('/[^a-z0-9_-]/i', '', $id);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function allowedSummarySheetExt(string $name): bool
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}

function assetUrl(string $projectId, string $filename): string
{
    return 'asset.php?' . http_build_query([
        'project' => $projectId,
        'file' => $filename,
    ], '', '&', PHP_QUERY_RFC3986);
}

function assetUrlDownloadLink(string $assetUrl): string
{
    if ($assetUrl === '' || str_contains($assetUrl, 'download=')) {
        return $assetUrl;
    }
    return $assetUrl . (str_contains($assetUrl, '?') ? '&' : '?') . 'download=1';
}

function assetFilenameFromUrl(string $assetUrl): string
{
    $query = parse_url($assetUrl, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return 'location-map';
    }
    parse_str($query, $params);
    $file = basename((string)($params['file'] ?? ''));
    return $file !== '' ? $file : 'location-map';
}

function safeAssetFile(string $projectId, string $filename): ?string
{
    $id = preg_replace('/[^a-z0-9_-]/i', '', $projectId);
    if ($id === '' || $filename === '' || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
        return null;
    }
    $dir = projectUploadDir($id);
    $full = realpath($dir . '/' . $filename);
    $dirReal = realpath($dir);
    if (!$full || !$dirReal || !str_starts_with(str_replace('\\', '/', $full), str_replace('\\', '/', $dirReal))) {
        return null;
    }
    return is_file($full) ? str_replace('\\', '/', $full) : null;
}
