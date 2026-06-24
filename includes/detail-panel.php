<?php
/** @var array $selected */
/** @var array $projects */
/** @var int $selIndex */
$tabs = detailTabs();
$duration = daysBetween($selected['startDate'] ?? '', $selected['closingDate'] ?? '');
$total = count($projects);
?>
<section class="dts-detail-panel" aria-label="<?= h($selected['title']) ?> details">
  <nav class="dts-detail-project-nav<?= $total < 2 ? ' is-single' : '' ?>" aria-label="Switch project">
    <?php
      $prev = $selIndex > 0 ? $projects[$selIndex - 1]['id'] : null;
      $next = $selIndex < $total - 1 ? $projects[$selIndex + 1]['id'] : null;
    ?>
    <a class="dts-detail-nav-arrow <?= $prev ? '' : 'disabled' ?>" href="<?= $prev ? h(url(['pick' => $prev])) : '#' ?>" <?= $prev ? '' : 'aria-disabled="true"' ?>>
      <span class="dts-nav-arrow-icon">‹</span><span>Previous</span>
    </a>
    <div class="dts-detail-project-center">
      <span class="dts-detail-project-counter">
        <span class="dts-detail-counter-num"><?= $selIndex + 1 ?></span>
        <span class="dts-detail-counter-sep">/</span><span><?= $total ?></span>
      </span>
      <div class="dts-detail-project-dots">
        <?php foreach ($projects as $i => $p): ?>
        <a href="<?= h(url(['pick' => $p['id']])) ?>" class="dts-detail-dot <?= $i === $selIndex ? 'is-active' : '' ?>" aria-label="Project <?= $i + 1 ?>"></a>
        <?php endforeach; ?>
      </div>
      <span class="dts-detail-keyboard-hint">← → to browse</span>
    </div>
    <a class="dts-detail-nav-arrow <?= $next ? '' : 'disabled' ?>" href="<?= $next ? h(url(['pick' => $next])) : '#' ?>" <?= $next ? '' : 'aria-disabled="true"' ?>>
      <span>Next</span><span class="dts-nav-arrow-icon">›</span>
    </a>
  </nav>

  <div class="dts-detail-banner<?= empty($selected['panoramaUrl']) ? ' dts-detail-banner--no-image' : '' ?>"
       <?php if (!empty($selected['panoramaUrl'])): ?>style="--dts-panorama: url('<?= h($selected['panoramaUrl']) ?>')"<?php endif; ?>>
    <div class="dts-detail-banner-photo" aria-hidden="true"></div>
    <div class="dts-detail-banner-overlay" aria-hidden="true"></div>
    <div class="dts-detail-banner-content">
      <div class="dts-detail-header">
        <div class="dts-detail-header-main">
          <div>
            <h2 class="dts-project-name"><?= h($selected['title']) ?></h2>
            <p class="dts-project-subtitle<?= ($selected['subtitle'] ?? '') === '' ? ' is-empty' : '' ?>"><?= ($selected['subtitle'] ?? '') !== '' ? h($selected['subtitle']) : "\u{200B}" ?></p>
          </div>
        </div>
        <div class="dts-spotlight-actions">
          <span class="status-badge <?= statusClass($selected['status']) ?>"><?= h($selected['status']) ?></span>
          <a class="dts-open-btn" href="<?= h(url(['project' => $selected['id']])) ?>">Open Project →</a>
        </div>
      </div>
    </div>
    <?php if (trim($selected['consultants'] ?? '') !== ''): ?>
    <div class="dts-detail-banner-consultants" aria-label="Project consultants">
      <div class="dts-detail-banner-consultants-label">Consultants:</div>
      <div class="dts-detail-banner-consultants-name"><?= h($selected['consultants']) ?></div>
    </div>
    <?php endif; ?>

  </div>

  <div class="dts-intro-box<?= ($selected['introduction'] ?? '') === '' ? ' is-empty' : '' ?>">
    <div class="dts-intro-box-title">Introduction:</div>
    <div class="dts-intro-box-text">
      <?= ($selected['introduction'] ?? '') !== '' ? h($selected['introduction']) : "\u{200B}" ?>
    </div>
  </div>

  <div class="dts-detail-layout">
    <nav class="dts-detail-nav" aria-label="Project information sections">
      <div class="dts-detail-nav-heading">Explore</div>
      <?php foreach ($tabs as $tab): ?>
      <button type="button" class="dts-detail-nav-item <?= $tab['id'] === 'summary' ? 'is-active' : '' ?>"
              data-detail-tab="<?= h($tab['id']) ?>" role="tab">
        <span class="dts-detail-nav-icon-wrap"><span class="dts-detail-nav-icon"><?= $tab['icon'] ?></span></span>
        <span class="dts-detail-nav-label"><?= h($tab['label']) ?></span>
        <span class="dts-detail-nav-active-bar" hidden></span>
      </button>
      <?php endforeach; ?>
    </nav>

    <div class="dts-detail-content">
      <div class="dts-detail-tab-viewport" id="detail-tab-viewport">
      <!-- Summary -->
      <div class="detail-tab-pane is-active" data-pane="summary">
        <div class="dts-detail-content-header"><span class="dts-detail-content-icon">📋</span><h3 class="dts-detail-pane-title">Executive Summary</h3></div>
        <div class="dts-detail-pane-body">
          <?php if ($selected['executiveSummary'] || $selected['summary']): ?>
          <p class="dts-detail-text"><?= nl2br(h($selected['executiveSummary'] ?: $selected['summary'])) ?></p>
          <?php else: ?>
          <p class="dts-detail-empty">No executive summary added yet. Edit in Settings → Projects.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Project Summary Sheet -->
      <?php
        $sheetUrl = $selected['projectSummarySheetUrl'] ?? '';
        $sheetName = $selected['projectSummarySheetName'] ?: 'Project Summary Sheet';
        $sheetExt = strtolower(pathinfo($sheetName, PATHINFO_EXTENSION));
        if ($sheetUrl && preg_match('/file=([^&]+)/', $sheetUrl, $sheetMatch)) {
            $sheetExt = strtolower(pathinfo(urldecode($sheetMatch[1]), PATHINFO_EXTENSION));
        }
        $sheetIsPdf = $sheetExt === 'pdf';
        $sheetRedirect = url(['pick' => $selected['id'], 'tab' => 'summary-sheet']);
      ?>
      <div class="detail-tab-pane" data-pane="summary-sheet">
        <div class="dts-detail-content-header">
          <span class="dts-detail-content-icon">📄</span>
          <h3 class="dts-detail-pane-title">Project Summary Sheet</h3>
        </div>
        <div class="dts-detail-pane-body dts-summary-sheet-body">
          <div class="dts-summary-sheet-toolbar">
            <form method="post" action="upload.php" enctype="multipart/form-data" class="dts-summary-sheet-form">
              <input type="hidden" name="project_id" value="<?= h($selected['id']) ?>">
              <input type="hidden" name="type" value="summary_sheet">
              <input type="hidden" name="redirect" value="<?= h($sheetRedirect) ?>">
              <input type="file" name="file" id="summary-sheet-file" class="dts-summary-sheet-file-input"
                     accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/*">
              <button type="submit" class="primary-btn dts-summary-sheet-upload-btn">Upload</button>
              <?php if ($sheetUrl): ?>
              <a class="ghost-btn dts-summary-sheet-download-btn" href="<?= h($sheetUrl) ?>&amp;download=1">Download</a>
              <?php endif; ?>
            </form>
            <?php if ($sheetUrl): ?>
            <span class="dts-summary-sheet-filename" title="<?= h($sheetName) ?>"><?= h($sheetName) ?></span>
            <?php endif; ?>
          </div>
          <div class="dts-summary-sheet-preview" id="summary-sheet-preview">
            <?php if ($sheetUrl): ?>
              <?php if ($sheetIsPdf): ?>
              <iframe src="<?= h($sheetUrl) ?>" title="<?= h($sheetName) ?>" class="dts-summary-sheet-iframe"></iframe>
              <?php else: ?>
              <img src="<?= h($sheetUrl) ?>" alt="<?= h($sheetName) ?>" class="dts-summary-sheet-image">
              <?php endif; ?>
            <?php else: ?>
            <div class="dts-summary-sheet-empty">
              <span class="dts-summary-sheet-empty-icon">📄</span>
              <p>No document uploaded yet</p>
              <span class="dts-summary-sheet-empty-hint">Choose a PDF or image above, then click Upload</span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Cost -->
      <div class="detail-tab-pane" data-pane="cost">
        <div class="dts-detail-content-header"><span class="dts-detail-content-icon">💰</span><h3 class="dts-detail-pane-title">Estimated Cost</h3></div>
        <div class="dts-detail-pane-body">
          <div class="dts-detail-stat-card dts-detail-stat-card--solo">
            <span class="dts-detail-stat-label">Estimated Cost</span>
            <span class="dts-detail-stat-value dts-detail-stat-cost"><?= h($selected['budget'] ?: '—') ?></span>
            <?php if (!empty($selected['budgetSource'])): ?>
            <span class="dts-detail-stat-source"><?= h($selected['budgetSource']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Client -->
      <div class="detail-tab-pane" data-pane="client">
        <div class="dts-detail-content-header"><span class="dts-detail-content-icon">🤝</span><h3 class="dts-detail-pane-title">Client & Sponsor</h3></div>
        <div class="dts-detail-pane-body">
          <div class="dts-detail-client-grid">
            <div class="dts-detail-party-card">
              <span class="dts-detail-party-heading">Client</span>
              <div class="dts-detail-party-body">
                <p class="dts-detail-party-name"><?= h($selected['client'] ?: '—') ?></p>
                <?php if (!empty($selected['clientLogoUrl'])): ?>
                <img src="<?= h($selected['clientLogoUrl']) ?>" alt="<?= h($selected['client'] ?: 'Client') ?>" class="dts-detail-party-logo">
                <?php else: ?>
                <div class="dts-detail-party-logo-placeholder"><span>🏢</span><p>Add in Settings</p></div>
                <?php endif; ?>
              </div>
            </div>
            <div class="dts-detail-party-card">
              <span class="dts-detail-party-heading">Sponsor</span>
              <div class="dts-detail-party-body">
                <p class="dts-detail-party-name"><?= h($selected['sponsor'] ?: '—') ?></p>
                <?php if (!empty($selected['sponsorLogoUrl'])): ?>
                <img src="<?= h($selected['sponsorLogoUrl']) ?>" alt="<?= h($selected['sponsor'] ?: 'Sponsor') ?>" class="dts-detail-party-logo">
                <?php else: ?>
                <div class="dts-detail-party-logo-placeholder"><span>⭐</span><p>Add in Settings</p></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Dates -->
      <div class="detail-tab-pane" data-pane="dates">
        <div class="dts-detail-content-header"><span class="dts-detail-content-icon">📅</span><h3 class="dts-detail-pane-title">Start & Closing Dates</h3></div>
        <div class="dts-detail-pane-body">
          <div class="dts-detail-timeline">
            <div class="dts-detail-timeline-item"><span class="dts-detail-timeline-dot start"></span><div><span class="dts-detail-info-label">Start Date</span><span class="dts-detail-info-value"><?= h(formatDate($selected['startDate'])) ?></span></div></div>
            <?php if ($duration !== null): ?><div class="dts-detail-timeline-duration"><span><?= $duration ?> days</span></div><?php endif; ?>
            <div class="dts-detail-timeline-item"><span class="dts-detail-timeline-dot end"></span><div><span class="dts-detail-info-label">Closing Date</span><span class="dts-detail-info-value"><?= h(formatDate($selected['closingDate'])) ?></span></div></div>
          </div>
        </div>
      </div>

      <!-- Team -->
      <div class="detail-tab-pane" data-pane="team">
        <div class="dts-detail-content-header"><span class="dts-detail-content-icon">👥</span><h3 class="dts-detail-pane-title">Project Team</h3></div>
        <div class="dts-detail-pane-body dts-detail-pane-body-flush">
          <?php if (empty($selected['cvMembers'])): ?>
          <p class="dts-detail-empty">No team members assigned yet.</p>
          <?php else: ?>
          <div class="dts-table-wrap dts-team-table-wrap">
            <table class="dts-table dts-team-table">
              <thead>
                <tr>
                  <th class="dts-team-th-name">Team member</th>
                  <th class="dts-team-th-role">Designation</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($selected['cvMembers'] as $m): ?>
              <tr class="dts-team-row">
                <td>
                  <div class="dts-team-name">
                    <?= teamAvatarHtml($selected['id'], $m) ?>
                    <span class="dts-team-member-name"><?= h($m['name']) ?></span>
                  </div>
                </td>
                <td>
                  <?php if (trim($m['role'] ?? '') !== ''): ?>
                  <span class="dts-role-badge"><?= h($m['role']) ?></span>
                  <?php else: ?>
                  <span class="dts-team-role-empty">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Location -->
      <?php
        $locMap = $selected['locationMapUrl'] ?? '';
        $locMapDownload = $locMap !== '' ? assetUrlDownloadLink($locMap) : '';
        $locMapFilename = $locMap !== '' ? assetFilenameFromUrl($locMap) : '';
      ?>
      <div class="detail-tab-pane" data-pane="location">
        <div class="dts-detail-content-header"><span class="dts-detail-content-icon">📍</span><h3 class="dts-detail-pane-title">Location</h3></div>
        <div class="dts-detail-pane-body">
          <div class="dts-detail-info-card full"><span class="dts-detail-info-icon">📍</span><div><span class="dts-detail-info-label">Project Location</span><span class="dts-detail-info-value"><?= h($selected['location'] ?: '—') ?></span></div></div>
          <div class="dts-detail-map-section">
            <div class="dts-detail-map-header">
              <span class="dts-detail-info-label">Location Map</span>
              <?php if ($locMapDownload): ?>
              <a class="ghost-btn dts-detail-map-download-btn" href="<?= h($locMapDownload) ?>" download="<?= h($locMapFilename) ?>">Download location map</a>
              <?php endif; ?>
            </div>
            <?php if ($locMap): ?>
            <img src="<?= h($locMap) ?>" alt="Location map (click to expand)" class="dts-detail-map-preview" data-lightbox-src="<?= h($locMap) ?>" tabindex="0">
            <?php else: ?>
            <div class="dts-detail-map-placeholder"><span>🗺️</span><p>Upload a location map in Settings</p></div>
            <?php endif; ?>
          </div>

          <div class="dts-lightbox-overlay" id="map-lightbox" hidden>
            <div class="dts-lightbox-card" role="dialog" aria-modal="true" aria-label="Location map">
              <button type="button" class="dts-lightbox-close" id="map-lightbox-close" aria-label="Close">×</button>
              <img src="" alt="Location map expanded" class="dts-lightbox-img" id="map-lightbox-img">
            </div>
          </div>
        </div>
      </div>
      </div><!-- /.dts-detail-tab-viewport -->
    </div>
  </div>

  <?php if (trim($selected['disclaimer'] ?? '') !== ''):
    $disclaimerBody = preg_replace('/^disclaimer\s*:\s*/i', '', trim($selected['disclaimer']));
  ?>
  <div class="dts-detail-disclaimer dts-detail-disclaimer--below" aria-label="Disclaimer">
    <div class="dts-detail-disclaimer-track">
      <span class="dts-detail-disclaimer-label">Disclaimer:</span>
      <span class="dts-detail-disclaimer-text"><?= h($disclaimerBody) ?></span>
    </div>
  </div>
  <?php endif; ?>
</section>
