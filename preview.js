const cfg = window.__PREVIEW__
const stage = document.getElementById('preview-stage')
const loading = document.getElementById('viewer-loading')
const zoomControls = document.getElementById('zoom-controls')
const zoomLabel = document.getElementById('zoom-label')

let zoom = 1
let zoomViewport = null
let zoomInner = null
let cadViewer = null
let fullCadDoc = null
let cadDxfRaw = ''
let cadLayouts = []
let activeCadLayout = 'model'
const supportsCssZoom = typeof CSS !== 'undefined' && CSS.supports('zoom', '1')

function hideLoading() {
  loading?.classList.add('is-hidden')
}

function showError(msg) {
  hideLoading()
  setZoomControls(false)
  stage.innerHTML = `
    <div class="viewer-notice">
      <div class="viewer-notice-icon">⚠️</div>
      <p class="viewer-notice-title">Preview unavailable</p>
      <p class="viewer-notice-hint">${escapeHtml(msg)}</p>
    </div>`
}

function escapeHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function setZoomControls(show) {
  if (zoomControls) zoomControls.style.display = show ? '' : 'none'
}

function updateZoomLabel() {
  const label = `${Math.round(zoom * 100)}%`
  const el = document.getElementById('zoom-label')
  if (el) el.textContent = label
  if (cfg?.embed && window.parent !== window) {
    window.parent.postMessage({ type: 'dts-zoom-level', zoom, label }, '*')
  }
}

function normalizeWideContent(root) {
  if (!root?.querySelectorAll) return
  root.querySelectorAll('table').forEach((table) => {
    table.style.width = 'auto'
    table.style.maxWidth = 'none'
    table.removeAttribute('width')
    table.querySelectorAll('colgroup col').forEach((col) => col.removeAttribute('width'))
    table.querySelectorAll('td, th').forEach((cell) => {
      cell.style.whiteSpace = 'nowrap'
      if (cell.hasAttribute('width')) cell.removeAttribute('width')
    })
  })
}

function applyZoom() {
  if (!zoomInner) return
  updateZoomLabel()
  if (supportsCssZoom) {
    zoomInner.style.zoom = String(zoom)
    zoomInner.style.transform = ''
    zoomInner.style.width = ''
    zoomInner.style.height = ''
  } else {
    zoomInner.style.zoom = ''
    zoomInner.style.transform = `scale(${zoom})`
    zoomInner.style.transformOrigin = 'top left'
    const w = Math.max(zoomInner.scrollWidth, zoomInner.offsetWidth)
    const h = Math.max(zoomInner.scrollHeight, zoomInner.offsetHeight)
    zoomInner.style.width = `${w}px`
    zoomInner.style.height = `${h}px`
  }
}

function resetZoomForMeasure() {
  zoom = 1
  if (!zoomInner) return
  if (supportsCssZoom) {
    zoomInner.style.zoom = '1'
    zoomInner.style.transform = ''
    zoomInner.style.width = ''
    zoomInner.style.height = ''
  } else {
    zoomInner.style.zoom = ''
    zoomInner.style.transform = ''
    zoomInner.style.width = ''
    zoomInner.style.height = ''
  }
}

function fitToViewport() {
  if (!zoomInner || !zoomViewport) return

  resetZoomForMeasure()

  const pad = 24
  const vw = Math.max(80, zoomViewport.clientWidth - pad)
  const vh = Math.max(80, zoomViewport.clientHeight - pad)
  const cw = Math.max(1, zoomInner.scrollWidth, zoomInner.offsetWidth)
  const ch = Math.max(1, zoomInner.scrollHeight, zoomInner.offsetHeight)
  const isTallDeck = Boolean(zoomInner.querySelector('.viewer-pdf-pages, .viewer-pptx-deck, .viewer-docx-html, .viewer-excel-shell'))

  let fitZoom = vw / cw
  if (!isTallDeck) {
    fitZoom = Math.min(fitZoom, vh / ch)
  }
  if (fitZoom > 1) fitZoom = 1

  zoom = Math.min(4, Math.max(0.12, +fitZoom.toFixed(2)))
  applyZoom()
  zoomViewport.scrollTop = 0
  zoomViewport.scrollLeft = 0
  updateZoomLabel()
}

