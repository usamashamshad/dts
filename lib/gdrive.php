<?php

/**
 * Google Drive public-folder sync for DTS.
 * Lists files from a shared folder via Drive API v3 (API key required in config.php).
 */

function gdriveApiKey(): string
{
    return trim((string)(cfg()['gdrive_api_key'] ?? ''));
}

function gdriveCacheTtl(): int
{
    return max(30, (int)(cfg()['gdrive_cache_ttl'] ?? 60));
}

function gdriveCachePath(string $projectId): string
{
    $safe = preg_replace('/[^a-z0-9_-]/i', '', $projectId);
    return __DIR__ . '/../data/gdrive-cache/' . $safe . '.json';
}

/** Extract folder ID from common Google Drive share URL formats */
function parseGdriveFolderId(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    if (preg_match('#^[\w-]{10,}$#', $input) && !str_contains($input, '/')) {
        return $input;
    }

    if (preg_match('#/folders/([\w-]+)#', $input, $m)) {
        return $m[1];
    }

    if (preg_match('#[?&]id=([\w-]+)#', $input, $m)) {
        return $m[1];
    }

    return null;
}

function gdriveHttpRequest(string $url): array
{
    $body = null;
    $code = 0;
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'DTS/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch) ?: 'Request failed';
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 45,
                'ignore_errors' => true,
                'header' => "User-Agent: DTS/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
        if ($body === false) {
            $error = 'HTTP request failed';
        }
    }

    return [
        'ok' => $code >= 200 && $code < 300 && is_string($body),
        'code' => $code,
        'body' => is_string($body) ? $body : '',
        'error' => $error,
    ];
}

function gdriveApiGet(string $endpoint, array $query = []): array
{
    $key = gdriveApiKey();
    if ($key === '') {
        return ['ok' => false, 'error' => 'Google Drive API key is not set in config.php', 'data' => null];
    }

    $query['key'] = $key;
    $url = 'https://www.googleapis.com/drive/v3/' . ltrim($endpoint, '/') . '?' . http_build_query($query);

    $res = gdriveHttpRequest($url);
    if (!$res['ok']) {
        $msg = $res['error'] ?: 'Google Drive API error (HTTP ' . ($res['code'] ?: '?') . ')';
        if ($res['body'] !== '') {
            $json = json_decode($res['body'], true);
            if (!empty($json['error']['message'])) {
                $msg = $json['error']['message'];
            }
        }
        return ['ok' => false, 'error' => $msg, 'data' => null];
    }

    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid response from Google Drive API', 'data' => null];
    }

    return ['ok' => true, 'error' => null, 'data' => $data];
}

function gdriveIsFolderMime(string $mime): bool
{
    return $mime === 'application/vnd.google-apps.folder';
}

function gdriveExportExtension(string $mime): ?string
{
    switch ($mime) {
        case 'application/vnd.google-apps.document':
            return 'pdf';
        case 'application/vnd.google-apps.spreadsheet':
            return 'xlsx';
        case 'application/vnd.google-apps.presentation':
            return 'pptx';
        case 'application/vnd.google-apps.drawing':
            return 'png';
        default:
            return null;
    }
}

function gdriveExportMime(string $googleMime): ?string
{
    switch ($googleMime) {
        case 'application/vnd.google-apps.document':
            return 'application/pdf';
        case 'application/vnd.google-apps.spreadsheet':
            return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        case 'application/vnd.google-apps.presentation':
            return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        case 'application/vnd.google-apps.drawing':
            return 'image/png';
        default:
            return null;
    }
}

function gdriveDisplayName(string $name, string $mime): string
{
    $exportExt = gdriveExportExtension($mime);
    if ($exportExt === null) {
        return $name;
    }
    $base = pathinfo($name, PATHINFO_FILENAME);
    return $base . '.' . $exportExt;
}

function gdriveFilePath(string $fileId, string $displayName): string
{
    return '__gdrive__/' . $fileId . '/' . str_replace(['/', '\\'], '_', $displayName);
}

function gdriveLoadCache(string $projectId, bool $allowStale = false): ?array
{
    $file = gdriveCachePath($projectId);
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        return null;
    }
    if (!$allowStale) {
        $age = time() - (int)($data['syncedAt'] ?? 0);
        if ($age > gdriveCacheTtl()) {
            return null;
        }
    }
    return $data;
}

