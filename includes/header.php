<?php
/** @var string $pageClass board|workspace */
/** @var array $config */
$isBoard = ($pageClass ?? 'board') === 'board';
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
  (function () {
    try {
      var stored = localStorage.getItem('dts-theme');
      var root = document.documentElement;
      var isBoard = <?= json_encode($isBoard) ?>;
      if (stored === 'light') root.classList.add('theme-light');
      else if (stored === 'dark') root.classList.remove('theme-light');
      else if (isBoard) root.classList.add('theme-light');
    } catch (e) {}
  })();
  </script>
  <title><?= h($config['site_title']) ?></title>
  <link rel="stylesheet" href="style.css?v=6">
  <link rel="stylesheet" href="fixes.css?v=54">
<?php if (($pageClass ?? '') === 'workspace' && !empty($projectId)): ?>
  <script>
  (function () {
    try {
      var pid = <?= json_encode($projectId, JSON_UNESCAPED_UNICODE) ?>;
      var params = new URLSearchParams(location.search);
      var panel = params.get('panel') || '';
      var folder = params.get('folder') || '';
      var key = panel ? ('panel:' + panel) : (folder ? ('folder:' + folder) : 'files');
      var store = JSON.parse(sessionStorage.getItem('dts-workspace-scroll') || '{}');
      var bucket = store[pid];
      if (!bucket) return;
      var saved = bucket[key] || bucket.__page__;
      if (!saved) return;
      document.documentElement.style.scrollBehavior = 'auto';
      if (saved.windowY > 0) window.scrollTo(0, saved.windowY);
    } catch (e) {}
  })();
  </script>
<?php endif; ?>
</head>
<body class="app-root page-<?= h($pageClass ?? 'board') ?>"<?php
  $bodyData = [];
  if (($pageClass ?? '') === 'workspace' && isset($projectId) && $projectId !== '') {
    $bodyData['project-id'] = $projectId;
  }
  if (($pageClass ?? '') === 'workspace' && !empty($project['syncSignature'])) {
    $bodyData['sync-signature'] = $project['syncSignature'];
  }
  if (($pageClass ?? '') === 'board' && !empty($boardSyncSignature)) {
    $bodyData['board-sync-signature'] = $boardSyncSignature;
  }
  foreach ($bodyData as $k => $v) {
    echo ' data-' . h($k) . '="' . h($v) . '"';
  }
?>>
<header class="app-header">
  <div class="brand">
    <div class="brand-mark" aria-hidden="true"></div>
    <div>
      <div class="brand-title"><?= h($config['site_title']) ?></div>
      <div class="brand-subtitle"><?= h($config['site_subtitle']) ?></div>
    </div>
  </div>
  <div class="header-right">
    <span class="header-sync-badge" title="XAMPP LAN server">
      <span class="header-sync-dot" aria-hidden="true"></span>
      <span>Live</span>
    </span>
    <button type="button" class="toggle-btn" id="theme-toggle" aria-label="Toggle light/dark theme">🌓 Theme</button>
    <button type="button" class="customise-btn" id="open-settings" aria-label="Open settings">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
        <circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
      </svg>
      Settings
    </button>
  </div>
</header>
