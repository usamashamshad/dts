<?php
/** Render preview panel inner HTML. Expects $project array and $file array|null */

function fileKindClass(string $kind): string
{
    return 'file-kind-' . preg_replace('/[^a-zA-Z0-9]/', '', $kind);
}

function renderPreviewTooLargeNotice(array $file): string
{
    $name = h($file['name']);
    $size = h($file['size_label'] ?? formatSize(fileSizeBytes($file)));
    $max = h(previewMaxSizeLabel());
    ob_start();
    ?>
    <div class="preview-notice preview-notice-card preview-size-blocked">
      <div class="preview-notice-icon">⚠️</div>
      <p><strong><?= $name ?></strong></p>
      <p class="preview-notice-hint">Unable to preview due to larger file size.</p>
      <p class="preview-notice-hint">This file (<?= $size ?>) exceeds the <?= $max ?> preview limit.</p>
    </div>
    <?php
    return ob_get_clean();
}

function renderPreviewPanel(?array $project, ?array $file): string
{
    if (!$file || !$project) {
        return '<div class="preview-empty">Select a file from the folder list to launch the document viewer.</div>';
    }

    if (isFileTooLargeForPreview($file, $project['id'] ?? null, $project['path'] ?? null)) {
        $dl = fileUrl($project['id'], $file['path'], true);
        ob_start();
        ?>
        <div class="preview-shell preview-shell-contained">
          <?= renderPreviewTooLargeNotice($file) ?>
          <div class="doc-viewer-actions preview-actions-row">
            <a class="primary-btn" href="<?= h($dl) ?>" download>Download file</a>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    $preview = canPreview($file['name']);
    $src = fileUrl($project['id'], $file['path']);
    $dl = fileUrl($project['id'], $file['path'], true);
    $kind = h($file['kind']);
    $name = h($file['name']);
    $date = date('j M Y', $file['updated']);
    $size = h($file['size_label']);
    $kClass = fileKindClass($file['kind']);

    ob_start();
    ?>
    <div class="preview-shell preview-shell-contained">
      <div class="preview-file-header">
        <div class="preview-file-header-main">
          <span class="file-kind <?= h($kClass) ?>"><?= $kind ?></span>
          <h3 class="preview-file-name" title="<?= $name ?>"><?= $name ?></h3>
        </div>
        <div class="preview-file-stats">
          <span class="preview-stat"><span class="preview-stat-label">Modified</span><span class="preview-stat-value"><?= $date ?></span></span>
          <span class="preview-stat"><span class="preview-stat-label">Size</span><span class="preview-stat-value"><?= $size ?></span></span>
        </div>
      </div>

      <div class="preview-viewport" data-preview-type="<?= h($preview) ?>">
        <?php if ($preview === 'pdf'): ?>
          <div class="preview-frame-wrap">
            <div class="preview-loading-bar" aria-hidden="true">Loading PDF…</div>
            <iframe class="preview-iframe" src="<?= h($src) ?>" title="<?= $name ?>" loading="lazy"></iframe>
          </div>
        <?php elseif ($preview === 'image'): ?>
          <div class="preview-image-wrap">
            <img class="preview-image" src="<?= h($src) ?>" alt="<?= $name ?>" loading="lazy">
          </div>
        <?php elseif ($preview === 'cad'): ?>
          <div class="preview-frame-wrap preview-cad-frame-wrap">
            <div class="preview-loading-bar" aria-hidden="true">Loading CAD drawing…</div>
            <iframe class="preview-iframe" src="<?= h(viewerUrl($project['id'], $file['path'])) ?>" title="<?= $name ?>" loading="lazy"></iframe>
          </div>
        <?php elseif ($preview === 'text'):
          $tf = safeFile($project['path'], $file['path']);
          $text = ($tf && filesize($tf) < 500000) ? file_get_contents($tf) : null;
        ?>
          <?php if ($text !== null): ?>
          <pre class="preview-text"><?= h($text) ?></pre>
          <?php else: ?>
          <div class="preview-notice"><p>File too large to preview inline.</p></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="preview-notice preview-notice-card">
            <div class="preview-notice-icon"><?= str_contains(strtolower($file['name']), '.dwg') ? '📐' : '📄' ?></div>
            <p><strong><?= $name ?></strong></p>
            <p class="preview-notice-hint">Preview not available in the browser for this file type.</p>
            <p class="preview-notice-hint">Download and open in the correct app<?= str_contains(strtolower($file['name']), '.dwg') ? ' (e.g. DWG FastView)' : '' ?>.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="doc-viewer-actions preview-actions-row">
        <a class="primary-btn" href="<?= h($dl) ?>" download>Download file</a>
        <?php if (!in_array($preview, ['binary', 'office-legacy'], true)): ?>
        <a class="soft-btn" href="<?= h($src) ?>" target="_blank" rel="noopener">Open in new tab</a>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