function scheduleFitToViewport() {
  const run = () => {
    if (!zoomInner || !zoomViewport) return
    if (zoomViewport.clientWidth < 40 || zoomViewport.clientHeight < 40) return
    normalizeWideContent(zoomInner)
    fitToViewport()
  }
  run()
  requestAnimationFrame(run)
  setTimeout(run, 0)
  setTimeout(run, 80)
  setTimeout(run, 200)
}

function refreshScrollSize() {
  if (!zoomInner) return
  normalizeWideContent(zoomInner)
  applyZoom()
}

function handleZoomAction(action) {
  if (cadViewer) {
    const vt = cadViewer.getViewTransform?.()
    const current = vt?.scale ?? 1
    if (action === 'fit') {
      cadViewer.fitToView()
      zoom = 1
    } else if (action === 'in') {
      cadViewer.zoomTo(current * 1.25)
      zoom = current * 1.25
    } else if (action === 'out') {
      cadViewer.zoomTo(current / 1.25)
      zoom = current / 1.25
    }
    updateZoomLabel()
    return
  }
  if (action === 'in') adjustZoom(0.15)
  else if (action === 'out') adjustZoom(-0.15)
  else if (action === 'fit') {
    scheduleFitToViewport()
  }
}

function adjustZoom(delta) {
  zoom = Math.min(4, Math.max(0.2, +(zoom + delta).toFixed(2)))
  applyZoom()
}

function bindZoomViewport(viewport) {
  zoomViewport = viewport
  viewport.addEventListener('wheel', (e) => {
    if (e.ctrlKey || e.metaKey) {
      e.preventDefault()
      adjustZoom(e.deltaY < 0 ? 0.1 : -0.1)
      return
    }
    if (e.shiftKey && viewport.scrollWidth > viewport.clientWidth) {
      e.preventDefault()
      viewport.scrollLeft += e.deltaY
    }
  }, { passive: false })
}

function mountZoomable(child, className = '') {
  normalizeWideContent(child)

  const shell = document.createElement('div')
  shell.className = 'viewer-zoom-shell'
  const viewport = document.createElement('div')
  viewport.className = 'viewer-zoom-viewport'
  const inner = document.createElement('div')
  inner.className = 'viewer-zoom-inner' + (className ? ` ${className}` : '')
  inner.appendChild(child)
  viewport.appendChild(inner)
  shell.appendChild(viewport)
  stage.appendChild(shell)

  zoomInner = inner
  zoom = 1
  setZoomControls(true)
  bindZoomViewport(viewport)
  zoomViewport = viewport

  const runReady = () => {
    refreshScrollSize()
    hideLoading()
    requestAnimationFrame(() => {
      refreshScrollSize()
      scheduleFitToViewport()
    })
    setTimeout(scheduleFitToViewport, 250)
  }

  if (child.tagName === 'IMG') {
    if (child.complete) runReady()
    else child.addEventListener('load', runReady, { once: true })
  } else {
    runReady()
  }

  return { shell, viewport, inner, remeasure: refreshScrollSize, fit: scheduleFitToViewport }
}

document.getElementById('viewer-toolbar')?.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-zoom]')
  if (!btn) return
  handleZoomAction(btn.getAttribute('data-zoom'))
})

window.addEventListener('message', (e) => {
  if (e.data?.type !== 'dts-zoom') return
  handleZoomAction(e.data.action)
})

let pdfDoc = null
let pdfPagesEl = null
let pdfRotation = 0
let pdfRenderToken = 0

