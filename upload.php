<?php
require __DIR__ . '/lib.php';

$type = $_POST['type'] ?? '';
$isJson = in_array($type, ['member_cv', 'member_photo', 'panorama', 'sponsor_logo', 'client_logo', 'location_map'], true) || (
    empty($_POST['redirect']) && (
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
    )
);

function uploadRespond(bool $ok, array $data = [], int $code = 200): void
{
    global $isJson;
    http_response_code($code);
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok], $data));
        exit;
    }
    $redirect = $_POST['redirect'] ?? 'index.php';
    $sep = str_contains($redirect, '?') ? '&' : '?';
    if ($ok) {
        header('Location: ' . $redirect . $sep . 'upload_ok=1');
    } else {
        $msg = urlencode($data['error'] ?? 'Upload failed');
        header('Location: ' . $redirect . $sep . 'upload_error=' . $msg);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadRespond(false, ['error' => 'POST only'], 405);
}

$projectId = $_POST['project_id'] ?? '';

if (!$projectId || !projectConfigById($projectId)) {
    uploadRespond(false, ['error' => 'Invalid project']);
}

if (!in_array($type, ['summary_sheet', 'member_cv', 'member_photo', 'panorama', 'sponsor_logo', 'client_logo', 'location_map'], true)) {
    uploadRespond(false, ['error' => 'Unknown upload type']);
}

$file = $_FILES['file'] ?? null;
if (!$file || !isset($file['error'])) {
    uploadRespond(false, ['error' => 'No file uploaded']);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit (check php.ini upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded — try again',
        UPLOAD_ERR_NO_FILE => 'No file selected',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension',
    ];
    uploadRespond(false, ['error' => $errors[$file['error']] ?? 'Upload error code ' . $file['error']]);
}

$original = basename($file['name'] ?? '');

if ($type === 'summary_sheet') {
    if (!allowedSummarySheetExt($original)) {
        uploadRespond(false, ['error' => 'Only PDF or image files (JPG, PNG, GIF, WebP) are allowed']);
    }
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $stored = 'summary-sheet-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
} elseif ($type === 'panorama') {
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        uploadRespond(false, ['error' => 'Panorama must be an image (JPG, PNG, GIF, WebP)']);
    }
    $stored = 'panorama-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
} elseif (in_array($type, ['sponsor_logo', 'client_logo', 'location_map'], true)) {
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        uploadRespond(false, ['error' => 'Image must be JPG, PNG, GIF, or WebP']);
    }
    $prefix = 'image-';
    if ($type === 'sponsor_logo') {
        $prefix = 'sponsor-logo-';
    } elseif ($type === 'client_logo') {
        $prefix = 'client-logo-';
    } elseif ($type === 'location_map') {
        $prefix = 'location-map-';
    }
    $stored = $prefix . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
} elseif ($type === 'member_photo') {
    $memberId = preg_replace('/[^a-z0-9_-]/i', '', $_POST['member_id'] ?? '') ?: 'member';
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        uploadRespond(false, ['error' => 'Photo must be an image (JPG, PNG, GIF, WebP)']);
    }
    $stored = 'photo-' . $memberId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
} else {
    $memberId = preg_replace('/[^a-z0-9_-]/i', '', $_POST['member_id'] ?? '') ?: 'member';
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
        uploadRespond(false, ['error' => 'CV must be PDF or Word (.pdf, .doc, .docx)']);
    }
    $stored = 'cv-' . $memberId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
}

$dest = projectUploadDir($projectId) . DIRECTORY_SEPARATOR . $stored;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    uploadRespond(false, ['error' => 'Could not save file — check folder permissions for data/uploads']);
}

if ($type === 'summary_sheet') {
    $fields = [
        'projectSummarySheetUrl' => assetUrl($projectId, $stored),
        'projectSummarySheetName' => $original,
    ];
    if (!updateProjectMeta($projectId, $fields)) {
        @unlink($dest);
        uploadRespond(false, ['error' => 'Could not update project metadata']);
    }
    uploadRespond(true, [
        'url' => $fields['projectSummarySheetUrl'],
        'name' => $original,
    ]);
}

