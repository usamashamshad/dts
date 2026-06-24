<?php
/** @var array $config */
/** @var array $projects */
/** @var array|null $selected */
/** @var int $selIndex */
/** @var string $search */
$filtered = $projects;
if ($search !== '') {
    $q = mb_strtolower($search);
    $filtered = array_values(array_filter($projects, function ($p) use ($q) {
        return str_contains(mb_strtolower($p['title'] . ' ' . $p['client'] . ' ' . $p['location'] . ' ' . ($p['linkedFolderName'] ?? '')), $q);
    }));
}
?>
<main class="page page-board page-board-dts">
  <section class="dts-hero">
    <div class="dts-hero-glow" aria-hidden="true"></div>
    <div class="dts-hero-inner">
      <div class="dts-hero-content">
        <span class="dts-hero-badge">Document Management</span>
        <h1 class="dts-hero-title"><?= h($config['site_title']) ?></h1>
        <p class="dts-hero-tagline"><?= h($config['site_tagline']) ?></p>
      </div>
    </div>
    <?php if (!empty($config['product_credit_name'])):
      $creditParts = preg_split('/\s+/u', trim($config['product_credit_name']), -1, PREG_SPLIT_NO_EMPTY);
      $creditNameHtml = implode('&nbsp;', array_map(static function ($p) {
          return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
      }, $creditParts));
    ?>
    <aside class="dts-hero-credit" aria-label="Product credit">
      <?php if (!empty($config['product_credit_kicker'])): ?>
      <span class="dts-hero-credit-kicker"><?= h($config['product_credit_kicker']) ?></span>
      <?php endif; ?>
      <span class="dts-hero-credit-name"><?= $creditNameHtml ?></span>
    </aside>
    <?php endif; ?>
  </section>

  <?php if ($selected): ?>
    <?php include __DIR__ . '/detail-panel.php'; ?>
  <?php endif; ?>

  <div class="board-toolbar-simple dts-toolbar-row">
    <div class="dts-project-count-control">
      <span class="dts-count-label">Projects:</span>
      <span class="dts-count-display"><?= count($projects) ?></span>
      <button type="button" class="soft-btn dts-add-project-btn" id="open-add-project">+ Add project</button>
    </div>
    <form class="board-actions" method="get">
      <input class="field-input dts-search" name="q" placeholder="Search projects, clients, locations…" value="<?= h($search) ?>">
      <?php if ($selected): ?><input type="hidden" name="pick" value="<?= h($selected['id']) ?>"><?php endif; ?>
    </form>
  </div>

  <section class="dts-projects-section">
    <h3 class="dts-section-title">
      <span class="dts-section-icon">📁</span> Your Projects
      <span class="dts-project-count-badge"><?= count($projects) ?></span>
    </h3>

    <?php if (empty($projects)): ?>
    <div class="dts-no-projects">
      <p>No projects yet. Click <strong>+ Add project</strong> to create your first one.</p>
    </div>
    <?php elseif (empty($filtered)): ?>
    <div class="dts-no-projects"><p>No projects match your search.</p></div>
    <?php else: ?>
    <div class="dts-project-grid">
      <?php foreach ($filtered as $p):
        $hasFolder = $p['scanOk'];
        $isSel = $selected && $selected['id'] === $p['id'];
      ?>
      <div class="project-kanban-card <?= $hasFolder ? 'has-folder' : 'no-folder' ?> <?= $isSel ? 'is-selected' : '' ?>"
           data-pick="<?= h($p['id']) ?>">
        <div class="card-header">
          <span class="card-project-name"><?= h($p['title']) ?></span>
          <span class="status-badge <?= statusClass($p['status']) ?>"><?= h($p['status']) ?></span>
        </div>
        <?php if ($p['subtitle']): ?><div class="card-subtitle"><?= h($p['subtitle']) ?></div><?php endif; ?>
        <div class="card-folder-row">
          <?php if ($hasFolder): ?>
          <span class="card-folder-linked">📁 <?= h($p['linkedFolderName']) ?></span>
          <?php else: ?>
          <span class="card-folder-missing">Folder not found — check config.php</span>
          <?php endif; ?>
        </div>
        <div class="card-folder-actions">
          <button type="button" class="soft-btn card-folder-btn" data-open-folder-picker
                  data-project-id="<?= h($p['id']) ?>"
                  data-current-path="<?= h($p['path']) ?>"><?= $hasFolder ? 'Change folder' : 'Select project folder' ?></button>
          <a class="primary-btn card-open-btn" href="<?= h(url(['project' => $p['id']])) ?>">Open →</a>
        </div>
        <div class="card-stats-mini">
          <span><?= (int)$p['foldersCount'] ?> folders</span>
          <span><?= (int)$p['filesCount'] ?> files</span>
          <span><?= (int)$p['progress'] ?>%</span>
        </div>
      </div>
      <?php endforeach; ?>

      <button type="button" class="project-kanban-card add-project-card-trigger" id="add-project-card-trigger" aria-label="Add new project">
        <span class="add-project-card-plus">+</span>
        <span class="add-project-card-label">Add new project</span>
        <span class="add-project-card-hint">Same board layout · link a folder · optional copy from existing</span>
      </button>
    </div>
    <?php endif; ?>
  </section>

  <div class="add-project-overlay" id="add-project-overlay" hidden>
    <div class="add-project-modal" role="dialog" aria-labelledby="add-project-title">
      <div class="add-project-modal-glow" aria-hidden="true"></div>
      <header class="add-project-modal-header">
        <div>
          <h3 id="add-project-title">Add new project</h3>
          <p class="add-project-modal-desc">Creates another project card like your existing ones. You can link its folder now or later.</p>
        </div>
        <button type="button" class="settings-close-btn" id="close-add-project" aria-label="Close">✕</button>
      </header>
      <form id="add-project-form" class="add-project-form">
        <label class="sf-field sf-field-full">
          <span class="sf-label">Project name</span>
          <input type="text" id="ap-name" name="name" required placeholder="e.g. Site B — Highway Extension" autocomplete="off">
        </label>
        <label class="sf-field">
          <span class="sf-label">Project ID <span class="muted">(URL slug)</span></span>
          <input type="text" id="ap-id" name="id" placeholder="auto-generated" pattern="[a-z][a-z0-9_-]{1,48}" autocomplete="off">
        </label>
        <label class="sf-field">
          <span class="sf-label">Folder path <span class="muted">(optional)</span></span>
          <input type="text" id="ap-path" name="path" placeholder="e.g. D:/Projects/SiteB">
        </label>
        <label class="sf-field sf-field-full">
          <span class="sf-label">Copy settings from</span>
          <select id="ap-clone" name="clone_from" class="sf-select">
            <option value="">Start blank</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= h($p['id']) ?>"><?= h($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <p class="add-project-note muted">Copy includes team, timesheets, summary text, and phases — not uploaded files.</p>
        <div class="add-project-actions">
          <button type="submit" class="primary-btn" id="ap-submit">Create project</button>
          <button type="button" class="ghost-btn" id="cancel-add-project">Cancel</button>
        </div>
      </form>
    </div>
  </div>

