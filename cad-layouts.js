/** Paper-space layout discovery and viewport expansion for @cadview/core */

function num(v) {
  const n = parseFloat(v)
  return Number.isFinite(n) ? n : 0
}

function walkDxf(dxf, visitor) {
  const lines = String(dxf || '').split(/\r?\n/)
  let section = null
  for (let i = 0; i < lines.length - 1; i++) {
    const codeStr = lines[i].trim()
    const value = lines[i + 1] ?? ''
    if (codeStr === '0' && value.trim() === 'SECTION') {
      section = null
      let j = i + 2
      while (j < lines.length - 1) {
        const c = lines[j].trim()
        if (c === '2') {
          section = (lines[j + 1] || '').trim()
          break
        }
        if (c === '0') break
        j += 2
      }
      i += 2
      continue
    }
    if (codeStr === '0' && value.trim() === 'ENDSEC') {
      section = null
      i += 2
      continue
    }
    const code = parseInt(codeStr, 10)
    if (!Number.isNaN(code)) visitor(code, value, section)
    i += 2
  }
}

function parseBlockRecordMap(dxf) {
  const map = new Map()
  let inTable = false
  let cur = null
  walkDxf(dxf, (code, value, section) => {
    if (section !== 'TABLES') return
    if (code === 0 && value.trim() === 'TABLE') inTable = false
    if (code === 0 && value.trim() === 'BLOCK_RECORD') {
      inTable = true
      cur = { handle: '', name: '' }
      return
    }
    if (!inTable || !cur) return
    if (code === 0) {
      if (cur.handle && cur.name) map.set(cur.handle.toUpperCase(), cur.name)
      cur = null
      inTable = false
      return
    }
    if (code === 5) cur.handle = value.trim().toUpperCase()
    if (code === 2) cur.name = value.trim()
  })
  if (cur?.handle && cur.name) map.set(cur.handle.toUpperCase(), cur.name)
  return map
}

function parseLayoutObjects(dxf) {
  const layouts = []
  let cur = null
  walkDxf(dxf, (code, value, section) => {
    if (section !== 'OBJECTS') return
    if (code === 0) {
      const type = value.trim()
      if (type === 'LAYOUT') {
        if (cur?.name) layouts.push(cur)
        cur = { name: '', blockHandle: '' }
        return
      }
      if (cur?.name) layouts.push(cur)
      cur = null
      return
    }
    if (!cur) return
    if (code === 1) cur.name = value.trim()
    if (code === 330) cur.blockHandle = value.trim().toUpperCase()
  })
  if (cur?.name) layouts.push(cur)
  return layouts
}

function extractTabLayoutNames(dxf) {
  const names = []
  let inTab = false
  walkDxf(dxf, (code, value, section) => {
    if (section !== 'TABLES') return
    if (code === 0 && value.trim() === 'TABRECORD') {
      inTab = true
      return
    }
    if (code === 0) {
      inTab = false
      return
    }
    if (inTab && code === 2) {
      const name = value.trim()
      if (name && name.toLowerCase() !== 'model') names.push(name)
    }
  })
  return [...new Set(names)]
}

export function parseEntitiesInBlock(dxf, blockName) {
  const target = String(blockName || '').toLowerCase()
  const entities = []
  let inBlock = false
  let cur = null
  let pendingBlock = false

  walkDxf(dxf, (code, value, section) => {
    if (section !== 'BLOCKS') return

    if (code === 0) {
      const type = value.trim()
      if (type === 'BLOCK') {
        if (cur && inBlock) entities.push(cur)
        cur = null
        inBlock = false
        pendingBlock = true
        return
      }
      if (type === 'ENDBLK') {
        if (cur && inBlock) entities.push(cur)
        cur = null
        inBlock = false
        pendingBlock = false
        return
      }
      if (pendingBlock) return
      if (inBlock) {
        if (cur) entities.push(cur)
        cur = { type, props: {} }
      }
      return
    }

    if (pendingBlock && code === 2) {
      inBlock = value.trim().toLowerCase() === target
      pendingBlock = false
      return
    }

    if (cur && inBlock) cur.props[code] = value.trim()
  })

  if (cur && inBlock) entities.push(cur)
  return entities
}