async function renderPdf() {
  const pdfjs = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.8.69/build/pdf.mjs')
  pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.8.69/build/pdf.worker.mjs'

  const buf = await fetch(cfg.src).then((r) => {
    if (!r.ok) throw new Error('Could not fetch PDF.')
    return r.arrayBuffer()
  })

  try {
    pdfDoc = await pdfjs.getDocument({ data: buf }).promise
  } catch (err) {
    // pdf.js throws specialized errors, but their class names vary by build.
    const name = err?.name || ''
    if (/Password/i.test(name)) {
      throw new Error('This PDF is password-protected and cannot be previewed in the browser. Please download and open it in a PDF reader.')
    }
    if (/InvalidPDF|FormatError|MissingPDF/i.test(name)) {
      throw new Error('This PDF file appears to be corrupted or in an unsupported format. Please download and open it in a PDF reader.')
    }
    throw err
  }

  pdfRotation = 0
  pdfPagesEl = document.createElement('div')
  pdfPagesEl.className = 'viewer-pdf-pages'

  function renderNotice(text) {
    const note = document.createElement('div')
    note.className = 'viewer-notice'
    note.innerHTML = `
      <div class="viewer-notice-icon">📄</div>
      <p class="viewer-notice-title">Large PDF</p>
      <p class="viewer-notice-hint">${escapeHtml(text)}</p>`
    stage.prepend(note)
  }

  function computeSafeScale(pageViewportAt1) {
    // Keep canvas sizes within a safe bound to avoid crashes on huge scanned PDFs.
    const dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1))
    const maxCanvasPx = 6_000_000 // ~6MP per page (safe-ish for browsers)
    const baseScale = 1.15

    // Scale down if page is huge.
    const areaAtBase = (pageViewportAt1.width * baseScale * dpr) * (pageViewportAt1.height * baseScale * dpr)
    if (areaAtBase <= maxCanvasPx) return baseScale

    const k = Math.sqrt(maxCanvasPx / Math.max(1, pageViewportAt1.width * pageViewportAt1.height)) / dpr
    return Math.max(0.5, Math.min(baseScale, +k.toFixed(2)))
  }

  async function renderPages(token) {
    if (!pdfDoc || !pdfPagesEl) return
    pdfPagesEl.innerHTML = ''

    const total = pdfDoc.numPages
    if (total > 80) {
      renderNotice(`Rendering the first 80 pages for performance. Download the file for the full document.`)
    }
    const maxPages = Math.min(total, 80)

    // Render a few pages immediately, then progressively render the rest.
    const immediate = Math.min(3, maxPages)

    const renderOne = async (i) => {
      if (token !== pdfRenderToken) return
      const page = await pdfDoc.getPage(i)
      const v1 = page.getViewport({ scale: 1, rotation: pdfRotation })
      const scale = computeSafeScale(v1)
      const viewport = page.getViewport({ scale, rotation: pdfRotation })
      const canvas = document.createElement('canvas')
      canvas.className = 'viewer-pdf-page-canvas'
      canvas.width = Math.floor(viewport.width)
      canvas.height = Math.floor(viewport.height)
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise
      const pageWrap = document.createElement('div')
      pageWrap.className = 'viewer-pdf-page'
      pageWrap.appendChild(canvas)
      pdfPagesEl.appendChild(pageWrap)
    }

    for (let i = 1; i <= immediate; i++) {
      await renderOne(i)
    }

    const tail = async (start) => {
      for (let i = start; i <= maxPages; i++) {
        if (token !== pdfRenderToken) return
        // Yield to the UI thread so the browser doesn't freeze on huge PDFs.
        // eslint-disable-next-line no-await-in-loop
        await new Promise((r) => setTimeout(r, 0))
        // eslint-disable-next-line no-await-in-loop
        await renderOne(i)
      }
    }
    tail(immediate + 1).catch((err) => console.error(err))
  }

  pdfRenderToken++
  const token = pdfRenderToken
  await renderPages(token)

  // Allow rotate in both embedded + standalone viewer.
  window.addEventListener('message', (e) => {
    if (e.data?.type !== 'dts-rotate') return
    if (cfg?.mode !== 'pdf') return
    pdfRotation = (pdfRotation + 90) % 360
    pdfRenderToken++
    const t = pdfRenderToken
    renderPages(t).then(() => {
      refreshScrollSize()
      scheduleFitToViewport()
    }).catch((err) => console.error(err))
  })

  document.getElementById('viewer-toolbar')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-rotate]')
    if (!btn || cfg?.mode !== 'pdf') return
    pdfRotation = (pdfRotation + 90) % 360
    pdfRenderToken++
    const t = pdfRenderToken
    renderPages(t).then(() => {
      refreshScrollSize()
      scheduleFitToViewport()
    }).catch((err) => console.error(err))
  })

  mountZoomable(pdfPagesEl, 'viewer-pdf-wrap')
}

