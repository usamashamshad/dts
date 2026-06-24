<?php
require_once __DIR__ . '/preview-render.php';
$projectCount = count(loadAllProjects());
/** @var array $project */
/** @var string $folder */
/** @var string $filePath */
/** @var string $q */
/** @var string $panel */
/** @var array $fileList */
/** @var array|null $selectedFile */
$viewFiles = ($panel === '' || $panel === 'files');
$showPreviewColumn = $viewFiles || $panel === 'cvs';
$previewFile = $viewFiles ? $selectedFile : ($panel === 'cvs' ? ($selectedCvFile ?? null) : null);
$wsContentClass = 'ws-content';
if ($viewFiles) {
    // default 50/50 file + preview
} elseif ($panel === 'cvs') {
    $wsContentClass .= ' ws-content--cv-split';
} elseif ($panel === 'timesheet' || $panel === 'timeline') {
    $wsContentClass .= ' ws-content--panel-centered';
} else {
    $wsContentClass .= ' ws-content--panel';
}
?>
<script>window.__DTS_PROJECT__ = <?= json_encode([
  'id' => $project['id'],
  'title' => $project['title'],
  'subtitle' => $project['subtitle'],
  'status' => $project['status'],
  'progress' => $project['progress'],
  'introduction' => $project['introduction'],
  'executiveSummary' => $project['executiveSummary'],
  'client' => $project['client'],
  'sponsor' => $project['sponsor'],
  'budget' => $project['budget'],
  'pm' => $project['pm'],
  'location' => $project['location'],
  'startDate' => $project['startDate'],
  'closingDate' => $project['closingDate'],
  'sponsorLogoUrl' => $project['sponsorLogoUrl'],
  'clientLogoUrl' => $project['clientLogoUrl'] ?? '',
  'locationMapUrl' => $project['locationMapUrl'],
  'panoramaUrl' => $project['panoramaUrl'] ?? '',
  'disclaimer' => $project['disclaimer'] ?? '',
  'projectSummarySheetUrl' => $project['projectSummarySheetUrl'] ?? '',
  'projectSummarySheetName' => $project['projectSummarySheetName'] ?? '',
  'cvMembers' => array_map(static function ($m) {
    return [
      'id' => $m['id'] ?? '',
      'initials' => $m['initials'] ?? '',
      'name' => $m['name'] ?? '',
      'role' => $m['role'] ?? '',
      'experienceYears' => (int)($m['experienceYears'] ?? 0),
      'group' => $m['group'] ?? '',
      'cvFilePath' => $m['cvFilePath'] ?? '',
      'cvAsset' => $m['cvAsset'] ?? '',
      'photoAsset' => $m['photoAsset'] ?? '',
      'photoUrl' => $m['photoUrl'] ?? '',
    ];
  }, $project['cvMembers'] ?? []),
  'timesheet' => $project['timesheet'] ?? [],
  'gdriveFolderUrl' => $project['gdriveFolderUrl'] ?? '',
  'gdriveScanOk' => !empty($project['gdriveScanOk']),
  'gdriveScanError' => $project['gdriveScanError'] ?? null,
  'gdriveFilesCount' => (int)($project['gdriveFilesCount'] ?? 0),
  'path' => $project['path'] ?? '',
  'localScanOk' => !empty($project['localScanOk'] ?? $project['scanOk']),
  'localFilesCount' => (int)($project['localFilesCount'] ?? 0),
  'canDelete' => $projectCount > 1,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<main class="page page-workspace">
  <div class="workspace-topbar">
    <a class="ghost-btn" href="<?= h(url()) ?>">← Back</a>
    <div class="workspace-title-block">
      <div class="workspace-title"><?= h($project['title']) ?></div>
      <div class="workspace-subtitle"><?= h($project['subtitle']) ?></div>
    </div>
    <div class="workspace-sync-actions">
      <button type="button" class="soft-btn" data-open-folder-picker
              data-project-id="<?= h($project['id']) ?>"
              data-current-path="<?= h($project['path']) ?>">Select project folder</button>
      <a class="primary-btn" href="<?= h(url(['project' => $project['id'], 'folder' => $folder, 'panel' => $panel])) ?>">↻ Refresh</a>
      <div class="workspace-sync">
        <div class="sync-dot" data-state="synced"></div>
        <div class="sync-text">
          <div class="sync-title">📁 <?= h($project['linkedFolderName'] ?? 'No folder') ?></div>
          <?php
            $syncSourceLabel = folderDataSource($folder) === 'gdrive' ? 'Google Drive' : 'local folder';
          ?>
          <div class="sync-meta" id="sync-meta-text"><?= (int)$project['filesCount'] ?> files · <?= (int)$project['foldersCount'] ?> folders · <?= h($syncSourceLabel) ?></div>
        </div>
      </div>
    </div>
  </div>

      <section class="ws-project-hero<?= !empty($project['panoramaUrl']) ? ' ws-project-hero--has-panorama' : '' ?>" id="ws-hero"
               <?php if (!empty($project['panoramaUrl'])): ?>style="--ws-panorama: url('<?= h($project['panoramaUrl']) ?>'); min-height: 220px;"<?php endif; ?>>
    <div class="ws-hero-top">
      <h2 class="ws-hero-title"><?= h($project['title']) ?></h2>
      <?php if ($project['subtitle']): ?><p class="ws-hero-subtitle"><?= h($project['subtitle']) ?></p><?php endif; ?>
    </div>
    <?php if (empty($project['panoramaUrl'])): ?>
    <?php if ($project['introduction']): ?><p class="ws-hero-intro"><?= h($project['introduction']) ?></p><?php endif; ?>
    <button type="button" class="dts-accordion ws-hero-accordion" id="summary-accordion">
      <span class="dts-accordion-icon">▶</span>
      <span class="dts-accordion-label">Executive Summary</span>
    </button>
    <div class="ws-hero-summary" id="summary-body" hidden>
      <?= nl2br(h($project['executiveSummary'] ?: $project['summary'] ?: 'No executive summary yet.')) ?>
    </div>
    <div class="ws-hero-meta">
      <?php if ($project['location']): ?><span class="ws-meta-chip">📍 <?= h($project['location']) ?></span><?php endif; ?>
      <?php if ($project['budget']): ?><span class="ws-meta-chip ws-meta-cost">💰 <?= h($project['budget']) ?></span><?php endif; ?>
      <?php if ($project['client']): ?><span class="ws-meta-chip">🏢 <?= h($project['client']) ?></span><?php endif; ?>
      <?php if ($project['closingDate']): ?><span class="ws-meta-chip">📅 <?= h(formatDate($project['closingDate'])) ?></span><?php endif; ?>
      <?php if ($project['sponsor']): ?><span class="ws-meta-chip">⭐ <?= h($project['sponsor']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php if (!$project['scanOk']): ?>
  <div class="folder-banner">
    <div>
      <strong>Project folder not found</strong>
      <p style="margin:4px 0 0;font-size:13px;color:var(--text-dim)">Path: <code><?= h($project['path']) ?></code> — click Select project folder and enter the correct path (e.g. <code>storage/Data</code>).</p>
    </div>
    <button type="button" class="primary-btn" data-open-folder-picker
            data-project-id="<?= h($project['id']) ?>"
            data-current-path="<?= h($project['path']) ?>">Select project folder</button>
  </div>
  <?php endif; ?>

  <div class="workspace-grid">
    <?php
      $sidebarSource = folderDataSource($folder);
      $hasLocalSource = projectHasLocalSource($project['id']);
      $hasGdriveSource = projectHasGdriveSource($project['id']);
      $hasLocalNav = $hasLocalSource;
      $hasGdriveNav = $hasGdriveSource;
      $sidebarNav = filterNavForSource($project['nav'], $sidebarSource);
      $localFirstFolder = $hasLocalSource
        ? ($sidebarSource === 'local' ? firstFolderInNav($project['nav'], 'local') : firstLocalFolderForProject($project['id']))
        : '';
      if ($localFirstFolder === '' && $hasLocalSource) {
        $localFirstFolder = $project['folders'][0] ?? 'Files';
      }
      $gdriveFirstFolder = $hasGdriveSource ? defaultGdriveEntryFolder($project['id']) : '';
    ?>
    <aside class="ws-sidebar">
      <?php if ($hasLocalNav && $hasGdriveNav): ?>
      <div class="ws-source-tabs" role="tablist" aria-label="Document source">
        <a class="ws-source-tab <?= $sidebarSource === 'local' ? 'is-active' : '' ?>"
           href="<?= h(url(['project' => $project['id'], 'folder' => $localFirstFolder])) ?>"
           role="tab"
           aria-selected="<?= $sidebarSource === 'local' ? 'true' : 'false' ?>">
          <span class="ws-source-tab-icon">📁</span>
          <span class="ws-source-tab-label">Local</span>
        </a>
        <a class="ws-source-tab <?= $sidebarSource === 'gdrive' ? 'is-active' : '' ?>"
           href="<?= h(url(['project' => $project['id'], 'folder' => $gdriveFirstFolder])) ?>"
           role="tab"
           aria-selected="<?= $sidebarSource === 'gdrive' ? 'true' : 'false' ?>">
          <span class="ws-source-tab-icon">☁️</span>
          <span class="ws-source-tab-label">Google Drive</span>
        </a>
      </div>
      <?php endif; ?>
      <div class="ws-section-title ws-nav-title"><span><?= $sidebarSource === 'gdrive' ? 'Google Drive Folders' : 'Local Folders' ?></span></div>
      <div class="nav-tree">
        <?php foreach ($sidebarNav as $section):
          $folderSectionOpen = false;
          foreach ($section['subs'] as $sub) {
            if ($folder === $sub['folder']) {
              $folderSectionOpen = true;
              break;
            }
          }
        ?>
        <details class="nav-section<?= !empty($section['gdrive']) ? ' nav-section--gdrive' : '' ?>" data-nav-id="folder-<?= h($section['title']) ?>"<?= $folderSectionOpen ? ' open' : '' ?> style="--section-color:<?= h($section['color']) ?>">
          <summary class="nav-section-header">
            <span class="nav-section-icon"><?= $section['icon'] ?></span>
            <span class="nav-section-title"><?= h($section['title']) ?></span>
          </summary>
          <div class="nav-subfields">
            <?php foreach ($section['subs'] as $sub):
              $depth = (int)($sub['depth'] ?? substr_count($sub['folder'], '/'));
              $active = $folder === $sub['folder'] && $viewFiles;
            ?>
            <a class="nav-subfield-btn <?= $active ? 'is-active' : '' ?>"
               data-depth="<?= $depth ?>"
               style="--depth: <?= $depth ?>"
               href="<?= h(url(['project' => $project['id'], 'folder' => $sub['folder']])) ?>"
               title="<?= h($sub['folder']) ?>">
              <span class="nav-subfield-label"><?= h($sub['label']) ?></span>
              <span class="nav-subfield-count"><?= (int)$sub['count'] ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endforeach; ?>
      </div>
    </aside>
    <script>
    (function () {
      try {
        var pid = <?= json_encode($project['id'], JSON_UNESCAPED_UNICODE) ?>;
        var openIds = JSON.parse(sessionStorage.getItem('dts-workspace-nav') || '{}')[pid];
        if (!openIds || !openIds.length) return;
        openIds.forEach(function (id) {
          var el = document.querySelector('.ws-sidebar details[data-nav-id=' + JSON.stringify(id) + ']');
          if (el) el.open = true;
        });
        var scrollStore = JSON.parse(sessionStorage.getItem('dts-workspace-scroll') || '{}');
        var bucket = scrollStore[pid];
        if (bucket) {
          var params = new URLSearchParams(location.search);
          var panel = params.get('panel') || '';
          var folder = params.get('folder') || '';
          var key = panel ? ('panel:' + panel) : (folder ? ('folder:' + folder) : 'files');
          var saved = bucket[key] || bucket.__page__;
          if (saved && saved.windowY > 0) {
            document.documentElement.style.scrollBehavior = 'auto';
            window.scrollTo(0, saved.windowY);
          }
        }
      } catch (e) {}
    })();
    </script>

    <main class="ws-main">
      <div class="ws-view-bar">
        <div class="ws-view-breadcrumb">
          <span class="ws-view-type"><?= $viewFiles ? '📁 Documents' : '📋 Panel' ?></span>
          <span class="ws-view-sep">›</span>
          <strong><?= h($viewFiles ? ($folder ?: 'Select folder') : ucfirst(str_replace('-', ' ', $panel))) ?></strong>
        </div>
      </div>

      <div class="<?= h($wsContentClass) ?>">
        <div class="ws-middle files-panel<?= ($panel === 'timesheet' || $panel === 'timeline') ? ' ws-panel-centered-wrap' : '' ?>">
          <?php if ($viewFiles): ?>
          <div class="panel-chrome files-panel-chrome">
            <div class="panel-chrome-left">
              <span class="panel-kicker">Project files</span>
              <span class="panel-title-text"><?= h($folder) ?></span>
            </div>
            <div class="panel-chrome-right">
              <button type="button" class="soft-btn files-print-btn" id="print-folder-list"
                      title="Print list of files in this folder"<?= empty($fileList) ? ' disabled' : '' ?>>
                🖨️ Print file list
              </button>
            </div>
          </div>
          <div class="files-panel-body">
          <form class="ws-search" method="get">
            <input type="hidden" name="project" value="<?= h($project['id']) ?>">
            <input type="hidden" name="folder" value="<?= h($folder) ?>">
            <span class="ws-search-label">Search in this folder…</span>
            <input class="ws-search-input" name="q" value="<?= h($q) ?>" placeholder="Keywords, file type…">
          </form>
          <div class="files-table-wrap">
            <?php if (empty($fileList)): ?>
            <div class="empty-state" style="padding:32px">No files in this folder.</div>
            <?php else: ?>
            <table class="files-table" id="files-table">
              <thead>
                <tr>
                  <th class="col-type"></th>
                  <th class="col-name">File</th>
                  <th class="col-action"></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($fileList as $f):
                $active = $selectedFile && $selectedFile['path'] === $f['path'];
                $kCls = kindClass($f['kind']);
              ?>
                <tr class="<?= $active ? 'is-active' : '' ?>"
                    data-path="<?= h($f['path']) ?>"
                    data-name="<?= h($f['name']) ?>"
                    data-kind="<?= h($f['kind']) ?>"
                    data-size="<?= h($f['size_label']) ?>"
                    data-size-bytes="<?= (int)($f['size'] ?? 0) ?>"
                    data-date="<?= date('j M Y', $f['updated']) ?>">
                  <td class="col-type"><span class="file-kind-pill <?= h($kCls) ?>" title="<?= h($f['kind']) ?>"><?= h(kindShort($f['kind'])) ?></span></td>
                  <td class="file-name-cell col-name">
                    <span class="file-name-main" title="<?= h($f['name']) ?>"><?= h($f['name']) ?><?php if (!empty($f['gdrive'])): ?><span class="file-source-badge" title="Google Drive">☁️</span><?php endif; ?></span>
                    <span class="file-name-sub"><?= h($f['size_label']) ?> · <?= date('j M Y', $f['updated']) ?></span>
                  </td>
                  <td class="col-action">
                    <a class="dl-btn" href="<?= h(fileUrl($project['id'], $f['path'], true)) ?>"
                       title="Download" download>⬇</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
          </div>

          <?php elseif ($panel === 'cvs' || $panel === 'team-table'): ?>
          <div class="panel ws-panel-card<?= $panel === 'cvs' ? ' ws-panel-card--list' : ' ws-panel-card--centered' ?>">
            <div class="panel-title"><?= $panel === 'cvs' ? 'All Team CVs' : 'Team Roster' ?></div>
            <?php if ($panel === 'cvs'): ?>
            <p class="panel-subtitle">Click a team member to preview their CV on the right</p>
            <?php endif; ?>
            <div class="dts-table-wrap">
              <table class="dts-table<?= $panel === 'cvs' ? ' cv-table' : '' ?>"<?= $panel === 'cvs' ? ' id="cv-table"' : '' ?>>
                <thead><tr><th>Name</th><th>Designation</th><th>Experience</th><?php if ($panel === 'cvs'): ?><th>CV</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if (empty($project['cvMembers'])): ?>
                <tr><td colspan="<?= $panel === 'cvs' ? 4 : 3 ?>" class="dts-empty-cell">No team members assigned.</td></tr>
                <?php else: foreach ($project['cvMembers'] as $m):
                  $cvFile = $m['cvFile'] ?? null;
                  $cvActive = $panel === 'cvs' && $selectedCvMember && ($selectedCvMember['id'] ?? '') === ($m['id'] ?? '');
                ?>
                <tr class="dts-table-row<?= $panel === 'cvs' ? ' cv-table-row' : '' ?><?= $cvActive ? ' is-active' : '' ?>"
                    <?php if ($panel === 'cvs'): ?>
                    data-member-id="<?= h($m['id']) ?>"
                    data-name="<?= h($m['name']) ?>"
                    data-cv-path="<?= $cvFile ? h($cvFile['path']) : '' ?>"
                    data-size-bytes="<?= $cvFile ? (int)fileSizeBytes($cvFile, $project['id'], $project['path']) : 0 ?>"
                    data-size="<?= $cvFile ? h($cvFile['size_label'] ?? formatSize(fileSizeBytes($cvFile, $project['id'], $project['path']))) : '' ?>"
                    <?php endif; ?>>
                  <td><div class="dts-team-name"><?= teamAvatarHtml($project['id'], $m) ?><?= h($m['name']) ?></div></td>
                  <td><span class="dts-role-badge"><?= h($m['role']) ?></span></td>
                  <td><?= (int)$m['experienceYears'] ?> years</td>
                  <?php if ($panel === 'cvs'): ?>
                  <td><?= $cvFile ? '<span class="cv-file-badge">📄 Available</span>' : '<span class="cv-file-missing">No file</span>' ?></td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <?php elseif ($panel === 'timesheet'): ?>
          <div class="panel ws-panel-card ws-panel-card--centered">
            <div class="panel-title">Manager Timesheet</div>
            <div class="panel-subtitle">Showing: <?= h($project['activePhase']) ?> phase</div>
            <?php
              $rows = array_filter($project['timesheet'], function ($r) use ($project) {
                  return $r['phase'] === $project['activePhase'];
              });
              if (empty($rows)) {
                  $rows = $project['timesheet'];
              }
            ?>
            <?php if (empty($rows)): ?>
            <div class="empty-state">No timesheet rows.</div>
            <?php else: ?>
            <div class="timesheet-table-wrap">
              <table class="timesheet-table">
                <thead><tr><th>Week</th><th>Hours</th><th>Phase</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr><td><?= h($r['week']) ?></td><td><?= h($r['hours']) ?></td><td><?= h($r['phase']) ?></td><td><?= h($r['notes']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>

          <?php elseif ($panel === 'timeline'): ?>
          <div class="panel ws-panel-card ws-panel-card--centered">
            <div class="panel-title">Project Timeline</div>
            <div class="timeline-panel">
              <?php foreach ($project['phases'] as $idx => $phase):
                $isActive = $phase === $project['activePhase'];
                $isPast = array_search($project['activePhase'], $project['phases'], true) > $idx;
                $tsCount = count(array_filter($project['timesheet'], function ($t) use ($phase) {
                    return $t['phase'] === $phase;
                }));
              ?>
              <a class="timeline-panel-step <?= $isActive ? 'is-active' : '' ?> <?= $isPast ? 'is-past' : '' ?>"
                 href="<?= h(url(['project' => $project['id'], 'phase' => $phase, 'panel' => 'timeline'])) ?>">
                <div class="timeline-step-num"><?= $idx + 1 ?></div>
                <div class="timeline-step-body">
                  <div class="timeline-step-name"><?= h($phase) ?></div>
                  <div class="timeline-step-meta"><?= $tsCount ?> timesheet entries</div>
                </div>
                <?php if ($isActive): ?><span class="timeline-step-badge">Current</span><?php endif; ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($showPreviewColumn): ?>
        <div class="ws-right doc-preview-panel" id="preview-panel">
          <div class="doc-preview-chrome<?= $previewFile ? '' : ' is-hidden' ?>" id="doc-preview-chrome"<?= $previewFile ? '' : ' hidden' ?>>
            <div class="doc-preview-chrome-left">
              <span class="doc-preview-kicker"><?= $panel === 'cvs' ? 'CV preview' : 'Document preview' ?></span>
              <span class="doc-preview-filename" id="preview-active-file" title="<?= $previewFile ? h($previewFile['name']) : '' ?>"><?= $previewFile ? h(shortDisplayName($previewFile['name'])) : '' ?></span>
            </div>
            <div class="doc-preview-tools" id="preview-tools">
              <button type="button" class="doc-tool-btn" data-zoom="out" title="Zoom out">−</button>
              <span class="doc-zoom-label" id="preview-zoom-label">100%</span>
              <button type="button" class="doc-tool-btn" data-zoom="in" title="Zoom in">+</button>
              <button type="button" class="doc-tool-btn doc-tool-text" data-zoom="fit" title="Fit to preview">Fit</button>
              <button type="button" class="doc-tool-btn" data-rotate="cw" title="Rotate (PDF)">⟳</button>
              <a class="doc-tool-btn doc-tool-dl" id="preview-download" href="<?= $previewFile ? h(fileUrl($project['id'], $previewFile['path'], true)) : '#' ?>" download title="Download">⬇</a>
            </div>
          </div>
          <div class="preview-panel-body">
            <div class="preview-viewport" id="preview-viewport">
              <div class="preview-empty-state<?= $previewFile ? ' is-hidden' : '' ?>" id="preview-empty"<?= $previewFile ? ' hidden style="display:none"' : '' ?>>
                <p><?= $panel === 'cvs' ? 'Select a team member to preview their CV' : 'Select a file from the list' ?></p>
                <span class="preview-empty-hint">Preview appears here with scroll &amp; zoom</span>
              </div>
              <div class="preview-iframe-host<?= $previewFile ? '' : ' is-hidden' ?>" id="preview-iframe-host"<?= $previewFile ? '' : ' hidden style="display:none"' ?>>
                <?php if ($previewFile):
                  $previewTooLarge = isFileTooLargeForPreview($previewFile, $project['id'], $project['path']);
                ?>
                  <?php if ($previewTooLarge): ?>
                  <?= renderPreviewTooLargeNotice($previewFile) ?>
                  <?php else: ?>
                  <iframe src="<?= h(viewerUrl($project['id'], $previewFile['path'])) ?>" title="Document preview" data-path="<?= h($previewFile['path']) ?>"></iframe>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</main>
