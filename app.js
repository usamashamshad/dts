(function () {
  const html = document.documentElement
  const stored = localStorage.getItem('dts-theme')
  if (stored === 'dark') {
    html.classList.remove('theme-light')
  } else if (stored === 'light') {
    html.classList.add('theme-light')
  } else if (document.body.classList.contains('page-board')) {
    html.classList.add('theme-light')
  }

  function appPath(file) {
    const p = window.location.pathname
    const base = p.substring(0, p.lastIndexOf('/') + 1)
    return base + file
  }

  function shortDisplayName(name, max = 28) {
    if (!name) return ''
    if (name.length <= max) return name
    const dot = name.lastIndexOf('.')
    const ext = dot > 0 ? name.slice(dot) : ''
    const base = dot > 0 ? name.slice(0, dot) : name
    const budget = max - ext.length - 1
    if (budget < 6) return name.slice(0, max - 1) + '…'
    const head = Math.ceil(budget * 0.6)
    const tail = Math.max(0, budget - head)
    return base.slice(0, head) + '…' + (tail ? base.slice(-tail) : '') + ext
  }

  const SCROLL_STORE_KEY = 'dts-workspace-scroll'
  const NAV_STORE_KEY = 'dts-workspace-nav'
  const SOURCE_STORE_KEY = 'dts-workspace-source'

  function loadSourceStore() {
    try {
      return JSON.parse(localStorage.getItem(SOURCE_STORE_KEY) || '{}')
    } catch {
      return {}
    }
  }

  function syncSourceCookie(store) {
    document.cookie = SOURCE_STORE_KEY + '=' + encodeURIComponent(JSON.stringify(store)) + ';path=/;max-age=31536000;SameSite=Lax'
  }

  function folderToSource(folder) {
    return folder.startsWith('Google Drive') ? 'gdrive' : 'local'
  }

  function saveWorkspaceSource(projectId, source, folder) {
    if (!projectId || (source !== 'local' && source !== 'gdrive')) return
    const store = loadSourceStore()
    const entry = { source }
    if (folder) entry.folder = folder
    store[projectId] = entry
    localStorage.setItem(SOURCE_STORE_KEY, JSON.stringify(store))
    syncSourceCookie(store)
  }

  function projectOpenHref(projectId) {
    const base = appPath('index.php') + '?project=' + encodeURIComponent(projectId)
    const saved = loadSourceStore()[projectId]
    if (!saved) return base
    if (saved.folder) {
      return base + '&folder=' + encodeURIComponent(saved.folder)
    }
    if (saved.source === 'gdrive') {
      return base + '&folder=' + encodeURIComponent('Google Drive')
    }
    return base
  }

  function applySavedProjectOpenLinks() {
    document.querySelectorAll('.dts-open-btn, .card-open-btn').forEach((link) => {
      const url = new URL(link.href, window.location.origin)
      const projectId = url.searchParams.get('project')
      if (!projectId) return
      link.href = projectOpenHref(projectId)
    })
  }

  function loadNavStore() {
    try {
      return JSON.parse(sessionStorage.getItem(NAV_STORE_KEY) || '{}')
    } catch {
      return {}
    }
  }

  function saveWorkspaceNav(projectId) {
    if (!projectId) return
    const openIds = []
    document.querySelectorAll('.ws-sidebar details[data-nav-id][open]').forEach((el) => {
      const id = el.getAttribute('data-nav-id')
      if (id) openIds.push(id)
    })
    const store = loadNavStore()
    store[projectId] = openIds
    sessionStorage.setItem(NAV_STORE_KEY, JSON.stringify(store))
  }

  function restoreWorkspaceNav(projectId) {
    const openIds = loadNavStore()[projectId]
    if (!openIds?.length) return false
    openIds.forEach((id) => {
      const el = document.querySelector(`.ws-sidebar details[data-nav-id="${CSS.escape(id)}"]`)
      if (el) el.open = true
    })
    return true
  }

  function loadScrollStore() {
    try {
      return JSON.parse(sessionStorage.getItem(SCROLL_STORE_KEY) || '{}')
    } catch {
      return {}
    }
  }

  function workspaceViewKey() {
    const params = new URLSearchParams(window.location.search)
    const panel = params.get('panel') || ''
    const folder = params.get('folder') || ''
    if (panel) return `panel:${panel}`
    if (folder) return `folder:${folder}`
    return 'files'
  }

  function saveWorkspaceScroll(projectId) {
    if (!projectId) return
    const store = loadScrollStore()
    if (!store[projectId]) store[projectId] = {}
    const key = workspaceViewKey()
    const list = document.querySelector('.files-table-wrap')
    const pos = {
      windowY: window.scrollY,
      listY: list?.scrollTop ?? 0,
    }
    store[projectId][key] = pos
    store[projectId].__page__ = { windowY: pos.windowY, listY: pos.listY }
    sessionStorage.setItem(SCROLL_STORE_KEY, JSON.stringify(store))
  }

  function restoreWorkspaceScroll(projectId) {
    const bucket = loadScrollStore()[projectId]
    if (!bucket) return false
    const key = workspaceViewKey()
    const saved = bucket[key] || bucket.__page__
    if (!saved) return false
    const apply = () => {
      window.scrollTo(0, saved.windowY || 0)
      const list = document.querySelector('.files-table-wrap')
      if (list) list.scrollTop = saved.listY || 0
    }
    apply()
    requestAnimationFrame(apply)
    setTimeout(apply, 0)
    setTimeout(apply, 100)
    setTimeout(apply, 250)
    return true
  }

  function toast(msg, isError) {
    const el = document.createElement('div')
    el.className = 'toast' + (isError ? ' error' : '')
    el.textContent = msg
    document.body.appendChild(el)
    setTimeout(() => el.remove(), 4000)
  }

  function escapeHtmlText(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
  }

  function printFolderFileList() {
    const rows = document.querySelectorAll('#files-table tbody tr[data-name]')
    if (!rows.length) {
      toast('No files in this folder to print', true)
      return
    }

    const projectTitle = document.querySelector('.workspace-title')?.textContent?.trim() || 'Project'
    const folder = document.querySelector('.files-panel-chrome .panel-title-text')?.textContent?.trim() || 'Folder'
    const printedAt = new Date().toLocaleString()
    const items = [...rows].map((tr, i) => ({
      num: i + 1,
      name: tr.getAttribute('data-name') || '',
      kind: tr.getAttribute('data-kind') || '',
      size: tr.getAttribute('data-size') || '',
      date: tr.getAttribute('data-date') || '',
    }))

    const listHtml = items.map((f) => (
      `<tr><td class="num">${f.num}</td>`
      + `<td class="name">${escapeHtmlText(f.name)}</td>`
      + `<td>${escapeHtmlText(f.kind)}</td>`
      + `<td>${escapeHtmlText(f.size)}</td>`
      + `<td>${escapeHtmlText(f.date)}</td></tr>`
    )).join('')

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>File list — ${escapeHtmlText(folder)}</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 24px; color: #111; }
    h1 { font-size: 18px; margin: 0 0 6px; }
    .meta { font-size: 12px; color: #444; margin-bottom: 16px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; }
    td.num { width: 36px; text-align: center; }
    td.name { word-break: break-word; }
    @media print { body { margin: 12mm; } }
  </style>
</head>
<body>
  <h1>${escapeHtmlText(projectTitle)}</h1>
  <div class="meta">
    <div><strong>Folder:</strong> ${escapeHtmlText(folder)}</div>
    <div><strong>Files:</strong> ${items.length}</div>
    <div><strong>Printed:</strong> ${escapeHtmlText(printedAt)}</div>
  </div>
  <table>
    <thead><tr><th>#</th><th>File name</th><th>Type</th><th>Size</th><th>Date</th></tr></thead>
    <tbody>${listHtml}</tbody>
  </table>
</body>
</html>`

    let frame = document.getElementById('dts-print-frame')
    if (!frame) {
      frame = document.createElement('iframe')
      frame.id = 'dts-print-frame'
      frame.setAttribute('title', 'Print file list')
      frame.style.cssText = 'position:fixed;width:0;height:0;border:0;visibility:hidden;'
      document.body.appendChild(frame)
    }

    const printWindow = frame.contentWindow
    const printDoc = printWindow?.document
    if (!printWindow || !printDoc) {
      toast('Could not open print view', true)
      return
    }

    let printed = false
    const doPrint = () => {
      if (printed) return
      printed = true
      frame.onload = null
      try {
        printWindow.focus()
        printWindow.print()
      } catch {
        toast('Print failed in this browser', true)
      }
    }

    frame.onload = doPrint
    setTimeout(doPrint, 600)

    printDoc.open()
    printDoc.write(html)
    printDoc.close()
  }

  async function uploadSummarySheet(projectId, fileInput) {
    if (!projectId || !fileInput?.files?.length) {
      toast('Choose a PDF or image file first', true)
      return false
    }
    const fd = new FormData()
    fd.append('project_id', projectId)
    fd.append('type', 'summary_sheet')
    fd.append('file', fileInput.files[0])
    try {
      const res = await fetch(appPath('upload.php'), { method: 'POST', body: fd })
      const text = await res.text()
      let json
      try {
        json = JSON.parse(text)
      } catch {
        toast('Upload failed — server returned an invalid response', true)
        return false
      }
      if (json.ok) {
        toast('Project summary sheet uploaded')
        return true
      }
      toast(json.error || 'Upload failed', true)
    } catch {
      toast('Network error during upload', true)
    }
    return false
  }

  function activateDetailTab(tabId) {
    if (!tabId) return
    const btn = document.querySelector(`[data-detail-tab="${tabId}"]`)
    if (!btn) return
    document.querySelectorAll('[data-detail-tab]').forEach((b) => b.classList.toggle('is-active', b === btn))
    document.querySelectorAll('.detail-tab-pane').forEach((pane) => {
      const show = pane.getAttribute('data-pane') === tabId
      pane.classList.toggle('is-active', show)
      pane.removeAttribute('hidden')
    })
    lockDetailTabViewport()
  }

  function lockDetailTabViewport() {
    const panel = document.querySelector('.dts-detail-panel')
    if (!panel) return
    const h = getComputedStyle(document.documentElement)
      .getPropertyValue('--dts-detail-panel-height')
      .trim() || '520px'
    const targets = [
      panel.querySelector('.dts-detail-layout'),
      panel.querySelector('.dts-detail-content'),
      document.getElementById('detail-tab-viewport'),
    ]
    targets.forEach((el) => {
      if (!el) return
      el.style.setProperty('height', h, 'important')
      el.style.setProperty('min-height', h, 'important')
      el.style.setProperty('max-height', h, 'important')
    })
    panel.querySelectorAll('.detail-tab-pane').forEach((pane) => {
      pane.style.setProperty('height', h, 'important')
      pane.style.setProperty('min-height', h, 'important')
      pane.style.setProperty('max-height', h, 'important')
    })
  }

  document.querySelectorAll('[data-detail-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      activateDetailTab(btn.getAttribute('data-detail-tab'))
    })
  })

  if (document.querySelector('.dts-detail-layout')) {
    lockDetailTabViewport()
    requestAnimationFrame(lockDetailTabViewport)
    window.addEventListener('resize', lockDetailTabViewport)
    window.addEventListener('pageshow', lockDetailTabViewport)
  }

  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('upload_ok') === '1') {
    activateDetailTab('summary-sheet')
    toast('Project summary sheet uploaded — preview is shown below')
    urlParams.delete('upload_ok')
    const clean = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '')
    history.replaceState({}, '', clean)
  } else {
    const tab = urlParams.get('tab')
    if (tab) activateDetailTab(tab)
  }
  const uploadErr = urlParams.get('upload_error')
  if (uploadErr) {
    activateDetailTab('summary-sheet')
    toast(decodeURIComponent(uploadErr), true)
    urlParams.delete('upload_error')
    const clean = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '')
    history.replaceState({}, '', clean)
  }

  document.getElementById('theme-toggle')?.addEventListener('click', () => {
    html.classList.toggle('theme-light')
    localStorage.setItem('dts-theme', html.classList.contains('theme-light') ? 'light' : 'dark')
  })

  document.querySelectorAll('.dts-summary-sheet-form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const input = form.querySelector('input[type="file"]')
      if (!input?.files?.length) {
        e.preventDefault()
        toast('Choose a PDF or image file first', true)
      }
    })
  })

  // Location map lightbox (click to expand)
  const mapLightbox = document.getElementById('map-lightbox')
  const mapLightboxImg = document.getElementById('map-lightbox-img')
  const mapLightboxClose = document.getElementById('map-lightbox-close')
  const mapThumb = document.querySelector('.dts-detail-map-preview[data-lightbox-src]')

  function openMapLightbox(src) {
    if (!mapLightbox || !mapLightboxImg || !src) return
    mapLightboxImg.src = src
    mapLightbox.hidden = false
    document.documentElement.classList.add('is-lightbox-open')
  }

  function closeMapLightbox() {
    if (!mapLightbox || !mapLightboxImg) return
    mapLightbox.hidden = true
    mapLightboxImg.src = ''
    document.documentElement.classList.remove('is-lightbox-open')
  }

  if (mapThumb && mapLightbox) {
    mapThumb.addEventListener('click', () => openMapLightbox(mapThumb.getAttribute('data-lightbox-src')))
    mapThumb.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault()
        openMapLightbox(mapThumb.getAttribute('data-lightbox-src'))
      }
    })
    mapLightbox.addEventListener('click', (e) => { if (e.target === mapLightbox) closeMapLightbox() })
    mapLightboxClose?.addEventListener('click', closeMapLightbox)
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !mapLightbox.hidden) closeMapLightbox()
    })
  }

  document.querySelectorAll('[data-pick]').forEach((card) => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('a, button')) return
      const id = card.getAttribute('data-pick')
      window.location.href = 'index.php?pick=' + encodeURIComponent(id)
    })
  })

  const acc = document.getElementById('summary-accordion')
  const body = document.getElementById('summary-body')
  if (acc && body) {
    acc.addEventListener('click', () => {
      const open = body.hidden
      body.hidden = !open
      acc.classList.toggle('is-open', open)
      acc.querySelector('.dts-accordion-icon').textContent = open ? '▼' : '▶'
    })
  }

  const overlay = document.getElementById('settings-overlay')
  const form = document.getElementById('settings-form')
  const openBtn = document.getElementById('open-settings')
  const closeBtn = document.getElementById('close-settings')
  const cancelBtn = document.getElementById('cancel-settings')
  const teamEditor = document.getElementById('team-members-editor')
  const timesheetEditor = document.getElementById('timesheet-editor')
  let settingsProject = null

  function initialsFromName(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean)
    if (!parts.length) return '?'
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }

  function syncProgressUI(val) {
    const n = Math.max(0, Math.min(100, Number(val) || 0))
    const num = document.getElementById('sf-progress')
    const range = document.getElementById('sf-progress-range')
    const fill = document.getElementById('sf-progress-fill')
    const label = document.getElementById('sf-progress-val')
    if (num) num.value = n
    if (range) range.value = n
    if (fill) fill.style.width = n + '%'
    if (label) label.textContent = n + '%'
  }

  function updateSettingsBadges(members, timesheet) {
    const teamCount = document.getElementById('settings-team-count')
    const tsCount = document.getElementById('settings-ts-count')
    const mc = (members || []).filter((m) => m?.name?.trim()).length
    const tc = (timesheet || []).filter((r) => r?.week?.trim()).length
    if (teamCount) teamCount.textContent = String(mc)
    if (tsCount) tsCount.textContent = String(tc)
  }

  function activateSettingsTab(tabId) {
    document.querySelectorAll('[data-settings-tab]').forEach((btn) => {
      btn.classList.toggle('is-active', btn.getAttribute('data-settings-tab') === tabId)
    })
    document.querySelectorAll('[data-settings-pane]').forEach((pane) => {
      const show = pane.getAttribute('data-settings-pane') === tabId
      if (show) {
        pane.hidden = false
        pane.classList.remove('is-active')
        void pane.offsetWidth
        pane.classList.add('is-active')
      } else {
        pane.hidden = true
        pane.classList.remove('is-active')
      }
    })
  }

  document.querySelectorAll('[data-settings-tab]').forEach((btn) => {
    btn.addEventListener('click', () => activateSettingsTab(btn.getAttribute('data-settings-tab')))
  })

  document.getElementById('sf-progress-range')?.addEventListener('input', (e) => syncProgressUI(e.target.value))
  document.getElementById('sf-progress')?.addEventListener('input', (e) => syncProgressUI(e.target.value))

  function teamPhotoUrl(projectId, photoAsset) {
    const asset = String(photoAsset || '').trim()
    if (!projectId || !asset) return ''
    return appPath('asset.php') + '?' + new URLSearchParams({ project: projectId, file: asset }).toString()
  }

  function updateTeamRowAvatar(row) {
    const name = row.querySelector('.team-field-name')?.value || ''
    const initials = row.querySelector('.team-field-initials')?.value?.trim() || initialsFromName(name)
    const photoAsset = row.querySelector('.team-field-photoasset')?.value?.trim() || ''
    const avatar = row.querySelector('.team-avatar')
    const title = row.querySelector('.team-editor-row-title')
    const sub = row.querySelector('.team-editor-row-sub')
    const photoUrl = teamPhotoUrl(settingsProject?.id, photoAsset)
    if (avatar) {
      if (photoUrl) {
        avatar.classList.add('has-photo')
        avatar.innerHTML = `<img src="${escapeAttr(photoUrl)}" alt="">`
      } else {
        avatar.classList.remove('has-photo')
        avatar.textContent = initials || '?'
      }
    }
    if (title) title.textContent = name.trim() || 'New team member'
    if (sub) sub.textContent = row.querySelector('.team-field-role')?.value?.trim() || 'Role not set'
  }

  function teamAvatarEditorHtml(m, projectId) {
    const initials = m.initials?.trim() || initialsFromName(m.name)
    const photoUrl = teamPhotoUrl(projectId, m.photoAsset)
    if (photoUrl) {
      return `<div class="team-avatar has-photo" aria-hidden="true"><img src="${escapeAttr(photoUrl)}" alt=""></div>`
    }
    return `<div class="team-avatar" aria-hidden="true">${escapeHtml(initials || '?')}</div>`
  }

  function teamRowHtml(member, index) {
    const m = member || {}
    const hasCv = !!(m.cvAsset || m.cvFilePath)
    const hasPhoto = !!m.photoAsset
    const cvLabel = m.cvAsset
      ? `Uploaded: ${m.cvAsset}`
      : (m.cvFilePath ? `Linked: ${m.cvFilePath}` : 'No CV attached yet')
    const photoLabel = m.photoAsset
      ? `Uploaded: ${m.photoAsset}`
      : 'No photo yet — add a headshot'
    const cvIcon = hasCv ? '✓' : '📎'
    const photoIcon = hasPhoto ? '✓' : '📷'
    return `
      <div class="team-editor-row" data-index="${index}">
        <div class="team-editor-row-head">
          <div class="team-editor-row-head-left">
            ${teamAvatarEditorHtml(m, settingsProject?.id)}
            <div>
              <div class="team-editor-row-title">${escapeHtml(m.name?.trim() || 'New team member')}</div>
              <div class="team-editor-row-sub">${escapeHtml(m.role?.trim() || 'Role not set')}</div>
            </div>
          </div>
          <button type="button" class="ghost-btn team-row-remove" title="Remove">✕ Remove</button>
        </div>
        <div class="team-editor-fields">
          <label>Full name<input type="text" class="team-field-name" value="${escapeAttr(m.name || '')}" placeholder="Engr. Salman Akhtar"></label>
          <label>Initials<input type="text" class="team-field-initials" value="${escapeAttr(m.initials || '')}" maxlength="4" placeholder="SA"></label>
          <label>Role / designation<input type="text" class="team-field-role" value="${escapeAttr(m.role || '')}" placeholder="Project Manager"></label>
          <label>Experience (years)<input type="number" class="team-field-exp" value="${Number(m.experienceYears) || 0}" min="0"></label>
          <label>Group<input type="text" class="team-field-group" value="${escapeAttr(m.group || '')}" placeholder="Managers"></label>
          <label class="team-field-wide">CV file path in project folder
            <input type="text" class="team-field-cvpath" value="${escapeAttr(m.cvFilePath || '')}" placeholder="Team/CVs/Salman_CV.pdf">
          </label>
          <input type="hidden" class="team-field-id" value="${escapeAttr(m.id || ('m' + (index + 1)))}">
          <input type="hidden" class="team-field-cvasset" value="${escapeAttr(m.cvAsset || '')}">
          <input type="hidden" class="team-field-photoasset" value="${escapeAttr(m.photoAsset || '')}">
          <div class="team-photo-zone" data-photo-drop>
            <div class="team-photo-zone-title">Member photo</div>
            <div class="team-photo-zone-hint">Upload a headshot (JPG, PNG, GIF, WebP)</div>
            <div class="team-photo-upload-row">
              <input type="file" class="team-photo-file team-photo-file-input" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
              <button type="button" class="soft-btn team-photo-browse-btn">Browse photo</button>
              <button type="button" class="primary-btn team-photo-upload-btn">Upload photo</button>
            </div>
            <div class="team-photo-status ${hasPhoto ? '' : 'is-empty'}">
              <span class="team-photo-status-icon">${photoIcon}</span>
              <span class="team-photo-status-text">${escapeHtml(photoLabel)}</span>
            </div>
          </div>
          <div class="team-cv-zone" data-cv-drop>
            <div class="team-cv-zone-title">Upload CV document</div>
            <div class="team-cv-zone-hint">Drag & drop PDF or Word here, or browse</div>
            <div class="team-cv-upload-row">
              <input type="file" class="team-cv-file team-cv-file-input" accept=".pdf,.doc,.docx,application/pdf">
              <button type="button" class="soft-btn team-cv-browse-btn">Browse file</button>
              <button type="button" class="primary-btn team-cv-upload-btn">Upload CV</button>
            </div>
            <div class="team-cv-status ${hasCv ? '' : 'is-empty'}">
              <span class="team-cv-status-icon">${cvIcon}</span>
              <span class="team-cv-status-text">${escapeHtml(cvLabel)}</span>
            </div>
          </div>
        </div>
      </div>`
  }

  function timesheetRowHtml(row, index) {
    const r = row || {}
    return `
      <div class="timesheet-editor-row" data-index="${index}">
        <div class="timesheet-editor-row-head">
          <strong>⏱️ Week ${index + 1}${r.week ? ` — ${escapeHtml(r.week)}` : ''}</strong>
          <button type="button" class="ghost-btn timesheet-row-remove">✕ Remove</button>
        </div>
        <div class="timesheet-editor-fields">
          <label>Week<input type="text" class="ts-field-week" value="${escapeAttr(r.week || '')}" placeholder="Wk 22"></label>
          <label>Hours<input type="text" class="ts-field-hours" value="${escapeAttr(r.hours || '')}" placeholder="40 h"></label>
          <label>Phase<input type="text" class="ts-field-phase" value="${escapeAttr(r.phase || '')}" placeholder="Planning"></label>
          <label class="ts-field-wide">Notes<input type="text" class="ts-field-notes" value="${escapeAttr(r.notes || '')}" placeholder="Site visit, client meeting…"></label>
          <input type="hidden" class="ts-field-id" value="${escapeAttr(r.id || ('t' + (index + 1)))}">
        </div>
      </div>`
  }

  function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
  }

  function renderTeamEditor(members) {
    if (!teamEditor) return
    const list = members?.length ? members : [{ id: 'm1', initials: '', name: '', role: '', experienceYears: 0, group: '', cvFilePath: '', cvAsset: '', photoAsset: '' }]
    teamEditor.innerHTML = list.map((m, i) => teamRowHtml(m, i)).join('')
    bindTeamEditorEvents()
  }

  function renderTimesheetEditor(rows) {
    if (!timesheetEditor) return
    const list = rows?.length ? rows : [{ id: 't1', week: '', hours: '', phase: '', notes: '' }]
    timesheetEditor.innerHTML = list.map((r, i) => timesheetRowHtml(r, i)).join('')
    bindTimesheetEditorEvents()
  }

  function collectTeamMembers() {
    return Array.from(document.querySelectorAll('.team-editor-row')).map((row, i) => ({
      id: row.querySelector('.team-field-id')?.value || ('m' + (i + 1)),
      name: row.querySelector('.team-field-name')?.value?.trim() || '',
      initials: row.querySelector('.team-field-initials')?.value?.trim() || '',
      role: row.querySelector('.team-field-role')?.value?.trim() || '',
      experienceYears: Number(row.querySelector('.team-field-exp')?.value) || 0,
      group: row.querySelector('.team-field-group')?.value?.trim() || '',
      cvFilePath: row.querySelector('.team-field-cvpath')?.value?.trim() || '',
      cvAsset: row.querySelector('.team-field-cvasset')?.value?.trim() || '',
      photoAsset: row.querySelector('.team-field-photoasset')?.value?.trim() || '',
    })).filter((m) => m.name !== '')
  }

  function collectTimesheet() {
    return Array.from(document.querySelectorAll('.timesheet-editor-row')).map((row, i) => ({
      id: row.querySelector('.ts-field-id')?.value || ('t' + (i + 1)),
      week: row.querySelector('.ts-field-week')?.value?.trim() || '',
      hours: row.querySelector('.ts-field-hours')?.value?.trim() || '',
      phase: row.querySelector('.ts-field-phase')?.value?.trim() || '',
      notes: row.querySelector('.ts-field-notes')?.value?.trim() || '',
    })).filter((r) => r.week !== '')
  }

  async function uploadTeamCv(row, file, btn) {
    const fileInput = row?.querySelector('.team-cv-file')
    const status = row?.querySelector('.team-cv-status')
    const statusText = row?.querySelector('.team-cv-status-text')
    const memberId = row?.querySelector('.team-field-id')?.value || 'member'
    if (!settingsProject?.id || !file) {
      toast('Choose a CV file first', true)
      return
    }
    const fd = new FormData()
    fd.append('project_id', settingsProject.id)
    fd.append('type', 'member_cv')
    fd.append('member_id', memberId)
    fd.append('file', file)
    if (btn) btn.disabled = true
    try {
      const res = await fetch(appPath('upload.php'), { method: 'POST', body: fd })
      const json = await res.json()
      if (json.ok && json.filename) {
        row.querySelector('.team-field-cvasset').value = json.filename
        const label = 'Uploaded: ' + (json.name || json.filename)
        if (statusText) statusText.textContent = label
        if (status) {
          status.classList.remove('is-empty')
          status.querySelector('.team-cv-status-icon').textContent = '✓'
        }
        toast('CV uploaded — click Save team & CVs to keep')
        if (fileInput) fileInput.value = ''
      } else {
        toast(json.error || 'CV upload failed', true)
      }
    } catch {
      toast('Network error uploading CV', true)
    }
    if (btn) btn.disabled = false
  }

  async function uploadTeamPhoto(row, file, btn) {
    const fileInput = row?.querySelector('.team-photo-file')
    const status = row?.querySelector('.team-photo-status')
    const statusText = row?.querySelector('.team-photo-status-text')
    const memberId = row?.querySelector('.team-field-id')?.value || 'member'
    if (!settingsProject?.id || !file) {
      toast('Choose a photo first', true)
      return
    }
    const fd = new FormData()
    fd.append('project_id', settingsProject.id)
    fd.append('type', 'member_photo')
    fd.append('member_id', memberId)
    fd.append('file', file)
    if (btn) btn.disabled = true
    try {
      const res = await fetch(appPath('upload.php'), { method: 'POST', body: fd })
      const json = await res.json()
      if (json.ok && json.filename) {
        row.querySelector('.team-field-photoasset').value = json.filename
        const label = 'Uploaded: ' + (json.name || json.filename)
        if (statusText) statusText.textContent = label
        if (status) {
          status.classList.remove('is-empty')
          status.querySelector('.team-photo-status-icon').textContent = '✓'
        }
        updateTeamRowAvatar(row)
        toast('Photo uploaded — click Save team & CVs to keep')
        if (fileInput) fileInput.value = ''
      } else {
        toast(json.error || 'Photo upload failed', true)
      }
    } catch {
      toast('Network error uploading photo', true)
    }
    if (btn) btn.disabled = false
  }

  function bindPhotoDropZone(zone) {
    if (!zone || zone.dataset.bound) return
    zone.dataset.bound = '1'
    ;['dragenter', 'dragover'].forEach((ev) => {
      zone.addEventListener(ev, (e) => {
        e.preventDefault()
        zone.classList.add('is-dragover')
      })
    })
    ;['dragleave', 'drop'].forEach((ev) => {
      zone.addEventListener(ev, (e) => {
        e.preventDefault()
        zone.classList.remove('is-dragover')
      })
    })
    zone.addEventListener('drop', (e) => {
      const row = zone.closest('.team-editor-row')
      const fileInput = row?.querySelector('.team-photo-file')
      const file = e.dataTransfer?.files?.[0]
      if (file && fileInput) {
        const dt = new DataTransfer()
        dt.items.add(file)
        fileInput.files = dt.files
        const statusText = row?.querySelector('.team-photo-status-text')
        if (statusText) statusText.textContent = 'Ready to upload: ' + file.name
      }
    })
  }

  function bindCvDropZone(zone) {
    if (!zone || zone.dataset.bound) return
    zone.dataset.bound = '1'
    ;['dragenter', 'dragover'].forEach((ev) => {
      zone.addEventListener(ev, (e) => {
        e.preventDefault()
        zone.classList.add('is-dragover')
      })
    })
    ;['dragleave', 'drop'].forEach((ev) => {
      zone.addEventListener(ev, (e) => {
        e.preventDefault()
        zone.classList.remove('is-dragover')
      })
    })
    zone.addEventListener('drop', (e) => {
      const row = zone.closest('.team-editor-row')
      const fileInput = row?.querySelector('.team-cv-file')
      const file = e.dataTransfer?.files?.[0]
      if (file && fileInput) {
        const dt = new DataTransfer()
        dt.items.add(file)
        fileInput.files = dt.files
      }
    })
  }

  function bindTeamEditorEvents() {
    teamEditor?.querySelectorAll('.team-row-remove').forEach((btn) => {
      btn.addEventListener('click', () => {
        btn.closest('.team-editor-row')?.remove()
        updateSettingsBadges(collectTeamMembers(), collectTimesheet())
        if (!teamEditor.querySelector('.team-editor-row')) {
          renderTeamEditor([])
        }
      })
    })
    teamEditor?.querySelectorAll('.team-editor-row').forEach((row) => {
      row.querySelectorAll('.team-field-name, .team-field-initials, .team-field-role').forEach((input) => {
        input.addEventListener('input', () => {
          updateTeamRowAvatar(row)
          updateSettingsBadges(collectTeamMembers(), collectTimesheet())
        })
      })
      row.querySelector('.team-field-name')?.addEventListener('blur', () => {
        const initialsEl = row.querySelector('.team-field-initials')
        if (initialsEl && !initialsEl.value.trim()) {
          initialsEl.value = initialsFromName(row.querySelector('.team-field-name')?.value)
          updateTeamRowAvatar(row)
        }
      })
      row.querySelector('.team-cv-browse-btn')?.addEventListener('click', () => {
        row.querySelector('.team-cv-file')?.click()
      })
      row.querySelector('.team-photo-browse-btn')?.addEventListener('click', () => {
        row.querySelector('.team-photo-file')?.click()
      })
      row.querySelector('.team-cv-file')?.addEventListener('change', (e) => {
        const name = e.target.files?.[0]?.name
        if (name) {
          const statusText = row.querySelector('.team-cv-status-text')
          if (statusText) statusText.textContent = 'Ready to upload: ' + name
        }
      })
      row.querySelector('.team-photo-file')?.addEventListener('change', (e) => {
        const name = e.target.files?.[0]?.name
        if (name) {
          const statusText = row.querySelector('.team-photo-status-text')
          if (statusText) statusText.textContent = 'Ready to upload: ' + name
        }
      })
      bindPhotoDropZone(row.querySelector('[data-photo-drop]'))
      bindCvDropZone(row.querySelector('[data-cv-drop]'))
    })
    teamEditor?.querySelectorAll('.team-cv-upload-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const row = btn.closest('.team-editor-row')
        const fileInput = row?.querySelector('.team-cv-file')
        await uploadTeamCv(row, fileInput?.files?.[0], btn)
      })
    })
    teamEditor?.querySelectorAll('.team-photo-upload-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const row = btn.closest('.team-editor-row')
        const fileInput = row?.querySelector('.team-photo-file')
        await uploadTeamPhoto(row, fileInput?.files?.[0], btn)
      })
    })
  }

  function bindTimesheetEditorEvents() {
    timesheetEditor?.querySelectorAll('.timesheet-row-remove').forEach((btn) => {
      btn.addEventListener('click', () => {
        btn.closest('.timesheet-editor-row')?.remove()
        updateSettingsBadges(collectTeamMembers(), collectTimesheet())
        if (!timesheetEditor.querySelector('.timesheet-editor-row')) {
          renderTimesheetEditor([])
        }
      })
    })
    timesheetEditor?.querySelectorAll('.ts-field-week').forEach((input) => {
      input.addEventListener('input', () => updateSettingsBadges(collectTeamMembers(), collectTimesheet()))
    })
  }

  const summaryDropZone = document.getElementById('summary-drop-zone')
  const summaryFileInput = document.getElementById('sf-summary-sheet')
  if (summaryDropZone && summaryFileInput) {
    ;['dragenter', 'dragover'].forEach((ev) => {
      summaryDropZone.addEventListener(ev, (e) => {
        e.preventDefault()
        summaryDropZone.classList.add('is-dragover')
      })
    })
    ;['dragleave', 'drop'].forEach((ev) => {
      summaryDropZone.addEventListener(ev, (e) => {
        e.preventDefault()
        summaryDropZone.classList.remove('is-dragover')
      })
    })
    summaryDropZone.addEventListener('drop', (e) => {
      const file = e.dataTransfer?.files?.[0]
      if (file) {
        const dt = new DataTransfer()
        dt.items.add(file)
        summaryFileInput.files = dt.files
        const hint = document.getElementById('sf-summary-sheet-current')
        if (hint) {
          hint.textContent = 'Ready to upload: ' + file.name
          hint.classList.add('has-file')
        }
      }
    })
    summaryFileInput.addEventListener('change', () => {
      const hint = document.getElementById('sf-summary-sheet-current')
      const file = summaryFileInput.files?.[0]
      if (hint && file) {
        hint.textContent = 'Ready to upload: ' + file.name
        hint.classList.add('has-file')
      }
    })
  }

  document.getElementById('add-team-member')?.addEventListener('click', () => {
    const members = collectTeamMembers()
    members.push({ id: 'm' + Date.now(), initials: '', name: '', role: '', experienceYears: 0, group: '', cvFilePath: '', cvAsset: '', photoAsset: '' })
    renderTeamEditor(members)
    updateSettingsBadges(members, collectTimesheet())
    teamEditor?.lastElementChild?.querySelector('.team-field-name')?.focus()
  })

  document.getElementById('add-timesheet-row')?.addEventListener('click', () => {
    const rows = collectTimesheet()
    rows.push({ id: 't' + Date.now(), week: '', hours: '', phase: '', notes: '' })
    renderTimesheetEditor(rows)
    updateSettingsBadges(collectTeamMembers(), rows)
    timesheetEditor?.lastElementChild?.querySelector('.ts-field-week')?.focus()
  })

  async function saveProjectPayload(extra) {
    const id = settingsProject?.id || document.getElementById('settings-project-id')?.value
    if (!id) return false
    const payload = { action: 'save_project', id, ...extra }
    const res = await fetch(appPath('api.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    const json = await res.json()
    return json.ok
  }

  document.getElementById('save-team-btn')?.addEventListener('click', async () => {
    try {
      const ok = await saveProjectPayload({ cvMembers: collectTeamMembers() })
      if (ok) { toast('Team & CVs saved'); closeSettings(); location.reload() }
      else toast('Could not save team data', true)
    } catch {
      toast('Network error', true)
    }
  })

  document.getElementById('save-timesheet-btn')?.addEventListener('click', async () => {
    try {
      const ok = await saveProjectPayload({ timesheet: collectTimesheet() })
      if (ok) { toast('Timesheets saved'); closeSettings(); location.reload() }
      else toast('Could not save timesheets', true)
    } catch {
      toast('Network error', true)
    }
  })

  function updateSettingsImagePreview(previewId, url, label = 'No image uploaded') {
    const el = document.getElementById(previewId)
    if (!el) return
    if (url) {
      el.innerHTML = `<img src="${escapeAttr(url)}" alt="">`
      el.classList.add('has-image')
    } else {
      el.innerHTML = `<span class="settings-image-placeholder">${escapeHtml(label)}</span>`
      el.classList.remove('has-image')
    }
  }

  async function uploadProjectImage(type, file, { urlFieldId, statusId, metaKey, btn }) {
    const pid = document.getElementById('settings-project-id')?.value || settingsProject?.id
    if (!pid) {
      toast('Open a project first', true)
      return false
    }
    if (!file) {
      toast('Choose an image file first', true)
      return false
    }
    const fd = new FormData()
    fd.set('project_id', pid)
    fd.set('type', type)
    fd.set('redirect', '')
    fd.set('file', file)
    const status = statusId ? document.getElementById(statusId) : null
    if (btn) btn.disabled = true
    if (status) {
      status.hidden = false
      status.textContent = 'Uploading…'
    }
    try {
      const res = await fetch(appPath('upload.php'), {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: fd,
      })
      const json = await res.json().catch(() => null)
      if (!json?.ok || !json.url) {
        toast(json?.error || 'Upload failed', true)
        return false
      }
      const urlField = urlFieldId ? document.getElementById(urlFieldId) : null
      if (urlField) urlField.value = json.url
      if (settingsProject && metaKey) settingsProject[metaKey] = json.url
      if (window.__DTS_PROJECT__ && metaKey) window.__DTS_PROJECT__[metaKey] = json.url
      try {
        await saveProjectPayload({ [metaKey]: json.url })
      } catch {}
      toast('Image uploaded and saved.')
      return json.url
    } catch {
      toast('Upload failed', true)
      return false
    } finally {
      if (btn) btn.disabled = false
      if (status) status.hidden = true
    }
  }

  function bindSettingsImageBrowse(browseBtnId, fileId, statusId) {
    const browseBtn = document.getElementById(browseBtnId)
    const fileInput = document.getElementById(fileId)
    if (!browseBtn || !fileInput || browseBtn.dataset.bound) return
    browseBtn.dataset.bound = '1'
    browseBtn.addEventListener('click', () => fileInput.click())
    fileInput.addEventListener('change', () => {
      const name = fileInput.files?.[0]?.name
      if (!name || !statusId) return
      const status = document.getElementById(statusId)
      if (status) {
        status.hidden = false
        status.textContent = 'Selected: ' + name
      }
    })
  }

  function bindSettingsImageUpload(btnId, fileId, urlFieldId, statusId, type, metaKey, previewId, emptyLabel) {
    const btn = document.getElementById(btnId)
    if (!btn || btn.dataset.bound) return
    btn.dataset.bound = '1'
    btn.addEventListener('click', async () => {
      const file = document.getElementById(fileId)?.files?.[0]
      const url = await uploadProjectImage(type, file, { urlFieldId, statusId, metaKey, btn })
      if (url && previewId) updateSettingsImagePreview(previewId, url, emptyLabel)
      const fileInput = document.getElementById(fileId)
      if (fileInput) fileInput.value = ''
    })
  }

  bindSettingsImageBrowse('sf-client-logo-browse-btn', 'sf-client-logo-file', 'sf-client-logo-upload-status')
  bindSettingsImageBrowse('sf-logo-browse-btn', 'sf-logo-file', 'sf-logo-upload-status')
  bindSettingsImageBrowse('sf-map-browse-btn', 'sf-map-file', 'sf-map-upload-status')
  bindSettingsImageBrowse('sf-panorama-browse-btn', 'sf-panorama-file', 'sf-panorama-upload-status')
  bindSettingsImageUpload('sf-client-logo-upload-btn', 'sf-client-logo-file', 'sf-client-logo', 'sf-client-logo-upload-status', 'client_logo', 'clientLogoUrl', 'sf-client-logo-preview', 'No image uploaded')
  bindSettingsImageUpload('sf-logo-upload-btn', 'sf-logo-file', 'sf-logo', 'sf-logo-upload-status', 'sponsor_logo', 'sponsorLogoUrl', 'sf-logo-preview', 'No image uploaded')
  bindSettingsImageUpload('sf-map-upload-btn', 'sf-map-file', 'sf-map', 'sf-map-upload-status', 'location_map', 'locationMapUrl', 'sf-map-preview', 'No map uploaded')
  bindSettingsImageUpload('sf-panorama-upload-btn', 'sf-panorama-file', 'sf-panorama', 'sf-panorama-upload-status', 'panorama', 'panoramaUrl', 'sf-panorama-preview', 'No panorama uploaded')

  function openSettings(project) {
    if (!project || !overlay) return
    settingsProject = project
    document.getElementById('settings-project-id').value = project.id || ''
    const projectNameEl = document.getElementById('settings-project-name')
    if (projectNameEl) {
      projectNameEl.textContent = project.title || project.id || 'Untitled project'
    }
    const map = {
      title: project.title, subtitle: project.subtitle, status: project.status, progress: project.progress,
      introduction: project.introduction, executiveSummary: project.executiveSummary, client: project.client,
      sponsor: project.sponsor, budget: project.budget, budgetSource: project.budgetSource, pm: project.pm, consultants: project.consultants, location: project.location,
      startDate: project.startDate, closingDate: project.closingDate,
      sponsorLogoUrl: project.sponsorLogoUrl, clientLogoUrl: project.clientLogoUrl,
      locationMapUrl: project.locationMapUrl,
      panoramaUrl: project.panoramaUrl, disclaimer: project.disclaimer,
      projectSummarySheetName: project.projectSummarySheetName,
      gdriveFolderUrl: project.gdriveFolderUrl,
      localFolderPath: project.path,
    }
    const ids = {
      title: 'sf-title', subtitle: 'sf-subtitle', status: 'sf-status', progress: 'sf-progress',
      introduction: 'sf-intro', executiveSummary: 'sf-summary', client: 'sf-client', sponsor: 'sf-sponsor',
      budget: 'sf-budget', budgetSource: 'sf-budget-source', pm: 'sf-pm', consultants: 'sf-consultants', location: 'sf-location', startDate: 'sf-start',
      closingDate: 'sf-end', sponsorLogoUrl: 'sf-logo', clientLogoUrl: 'sf-client-logo',
      locationMapUrl: 'sf-map',
      panoramaUrl: 'sf-panorama', disclaimer: 'sf-disclaimer',
      gdriveFolderUrl: 'sf-gdrive-url',
      localFolderPath: 'sf-local-folder',
    }
    Object.entries(map).forEach(([k, v]) => {
      if (k === 'progress') return
      const el = document.getElementById(ids[k])
      if (el) el.value = v ?? ''
    })
    syncProgressUI(project.progress ?? 0)
    renderTeamEditor(project.cvMembers || [])
    renderTimesheetEditor(project.timesheet || [])
    updateSettingsBadges(project.cvMembers || [], project.timesheet || [])
    activateSettingsTab('project')
    const sheetHint = document.getElementById('sf-summary-sheet-current')
    const sheetInput = document.getElementById('sf-summary-sheet')
    const sheetProjectId = document.getElementById('sf-sheet-project-id')
    const sheetRedirect = document.getElementById('sf-sheet-redirect')
    if (sheetInput) sheetInput.value = ''
    if (sheetProjectId) sheetProjectId.value = project.id || ''
    if (sheetRedirect) {
      const params = new URLSearchParams(window.location.search)
      if (project.id) {
        if (params.get('project')) params.set('project', project.id)
        else params.set('pick', project.id)
      }
      params.set('tab', 'summary-sheet')
      sheetRedirect.value = window.location.pathname + '?' + params.toString()
    }
    document.getElementById('sf-client-logo-file') && (document.getElementById('sf-client-logo-file').value = '')
    document.getElementById('sf-logo-file') && (document.getElementById('sf-logo-file').value = '')
    document.getElementById('sf-map-file') && (document.getElementById('sf-map-file').value = '')
    document.getElementById('sf-panorama-file') && (document.getElementById('sf-panorama-file').value = '')
    updateSettingsImagePreview('sf-client-logo-preview', project.clientLogoUrl, 'No image uploaded')
    updateSettingsImagePreview('sf-logo-preview', project.sponsorLogoUrl, 'No image uploaded')
    updateSettingsImagePreview('sf-map-preview', project.locationMapUrl, 'No map uploaded')
    updateSettingsImagePreview('sf-panorama-preview', project.panoramaUrl, 'No panorama uploaded')
    if (sheetHint) {
      if (project.projectSummarySheetName) {
        sheetHint.textContent = '✓ Current file: ' + project.projectSummarySheetName
        sheetHint.classList.add('has-file')
      } else {
        sheetHint.textContent = 'No file attached yet'
        sheetHint.classList.remove('has-file')
      }
    }
    const deleteBtn = document.getElementById('delete-project-btn')
    if (deleteBtn) {
      deleteBtn.disabled = !project.canDelete
      deleteBtn.title = project.canDelete ? '' : 'Cannot delete the only remaining project'
    }
    const gdriveStatus = document.getElementById('sf-gdrive-status')
    if (gdriveStatus) {
      const url = (project.gdriveFolderUrl || '').trim()
      if (!url) {
        gdriveStatus.hidden = true
        gdriveStatus.textContent = ''
      } else if (project.gdriveScanOk) {
        gdriveStatus.hidden = false
        gdriveStatus.textContent = `✓ Connected — ${project.gdriveFilesCount || 0} files from Google Drive`
        gdriveStatus.className = 'muted settings-gdrive-status is-ok'
      } else {
        gdriveStatus.hidden = false
        gdriveStatus.textContent = '⚠ ' + (project.gdriveScanError || 'Could not sync Google Drive folder')
        gdriveStatus.className = 'muted settings-gdrive-status is-error'
      }
    }
    const localStatus = document.getElementById('sf-local-folder-status')
    if (localStatus) {
      const path = (project.path || '').trim()
      if (!path) {
        localStatus.hidden = true
        localStatus.textContent = ''
      } else if (project.localScanOk !== false) {
        localStatus.hidden = false
        localStatus.textContent = `✓ ${project.localFilesCount || 0} files in local folder`
        localStatus.className = 'muted settings-gdrive-status is-ok'
      } else {
        localStatus.hidden = false
        localStatus.textContent = '⚠ Local folder not found or not readable'
        localStatus.className = 'muted settings-gdrive-status is-error'
      }
    }
    overlay.hidden = false
  }

  function closeSettings() {
    if (overlay) overlay.hidden = true
  }

  openBtn?.addEventListener('click', () => {
    const data = window.__DTS_PROJECT__
    if (data) openSettings(data)
  })
  closeBtn?.addEventListener('click', closeSettings)
  cancelBtn?.addEventListener('click', closeSettings)
  overlay?.addEventListener('click', (e) => { if (e.target === overlay) closeSettings() })

  form?.addEventListener('submit', async (e) => {
    e.preventDefault()
    const fd = new FormData(form)
    const payload = Object.fromEntries(fd.entries())
    payload.action = 'save_project'
    payload.progress = Number(payload.progress) || 0
    try {
      const res = await fetch(appPath('api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      const json = await res.json()
      if (json.ok) { closeSettings(); location.reload() }
      else toast(json.error || 'Could not save settings', true)
    } catch {
      toast('Network error', true)
    }
  })

  document.getElementById('sf-gdrive-test-btn')?.addEventListener('click', async () => {
    const url = document.getElementById('sf-gdrive-url')?.value?.trim()
    const pid = document.getElementById('settings-project-id')?.value
    const statusEl = document.getElementById('sf-gdrive-status')
    const btn = document.getElementById('sf-gdrive-test-btn')
    if (!url) {
      toast('Enter a Google Drive folder link first', true)
      return
    }
    if (btn) btn.disabled = true
    if (statusEl) {
      statusEl.hidden = false
      statusEl.className = 'muted settings-gdrive-status'
      statusEl.textContent = 'Testing connection…'
    }
    try {
      const res = await fetch(appPath('api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'test_gdrive', url, id: pid }),
      })
      const json = await res.json()
      if (statusEl) {
        statusEl.hidden = false
        if (json.ok) {
          statusEl.className = 'muted settings-gdrive-status is-ok'
          statusEl.textContent = `✓ Found ${json.filesCount} files in ${json.foldersCount} folders (${json.folderName || 'Google Drive'})`
          toast('Google Drive connection successful')
        } else {
          statusEl.className = 'muted settings-gdrive-status is-error'
          statusEl.textContent = '⚠ ' + (json.error || 'Connection failed')
          toast(json.error || 'Google Drive connection failed', true)
        }
      }
    } catch {
      if (statusEl) {
        statusEl.hidden = false
        statusEl.className = 'muted settings-gdrive-status is-error'
        statusEl.textContent = '⚠ Network error'
      }
      toast('Network error testing Google Drive', true)
    } finally {
      if (btn) btn.disabled = false
    }
  })

  document.getElementById('sf-local-folder-test-btn')?.addEventListener('click', async () => {
    const path = document.getElementById('sf-local-folder')?.value?.trim()
    const statusEl = document.getElementById('sf-local-folder-status')
    const btn = document.getElementById('sf-local-folder-test-btn')
    if (!path) {
      toast('Enter a local folder path first', true)
      return
    }
    if (btn) btn.disabled = true
    if (statusEl) {
      statusEl.hidden = false
      statusEl.className = 'muted settings-gdrive-status'
      statusEl.textContent = 'Checking folder…'
    }
    try {
      const res = await fetch(appPath('api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'test_local_folder', path }),
      })
      const json = await res.json()
      if (statusEl) {
        statusEl.hidden = false
        if (json.ok) {
          statusEl.className = 'muted settings-gdrive-status is-ok'
          statusEl.textContent = `✓ Found ${json.filesCount} files in ${json.foldersCount} folders (${json.folderName || 'folder'})`
          toast('Local folder verified')
        } else {
          statusEl.className = 'muted settings-gdrive-status is-error'
          statusEl.textContent = '⚠ ' + (json.error || 'Folder not found')
          toast(json.error || 'Local folder check failed', true)
        }
      }
    } catch {
      if (statusEl) {
        statusEl.hidden = false
        statusEl.className = 'muted settings-gdrive-status is-error'
        statusEl.textContent = '⚠ Network error'
      }
      toast('Network error verifying folder', true)
    } finally {
      if (btn) btn.disabled = false
    }
  })

  document.getElementById('delete-project-btn')?.addEventListener('click', async () => {
    const id = settingsProject?.id || document.getElementById('settings-project-id')?.value
    const title = settingsProject?.title || id
    if (!id) return
    if (!settingsProject?.canDelete) {
      toast('Cannot delete the only remaining project', true)
      return
    }
    const typed = prompt(
      `Delete project "${title}"?\n\nThis removes it from the board and deletes uploaded CVs and summary sheets.\n\nType the project ID to confirm: ${id}`
    )
    if (typed !== id) {
      if (typed !== null) toast('Delete cancelled — project ID did not match', true)
      return
    }
    const btn = document.getElementById('delete-project-btn')
    if (btn) btn.disabled = true
    try {
      const res = await fetch(appPath('api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_project', id }),
      })
      const json = await res.json()
      if (json.ok) {
        toast('Project deleted')
        window.location.href = appPath(json.redirect || 'index.php')
      } else {
        toast(json.error || 'Could not delete project', true)
        if (btn) btn.disabled = false
      }
    } catch {
      toast('Network error', true)
      if (btn) btn.disabled = false
    }
  })

  document.querySelectorAll('[data-open-folder-picker]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const picker = document.getElementById('folder-picker-overlay')
      const input = document.getElementById('folder-path-input')
      const pid = document.getElementById('folder-project-id')
      if (!picker) return
      pid.value = btn.getAttribute('data-project-id') || ''
      input.value = btn.getAttribute('data-current-path') || ''
      picker.hidden = false
    })
  })

  document.getElementById('folder-picker-cancel')?.addEventListener('click', () => {
    const picker = document.getElementById('folder-picker-overlay')
    if (picker) picker.hidden = true
  })

  document.getElementById('folder-picker-form')?.addEventListener('submit', async (e) => {
    e.preventDefault()
    const pid = document.getElementById('folder-project-id')?.value
    const path = document.getElementById('folder-path-input')?.value?.trim()
    if (!pid || !path) return
    try {
      const res = await fetch(appPath('api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_folder', id: pid, path }),
      })
      const json = await res.json()
      if (json.ok) {
        document.getElementById('folder-picker-overlay').hidden = true
        location.reload()
      } else {
        toast(json.error || 'Could not save folder path', true)
      }
    } catch {
      toast('Network error saving folder', true)
    }
  })

  function slugifyProjectId(name) {
    return String(name || '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'project'
  }

  const addProjectOverlay = document.getElementById('add-project-overlay')
  const addProjectForm = document.getElementById('add-project-form')
  const apName = document.getElementById('ap-name')
  const apId = document.getElementById('ap-id')
  let apIdTouched = false

  function openAddProject() {
    if (!addProjectOverlay) return
    apIdTouched = false
    addProjectForm?.reset()
    if (apId) apId.placeholder = 'auto-generated'
    addProjectOverlay.hidden = false
    apName?.focus()
  }

  function closeAddProject() {
    if (addProjectOverlay) addProjectOverlay.hidden = true
  }

  document.getElementById('open-add-project')?.addEventListener('click', openAddProject)
  document.getElementById('add-project-card-trigger')?.addEventListener('click', openAddProject)
  document.getElementById('close-add-project')?.addEventListener('click', closeAddProject)
  document.getElementById('cancel-add-project')?.addEventListener('click', closeAddProject)
  addProjectOverlay?.addEventListener('click', (e) => {
    if (e.target === addProjectOverlay) closeAddProject()
  })

  apId?.addEventListener('input', () => { apIdTouched = true })
  apName?.addEventListener('input', () => {
    if (!apIdTouched && apId) {
      apId.value = slugifyProjectId(apName.value)
    }
  })

  addProjectForm?.addEventListener('submit', async (e) => {
    e.preventDefault()
    const name = apName?.value?.trim()
    const id = (apId?.value?.trim() || slugifyProjectId(name))
    const path = document.getElementById('ap-path')?.value?.trim() || ''
    const cloneFrom = document.getElementById('ap-clone')?.value || ''
    if (!name) {
      toast('Enter a project name', true)
      return
    }
    const submitBtn = document.getElementById('ap-submit')
    if (submitBtn) submitBtn.disabled = true
    try {
      const res = await fetch(appPath('api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create_project',
          name,
          id,
          path,
          clone_from: cloneFrom || undefined,
        }),
      })
      const json = await res.json()
      if (json.ok && json.id) {
        toast('Project created!')
        window.location.href = 'index.php?pick=' + encodeURIComponent(json.id)
      } else {
        toast(json.error || 'Could not create project', true)
      }
    } catch {
      toast('Network error', true)
    }
    if (submitBtn) submitBtn.disabled = false
  })

  if (document.body.classList.contains('page-workspace')) {
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual'

    const projectIdEarly = document.body.getAttribute('data-project-id')
      || new URLSearchParams(window.location.search).get('project')

    const wsParams = new URLSearchParams(window.location.search)
    const wsFolder = wsParams.get('folder') || ''
    if (projectIdEarly && wsFolder) {
      saveWorkspaceSource(projectIdEarly, folderToSource(wsFolder), wsFolder)
    }

    document.querySelectorAll('.ws-source-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        const href = tab.getAttribute('href') || ''
        try {
          const folder = new URL(href, window.location.origin).searchParams.get('folder') || ''
          if (folder) {
            saveWorkspaceSource(projectIdEarly, folderToSource(folder), folder)
          }
        } catch { /* ignore */ }
      })
    })

    document.querySelectorAll('a.nav-subfield-btn, a.nav-panel-btn, a.phase-btn').forEach((link) => {
      link.addEventListener('click', () => {
        saveWorkspaceNav(projectIdEarly)
        saveWorkspaceScroll(projectIdEarly)
        const href = link.getAttribute('href') || ''
        try {
          const folder = new URL(href, window.location.origin).searchParams.get('folder') || ''
          if (folder) saveWorkspaceSource(projectIdEarly, folderToSource(folder), folder)
        } catch { /* ignore */ }
      })
    })

    document.querySelectorAll('.ws-sidebar details[data-nav-id]').forEach((el) => {
      el.addEventListener('toggle', () => saveWorkspaceNav(projectIdEarly))
    })

    let scrollSaveTimer
    function queueScrollSave() {
      clearTimeout(scrollSaveTimer)
      scrollSaveTimer = setTimeout(() => saveWorkspaceScroll(projectIdEarly), 120)
    }
    window.addEventListener('scroll', queueScrollSave, { passive: true })
    document.querySelector('.files-table-wrap')?.addEventListener('scroll', queueScrollSave, { passive: true })
    window.addEventListener('beforeunload', () => {
      saveWorkspaceNav(projectIdEarly)
      saveWorkspaceScroll(projectIdEarly)
      const f = new URLSearchParams(window.location.search).get('folder') || ''
      if (f) saveWorkspaceSource(projectIdEarly, folderToSource(f), f)
    })
    window.addEventListener('pagehide', () => {
      saveWorkspaceNav(projectIdEarly)
      saveWorkspaceScroll(projectIdEarly)
      const f = new URLSearchParams(window.location.search).get('folder') || ''
      if (f) saveWorkspaceSource(projectIdEarly, folderToSource(f), f)
    })

    if (projectIdEarly) {
      restoreWorkspaceNav(projectIdEarly)
      restoreWorkspaceScroll(projectIdEarly)
      window.addEventListener('pageshow', () => {
        restoreWorkspaceNav(projectIdEarly)
        restoreWorkspaceScroll(projectIdEarly)
      })
    }

    const previewHost = document.getElementById('preview-iframe-host')
    const previewEmpty = document.getElementById('preview-empty')
    const previewPanel = document.querySelector('.preview-panel-body')
    const previewChrome = document.getElementById('doc-preview-chrome')
    const previewActiveFile = document.getElementById('preview-active-file')
    const previewZoomLabel = document.getElementById('preview-zoom-label')
    const previewDownload = document.getElementById('preview-download')
    const previewTools = document.getElementById('preview-tools')
    const projectId = document.body.getAttribute('data-project-id')
      || new URLSearchParams(window.location.search).get('project')

    function previewIframe() {
      return previewHost?.querySelector('iframe')
    }

    function viewerUrl(path) {
      if (path.startsWith('__asset__/')) {
        const file = path.slice('__asset__/'.length)
        return appPath('asset.php') + '?' + new URLSearchParams({ project: projectId, file }).toString()
      }
      return appPath('viewer.php') + '?' + new URLSearchParams({
        project: projectId,
        path,
        embed: '1',
      }).toString()
    }

    function sendZoom(action) {
      previewIframe()?.contentWindow?.postMessage({ type: 'dts-zoom', action }, '*')
    }

    function sendRotate(action) {
      previewIframe()?.contentWindow?.postMessage({ type: 'dts-rotate', action }, '*')
    }

    previewTools?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-zoom]')
      const rot = e.target.closest('[data-rotate]')
      if (!btn && !rot) return
      e.preventDefault()
      if (btn) sendZoom(btn.getAttribute('data-zoom'))
      if (rot) sendRotate(rot.getAttribute('data-rotate'))
    })

    window.addEventListener('message', (e) => {
      if (e.data?.type === 'dts-zoom-level' && previewZoomLabel) {
        previewZoomLabel.textContent = e.data.label || '100%'
      }
      if (e.data?.type === 'dts-preview-ready') {
        previewPanel?.classList.remove('is-loading')
        if (previewDownload && e.data.download) previewDownload.href = e.data.download
      }
    })

    const PREVIEW_MAX_BYTES = 100 * 1024 * 1024

    function previewTooLargeHtml(sizeLabel) {
      return (
        '<div class="preview-notice preview-notice-card preview-size-blocked">'
        + '<div class="preview-notice-icon">⚠️</div>'
        + '<p class="preview-notice-hint">Unable to preview due to larger file size.</p>'
        + (sizeLabel
          ? `<p class="preview-notice-hint">This file (${escapeHtmlText(sizeLabel)}) exceeds the 100 MB preview limit.</p>`
          : '<p class="preview-notice-hint">This file exceeds the 100 MB preview limit.</p>')
        + '</div>'
      )
    }

    function isPreviewTooLarge(sizeBytes) {
      const n = Number(sizeBytes)
      return Number.isFinite(n) && n > PREVIEW_MAX_BYTES
    }

    function showPreviewTooLarge(path, name, sizeLabel, meta = {}) {
      if (!previewHost || !projectId || !path) return
      const scrollY = window.scrollY

      document.querySelectorAll('.files-table tbody tr').forEach((tr) => {
        tr.classList.toggle('is-active', !meta.memberId && tr.getAttribute('data-path') === path)
      })
      document.querySelectorAll('#cv-table tbody tr').forEach((tr) => {
        const match = meta.memberId
          ? tr.getAttribute('data-member-id') === meta.memberId
          : tr.getAttribute('data-cv-path') === path
        tr.classList.toggle('is-active', !!match)
      })

      if (previewEmpty) {
        previewEmpty.classList.add('is-hidden')
        previewEmpty.hidden = true
        previewEmpty.style.display = 'none'
      }
      previewHost.classList.remove('is-hidden')
      previewHost.hidden = false
      previewHost.style.display = 'block'
      previewChrome?.classList.remove('is-hidden')
      if (previewChrome) {
        previewChrome.hidden = false
        previewChrome.style.display = ''
      }

      if (previewActiveFile) {
        previewActiveFile.textContent = shortDisplayName(name || '')
        previewActiveFile.title = name || ''
      }
      if (previewDownload) {
        if (path.startsWith('__asset__/')) {
          const file = path.slice('__asset__/'.length)
          previewDownload.href = appPath('asset.php') + '?' + new URLSearchParams({
            project: projectId,
            file,
            download: '1',
          }).toString()
        } else {
          previewDownload.href = appPath('file.php') + '?' + new URLSearchParams({
            project: projectId,
            path,
            download: '1',
          }).toString()
        }
      }
      if (previewZoomLabel) previewZoomLabel.textContent = '—'

      previewPanel?.classList.remove('is-loading')
      previewHost.querySelector('iframe')?.remove()
      previewHost.innerHTML = previewTooLargeHtml(sizeLabel)
      previewHost.setAttribute('data-path', path)
      previewHost.setAttribute('data-too-large', '1')

      const params = new URLSearchParams(window.location.search)
      if (meta.memberId) {
        params.set('cv', meta.memberId)
        params.delete('file')
      } else {
        params.set('file', path)
        params.delete('cv')
      }
      history.replaceState({}, '', window.location.pathname + '?' + params.toString())
      requestAnimationFrame(() => {
        window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' })
      })
      queueScrollSave()
    }

    function showPreview(path, name, forceReload, meta = {}) {
      if (!previewHost || !projectId || !path) return

      const sizeBytes = meta.sizeBytes != null
        ? meta.sizeBytes
        : (document.querySelector(`tr[data-path="${CSS.escape(path)}"]`)?.getAttribute('data-size-bytes')
          || document.querySelector(`tr[data-cv-path="${CSS.escape(path)}"]`)?.getAttribute('data-size-bytes')
          || '')
      const sizeLabel = meta.sizeLabel != null
        ? meta.sizeLabel
        : (document.querySelector(`tr[data-path="${CSS.escape(path)}"]`)?.getAttribute('data-size')
          || document.querySelector(`tr[data-cv-path="${CSS.escape(path)}"]`)?.getAttribute('data-size')
          || '')

      if (isPreviewTooLarge(sizeBytes)) {
        showPreviewTooLarge(path, name, sizeLabel, meta)
        return
      }

      previewHost.removeAttribute('data-too-large')
      const scrollY = window.scrollY

      document.querySelectorAll('.files-table tbody tr').forEach((tr) => {
        tr.classList.toggle('is-active', !meta.memberId && tr.getAttribute('data-path') === path)
      })
      document.querySelectorAll('#cv-table tbody tr').forEach((tr) => {
        const match = meta.memberId
          ? tr.getAttribute('data-member-id') === meta.memberId
          : tr.getAttribute('data-cv-path') === path
        tr.classList.toggle('is-active', !!match)
      })

      if (previewEmpty) {
        previewEmpty.classList.add('is-hidden')
        previewEmpty.hidden = true
        previewEmpty.style.display = 'none'
      }
      previewHost.classList.remove('is-hidden')
      previewHost.hidden = false
      previewHost.style.display = 'block'
      previewChrome?.classList.remove('is-hidden')
      if (previewChrome) {
        previewChrome.hidden = false
        previewChrome.style.display = ''
      }

      if (previewActiveFile) {
        previewActiveFile.textContent = shortDisplayName(name || '')
        previewActiveFile.title = name || ''
      }
      if (previewDownload) {
        previewDownload.href = appPath('file.php') + '?' + new URLSearchParams({
          project: projectId,
          path,
          download: '1',
        }).toString()
      }
      if (previewZoomLabel) previewZoomLabel.textContent = '…'

      let iframe = previewIframe()
      if (!iframe) {
        previewHost.innerHTML = ''
        iframe = document.createElement('iframe')
        iframe.title = 'Document preview'
        iframe.setAttribute('allow', 'fullscreen')
        previewHost.appendChild(iframe)
      }

      const nextSrc = viewerUrl(path)
      if (forceReload || iframe.getAttribute('data-path') !== path) {
        previewPanel?.classList.add('is-loading')
        iframe.onload = () => previewPanel?.classList.remove('is-loading')
        iframe.src = nextSrc
        iframe.setAttribute('data-path', path)
      }

      const params = new URLSearchParams(window.location.search)
      if (meta.memberId) {
        params.set('cv', meta.memberId)
        params.delete('file')
      } else {
        params.set('file', path)
        params.delete('cv')
      }
      history.replaceState({}, '', window.location.pathname + '?' + params.toString())
      requestAnimationFrame(() => {
        window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' })
      })
      queueScrollSave()
    }

    document.getElementById('files-table')?.addEventListener('click', (e) => {
      if (e.target.closest('.dl-btn')) {
        e.stopPropagation()
        return
      }
      const row = e.target.closest('tr[data-path]')
      if (!row) return
      e.preventDefault()
      showPreview(row.getAttribute('data-path'), row.getAttribute('data-name'), true, {
        sizeBytes: row.getAttribute('data-size-bytes'),
        sizeLabel: row.getAttribute('data-size'),
      })
    })

    document.getElementById('print-folder-list')?.addEventListener('click', (e) => {
      e.preventDefault()
      printFolderFileList()
    })

    document.getElementById('cv-table')?.addEventListener('click', (e) => {
      const row = e.target.closest('tr[data-member-id]')
      if (!row) return
      e.preventDefault()
      const path = row.getAttribute('data-cv-path')
      if (!path) {
        toast('No CV file found for this team member in the project folder', true)
        return
      }
      showPreview(path, row.getAttribute('data-name'), true, {
        memberId: row.getAttribute('data-member-id'),
        sizeBytes: row.getAttribute('data-size-bytes'),
        sizeLabel: row.getAttribute('data-size'),
      })
    })

    const initial = document.querySelector('.files-table tbody tr.is-active')
    if (initial) {
      const path = initial.getAttribute('data-path')
      const existing = previewIframe()
      if (existing && existing.getAttribute('data-path') === path) {
        if (previewEmpty) {
          previewEmpty.classList.add('is-hidden')
          previewEmpty.hidden = true
          previewEmpty.style.display = 'none'
        }
        previewHost.classList.remove('is-hidden')
        previewHost.hidden = false
        previewHost.style.display = 'block'
        previewChrome?.classList.remove('is-hidden')
      } else {
        showPreview(path, initial.getAttribute('data-name'), !existing, {
          sizeBytes: initial.getAttribute('data-size-bytes'),
          sizeLabel: initial.getAttribute('data-size'),
        })
      }
    }

    const initialCv = document.querySelector('#cv-table tbody tr.is-active')
    if (initialCv && !initial) {
      const path = initialCv.getAttribute('data-cv-path')
      const existing = previewIframe()
      if (path && existing && existing.getAttribute('data-path') === path) {
        if (previewEmpty) {
          previewEmpty.classList.add('is-hidden')
          previewEmpty.hidden = true
          previewEmpty.style.display = 'none'
        }
        previewHost.classList.remove('is-hidden')
        previewHost.hidden = false
        previewHost.style.display = 'block'
        previewChrome?.classList.remove('is-hidden')
      } else if (path) {
        showPreview(path, initialCv.getAttribute('data-name'), !existing, {
          memberId: initialCv.getAttribute('data-member-id'),
          sizeBytes: initialCv.getAttribute('data-size-bytes'),
          sizeLabel: initialCv.getAttribute('data-size'),
        })
      }
    }

    function formatSyncTime(ts) {
      if (!ts) return 'just now'
      const diff = Math.floor(Date.now() / 1000) - ts
      if (diff < 45) return 'just now'
      if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
      return new Date(ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }

    function setSyncDot(state) {
      document.querySelector('.workspace-sync .sync-dot')?.setAttribute('data-state', state)
    }

    function workspaceSourceFromUrl() {
      const folder = new URLSearchParams(location.search).get('folder') || ''
      return folder.startsWith('Google Drive') ? 'gdrive' : 'local'
    }

    function runWorkspaceAutoSync() {
      const signature = document.body.getAttribute('data-sync-signature')
      if (!projectIdEarly || !signature) return

      const source = workspaceSourceFromUrl()

      setSyncDot('syncing')
      fetch(appPath('sync.php') + '?project=' + encodeURIComponent(projectIdEarly) + '&source=' + encodeURIComponent(source), { cache: 'no-store' })
        .then((r) => r.json())
        .then((data) => {
          if (!data.ok) {
            setSyncDot('synced')
            return
          }
          const meta = document.getElementById('sync-meta-text')
          if (meta) {
            const sourceLabel = source === 'gdrive' ? 'Google Drive' : 'local folder'
            meta.textContent = `${data.filesCount} files · ${data.foldersCount} folders · ${sourceLabel} · synced ${formatSyncTime(data.syncedAt)}`
          }
          setSyncDot('synced')
          if (data.signature && data.signature !== signature) {
            saveWorkspaceNav(projectIdEarly)
            saveWorkspaceScroll(projectIdEarly)
            location.reload()
          }
        })
        .catch(() => setSyncDot('synced'))
    }

    runWorkspaceAutoSync()
    const syncIntervalMs = workspaceSourceFromUrl() === 'gdrive' ? 30000 : 60000
    setInterval(runWorkspaceAutoSync, syncIntervalMs)

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) runWorkspaceAutoSync()
    })
    window.addEventListener('focus', runWorkspaceAutoSync)
  }

  if (document.body.classList.contains('page-board')) {
    applySavedProjectOpenLinks()

    function runBoardAutoSync() {
      const signature = document.body.getAttribute('data-board-sync-signature')
      if (!signature) return

      fetch(appPath('sync.php') + '?board=1', { cache: 'no-store' })
        .then((r) => r.json())
        .then((data) => {
          if (data.ok && data.signature && data.signature !== signature) {
            location.reload()
          }
        })
        .catch(() => {})
    }

    runBoardAutoSync()
    setInterval(runBoardAutoSync, 60000)
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) runBoardAutoSync()
    })
    window.addEventListener('focus', runBoardAutoSync)
  }
})()