async function renderImage() {
  const img = document.createElement('img')
  img.className = 'viewer-image'
  img.alt = cfg.name
  img.src = cfg.src
  img.onerror = () => showError('Could not load image.')
  mountZoomable(img, 'viewer-image-wrap')
}

async function loadModule(url) {
  const mod = await import(url)
  return mod.default ?? mod
}

async function renderTiff() {
  const UTIF = await loadModule('https://cdn.jsdelivr.net/npm/utif@3.1.0/+esm')
  const buf = await fetch(cfg.src).then((r) => {
    if (!r.ok) throw new Error('Could not fetch file.')
    return r.arrayBuffer()
  })
  const ifds = UTIF.decode(buf)
  if (!ifds.length) throw new Error('Invalid TIFF file.')
  UTIF.decodeImage(buf, ifds[0])
  const rgba = UTIF.toRGBA8(ifds[0])
  const canvas = document.createElement('canvas')
  canvas.width = ifds[0].width
  canvas.height = ifds[0].height
  const ctx = canvas.getContext('2d')
  const imageData = ctx.createImageData(canvas.width, canvas.height)
  imageData.data.set(rgba)
  ctx.putImageData(imageData, 0, 0)
  canvas.className = 'viewer-image'
  mountZoomable(canvas, 'viewer-image-wrap')
}

async function renderText() {
  const res = await fetch(cfg.src)
  if (!res.ok) throw new Error('Could not fetch file.')
  const text = await res.text()
  const pre = document.createElement('pre')
  pre.className = 'viewer-text'
  pre.textContent = text.length > 800000 ? text.slice(0, 800000) + '\n\n… (truncated)' : text
  mountZoomable(pre, 'viewer-text-wrap')
}

async function renderDocx() {
  const mammoth = await loadModule('https://cdn.jsdelivr.net/npm/mammoth@1.12.0/+esm')
  const buf = await fetch(cfg.src).then((r) => {
    if (!r.ok) throw new Error('Could not fetch document.')
    return r.arrayBuffer()
  })
  const result = await mammoth.convertToHtml({ arrayBuffer: buf })
  const doc = document.createElement('div')
  doc.className = 'viewer-docx-html'
  doc.innerHTML = result.value
  normalizeWideContent(doc)
  mountZoomable(doc, 'viewer-office-wrap')
}

async function renderExcel() {
  const XLSX = await import('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/+esm')
  const buf = await fetch(cfg.src).then((r) => {
    if (!r.ok) throw new Error('Could not fetch spreadsheet.')
    return r.arrayBuffer()
  })
  const wb = XLSX.read(buf, { type: 'array' })
  const shell = document.createElement('div')
  shell.className = 'viewer-excel-shell'
  const tabs = document.createElement('div')
  tabs.className = 'viewer-sheet-tabs'
  const tableHost = document.createElement('div')
  tableHost.className = 'viewer-sheet-table'

  let zoomApi = null

  function showSheet(name) {
    const ws = wb.Sheets[name]
    tableHost.innerHTML = XLSX.utils.sheet_to_html(ws, { id: 'sheet-table', editable: false })
    normalizeWideContent(tableHost)
    tabs.querySelectorAll('.viewer-sheet-tab').forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.sheet === name)
    })
    zoomApi?.remeasure()
    zoomApi?.fit?.()
  }

  wb.SheetNames.forEach((name, i) => {
    const btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'viewer-sheet-tab' + (i === 0 ? ' is-active' : '')
    btn.textContent = name
    btn.dataset.sheet = name
    btn.addEventListener('click', () => showSheet(name))
    tabs.appendChild(btn)
  })
  shell.append(tabs, tableHost)
  zoomApi = mountZoomable(shell, 'viewer-office-wrap')
  showSheet(wb.SheetNames[0])
}

