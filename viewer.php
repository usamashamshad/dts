<?php
require __DIR__ . '/lib.php';

$projectId = $_GET['project'] ?? '';
$path = $_GET['path'] ?? '';
$embed = !empty($_GET['embed']);

$file = ($projectId && $path !== '') ? resolveProjectFile($projectId, $path) : null;
$project = $projectId ? projectPreviewContext($projectId) : null;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $file ? h($file['name']) : 'Preview' ?></title>
  <link rel="stylesheet" href="viewer.css?v=5">
</head>
<body class="viewer-page<?= $embed ? ' viewer-embed' : '' ?>">
<?php if (!$project || !$file): ?>
  <div class="viewer-empty">
    <div class="viewer-empty-icon">📂</div>
    <p>File not found.</p>
  </div>
<?php else:
    $tooLarge = isFileTooLargeForPreview($file, $project['id'], $project['path']);
    $mode = canPreview($file['name']);
    $src = fileUrl($project['id'], $file['path']);
    $dl = fileUrl($project['id'], $file['path'], true);
    $config = [
        'mode' => $mode,
        'name' => $file['name'],
        'src' => $src,
        'download' => $dl,
        'kind' => $file['kind'] ?? fileKind($file['name']),
        'embed' => $embed,
        'tooLarge' => $tooLarge,
        'sizeLabel' => $file['size_label'] ?? formatSize(fileSizeBytes($file, $project['id'], $project['path'])),
        'maxSizeLabel' => previewMaxSizeLabel(),
    ];
?>
  <?php if (!$embed): ?>
  <header class="viewer-header">
    <div class="viewer-header-main">
      <span class="viewer-file-icon" aria-hidden="true"><?= previewIcon($mode) ?></span>
      <h1 class="viewer-file-name" title="<?= h($file['name']) ?>"><?= h($file['name']) ?></h1>
    </div>
    <div class="viewer-standalone-toolbar" id="viewer-toolbar">
      <div class="viewer-zoom-group" id="zoom-controls">
        <button type="button" class="viewer-btn viewer-btn-icon" data-zoom="out" title="Zoom out">−</button>
        <span class="viewer-zoom-label" id="zoom-label">100%</span>
        <button type="button" class="viewer-btn viewer-btn-icon" data-zoom="in" title="Zoom in">+</button>
        <button type="button" class="viewer-btn" data-zoom="fit" title="Fit to preview">Fit</button>
        <button type="button" class="viewer-btn" data-rotate="cw" title="Rotate (PDF)">Rotate</button>
      </div>
      <a class="viewer-btn viewer-btn-primary" href="<?= h($dl) ?>" download>Download</a>
    </div>
  </header>
  <?php endif; ?>

  <main class="viewer-stage-wrap">
    <?php if ($tooLarge): ?>
    <div class="viewer-stage viewer-stage-blocked">
      <div class="viewer-notice">
        <div class="viewer-notice-icon">⚠️</div>
        <p class="viewer-notice-title">Unable to preview due to larger file size</p>
        <p class="viewer-notice-hint">This file (<?= h($config['sizeLabel']) ?>) exceeds the <?= h($config['maxSizeLabel']) ?> preview limit.</p>
        <p class="viewer-notice-hint"><a href="<?= h($dl) ?>" download>Download the file</a> to open it locally.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="viewer-loading" id="viewer-loading">
      <div class="viewer-spinner"></div>
      <p>Loading…</p>
    </div>
    <div class="viewer-stage" id="preview-stage"></div>
    <?php endif; ?>
  </main>

  <?php if (!$tooLarge): ?>
  <script>
    window.__PREVIEW__ = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script type="module" src="preview.js?v=7"></script>
  <?php else: ?>
  <script>
    window.__PREVIEW__ = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (window.__PREVIEW__?.embed && window.parent !== window) {
      window.parent.postMessage({
        type: 'dts-preview-ready',
        download: window.__PREVIEW__.download || '',
      }, '*');
    }
  </script>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
