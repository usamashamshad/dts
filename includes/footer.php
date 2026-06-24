<div class="folder-picker-overlay" id="folder-picker-overlay" hidden>
  <div class="folder-picker-card">
    <h3>📁 Select project folder</h3>
    <p>Enter the path to your project folder. Use <code>storage/Data</code> (inside this website folder) when moving to another server.</p>
    <form id="folder-picker-form">
      <input type="hidden" id="folder-project-id" name="id" value="">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:6px">Folder path</label>
      <input type="text" id="folder-path-input" name="path" placeholder="e.g. storage/Data" required>
      <div class="folder-picker-actions">
        <button type="submit" class="primary-btn">Link folder</button>
        <button type="button" class="ghost-btn" id="folder-picker-cancel">Cancel</button>
      </div>
    </form>
    <p style="margin-top:12px;font-size:11px;color:var(--text-dim)">Examples: <code>storage/Data</code> · <code>D:/Projects/MySite</code> (absolute path)</p>
  </div>
</div>

<div class="settings-overlay" id="settings-overlay" hidden>
  <div class="settings-drawer" role="dialog" aria-labelledby="settings-title">
    <div class="settings-drawer-glow" aria-hidden="true"></div>

    <header class="settings-header">
      <div class="settings-header-main">
        <div class="settings-header-icon" aria-hidden="true">⚙️</div>
        <div>
          <h2 id="settings-title">Project Settings</h2>
          <p class="settings-project-name" id="settings-project-name">Select a project</p>
        </div>
      </div>
      <button type="button" class="settings-close-btn" id="close-settings" aria-label="Close settings">✕</button>
    </header>

    <nav class="settings-tabs" aria-label="Settings sections">
      <button type="button" class="settings-tab is-active" data-settings-tab="project">
        <span class="settings-tab-icon">📋</span>
        <span class="settings-tab-label">Details</span>
      </button>
      <button type="button" class="settings-tab" data-settings-tab="team">
        <span class="settings-tab-icon">👥</span>
        <span class="settings-tab-label">Team &amp; CVs</span>
        <span class="settings-tab-badge" id="settings-team-count">0</span>
      </button>
      <button type="button" class="settings-tab" data-settings-tab="timesheet">
        <span class="settings-tab-icon">⏱️</span>
        <span class="settings-tab-label">Timesheets</span>
        <span class="settings-tab-badge" id="settings-ts-count">0</span>
      </button>
      <button type="button" class="settings-tab" data-settings-tab="files">
        <span class="settings-tab-icon">📄</span>
        <span class="settings-tab-label">Summary</span>
      </button>
    </nav>

    <div class="settings-body">
      <!-- Project details -->
      <div class="settings-pane is-active" data-settings-pane="project">
        <div class="settings-pane-hero">
          <span class="settings-pane-hero-icon">📋</span>
          <div>
            <h3 class="settings-pane-title">Project details</h3>
            <p class="settings-pane-desc">Core information shown on the board and workspace.</p>
          </div>
        </div>
        <form id="settings-form" class="settings-form">
          <input type="hidden" name="id" id="settings-project-id" value="">

          <div class="settings-section">
            <h4 class="settings-section-title">Basic info</h4>
            <div class="form-grid settings-form-grid">
              <label class="sf-field"><span class="sf-label">Project title</span><input name="title" id="sf-title" placeholder="Project name"></label>
              <label class="sf-field"><span class="sf-label">Subtitle</span><input name="subtitle" id="sf-subtitle" placeholder="Short tagline"></label>
              <label class="sf-field"><span class="sf-label">Status</span>
                <select name="status" id="sf-status" class="sf-select">
                  <option>Active</option><option>In Review</option><option>Archived</option>
                </select>
              </label>
              <label class="sf-field sf-field-progress">
                <span class="sf-label">Progress <span id="sf-progress-val" class="sf-progress-val">0%</span></span>
                <div class="sf-progress-wrap">
                  <input type="range" id="sf-progress-range" min="0" max="100" value="0" class="sf-progress-range">
                  <input type="number" name="progress" id="sf-progress" min="0" max="100" class="sf-progress-num">
                </div>
                <div class="sf-progress-bar"><div class="sf-progress-fill" id="sf-progress-fill"></div></div>
              </label>
            </div>
          </div>

          <div class="settings-section">
            <h4 class="settings-section-title">Summary</h4>
            <div class="form-grid settings-form-grid">
              <label class="sf-field sf-field-full"><span class="sf-label">Introduction</span><textarea name="introduction" id="sf-intro" rows="2" placeholder="Brief intro…"></textarea></label>
              <label class="sf-field sf-field-full"><span class="sf-label">Executive Summary</span><textarea name="executiveSummary" id="sf-summary" rows="4" placeholder="Full executive summary…"></textarea></label>
            </div>
          </div>

          <div class="settings-section">
            <h4 class="settings-section-title">Client &amp; budget</h4>
            <input type="hidden" name="sponsorLogoUrl" id="sf-logo">
            <input type="hidden" name="clientLogoUrl" id="sf-client-logo">
            <div class="settings-party-grid">
              <div class="settings-party-block settings-image-upload-card">
                <label class="sf-field sf-field-full">
                  <span class="sf-label">Client</span>
                  <input name="client" id="sf-client" placeholder="Client name">
                </label>
                <div class="settings-image-preview" id="sf-client-logo-preview"><span class="settings-image-placeholder">No image uploaded</span></div>
                <div class="settings-image-upload-row">
                  <input type="file" id="sf-client-logo-file" class="settings-image-file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                  <button type="button" class="soft-btn" id="sf-client-logo-browse-btn">Browse</button>
                  <button type="button" class="primary-btn" id="sf-client-logo-upload-btn">Upload</button>
                  <span class="muted settings-image-status" id="sf-client-logo-upload-status" hidden></span>
                </div>
              </div>
              <div class="settings-party-block settings-image-upload-card">
                <label class="sf-field sf-field-full">
                  <span class="sf-label">Sponsor</span>
                  <input name="sponsor" id="sf-sponsor" placeholder="Sponsor name">
                </label>
                <div class="settings-image-preview" id="sf-logo-preview"><span class="settings-image-placeholder">No image uploaded</span></div>
                <div class="settings-image-upload-row">
                  <input type="file" id="sf-logo-file" class="settings-image-file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                  <button type="button" class="soft-btn" id="sf-logo-browse-btn">Browse</button>
                  <button type="button" class="primary-btn" id="sf-logo-upload-btn">Upload</button>
                  <span class="muted settings-image-status" id="sf-logo-upload-status" hidden></span>
                </div>
              </div>
            </div>
            <div class="form-grid settings-form-grid settings-form-grid--after-party">
              <label class="sf-field"><span class="sf-label">Budget</span><input name="budget" id="sf-budget" placeholder="e.g. $2.5M"></label>
              <label class="sf-field"><span class="sf-label">Budget source</span><input name="budgetSource" id="sf-budget-source" placeholder="e.g. Second Revised PC-I, WAPDA, March 2026"></label>
              <label class="sf-field"><span class="sf-label">Project Manager</span><input name="pm" id="sf-pm" placeholder="PM name"></label>
              <label class="sf-field sf-field-full"><span class="sf-label">Consultants</span><input name="consultants" id="sf-consultants" placeholder="e.g. DOLSAR-MMP JV"></label>
              <label class="sf-field sf-field-full"><span class="sf-label">Location</span><input name="location" id="sf-location" placeholder="Project location"></label>
            </div>
          </div>

          <div class="settings-section">
            <h4 class="settings-section-title">Dates &amp; media</h4>
            <div class="form-grid settings-form-grid">
              <label class="sf-field"><span class="sf-label">Start date</span><input type="date" name="startDate" id="sf-start"></label>
              <label class="sf-field"><span class="sf-label">Closing date</span><input type="date" name="closingDate" id="sf-end"></label>
              <label class="sf-field sf-field-full"><span class="sf-label">Disclaimer</span><input name="disclaimer" id="sf-disclaimer" placeholder="Notice text only — “Disclaimer:” is added automatically"></label>
            </div>

            <input type="hidden" name="locationMapUrl" id="sf-map">
            <input type="hidden" name="panoramaUrl" id="sf-panorama">

            <div class="settings-image-uploads">
              <div class="settings-image-upload-card">
                <span class="sf-label">Location map</span>
                <div class="settings-image-preview settings-image-preview--map" id="sf-map-preview"><span class="settings-image-placeholder">No map uploaded</span></div>
                <div class="settings-image-upload-row">
                  <input type="file" id="sf-map-file" class="settings-image-file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                  <button type="button" class="soft-btn" id="sf-map-browse-btn">Browse</button>
                  <button type="button" class="primary-btn" id="sf-map-upload-btn">Upload</button>
                  <span class="muted settings-image-status" id="sf-map-upload-status" hidden></span>
                </div>
              </div>

              <div class="settings-image-upload-card">
                <span class="sf-label">Panorama banner</span>
                <div class="settings-image-preview settings-image-preview--panorama" id="sf-panorama-preview"><span class="settings-image-placeholder">No panorama uploaded</span></div>
                <div class="settings-image-upload-row">
                  <input type="file" id="sf-panorama-file" class="settings-image-file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                  <button type="button" class="soft-btn" id="sf-panorama-browse-btn">Browse</button>
                  <button type="button" class="primary-btn" id="sf-panorama-upload-btn">Upload</button>
                  <span class="muted settings-image-status" id="sf-panorama-upload-status" hidden></span>
                </div>
                <span class="muted settings-image-hint">Wide landscape photo works best (shown behind project name).</span>
              </div>
            </div>
          </div>

          <div class="settings-section">
            <h4 class="settings-section-title">Document sources</h4>
            <p class="settings-section-desc muted">Configure where DTS reads project files. Local folder and Google Drive can both be active — files from both appear in the workspace.</p>

            <div class="settings-source-block">
              <h5 class="settings-source-label">📁 Local storage folder</h5>
              <div class="form-grid settings-form-grid">
                <label class="sf-field sf-field-full">
                  <span class="sf-label">Folder path on this server / PC</span>
                  <input type="text" name="localFolderPath" id="sf-local-folder" placeholder="e.g. storage/Data or D:/Projects/NAULONG">
                </label>
              </div>
              <div class="settings-gdrive-actions">
                <button type="button" class="soft-btn" id="sf-local-folder-test-btn">Verify folder</button>
                <span class="muted settings-gdrive-status" id="sf-local-folder-status" hidden></span>
              </div>
              <p class="settings-hint muted settings-source-hint">Relative to the DTS app folder (e.g. <code>storage/Data</code>) or an absolute path. Leave empty to use the default from <code>config.php</code>.</p>
            </div>

            <div class="settings-source-block settings-source-block--gdrive">
              <h5 class="settings-source-label">☁️ Google Drive folder</h5>
              <div class="form-grid settings-form-grid">
                <label class="sf-field sf-field-full">
                  <span class="sf-label">Public folder link</span>
                  <input type="url" name="gdriveFolderUrl" id="sf-gdrive-url" placeholder="https://drive.google.com/drive/folders/…">
                </label>
              </div>
              <div class="settings-gdrive-actions">
                <button type="button" class="soft-btn" id="sf-gdrive-test-btn">Test connection</button>
                <span class="muted settings-gdrive-status" id="sf-gdrive-status" hidden></span>
              </div>
              <p class="settings-hint muted settings-source-hint">
                Share folder as <strong>Anyone with the link can view</strong>.
                Set <code>gdrive_api_key</code> in <code>config.php</code>.
                Drive files appear under <strong>Google Drive</strong> in the sidebar.
              </p>
            </div>
          </div>

          <div class="settings-footer">
            <button type="submit" class="primary-btn settings-save-btn">💾 Save project details</button>
            <button type="button" class="ghost-btn" id="cancel-settings">Cancel</button>
          </div>
          <p class="settings-hint muted"><span class="settings-hint-icon">🔒</span> Saved to <code>data/projects.json</code> — persists after refresh.</p>

          <div class="settings-danger-zone" id="settings-danger-zone">
            <h4 class="settings-danger-title">Danger zone</h4>
            <p class="settings-danger-desc">Remove this project from the board. Uploaded CVs and summary sheets for this project are deleted. Built-in <code>config.php</code> projects are hidden, not removed from the config file.</p>
            <button type="button" class="settings-delete-btn" id="delete-project-btn">🗑️ Delete this project</button>
          </div>
        </form>
      </div>

      <!-- Team & CVs -->
      <div class="settings-pane" data-settings-pane="team" hidden>
        <div class="settings-pane-hero">
          <span class="settings-pane-hero-icon">👥</span>
          <div>
            <h3 class="settings-pane-title">Team &amp; CVs</h3>
            <p class="settings-pane-desc">Add people and attach CV documents for preview in the workspace.</p>
          </div>
        </div>
        <div id="team-members-editor" class="team-members-editor"></div>
        <button type="button" class="settings-add-btn" id="add-team-member">
          <span class="settings-add-icon">+</span> Add team member
        </button>
        <div class="settings-footer">
          <button type="button" class="primary-btn settings-save-btn" id="save-team-btn">💾 Save team &amp; CVs</button>
        </div>
      </div>

      <!-- Timesheets -->
      <div class="settings-pane" data-settings-pane="timesheet" hidden>
        <div class="settings-pane-hero">
          <span class="settings-pane-hero-icon">⏱️</span>
          <div>
            <h3 class="settings-pane-title">Timesheets</h3>
            <p class="settings-pane-desc">Weekly hours and phase notes for manager timesheets.</p>
          </div>
        </div>
        <div id="timesheet-editor" class="timesheet-editor"></div>
        <button type="button" class="settings-add-btn" id="add-timesheet-row">
          <span class="settings-add-icon">+</span> Add timesheet row
        </button>
        <div class="settings-footer">
          <button type="button" class="primary-btn settings-save-btn" id="save-timesheet-btn">💾 Save timesheets</button>
        </div>
      </div>

      <!-- Summary sheet -->
      <div class="settings-pane" data-settings-pane="files" hidden>
        <div class="settings-pane-hero">
          <span class="settings-pane-hero-icon">📄</span>
          <div>
            <h3 class="settings-pane-title">Project Summary Sheet</h3>
            <p class="settings-pane-desc">Upload a PDF or image — shown on the project detail panel.</p>
          </div>
        </div>
        <div class="settings-upload-card" id="summary-drop-zone">
          <div class="settings-upload-icon">📎</div>
          <p class="settings-upload-title">Drop file here or browse</p>
          <p class="settings-upload-hint">PDF, JPG, PNG, GIF, WebP</p>
          <form id="settings-sheet-form" method="post" action="upload.php" enctype="multipart/form-data" class="settings-sheet-form">
            <input type="hidden" name="project_id" id="sf-sheet-project-id" value="">
            <input type="hidden" name="type" value="summary_sheet">
            <input type="hidden" name="redirect" id="sf-sheet-redirect" value="">
            <label class="settings-upload-browse">
              <input type="file" name="file" id="sf-summary-sheet" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/*">
              <span class="soft-btn">Choose file</span>
            </label>
            <button type="submit" class="primary-btn settings-sheet-upload-btn">Upload summary sheet</button>
          </form>
          <div class="settings-upload-current" id="sf-summary-sheet-current">No file attached yet</div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="app.js?v=21"></script>
</body>
</html>