async function getOrderedSlidePaths(zip) {
  const relsFile = zip.file('ppt/_rels/presentation.xml.rels')
  const presFile = zip.file('ppt/presentation.xml')
  if (relsFile && presFile) {
    const relsXml = await relsFile.async('string')
    const presXml = await presFile.async('string')
    const relMap = {}
    const relRe = /Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"|Relationship[^>]*Target="([^"]+)"[^>]*Id="([^"]+)"/g
    let m
    while ((m = relRe.exec(relsXml)) !== null) {
      const id = m[1] || m[4]
      const target = m[2] || m[3]
      if (!id || !target || !/slides\/slide/i.test(target)) continue
      const path = target.startsWith('ppt/') ? target : `ppt/${target.replace(/^\.\.\//, '')}`
      relMap[id] = path.replace(/\\/g, '/')
    }
    const ordered = []
    const sldRe = /<p:sldId[^>]*r:id="([^"]+)"/g
    while ((m = sldRe.exec(presXml)) !== null) {
      const path = relMap[m[1]]
      if (path && zip.file(path)) ordered.push(path)
    }
    if (ordered.length) return ordered
  }

  return Object.keys(zip.files)
    .filter((p) => /^ppt\/slides\/slide\d+\.xml$/i.test(p))
    .sort((a, b) => {
      const na = parseInt(a.match(/slide(\d+)/i)?.[1] ?? '0', 10)
      const nb = parseInt(b.match(/slide(\d+)/i)?.[1] ?? '0', 10)
      return na - nb
    })
}

async function loadSlideImages(zip, slidePath, slideXml) {
  const relPath = slidePath.replace('slides/', 'slides/_rels/').replace('.xml', '.xml.rels')
  const relMap = {}
  const relsFile = zip.file(relPath)
  if (relsFile) {
    const relsXml = await relsFile.async('string')
    const relRe = /Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"|Relationship[^>]*Target="([^"]+)"[^>]*Id="([^"]+)"/g
    let rm
    while ((rm = relRe.exec(relsXml)) !== null) {
      const id = rm[1] || rm[4]
      const target = rm[2] || rm[3]
      if (!id || !target) continue
      relMap[id] = target.replace(/^\.\.\//, '')
    }
  }

  const imageUrls = []
  const seen = new Set()
  const embedRe = /r:embed="([^"]+)"/g
  let em
  while ((em = embedRe.exec(slideXml)) !== null) {
    const target = relMap[em[1]]
    if (!target) continue
    const mediaPath = target.startsWith('ppt/') ? target : `ppt/${target}`
    if (seen.has(mediaPath)) continue
    seen.add(mediaPath)
    const file = zip.file(mediaPath)
    if (file) {
      const blob = await file.async('blob')
      imageUrls.push(URL.createObjectURL(blob))
    }
  }
  return imageUrls
}

async function loadPptxSlides(url) {
  const JSZip = (await import('https://cdn.jsdelivr.net/npm/jszip@3.10.1/+esm')).default
  const buf = await fetch(url).then((r) => {
    if (!r.ok) throw new Error('Could not fetch presentation.')
    return r.arrayBuffer()
  })
  const zip = await JSZip.loadAsync(buf)
  const slidePaths = await getOrderedSlidePaths(zip)

  const slides = []
  for (let i = 0; i < slidePaths.length; i++) {
    const slidePath = slidePaths[i]
    const xml = await zip.file(slidePath).async('string')
    const texts = []
    const re = /<a:t[^>]*>([^<]*)<\/a:t>/g
    let m
    while ((m = re.exec(xml)) !== null) {
      const t = m[1].trim()
      if (t) texts.push(t)
    }
    const imageUrls = await loadSlideImages(zip, slidePath, xml)
    slides.push({
      index: i,
      title: texts[0] ?? `Slide ${i + 1}`,
      bullets: texts.slice(1),
      imageUrls,
    })
  }
  return slides
}