function gdriveSaveCache(string $projectId, array $payload): void
{
    $file = gdriveCachePath($projectId);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $payload['syncedAt'] = time();
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function gdriveClearCache(string $projectId): void
{
    $file = gdriveCachePath($projectId);
    if (is_file($file)) {
        @unlink($file);
    }
}

function gdriveGetFolderMeta(string $folderId): array
{
    $res = gdriveApiGet('files/' . rawurlencode($folderId), [
        'fields' => 'id,name,mimeType',
        'supportsAllDrives' => 'true',
        'includeItemsFromAllDrives' => 'true',
    ]);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'], 'name' => 'Google Drive'];
    }
    $data = $res['data'];
    if (gdriveIsFolderMime((string)($data['mimeType'] ?? ''))) {
        return ['ok' => true, 'error' => null, 'name' => (string)($data['name'] ?? 'Google Drive')];
    }
    return ['ok' => false, 'error' => 'The link does not point to a Google Drive folder', 'name' => 'Google Drive'];
}

function gdriveListChildren(string $folderId): array
{
    $items = [];
    $pageToken = null;

    do {
        $query = [
            'q' => sprintf("'%s' in parents and trashed=false", str_replace("'", "\\'", $folderId)),
            'fields' => 'nextPageToken,files(id,name,mimeType,size,modifiedTime)',
            'pageSize' => 200,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            'orderBy' => 'folder,name',
        ];
        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }

        $res = gdriveApiGet('files', $query);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'], 'items' => []];
        }

        foreach ($res['data']['files'] ?? [] as $file) {
            if (!is_array($file) || empty($file['id'])) {
                continue;
            }
            $items[] = $file;
        }
        $pageToken = $res['data']['nextPageToken'] ?? null;
    } while ($pageToken);

    return ['ok' => true, 'error' => null, 'items' => $items];
}

/**
 * Recursively walk a Drive folder and build DTS scan-compatible file index.
 *
 * @return array{ok:bool,error:?string,folders:array,files:array,file_index:array,root_label:string}
 */
function gdriveWalkFolder(string $folderId, string $categoryPrefix, array &$folders, array &$files, array &$fileIndex): array
{
    $list = gdriveListChildren($folderId);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => $list['error']];
    }

    foreach ($list['items'] as $item) {
        $id = (string)$item['id'];
        $name = (string)($item['name'] ?? 'Untitled');
        $mime = (string)($item['mimeType'] ?? '');

        if (gdriveIsFolderMime($mime)) {
            $rel = $categoryPrefix === '' ? $name : $categoryPrefix . '/' . $name;
            if (!in_array($rel, $folders, true)) {
                $folders[] = $rel;
            }
            if (!isset($files[$rel])) {
                $files[$rel] = [];
            }
            $sub = gdriveWalkFolder($id, $rel, $folders, $files, $fileIndex);
            if (!$sub['ok']) {
                return $sub;
            }
            continue;
        }

        $exportExt = gdriveExportExtension($mime);
        if ($exportExt === null && str_starts_with($mime, 'application/vnd.google-apps.')) {
            continue;
        }

        $displayName = gdriveDisplayName($name, $mime);
        $category = $categoryPrefix === '' ? 'Files' : $categoryPrefix;
        if (!isset($files[$category])) {
            $files[$category] = [];
        }

        $updated = 0;
        if (!empty($item['modifiedTime'])) {
            $updated = (int)strtotime($item['modifiedTime']);
        }
        $size = (int)($item['size'] ?? 0);

        $entry = [
            'name' => $displayName,
            'path' => gdriveFilePath($id, $displayName),
            'kind' => fileKind($displayName),
            'size' => $size,
            'size_label' => formatSize($size),
            'updated' => $updated,
            'gdrive' => true,
            'gdriveId' => $id,
            'gdriveMime' => $mime,
            'gdriveName' => $name,
        ];

        $files[$category][] = $entry;
        $fileIndex[$id] = $entry;
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Scan a public Google Drive folder — same output shape as scanProject().
 */
function scanGdriveFolder(string $folderUrlOrId, bool $forceRefresh = false, ?string $projectId = null, bool $cacheOnly = false): array
{
    $empty = static function (string $error, string $folderName = 'Google Drive') {
        return [
            'ok' => false,
            'error' => $error,
            'folders' => [],
            'files' => [],
            'nav' => [],
            'files_count' => 0,
            'folders_count' => 0,
            'folder_name' => $folderName,
            'source' => 'gdrive',
        ];
    };

    $folderId = parseGdriveFolderId($folderUrlOrId);
    if (!$folderId) {
        return $empty('Invalid Google Drive folder link');
    }

    if (!$forceRefresh && $projectId) {
        $cached = gdriveLoadCache($projectId, $cacheOnly);
        if ($cached && ($cached['folderId'] ?? '') === $folderId && !empty($cached['scan'])) {
            return $cached['scan'];
        }
    }

    if ($cacheOnly) {
        return $empty('Google Drive cache not ready — open the Google Drive tab to refresh');
    }

    if (gdriveApiKey() === '') {
        return $empty('Set gdrive_api_key in config.php (Google Cloud → Drive API → API key)');
    }

    $meta = gdriveGetFolderMeta($folderId);
    if (!$meta['ok']) {
        return $empty($meta['error'], $meta['name']);
    }

    $rootLabel = $meta['name'];
    $folders = [];
    $files = [];
    $fileIndex = [];

    $walk = gdriveWalkFolder($folderId, '', $folders, $files, $fileIndex);
    if (!$walk['ok']) {
        return $empty($walk['error'] ?? 'Google Drive scan failed', $rootLabel);
    }

    sort($folders);
    foreach ($files as &$list) {
        usort($list, static function ($a, $b) {
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

    $scan = [
        'ok' => true,
        'error' => null,
        'root' => 'gdrive:' . $folderId,
        'folder_name' => $rootLabel,
        'folders' => $folders,
        'files' => $files,
        'nav' => buildNav($folders, $files),
        'files_count' => $total,
        'folders_count' => count($folders),
        'source' => 'gdrive',
        'gdriveFolderId' => $folderId,
    ];

    if ($projectId) {
        gdriveSaveCache($projectId, [
            'folderId' => $folderId,
            'folderUrl' => $folderUrlOrId,
            'scan' => $scan,
            'fileIndex' => $fileIndex,
        ]);
    }

    return $scan;
}

function gdriveFileFromCache(string $projectId, string $fileId): ?array
{
    $file = gdriveCachePath($projectId);
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        return null;
    }
    return $data['fileIndex'][$fileId] ?? null;
}

function gdriveMediaUrl(string $fileId, string $googleMime): string
{
    $key = gdriveApiKey();
    $exportMime = gdriveExportMime($googleMime);
    if ($exportMime !== null) {
        return 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '/export?' . http_build_query([
            'mimeType' => $exportMime,
            'key' => $key,
        ]);
    }
    return 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?' . http_build_query([
        'alt' => 'media',
        'key' => $key,
    ]);
}

/** Stream a Google Drive file to the browser (used by file.php) */
function gdriveStreamFile(string $projectId, string $fileId, bool $download, ?string $displayName = null): void
{
    $meta = gdriveFileFromCache($projectId, $fileId);
    $googleMime = (string)($meta['gdriveMime'] ?? 'application/octet-stream');
    $name = $displayName ?: (string)($meta['name'] ?? ('file-' . $fileId));

    if (gdriveApiKey() === '') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Google Drive API key not configured';
        exit;
    }

    $url = gdriveMediaUrl($fileId, $googleMime);
    $res = gdriveHttpRequest($url);

    if (!$res['ok']) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not fetch file from Google Drive';
        exit;
    }

    $body = $res['body'];
    $mime = $googleMime;
    $exportMime = gdriveExportMime($googleMime);
    if ($exportMime !== null) {
        $mime = $exportMime;
    } else {
        $mime = mimeType($name);
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');

    $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $name);
    if ($download) {
        header('Content-Disposition: attachment; filename="' . $asciiName . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $asciiName . '"');
    }

    echo $body;
    exit;
}

