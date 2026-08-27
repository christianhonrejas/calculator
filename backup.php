<?php
require_once 'auth.php';
$pageTitle = 'Google Drive Backup';

if (!in_array($_SESSION['admin_role'], ['superadmin', 'admin'])) {
    header('Location: dashboard.php'); exit;
}

// Ensure tables
$conn->query("CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(200) NOT NULL,
    file_id VARCHAR(200) NULL,
    folder_id VARCHAR(200) NULL,
    file_size INT NOT NULL DEFAULT 0,
    status ENUM('success','failed') NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    created_by VARCHAR(100) NULL,
    backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS backup_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Migrate backup_type
$bkCols = [];
$bkCr = $conn->query("SHOW COLUMNS FROM backup_logs");
if ($bkCr) while ($c = $bkCr->fetch_assoc()) $bkCols[] = $c['Field'];
if (!in_array('backup_type', $bkCols))
    $conn->query("ALTER TABLE backup_logs ADD COLUMN backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive' AFTER error_message");

function bkSetting(mysqli $c, string $k, string $d = ''): string {
    $r = $c->query("SELECT setting_value FROM backup_settings WHERE setting_key='" . $c->real_escape_string($k) . "'");
    return ($r && $r->num_rows) ? ($r->fetch_assoc()['setting_value'] ?? $d) : $d;
}

$isSuperadmin = $_SESSION['admin_role'] === 'superadmin';
$connected    = (bool)bkSetting($conn, 'oauth_refresh_token');
$connectedAt  = bkSetting($conn, 'oauth_connected_at');
$clientId     = bkSetting($conn, 'oauth_client_id');
$clientSecret = bkSetting($conn, 'oauth_client_secret');
$redirectUri  = bkSetting($conn, 'oauth_redirect_uri');
$keepCount    = bkSetting($conn, 'keep_count', '10');

// Flash message from OAuth callback
$flashMsg = $_SESSION['backup_msg'] ?? null;
unset($_SESSION['backup_msg']);

// Stats
$totalBackups   = (int)$conn->query("SELECT COUNT(*) as c FROM backup_logs")->fetch_assoc()['c'];
$successBackups = (int)$conn->query("SELECT COUNT(*) as c FROM backup_logs WHERE status='success'")->fetch_assoc()['c'];
$failedBackups  = (int)$conn->query("SELECT COUNT(*) as c FROM backup_logs WHERE status='failed'")->fetch_assoc()['c'];
$driveBackups   = (int)$conn->query("SELECT COUNT(*) as c FROM backup_logs WHERE backup_type='drive' AND status='success'")->fetch_assoc()['c'];
$lastBackup     = $conn->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$needsBackup    = !$lastBackup || (time() - strtotime($lastBackup['created_at'])) > 86400;

// Default redirect URI hint
$protocol       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$defaultRedirect= $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/backup_oauth.php';

include 'header.php';
?>

<!-- ── Page Header ──────────────────────────────────────────────────────── -->
<div class="page-header mb-4">
  <div>
    <h4 style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;margin-bottom:2px;">
      <i class="fab fa-google-drive me-2" style="color:#4285F4;"></i>Google Drive Backup
    </h4>
    <p class="text-muted mb-0" style="font-size:13px;">Back up your database to your personal Google Drive or download for USB</p>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <!-- USB/Local Download — always available -->
    <a href="backup_download.php" class="btn-success-custom" onclick="return confirm('Download a full database backup to your computer / USB?')">
      <i class="fas fa-usb"></i> Download for USB
    </a>
    <!-- Drive Backup — only if connected -->
    <button id="btnRunBackup" class="btn-primary-custom" <?= !$connected ? 'disabled title="Connect Google Drive first"' : '' ?>>
      <i class="fab fa-google-drive"></i> Backup to Drive
    </button>
  </div>
</div>

<?php if ($flashMsg): ?>
<div style="background:<?= $flashMsg['type']==='success'?'#f0fff8':'#fff0f0' ?>;border:1px solid <?= $flashMsg['type']==='success'?'#a7f3d0':'#fca5a5' ?>;border-radius:12px;padding:14px 18px;margin-bottom:20px;color:<?= $flashMsg['type']==='success'?'#065f46':'#991b1b' ?>;font-size:13px;">
  <i class="fas fa-<?= $flashMsg['type']==='success'?'check':'exclamation' ?>-circle me-2"></i><?= htmlspecialchars($flashMsg['text']) ?>
</div>
<?php endif; ?>

<?php if ($needsBackup && ($connected || true)): ?>
<div style="background:#fff8e6;border:1px solid #fcd34d;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
  <i class="fas fa-exclamation-triangle" style="color:#f59e0b;font-size:18px;flex-shrink:0;"></i>
  <div>
    <strong style="color:#92400e;">No backup in the last 24 hours!</strong>
    <div style="font-size:12px;color:#b45309;margin-top:2px;">Use <strong>Backup to Drive</strong> or <strong>Download for USB</strong> to protect your data.</div>
  </div>
</div>
<?php endif; ?>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div><div class="card-label">Total Backups</div><div class="card-value"><?= $totalBackups ?></div></div>
        <div class="card-icon icon-blue"><i class="fas fa-database"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div><div class="card-label">Drive Backups</div><div class="card-value" style="color:var(--primary)"><?= $driveBackups ?></div></div>
        <div class="card-icon icon-blue"><i class="fab fa-google-drive"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div><div class="card-label">Successful</div><div class="card-value" style="color:var(--success)"><?= $successBackups ?></div></div>
        <div class="card-icon icon-green"><i class="fas fa-check-circle"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div><div class="card-label">Failed</div><div class="card-value" style="color:var(--danger)"><?= $failedBackups ?></div></div>
        <div class="card-icon icon-red"><i class="fas fa-times-circle"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
<!-- ── LEFT COL ─────────────────────────────────────────────────────────── -->
<div class="col-lg-5">

  <!-- Drive Connection Status -->
  <div class="section-card mb-4">
    <div class="section-card-header">
      <span class="section-card-title"><i class="fab fa-google-drive me-2" style="color:#4285F4;"></i>Google Drive Connection</span>
      <span id="connBadge" class="<?= $connected?'badge-active':'badge-inactive' ?>">
        <i class="fas fa-<?= $connected?'check':'times' ?> me-1"></i><?= $connected?'Connected':'Not Connected' ?>
      </span>
    </div>
    <div class="section-card-body">
      <div id="connInfo" style="<?= !$connected?'display:none':'' ?>">
        <div style="background:rgba(66,133,244,0.07);border:1px solid rgba(66,133,244,0.2);border-radius:10px;padding:12px 16px;margin-bottom:14px;">
          <div style="font-size:11px;color:#6b7a99;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Connected Account</div>
          <div id="accountEmail" style="font-size:14px;font-weight:700;color:#0a1628;margin-top:3px;">—</div>
          <div id="connectedAt" style="font-size:11px;color:#6b7a99;margin-top:2px;"><?= $connectedAt ? 'Since ' . date('M d, Y h:i A', strtotime($connectedAt)) : '' ?></div>
        </div>
        <?php if ($isSuperadmin): ?>
        <button id="btnDisconnect" class="btn-outline-custom w-100" style="justify-content:center;color:var(--danger);border-color:var(--danger);">
          <i class="fas fa-unlink me-1"></i> Disconnect Google Drive
        </button>
        <?php endif; ?>
      </div>
      <div id="notConnInfo" style="<?= $connected?'display:none':'' ?>">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
          Connect your personal Google account to enable automatic backups to your Google Drive.
        </p>
        <?php if ($isSuperadmin && $clientId && $redirectUri): ?>
        <button id="btnConnect" class="btn-primary-custom w-100" style="justify-content:center;">
          <i class="fab fa-google me-2"></i> Connect Google Drive
        </button>
        <?php elseif ($isSuperadmin): ?>
        <div style="background:#fff8e6;border:1px solid #fcd34d;border-radius:10px;padding:12px 14px;font-size:12px;color:#92400e;">
          <i class="fas fa-exclamation-triangle me-1"></i> Save your OAuth settings first, then the Connect button will appear.
        </div>
        <?php else: ?>
        <div style="background:#f0f4fb;border-radius:10px;padding:12px 14px;font-size:12px;color:#6b7a99;">
          <i class="fas fa-info-circle me-1"></i> Ask the owner (superadmin) to connect Google Drive.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- USB Download Card -->
  <div class="section-card mb-4">
    <div class="section-card-header">
      <span class="section-card-title"><i class="fas fa-usb me-2" style="color:var(--success);"></i>Local / USB Backup</span>
    </div>
    <div class="section-card-body">
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
        Download a full <code>.sql</code> backup file directly to your computer. Then copy it to a USB drive for safe offline storage.
      </p>
      <a href="backup_download.php" class="btn-success-custom w-100" style="justify-content:center;"
         onclick="return confirm('This will generate and download a full database backup. Continue?')">
        <i class="fas fa-download me-2"></i> Download .SQL Backup File
      </a>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
        <div style="background:#f0f4fb;border-radius:8px;padding:8px 12px;font-size:11px;color:#6b7a99;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-check" style="color:var(--success);"></i> No Google account needed
        </div>
        <div style="background:#f0f4fb;border-radius:8px;padding:8px 12px;font-size:11px;color:#6b7a99;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-check" style="color:var(--success);"></i> Works offline
        </div>
        <div style="background:#f0f4fb;border-radius:8px;padding:8px 12px;font-size:11px;color:#6b7a99;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-check" style="color:var(--success);"></i> Importable to phpMyAdmin
        </div>
      </div>
    </div>
  </div>

  <?php if ($isSuperadmin): ?>
  <!-- OAuth Settings -->
  <div class="section-card mb-4">
    <div class="section-card-header">
      <span class="section-card-title"><i class="fas fa-cog me-2" style="color:var(--primary);"></i>OAuth Settings</span>
    </div>
    <div class="section-card-body">
      <div id="settingsMsg" style="display:none;" class="mb-3"></div>
      <div class="mb-3">
        <label class="form-label">Client ID <span class="text-danger">*</span></label>
        <input type="text" id="oauthClientId" class="form-control" value="<?= htmlspecialchars($clientId) ?>" placeholder="xxxx.apps.googleusercontent.com">
      </div>
      <div class="mb-3">
        <label class="form-label">Client Secret <span class="text-danger">*</span></label>
        <input type="password" id="oauthClientSecret" class="form-control" value="<?= htmlspecialchars($clientSecret) ?>" placeholder="GOCSPX-…">
        <button type="button" onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password';this.textContent=this.previousElementSibling.type==='password'?'Show':'Hide';" style="background:none;border:none;color:var(--primary);font-size:11px;cursor:pointer;padding:4px 0;">Show</button>
      </div>
      <div class="mb-3">
        <label class="form-label">Authorised Redirect URI <span class="text-danger">*</span></label>
        <input type="text" id="oauthRedirectUri" class="form-control" value="<?= htmlspecialchars($redirectUri ?: $defaultRedirect) ?>" placeholder="https://yourdomain.com/gym-system/backup_oauth.php">
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Copy this exact URL into Google Cloud Console → Credentials → OAuth Client → Authorised redirect URIs.</div>
      </div>
      <div class="mb-4">
        <label class="form-label">Keep How Many Drive Backups</label>
        <select id="keepCount" class="form-select">
          <?php foreach ([5,7,10,14,20,30] as $k): ?>
          <option value="<?= $k ?>" <?= $keepCount==$k?'selected':'' ?>><?= $k ?> most recent</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button id="btnSaveSettings" class="btn-primary-custom w-100" style="justify-content:center;">
        <i class="fas fa-save me-1"></i> Save Settings
      </button>
    </div>
  </div>

  <!-- Setup Guide -->
  <div class="section-card">
    <div class="section-card-header">
      <span class="section-card-title"><i class="fas fa-book me-2" style="color:var(--warning);"></i>Setup Guide</span>
    </div>
    <div class="section-card-body" style="font-size:13px;line-height:1.8;">
      <?php
      $steps = [
        ['Google Cloud Console', 'Go to <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a>. Create a new project named <em>DiozabethBackup</em>.'],
        ['Enable Drive API', 'APIs & Services → Library → search <em>Google Drive API</em> → Enable.'],
        ['Create OAuth Client', 'Credentials → Create Credentials → OAuth Client ID → Application type: <strong>Web application</strong>.'],
        ['Add Redirect URI', 'In Authorised Redirect URIs, paste your <strong>Redirect URI</strong> from the settings above. Save.'],
        ['Copy Credentials', 'Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into the settings above. Click Save Settings.'],
        ['Connect Your Account', 'Click <strong>Connect Google Drive</strong>. Sign in with your personal Google account and allow access.'],
        ['Run First Backup', 'Click <strong>Backup to Drive</strong>. A folder <em>DiozabethFitness_Backups</em> will be created in your Drive.'],
      ];
      foreach ($steps as $i => [$title, $desc]):
        $color = $i === count($steps)-1 ? '#10b981' : 'var(--primary)';
      ?>
      <div style="display:flex;gap:12px;margin-bottom:14px;">
        <div style="width:24px;height:24px;background:<?= $color ?>;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0;"><?= $i+1 ?></div>
        <div><strong><?= $title ?></strong><br><span style="color:var(--text-muted);"><?= $desc ?></span></div>
      </div>
      <?php endforeach; ?>
      <div style="background:rgba(30,120,255,0.06);border-left:3px solid var(--primary);padding:10px 14px;border-radius:0 8px 8px 0;font-size:12px;">
        <strong><i class="fas fa-shield-halved me-1" style="color:var(--primary);"></i>Privacy</strong><br>
        The app only gets access to files <em>it creates itself</em> (drive.file scope). It cannot see your other Google Drive files.
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /col-lg-5 -->

<!-- ── RIGHT COL ────────────────────────────────────────────────────────── -->
<div class="col-lg-7">
  <div class="section-card">
    <div class="section-card-header">
      <span class="section-card-title"><i class="fas fa-history me-2" style="color:var(--text-muted);"></i>Backup History</span>
      <button class="btn-outline-custom" style="padding:6px 13px;font-size:12px;" onclick="loadLogs()">
        <i class="fas fa-sync-alt me-1"></i>Refresh
      </button>
    </div>
    <div id="logsContainer" class="section-card-body p-0">
      <div style="padding:40px;text-align:center;color:var(--text-muted);">
        <i class="fas fa-spinner fa-spin fa-2x mb-3 d-block"></i>Loading…
      </div>
    </div>
  </div>
</div>

</div><!-- /row -->

<!-- ── Backup Overlay ──────────────────────────────────────────────────────── -->
<div id="backupOverlay" style="display:none;position:fixed;inset:0;background:rgba(10,22,40,.85);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;padding:40px;max-width:400px;width:90%;text-align:center;box-shadow:0 32px 80px rgba(0,0,0,.4);">
    <div style="width:64px;height:64px;background:rgba(66,133,244,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <i class="fab fa-google-drive" style="font-size:28px;color:#4285F4;"></i>
    </div>
    <div style="font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:800;margin-bottom:6px;">Backing Up…</div>
    <div id="backupStatus" style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">Generating database dump…</div>
    <div style="background:#f0f4fb;border-radius:8px;height:8px;overflow:hidden;">
      <div id="backupBar" style="height:100%;background:linear-gradient(90deg,#4285F4,#34A853);width:0%;transition:width .4s ease;border-radius:8px;"></div>
    </div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:8px;">Please do not close this tab.</div>
  </div>
</div>
<style>#backupOverlay.show{display:flex!important;}</style>

<?php
$extraScripts = '
<script>
// ── Load backup history ─────────────────────────────────────────────────────
function loadLogs() {
  fetch("backup_ajax.php?action=get_status")
    .then(r => r.json()).then(d => {
      if (!d.success) return;

      // Update connection badge + info
      if (d.connected) {
        document.getElementById("connBadge").className = "badge-active";
        document.getElementById("connBadge").innerHTML = \'<i class="fas fa-check me-1"></i>Connected\';
        document.getElementById("connInfo").style.display = "";
        document.getElementById("notConnInfo").style.display = "none";
        if (d.account_email) document.getElementById("accountEmail").textContent = d.account_email;
        document.getElementById("btnRunBackup").disabled = false;
      } else {
        document.getElementById("connBadge").className = "badge-inactive";
        document.getElementById("connBadge").innerHTML = \'<i class="fas fa-times me-1"></i>Not Connected\';
        document.getElementById("connInfo").style.display = "none";
        document.getElementById("notConnInfo").style.display = "";
        document.getElementById("btnRunBackup").disabled = true;
      }

      const c = document.getElementById("logsContainer");
      if (!d.logs || !d.logs.length) {
        c.innerHTML = \'<div style="padding:40px;text-align:center;color:var(--text-muted);"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No backups yet.</div>\';
        return;
      }

      let html = \'<div class="table-responsive"><table class="table mb-0"><thead><tr>\' +
        \'<th>Filename</th><th>Type</th><th>Size</th><th>Status</th><th>Date</th><th>By</th></tr></thead><tbody>\';
      d.logs.forEach(l => {
        const isOk   = l.status === "success";
        const isDrive= l.backup_type === "drive";
        const icon   = isOk
          ? \'<i class="fas fa-check-circle" style="color:var(--success)"></i>\'
          : \'<i class="fas fa-times-circle" style="color:var(--danger)"></i>\';
        const typeTag= isDrive
          ? \'<span style="background:rgba(66,133,244,.12);color:#4285F4;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;"><i class="fab fa-google-drive me-1"></i>Drive</span>\'
          : \'<span style="background:rgba(16,185,129,.12);color:#10b981;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;"><i class="fas fa-usb me-1"></i>Local</span>\';
        const tip    = (!isOk && l.error) ? ` title="${l.error.replace(/"/g,\'&quot;\')}"` : "";
        html += `<tr>
          <td><code style="font-size:10px;background:#f0f4fb;padding:2px 7px;border-radius:5px;word-break:break-all;">${l.filename}</code></td>
          <td>${typeTag}</td>
          <td style="font-size:12px;white-space:nowrap;">${l.file_size}</td>
          <td${tip}>${icon} <span style="font-size:11px;font-weight:600;">${l.status}</span></td>
          <td style="font-size:11px;">${l.date}<br><span style="color:var(--text-muted);">${l.ago}</span></td>
          <td style="font-size:12px;">${l.by||"—"}</td>
        </tr>`;
      });
      html += "</tbody></table></div>";
      c.innerHTML = html;
    });
}

// ── Connect Drive ───────────────────────────────────────────────────────────
document.getElementById("btnConnect")?.addEventListener("click", () => {
  fetch("backup_ajax.php?action=get_auth_url")
    .then(r => r.json()).then(d => {
      if (d.success) window.location.href = d.url;
      else Swal.fire({icon:"error", title:"Error", text: d.message, confirmButtonColor:"#ef4444"});
    });
});

// ── Disconnect Drive ────────────────────────────────────────────────────────
document.getElementById("btnDisconnect")?.addEventListener("click", () => {
  Swal.fire({
    icon:"warning", title:"Disconnect Google Drive?",
    text: "Future backups will not be uploaded to Drive until you reconnect.",
    showCancelButton: true, confirmButtonColor:"#ef4444", confirmButtonText:"Disconnect"
  }).then(res => {
    if (!res.isConfirmed) return;
    fetch("backup_ajax.php?action=disconnect", {method:"POST"})
      .then(r=>r.json()).then(d => {
        if (d.success) { Swal.fire({icon:"success",title:"Disconnected",timer:1500,showConfirmButton:false}); loadLogs(); }
      });
  });
});

// ── Backup to Drive ─────────────────────────────────────────────────────────
document.getElementById("btnRunBackup")?.addEventListener("click", () => {
  const overlay = document.getElementById("backupOverlay");
  const bar     = document.getElementById("backupBar");
  const status  = document.getElementById("backupStatus");
  overlay.classList.add("show");
  bar.style.width = "0%";

  const steps = [[10,800,"Generating database dump…"],[30,1200,"Connecting to Google Drive…"],[55,1000,"Authenticating…"],[75,800,"Uploading to Drive…"],[90,600,"Almost done…"]];
  let i = 0;
  (function next() {
    if (i >= steps.length) return;
    const [p,t,m] = steps[i++];
    setTimeout(() => { bar.style.width=p+"%"; status.textContent=m; next(); }, t);
  })();

  fetch("backup_ajax.php?action=run_backup")
    .then(r=>r.json()).then(d => {
      overlay.classList.remove("show");
      if (d.success) {
        bar.style.width = "100%";
        Swal.fire({icon:"success", title:"Backup Complete!",
          html:`<b>${d.filename}</b><br><small>${d.file_size}</small>`,
          confirmButtonColor:"#1e78ff"});
        loadLogs();
      } else {
        Swal.fire({icon:"error", title:"Backup Failed", text:d.message, confirmButtonColor:"#ef4444"});
      }
    })
    .catch(() => {
      overlay.classList.remove("show");
      Swal.fire({icon:"error", title:"Network Error", text:"Could not reach backup_ajax.php.", confirmButtonColor:"#ef4444"});
    });
});

// ── Save Settings ───────────────────────────────────────────────────────────
document.getElementById("btnSaveSettings")?.addEventListener("click", () => {
  const msg = document.getElementById("settingsMsg");
  const body = new URLSearchParams({
    oauth_client_id:     document.getElementById("oauthClientId")?.value || "",
    oauth_client_secret: document.getElementById("oauthClientSecret")?.value || "",
    oauth_redirect_uri:  document.getElementById("oauthRedirectUri")?.value || "",
    keep_count:          document.getElementById("keepCount")?.value || "10",
  });
  fetch("backup_ajax.php?action=save_settings", {method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body})
    .then(r=>r.json()).then(d => {
      msg.style.cssText = "display:block;background:"+(d.success?"#f0fff8":"#fff0f0")+";border:1px solid "+(d.success?"#a7f3d0":"#fca5a5")+";border-radius:10px;padding:10px 14px;color:"+(d.success?"#065f46":"#991b1b")+";font-size:13px;margin-bottom:12px;";
      msg.innerHTML = (d.success?"<i class=\'fas fa-check-circle me-2\'></i>":"<i class=\'fas fa-exclamation-circle me-2\'></i>") + d.message;
      if (d.success) { setTimeout(()=>{ msg.style.display="none"; location.reload(); }, 1500); }
    });
});

// init
loadLogs();
</script>';
include 'footer.php';
?>
