# Document Tracking System — PHP / XAMPP

Full **same UI** as the React app: light home page, executive summary panel, project cards, workspace with folders, file preview, team/timesheet/timeline panels, and Settings.

## Quick start

1. Clone this repo to `C:\xampp\htdocs\dts` (or your web root)
2. Copy config and data templates:
   ```bash
   copy config.php.example config.php
   copy data\projects.json.example data\projects.json
   ```
3. Put project files in **`storage/Data`** (or set another path in **`config.php`**)
4. Optional — Google Drive sync: add your API key in `config.php` and a folder URL in Settings
5. Start **Apache** in XAMPP
6. Open `http://localhost/dts` (LAN: `http://YOUR_IP/dts`)

> **Note:** `config.php`, `data/projects.json`, `storage/Data/`, uploads, and Drive cache are **not** in git — each install keeps its own data.

## Files

| File | Purpose |
|------|---------|
| `config.php.example` | Copy to `config.php` — paths, Drive API key |
| `data/projects.json.example` | Copy to `data/projects.json` — project metadata |
| `lib.php` | Scan folders, load/save metadata |
| `lib/gdrive.php` | Google Drive folder sync |
| `index.php` | Board + workspace |
| `file.php` | Secure file preview/download |
| `api.php` | Save settings |
| `sync.php` | Background folder sync |
| `style.css` / `fixes.css` | UI styles |
| `app.js` | Tabs, theme, accordion, settings |
| `includes/` | Page templates |

## Features

- **Home (light theme)** — hero, project detail panel with tabs (Summary, Cost, Client, Dates, Team, Location)
- **Project cards** — folders/files count, open project
- **Workspace** — executive summary accordion, sidebar panels, folder tree, phases
- **Local + Google Drive** — separate tabs; local browsing does not sync Drive in the background
- **File preview** — PDF, images, text; download for Office/DWG
- **Settings** — edit project metadata (saved to `data/projects.json`)
- **Theme toggle** — light / dark
- **LAN** — any PC on the network opens the same site and files

## Add projects

In `config.php`:

```php
['id' => 'site-b', 'name' => 'Site B', 'path' => 'storage/SiteB'],
```

Add matching entry in `data/projects.json` or use Settings after first open.