/** Prefix GDrive scan paths so they sit alongside local folders without collision */
function prefixGdriveScan(array $scan, string $prefix = 'Google Drive'): array
{
    if (empty($scan['ok'])) {
        return $scan;
    }

    $rootName = trim((string)($scan['folder_name'] ?? 'Google Drive'));
    $base = $prefix;
    if ($rootName !== '' && $rootName !== 'Google Drive' && $rootName !== $prefix) {
        $base = $prefix . '/' . $rootName;
    } elseif ($rootName === 'Google Drive') {
        $base = $prefix;
    }

    $folders = [];
    foreach ($scan['folders'] ?? [] as $f) {
        if ($f === 'Files' || $f === $rootName) {
            $folders[] = $base;
        } else {
            $folders[] = $base . '/' . $f;
        }
    }
    if (empty($folders) && !empty($scan['files_count'])) {
        $folders[] = $base;
    }

    $files = [];
    foreach ($scan['files'] ?? [] as $cat => $list) {
        if ($cat === 'Files' || $cat === $rootName || $cat === '') {
            $newCat = $base;
        } else {
            $newCat = $base . '/' . $cat;
        }
        $files[$newCat] = $list;
    }

    $total = 0;
    foreach ($files as $list) {
        $total += count($list);
    }

    return [
        'ok' => true,
        'error' => null,
        'root' => $scan['root'] ?? '',
        'folder_name' => $base,
        'folders' => array_values(array_unique($folders)),
        'files' => $files,
        'nav' => buildNav(array_values(array_unique($folders)), $files),
        'files_count' => $total,
        'folders_count' => count(array_values(array_unique($folders))),
        'source' => 'gdrive',
        'gdriveFolderId' => $scan['gdriveFolderId'] ?? null,
    ];
}