async function renderPptx() {
  const slides = await loadPptxSlides(cfg.src)
  if (!slides.length) throw new Error('No slides found in this presentation.')

  const deck = document.createElement('div')
  deck.className = 'viewer-pptx-deck'

  slides.forEach((s, idx) => {
    const slideEl = document.createElement('article')
    slideEl.className = 'viewer-pptx-slide'
    slideEl.setAttribute('aria-label', `Slide ${idx + 1} of ${slides.length}`)
    slideEl.innerHTML = `
      <div class="viewer-pptx-slide-badge">${idx + 1} / ${slides.length}</div>
      <div class="viewer-pptx-frame">
        <h2 class="viewer-pptx-title">${escapeHtml(s.title)}</h2>
        ${s.bullets.length ? `<ul class="viewer-pptx-bullets">${s.bullets.map((b) => `<li>${escapeHtml(b)}</li>`).join('')}</ul>` : ''}
        ${s.imageUrls.length ? `<div class="viewer-pptx-images">${s.imageUrls.map((u) => `<img src="${u}" alt="">`).join('')}</div>` : ''}
      </div>`
    deck.appendChild(slideEl)
  })

  mountZoomable(deck, 'viewer-office-wrap')
}

function showCadDownloadNotice(hint) {
  hideLoading()
  setZoomControls(false)
  if (cadViewer) {
    try { cadViewer.destroy() } catch { /* ignore */ }
    cadViewer = null
  }
  const dl = cfg?.download
    ? `<a class="viewer-btn viewer-btn-primary viewer-cad-dl-btn" href="${escapeHtml(cfg.download)}" download>Download file</a>`
    : ''
  stage.innerHTML = `
    <div class="viewer-notice">
      <div class="viewer-notice-icon">📐</div>
      <p class="viewer-notice-title">Preview not available for this drawing</p>
      <p class="viewer-notice-hint">${escapeHtml(hint || 'This DWG/DXF file could not be displayed in the browser.')}</p>
      <p class="viewer-notice-hint">Download the file and open it in AutoCAD, DWG FastView, or another CAD application.</p>
      ${dl}
    </div>`
  notifyParent()
}

function cadViewIsDrawable(doc, computeEntitiesBounds) {
  if (!doc?.entities?.length) return false
  try {
    const bounds = computeEntitiesBounds(doc.entities, doc)
    if (!bounds) return false
    const span = Math.max(bounds.maxX - bounds.minX, bounds.maxY - bounds.minY)
    return Number.isFinite(span) && span > 1e-6
  } catch {
    return false
  }
}

function findDrawableCadLayout(doc, layouts, dxfRaw, buildCadViewDocument, computeEntitiesBounds) {
  for (const layout of layouts) {
    const viewDoc = buildCadViewDocument(doc, layout.id, dxfRaw)
    if (cadViewIsDrawable(viewDoc, computeEntitiesBounds)) return layout.id
  }
  return null
}

function setCadLayoutEmptyNotice(shell, show) {
  let notice = shell.querySelector('.viewer-cad-empty-notice')
  if (!show) {
    notice?.remove()
    return
  }
  if (!notice) {
    notice = document.createElement('div')
    notice.className = 'viewer-cad-empty-notice'
    notice.innerHTML = `
      <div class="viewer-cad-empty-card">
        <p><strong>Nothing to show in this layout</strong></p>
        <p>Try another layout from the View menu, or download the file to open in CAD software.</p>
        ${cfg?.download ? `<a class="viewer-btn viewer-btn-primary" href="${escapeHtml(cfg.download)}" download>Download file</a>` : ''}
      </div>`
    shell.appendChild(notice)
  }
}