function viewportFromRaw(ent) {
  const p = ent.props || {}
  const width = num(p[40])
  const height = num(p[41])
  const viewHeight = num(p[45])
  return {
    centerX: num(p[10]),
    centerY: num(p[20]),
    width,
    height,
    viewCenterX: num(p[12]),
    viewCenterY: num(p[22]),
    viewTargetX: num(p[17]),
    viewTargetY: num(p[27]),
    viewHeight: viewHeight > 0 ? viewHeight : 1,
    viewportId: num(p[69]),
    status: num(p[90]),
  }
}

function modelViewCenter(vp) {
  return {
    x: vp.viewCenterX + vp.viewTargetX,
    y: vp.viewCenterY + vp.viewTargetY,
  }
}

function viewportScale(vp) {
  return vp.height / vp.viewHeight
}

function transformPoint(px, py, vp) {
  const scale = viewportScale(vp)
  const vc = modelViewCenter(vp)
  return {
    x: vp.centerX + (px - vc.x) * scale,
    y: vp.centerY + (py - vc.y) * scale,
  }
}

function cloneEntity(ent) {
  return JSON.parse(JSON.stringify(ent))
}

function transformEntity(ent, vp, doc) {
  if (!ent || !vp?.viewHeight) return null
  const scale = viewportScale(vp)

  if (ent.type === 'LINE') {
    const out = cloneEntity(ent)
    out.start = { ...ent.start, ...transformPoint(ent.start.x, ent.start.y, vp), z: ent.start.z }
    out.end = { ...ent.end, ...transformPoint(ent.end.x, ent.end.y, vp), z: ent.end.z }
    return out
  }

  if (ent.type === 'CIRCLE' || ent.type === 'ARC') {
    const out = cloneEntity(ent)
    const c = transformPoint(ent.center.x, ent.center.y, vp)
    out.center = { ...ent.center, ...c, z: ent.center.z }
    out.radius = ent.radius * scale
    return out
  }

  if (ent.type === 'LWPOLYLINE' || ent.type === 'POLYLINE') {
    const out = cloneEntity(ent)
    out.vertices = (ent.vertices || []).map((v) => {
      const p = transformPoint(v.x, v.y, vp)
      return { ...v, x: p.x, y: p.y }
    })
    return out
  }

  if (ent.type === 'POINT') {
    const out = cloneEntity(ent)
    const p = transformPoint(ent.position.x, ent.position.y, vp)
    out.position = { ...ent.position, ...p, z: ent.position.z }
    return out
  }

  if (ent.type === 'TEXT' || ent.type === 'MTEXT') {
    const out = cloneEntity(ent)
    const p = transformPoint(ent.insertionPoint.x, ent.insertionPoint.y, vp)
    out.insertionPoint = { ...ent.insertionPoint, ...p, z: ent.insertionPoint.z }
    if (out.height) out.height = out.height * scale
    return out
  }

  if (ent.type === 'INSERT') {
    const out = cloneEntity(ent)
    const p = transformPoint(ent.insertionPoint.x, ent.insertionPoint.y, vp)
    out.insertionPoint = { ...ent.insertionPoint, ...p, z: ent.insertionPoint.z }
    out.scaleX = (ent.scaleX || 1) * scale
    out.scaleY = (ent.scaleY || 1) * scale
    return out
  }

  if (ent.type === 'ELLIPSE') {
    const out = cloneEntity(ent)
    const c = transformPoint(ent.center.x, ent.center.y, vp)
    out.center = { ...ent.center, ...c, z: ent.center.z }
    out.majorAxis = {
      x: ent.majorAxis.x * scale,
      y: ent.majorAxis.y * scale,
      z: ent.majorAxis.z,
    }
    return out
  }

  return null
}

function entityBBox(ent) {
  if (!ent) return null
  if (ent.type === 'LINE') {
    return {
      minX: Math.min(ent.start.x, ent.end.x),
      minY: Math.min(ent.start.y, ent.end.y),
      maxX: Math.max(ent.start.x, ent.end.x),
      maxY: Math.max(ent.start.y, ent.end.y),
    }
  }
  if (ent.type === 'CIRCLE' || ent.type === 'ARC') {
    const r = ent.radius || 0
    return {
      minX: ent.center.x - r,
      minY: ent.center.y - r,
      maxX: ent.center.x + r,
      maxY: ent.center.y + r,
    }
  }
  if (ent.type === 'LWPOLYLINE' || ent.type === 'POLYLINE') {
    const xs = (ent.vertices || []).map((v) => v.x)
    const ys = (ent.vertices || []).map((v) => v.y)
    if (!xs.length) return null
    return { minX: Math.min(...xs), minY: Math.min(...ys), maxX: Math.max(...xs), maxY: Math.max(...ys) }
  }
  if (ent.type === 'POINT') {
    return { minX: ent.position.x, minY: ent.position.y, maxX: ent.position.x, maxY: ent.position.y }
  }
  if (ent.type === 'INSERT') {
    return { minX: ent.insertionPoint.x, minY: ent.insertionPoint.y, maxX: ent.insertionPoint.x, maxY: ent.insertionPoint.y }
  }
  return null
}