<script>window.__DTS_PROJECT__ = <?= $selected ? json_encode([
  'id' => $selected['id'],
  'title' => $selected['title'],
  'subtitle' => $selected['subtitle'],
  'status' => $selected['status'],
  'progress' => $selected['progress'],
  'introduction' => $selected['introduction'],
  'executiveSummary' => $selected['executiveSummary'],
  'client' => $selected['client'],
  'sponsor' => $selected['sponsor'],
  'budget' => $selected['budget'],
  'pm' => $selected['pm'],
  'consultants' => $selected['consultants'] ?? '',
  'location' => $selected['location'],
  'startDate' => $selected['startDate'],
  'closingDate' => $selected['closingDate'],
  'sponsorLogoUrl' => $selected['sponsorLogoUrl'],
  'clientLogoUrl' => $selected['clientLogoUrl'] ?? '',
  'locationMapUrl' => $selected['locationMapUrl'],
  'panoramaUrl' => $selected['panoramaUrl'] ?? '',
  'disclaimer' => $selected['disclaimer'] ?? '',
  'projectSummarySheetUrl' => $selected['projectSummarySheetUrl'] ?? '',
  'projectSummarySheetName' => $selected['projectSummarySheetName'] ?? '',
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
  }, $selected['cvMembers'] ?? []),
  'timesheet' => $selected['timesheet'] ?? [],
  'gdriveFolderUrl' => $selected['gdriveFolderUrl'] ?? '',
  'gdriveScanOk' => !empty($selected['gdriveScanOk']),
  'gdriveScanError' => $selected['gdriveScanError'] ?? null,
  'gdriveFilesCount' => (int)($selected['gdriveFilesCount'] ?? 0),
  'path' => $selected['path'] ?? '',
  'localScanOk' => !empty($selected['localScanOk'] ?? $selected['scanOk']),
  'localFilesCount' => (int)($selected['localFilesCount'] ?? 0),
  'canDelete' => count($projects) > 1,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>;</script>
</main>