if ($type === 'panorama') {
    // Convert any image (portrait/landscape) into a wide banner crop.
    // This makes uploads "fit" the panorama area without requiring the user to provide a wide image.
    $converted = false;
    $srcPath = $dest;
    $dstPath = $dest;
    $dstExt = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    $canGd = function_exists('imagecreatetruecolor') && function_exists('getimagesize');

    if ($canGd) {
        $info = @getimagesize($srcPath);
        $mime = is_array($info) ? ($info['mime'] ?? '') : '';
        $srcW = is_array($info) ? (int)($info[0] ?? 0) : 0;
        $srcH = is_array($info) ? (int)($info[1] ?? 0) : 0;

        if ($srcW > 0 && $srcH > 0 && $mime) {
            $create = null;
            if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $create = 'imagecreatefromjpeg';
            if ($mime === 'image/png' && function_exists('imagecreatefrompng')) $create = 'imagecreatefrompng';
            if ($mime === 'image/gif' && function_exists('imagecreatefromgif')) $create = 'imagecreatefromgif';
            if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $create = 'imagecreatefromwebp';

            if ($create) {
                $src = @$create($srcPath);
                if ($src) {
                    // target wide size (good for banner + fast to load)
                    $targetW = 1800;
                    $targetH = 420;
                    $dst = imagecreatetruecolor($targetW, $targetH);

                    // fill background (for PNG/GIF transparency)
                    $bg = imagecolorallocate($dst, 8, 6, 20);
                    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $bg);

                    // "cover" crop: scale to fill, then center-crop
                    $scale = max($targetW / $srcW, $targetH / $srcH);
                    $cropW = (int)round($targetW / $scale);
                    $cropH = (int)round($targetH / $scale);
                    $cropX = (int)max(0, floor(($srcW - $cropW) / 2));
                    $cropY = (int)max(0, floor(($srcH - $cropH) / 2));

                    imagecopyresampled(
                        $dst,
                        $src,
                        0,
                        0,
                        $cropX,
                        $cropY,
                        $targetW,
                        $targetH,
                        $cropW,
                        $cropH
                    );

                    // Save as JPEG for best compatibility
                    $jpgName = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '.jpg', basename($dest));
                    $dstPath = projectUploadDir($projectId) . DIRECTORY_SEPARATOR . $jpgName;
                    if (@imagejpeg($dst, $dstPath, 85)) {
                        $converted = true;
                        // remove the original if we created a new file
                        if (realpath($dstPath) !== realpath($dest)) {
                            @unlink($dest);
                            $dest = $dstPath;
                            $stored = $jpgName;
                        }
                    } else {
                        // keep original if conversion fails
                        $dstPath = $dest;
                    }

                    imagedestroy($dst);
                    imagedestroy($src);
                }
            }
        }
    }

    $fields = [
        'panoramaUrl' => assetUrl($projectId, $stored),
    ];
    if (!updateProjectMeta($projectId, $fields)) {
        @unlink($dest);
        uploadRespond(false, ['error' => 'Could not update project metadata']);
    }
    uploadRespond(true, [
        'url' => $fields['panoramaUrl'],
        'name' => $original,
    ]);
}

if (in_array($type, ['sponsor_logo', 'client_logo', 'location_map'], true)) {
    $metaField = '';
    if ($type === 'sponsor_logo') {
        $metaField = 'sponsorLogoUrl';
    } elseif ($type === 'client_logo') {
        $metaField = 'clientLogoUrl';
    } elseif ($type === 'location_map') {
        $metaField = 'locationMapUrl';
    }
    $fields = [$metaField => assetUrl($projectId, $stored)];
    if (!updateProjectMeta($projectId, $fields)) {
        @unlink($dest);
        uploadRespond(false, ['error' => 'Could not update project metadata']);
    }
    uploadRespond(true, [
        'url' => $fields[$metaField],
        'name' => $original,
    ]);
}

uploadRespond(true, [
    'filename' => $stored,
    'name' => $original,
    'url' => assetUrl($projectId, $stored),
]);