function entityInViewport(ent, vp) {
  const bb = entityBBox(ent)
  if (!bb) return true
  const scale = viewportScale(vp)
  const vc = modelViewCenter(vp)
  const halfW = (vp.width / scale) / 2
  const halfH = (vp.height / scale) / 2
  return !(
    bb.maxX < vc.x - halfW
    || bb.minX > vc.x + halfW
    || bb.maxY < vc.y - halfH
    || bb.minY > vc.y + halfH
  )
}

function getModelEntities(doc) {
  if (doc?.entities?.length) return doc.entities
  const blocks = doc?.blocks
  if (!blocks) return []
  return blocks.get('*Model_Space')?.entities
    || blocks.get('*MODEL_SPACE')?.entities
    || []
}

function resolvePaperLayouts(dxfRaw, doc) {
  const blockMap = parseBlockRecordMap(dxfRaw)
  const results = []
  const seen = new Set()

  const add = (name, blockName) => {
    if (!name || !blockName) return
    if (name.toLowerCase() === 'model') return
    if (/^\*Model_Space/i.test(blockName)) return
    const key = blockName.toLowerCase()
    if (seen.has(key)) return
    seen.add(key)
    results.push({ name, blockName })
  }

  for (const lo of parseLayoutObjects(dxfRaw)) {
    const blockName = blockMap.get(lo.blockHandle) || lo.name
    add(lo.name, blockName)
  }

  for (const tab of extractTabLayoutNames(dxfRaw)) {
    if (doc?.blocks?.has(tab)) add(tab, tab)
    else {
      for (const [bname] of doc?.blocks || []) {
        if (bname.toLowerCase() === tab.toLowerCase()) add(tab, bname)
      }
    }
  }

  for (const [bname] of doc?.blocks || []) {
    if (/^\*Paper_Space/i.test(bname) && bname !== '*Paper_Space') {
      const label = bname.replace(/^\*Paper_Space/i, 'Layout ')
      add(label, bname)
    }
  }

  return results
}

export function discoverPaperLayouts(doc, dxfRaw) {
  const layouts = [{ id: 'model', label: 'Model', kind: 'model' }]
  for (const item of resolvePaperLayouts(dxfRaw, doc)) {
    layouts.push({
      id: item.blockName,
      label: item.name,
      kind: 'layout',
      blockName: item.blockName,
    })
  }
  return layouts
}

export function cadDrawingHasAnyEntities(doc) {
  if (!doc) return false
  if (getModelEntities(doc).length > 0) return true
  for (const [, block] of doc.blocks || []) {
    if (block?.entities?.length > 0) return true
  }
  return false
}

export function buildCadViewDocument(doc, layoutId, dxfRaw) {
  if (!doc || !layoutId || layoutId === 'model') {
    return { ...doc, entities: [...getModelEntities(doc)] }
  }

  const block = doc.blocks?.get(layoutId)
  const paperEntities = block?.entities?.length ? [...block.entities] : []
  const rawInBlock = parseEntitiesInBlock(dxfRaw, layoutId)
  const viewports = rawInBlock
    .filter((e) => e.type === 'VIEWPORT')
    .map(viewportFromRaw)
    .filter((vp) => vp.width > 1 && vp.height > 1 && vp.viewportId !== 1)

  const modelEntities = getModelEntities(doc)
  const combined = [...paperEntities]

  for (const vp of viewports) {
    for (const ent of modelEntities) {
      if (!entityInViewport(ent, vp)) continue
      const transformed = transformEntity(ent, vp, doc)
      if (transformed) combined.push(transformed)
    }
  }

  if (!combined.length && block?.entities?.length) {
    return { ...doc, entities: [...block.entities] }
  }

  return { ...doc, entities: combined }
}