function buildCadLayoutToolbar(layouts, activeId, onChange) {
  const bar = document.createElement('div')
  bar.className = 'viewer-cad-toolbar'
  const label = document.createElement('span')
  label.className = 'viewer-cad-toolbar-label'
  label.textContent = 'View'
  const select = document.createElement('select')
  select.className = 'viewer-cad-layout-select'
  select.setAttribute('aria-label', 'Model or paper layout')

  const modelLayouts = layouts.filter((l) => l.kind === 'model')
  const paperLayouts = layouts.filter((l) => l.kind === 'layout')

  const addGroup = (title, items) => {
    if (!items.length) return
    const group = document.createElement('optgroup')
    group.label = title
    for (const layout of items) {
      const opt = document.createElement('option')
      opt.value = layout.id
      opt.textContent = layout.label
      opt.selected = layout.id === activeId
      group.appendChild(opt)
    }
    select.appendChild(group)
  }

  addGroup('Model space', modelLayouts)
  addGroup('Paper layouts', paperLayouts)

  if (!select.options.length) {
    for (const layout of layouts) {
      const opt = document.createElement('option')
      opt.value = layout.id
      opt.textContent = layout.label
      opt.selected = layout.id === activeId
      select.appendChild(opt)
    }
  }

  select.addEventListener('change', () => onChange(select.value))
  bar.append(label, select)
  return bar
}

async function switchCadLayout(layoutId) {
  if (!cadViewer || !fullCadDoc) return
  const { buildCadViewDocument } = await import('./cad-layouts.js')
  const { computeEntitiesBounds } = await import('https://cdn.jsdelivr.net/npm/@cadview/core@0.5.0/+esm')
  activeCadLayout = layoutId
  const viewDoc = buildCadViewDocument(fullCadDoc, layoutId, cadDxfRaw)
  cadViewer.loadDocument(viewDoc)
  cadViewer.fitToView()
  zoom = 1
  updateZoomLabel()
  const shell = stage.querySelector('.viewer-cad-shell')
  if (shell) {
    setCadLayoutEmptyNotice(shell, !cadViewIsDrawable(viewDoc, computeEntitiesBounds))
  }
}

async function renderCad() {
  const { CadViewer, parseDxf, computeEntitiesBounds } = await import('https://cdn.jsdelivr.net/npm/@cadview/core@0.5.0/+esm')
  const { dwgConverter, convertDwgToDxf } = await import('https://cdn.jsdelivr.net/npm/@cadview/dwg@0.2.0/+esm')
  const { discoverPaperLayouts, buildCadViewDocument, cadDrawingHasAnyEntities } = await import('./cad-layouts.js')

  const buf = await fetch(cfg.src).then((r) => {
    if (!r.ok) throw new Error('Could not fetch drawing.')
    return r.arrayBuffer()
  })

  try {
    if (dwgConverter.detect(buf)) {
      cadDxfRaw = await convertDwgToDxf(buf, { timeout: 120000 })
      fullCadDoc = parseDxf(cadDxfRaw)
    } else {
      cadDxfRaw = new TextDecoder('utf-8', { fatal: false }).decode(buf)
      fullCadDoc = parseDxf(buf)
    }
  } catch (err) {
    console.error(err)
    showCadDownloadNotice('The drawing could not be converted for browser preview.')
    return
  }

  if (!cadDrawingHasAnyEntities(fullCadDoc)) {
    showCadDownloadNotice('No drawable geometry was found after conversion.')
    return
  }

  cadLayouts = discoverPaperLayouts(fullCadDoc, cadDxfRaw)
  const drawableLayoutId = findDrawableCadLayout(
    fullCadDoc,
    cadLayouts,
    cadDxfRaw,
    buildCadViewDocument,
    computeEntitiesBounds,
  )

  if (!drawableLayoutId) {
    showCadDownloadNotice('This drawing uses features not supported by the browser preview.')
    return
  }

  activeCadLayout = drawableLayoutId

  const wrap = document.createElement('div')
  wrap.className = 'viewer-cad-wrap'
  const shell = document.createElement('div')
  shell.className = 'viewer-cad-shell'
  const canvas = document.createElement('canvas')
  canvas.className = 'viewer-cad-canvas'
  shell.appendChild(canvas)
  wrap.appendChild(shell)
  stage.appendChild(wrap)

  cadViewer = new CadViewer(canvas, {
    theme: 'dark',
    formatConverters: [dwgConverter],
  })

  if (cadLayouts.length > 1) {
    const toolbar = buildCadLayoutToolbar(cadLayouts, activeCadLayout, switchCadLayout)
    wrap.insertBefore(toolbar, shell)
  }

  const viewDoc = buildCadViewDocument(fullCadDoc, activeCadLayout, cadDxfRaw)
  cadViewer.loadDocument(viewDoc)
  try { cadViewer.setTool?.('pan') } catch { /* optional */ }

  const resize = () => {
    const rect = shell.getBoundingClientRect()
    const w = Math.max(300, Math.floor(rect.width))
    const h = Math.max(300, Math.floor(rect.height))
    if (w > 0 && h > 0) {
      canvas.width = w
      canvas.height = h
      cadViewer.fitToView()
    }
  }
  resize()
  const ro = new ResizeObserver(() => resize())
  ro.observe(shell)
  cadViewer.fitToView()
  setCadLayoutEmptyNotice(shell, !cadViewIsDrawable(viewDoc, computeEntitiesBounds))
  appendDwgPreviewDisclaimer(wrap)
  zoom = 1
  hideLoading()
  setZoomControls(true)
  updateZoomLabel()
}

