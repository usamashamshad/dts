# Document Tracking System (DTS)

A **PHP / XAMPP** web application for managing engineering and infrastructure projects — centralising reports, drawings, maps, team CVs, and metadata in one place. Built for LAN teams and optional cloud deployment.

**Developed by:** Knowledge & Innovation Cell

**Repository:** [github.com/usamashamshad/dts](https://github.com/usamashamshad/dts)

---

## About

The Document Tracking System (DTS) helps project teams **organise, browse, and preview** large collections of project documents without a database. It scans folders on disk (or Google Drive) and presents them in a modern workspace with executive summaries, folder navigation, inline previews, and team management.

DTS is designed for:

- **Consulting & engineering firms** tracking deliverables across phases  
- **Infrastructure projects** (dams, water, transport, energy) with thousands of files  
- **Office LAN setups** where everyone on the network opens the same site  
- **Hybrid storage** — local project folder **and** a linked Google Drive folder  

Tagline: *Navigational Data · Data Integration · Accountability · Accessibility · Processed Data*

---

## Features

### Home board
- Light-theme landing page with project hero and product credit
- **Project cards** — status, progress, folder/file counts, client & location
- **Detail panel** — tabs for Summary, Cost, Client, Dates, Team, Location
- Search across projects, clients, and locations
- Add / hide projects from the board
- Auto-sync when folders change on disk

### Workspace
- **Executive summary** accordion with introduction and full summary
- **Project phases** — Initiation, Planning, Design, Construction, Closeout
- **Sidebar navigation** — folder tree with depth indentation
- **Local + Google Drive tabs** — switch sources; local browsing does not sync Drive in the background
- **File list** — sortable table with type badges and file counts
- **Inline preview** — PDF, images, text, DOCX, Excel, PPTX, TIFF, CAD hints
- **Full-screen viewer** — zoom, fit, download for large or unsupported files
- Search files within the current folder
- Scroll position and folder source remembered per project (browser storage)

### Team & project data
- **Settings drawer** — project title, subtitle, status, progress slider
- Introduction & executive summary (rich text areas)
- Client, sponsor, budget, PM, consultants, location, dates
- Upload **client logo**, **sponsor logo**, **location map**, **panorama**, **project summary sheet**
- **Team members** — roles, experience, groups, CV upload, profile photos
- **CV panel** — browse and preview team CVs in the workspace
- **Timesheet editor** — weekly effort tracking per team member
- **Timeline panel** — project schedule view

### File sources
| Source | Description |
|--------|-------------|
| **Local folder** | Fast disk scan — `storage/Data` or any path in `config.php` |
| **Google Drive** | Public-folder sync via Drive API; cached listings; subfolder tree with clear indentation |

### Other
- **Light / dark theme** toggle (persisted)
- **LAN access** — `http://YOUR_IP/dts` from any PC on the network
- **Docker** ready — `Dockerfile` + `docker-compose.yml` for cloud deploy
- **No database** — metadata in JSON; documents on disk or Drive

---

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.2+ (7.4+ via `compat.php`) |
| Server | Apache (XAMPP) or Docker `php:8.2-apache` |
| Frontend | Vanilla JavaScript, CSS |
| Data | `data/projects.json`, folder scan, optional Google Drive API |
| Deploy | XAMPP, Docker, Render, Railway, VPS, shared PHP hosting |

---

## Quick start (XAMPP)

1. **Clone** into your web root:
   ```bash
   git clone https://github.com/usamashamshad/dts.git
   cd dts
   ```

2. **Copy templates** (not in git — each install has its own data):
   ```bash
   copy config.php.example config.php
   copy data\projects.json.example data\projects.json
   ```

3. **Add project files** to `storage/Data/` (create subfolders as needed).

4. **Start Apache** in XAMPP and open:
   - Local: `http://localhost/dts`
   - LAN: `http://YOUR_PC_IP/dts`

---

## Configuration

### `config.php`
Copy from `config.php.example`. Main settings:

| Setting | Purpose |
|---------|---------|
| `site_title`, `site_subtitle`, `site_tagline` | Board hero text |
| `product_credit_*` | Credit line on home page |
| `gdrive_api_key` | Google Drive API key (leave empty for local-only) |
| `gdrive_cache_ttl` | Seconds to cache Drive folder listings |
| `projects[]` | `id`, `name`, `path` per project |

Example project path:
```php
'path' => 'storage/Data',           // relative to app folder
'path' => 'D:/Projects/MySite',     // or absolute on Windows
```

### Google Drive (optional)

1. Enable **Google Drive API** in [Google Cloud Console](https://console.cloud.google.com/)
2. Create an **API key** and paste it in `config.php` → `gdrive_api_key`
3. Share your Drive folder as **Anyone with the link can view**
4. In DTS **Settings → Data sources**, paste the folder URL
5. Open the **Google Drive** tab in the workspace sidebar

---

## What is not in git

These stay on each machine / server (see `.gitignore`):

| Path | Contents |
|------|----------|
| `config.php` | API keys and local paths |
| `data/projects.json` | Project metadata, team, summaries |
| `data/uploads/` | Logos, CVs, photos uploaded via UI |
| `data/gdrive-cache/` | Drive listing cache |
| `storage/Data/` | Your actual project documents |
| `assets/` | Project images (panoramas, etc.) |

---

## Project structure

```
dts/
├── index.php              # Board + workspace router
├── lib.php                # Core: scan, metadata, navigation
├── lib/gdrive.php         # Google Drive sync
├── api.php                # Settings save API
├── sync.php               # Background folder sync
├── file.php / preview.php / viewer.php
├── app.js / preview.js    # UI logic
├── style.css / fixes.css  # Styles
├── config.php.example     # → copy to config.php
├── data/
│   ├── projects.json.example
│   ├── folders.json
│   └── uploads/           # runtime uploads
├── storage/Data/          # put project files here
├── includes/              # PHP templates
├── Dockerfile
└── docker-compose.yml
```

---

## Cloud deployment

DTS can run on **Render**, **Railway**, **Oracle Cloud VM**, **VPS**, or **shared PHP hosting**.

```bash
# Test with Docker locally
docker compose up --build
# → http://localhost:8080
```

**Important for cloud:**
- Copy `config.php` and `data/projects.json` on the server
- Use a **persistent disk** for `data/` and `storage/` (free tiers may wipe files on restart)
- Or rely on **Google Drive** for documents and keep only metadata on the server

See `CLOUD_DEPLOYMENT.txt` in your local copy for a full step-by-step guide (not shipped in git).

---

## Add more projects

In `config.php`:
```php
['id' => 'site-b', 'name' => 'Site B', 'path' => 'storage/SiteB'],
```

Add a matching entry in `data/projects.json` or use **Settings** after first open.

---

## Suggested GitHub topics

`php` `xampp` `document-management` `project-management` `google-drive` `file-browser` `pdf-viewer` `engineering` `infrastructure` `lan` `docker`

---

## License

All rights reserved — contact the repository owner for use outside your organisation.