/** Merge local disk scan + Google Drive scan into one project index */
function mergeProjectScans(array $local, array $gdrive): array
{
    $hasLocal = !empty($local['ok']);
    $hasGdrive = !empty($gdrive['ok']);

    if (!$hasLocal && !$hasGdrive) {
        $err = $local['error'] ?? $gdrive['error'] ?? 'No data sources available';
        return [
            'ok' => false,
            'error' => $err,
            'folders' => [],
            'files' => [],
            'nav' => [],
            'files_count' => 0,
            'folders_count' => 0,
            'folder_name' => $local['folder_name'] ?? '',
            'sources' => [],
        ];
    }

    $folders = [];
    $files = [];
    $sources = [];

    if ($hasLocal) {
        $sources[] = 'local';
        $folders = array_merge($folders, $local['folders'] ?? []);
        foreach ($local['files'] ?? [] as $cat => $list) {
            if (!isset($files[$cat])) {
                $files[$cat] = [];
            }
            $files[$cat] = array_merge($files[$cat], $list);
        }
    }

    if ($hasGdrive) {
        $sources[] = 'gdrive';
        $prefixed = prefixGdriveScan($gdrive);
        $folders = array_merge($folders, $prefixed['folders'] ?? []);
        foreach ($prefixed['files'] ?? [] as $cat => $list) {
            if (!isset($files[$cat])) {
                $files[$cat] = [];
            }
            $files[$cat] = array_merge($files[$cat], $list);
        }
    }

    $folders = array_values(array_unique($folders));
    sort($folders);

    foreach ($files as &$list) {
        usort($list, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
    }
    unset($list);

    $total = 0;
    foreach ($files as $list) {
        $total += count($list);
    }

    $folderName = $local['folder_name'] ?? '';
    if ($hasGdrive) {
        $gd = prefixGdriveScan($gdrive);
        $folderName = $folderName !== ''
            ? $folderName . ' + ' . ($gd['folder_name'] ?? 'Google Drive')
            : ($gd['folder_name'] ?? 'Google Drive');
    }

    return [
        'ok' => true,
        'error' => null,
        'root' => $local['root'] ?? '',
        'folder_name' => $folderName,
        'folders' => $folders,
        'files' => $files,
        'nav' => buildNav($folders, $files),
        'files_count' => $total,
        'folders_count' => count($folders),
        'sources' => $sources,
        'localScan' => $hasLocal ? $local : null,
        'gdriveScan' => $hasGdrive ? $gdrive : null,
    ];
}

/**
 * Full project scan.
 *
 * @param string $mode local = disk only, gdrive = cloud only, all = both, board = disk + stale gdrive cache
 */
function scanProjectForId(string $projectId, bool $forceGdriveRefresh = false, string $mode = 'all'): array
{
    $mode = in_array($mode, ['local', 'gdrive', 'all', 'board'], true) ? $mode : 'all';
    $store = loadMetaStore();
    $meta = $store[$projectId] ?? [];
    $gdriveUrl = trim((string)($meta['gdriveFolderUrl'] ?? ''));

    if ($mode === 'local') {
        $local = scanProject(projectFolderPath($projectId));
        $local['sources'] = !empty($local['ok']) ? ['local'] : [];
        $local['localScan'] = !empty($local['ok']) ? $local : null;
        $local['gdriveScan'] = null;
        return $local;
    }

    if ($mode === 'gdrive') {
        if ($gdriveUrl === '') {
            return [
                'ok' => false,
                'error' => 'No Google Drive folder configured',
                'folders' => [],
                'files' => [],
                'nav' => [],
                'files_count' => 0,
                'folders_count' => 0,
                'folder_name' => '',
                'sources' => [],
                'localScan' => null,
                'gdriveScan' => null,
            ];
        }
        $gdrive = scanGdriveFolder($gdriveUrl, $forceGdriveRefresh, $projectId);
        $scan = prefixGdriveScan($gdrive);
        $scan['sources'] = !empty($gdrive['ok']) ? ['gdrive'] : [];
        $scan['localScan'] = null;
        $scan['gdriveScan'] = $gdrive;
        return $scan;
    }

    $local = scanProject(projectFolderPath($projectId));

    if ($gdriveUrl === '') {
        $local['sources'] = !empty($local['ok']) ? ['local'] : [];
        $local['localScan'] = !empty($local['ok']) ? $local : null;
        $local['gdriveScan'] = null;
        return $local;
    }

    $cacheOnly = ($mode === 'board');
    $gdrive = scanGdriveFolder($gdriveUrl, $forceGdriveRefresh, $projectId, $cacheOnly);
    return mergeProjectScans($local, $gdrive);
}