function appendDwgPreviewDisclaimer(wrap) {
  if (!/\.dwg$/i.test(cfg?.name || '')) return
  const note = document.createElement('p')
  note.className = 'viewer-cad-disclaimer'
  note.textContent = "Preview of the 'DWG' format files is subject to depiction of the AutoCad Model Space and doesn't show sheet formats under template control."
  wrap.appendChild(note)
}

function renderLegacyOffice() {
  hideLoading()
  setZoomControls(false)
  stage.innerHTML = `
    <div class="viewer-notice">
      <div class="viewer-notice-icon">📄</div>
      <p class="viewer-notice-title">Legacy Office format</p>
      <p class="viewer-notice-hint">Preview works for <strong>.docx</strong>, <strong>.xlsx</strong>, and <strong>.pptx</strong>. Download to open this file.</p>
    </div>`
}

function renderBinary() {
  hideLoading()
  setZoomControls(false)
  stage.innerHTML = `
    <div class="viewer-notice">
      <div class="viewer-notice-icon">📦</div>
      <p class="viewer-notice-title">${escapeHtml(cfg.name)}</p>
      <p class="viewer-notice-hint">No preview for this type. Use Download.</p>
    </div>`
}

function notifyParent() {
  if (!cfg?.embed || window.parent === window) return
  window.parent.postMessage({
    type: 'dts-preview-ready',
    download: cfg.download,
    name: cfg.name,
    zoomable: Boolean(zoomInner || cadViewer),
  }, '*')
}

async function main() {
  if (!cfg || !stage) return
  if (cfg.tooLarge) {
    showError(`Unable to preview due to larger file size. This file (${cfg.sizeLabel || 'unknown size'}) exceeds the ${cfg.maxSizeLabel || '100 MB'} preview limit.`)
    return
  }
  try {
    switch (cfg.mode) {
      case 'pdf': await renderPdf(); break
      case 'image': await renderImage(); break
      case 'tiff': await renderTiff(); break
      case 'text': await renderText(); break
      case 'docx': await renderDocx(); break
      case 'excel': await renderExcel(); break
      case 'pptx': await renderPptx(); break
      case 'cad': await renderCad(); break
      case 'office-legacy': renderLegacyOffice(); break
      default: renderBinary()
    }
  } catch (err) {
    console.error(err)
    if (cfg.mode === 'cad') {
      showCadDownloadNotice(err?.message || 'The drawing could not be loaded for browser preview.')
    } else {
      showError(err?.message || 'Preview failed.')
    }
  } finally {
    notifyParent()
  }
}

main()
