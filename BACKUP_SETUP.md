# 🔒 Google Drive Backup — Setup Guide
## Diozabeth Fitness Gym Management System

---

## What This Does
- Backs up your entire MySQL database to Google Drive
- Uses a **Service Account** (no user login/OAuth popup needed)
- InfinityFree-safe: pure PHP + cURL, no `exec()` or cron required
- Automatically keeps only the N most recent backups (configurable)

---

## Files Added / Modified

| File | Purpose |
|------|---------|
| `gdrive_backup.php` | Core backup engine (SQL dump + Drive upload) |
| `backup.php` | Admin backup management page |
| `backup_ajax.php` | AJAX handler (run, settings, logs) |
| `header.php` | Modified — added "Drive Backup" nav item |
| `database.php` | Modified — creates `backup_logs` and `backup_settings` tables |
| `auth.php` | Modified — allows managers to access backup pages |
| `.htaccess` | Protects `service_account.json` from direct browser access |

---

## Step-by-Step Setup

### Step 1 — Google Cloud Console
1. Go to https://console.cloud.google.com
2. Click the project dropdown (top left) → **New Project**
3. Name it `DiozabethBackup` → Create

### Step 2 — Enable Google Drive API
1. In your project, go to **APIs & Services → Library**
2. Search for **Google Drive API**
3. Click it → **Enable**

### Step 3 — Create a Service Account
1. Go to **APIs & Services → Credentials**
2. Click **+ Create Credentials → Service Account**
3. Name it anything (e.g. `gym-backup-bot`) → **Done**

### Step 4 — Download JSON Key
1. In the Credentials page, click your new service account
2. Go to the **Keys** tab
3. Click **Add Key → Create new key → JSON**
4. A file will download — **rename it to `service_account.json`**

### Step 5 — Upload to InfinityFree
1. Log in to your InfinityFree control panel
2. Open **File Manager**
3. Navigate to `htdocs/gym-system/` (or wherever your system is)
4. Upload `service_account.json` there

> ✅ The `.htaccess` file already blocks direct browser access to this file.
> Nobody can download it from a browser URL.

### Step 6 — Configure in Admin Panel
1. Log in as **superadmin** (diozabeth)
2. In the sidebar, click **Drive Backup**
3. In **Backup Settings**:
   - **Service Account File Path**: `service_account.json` (default — no change needed)
   - **Google Drive Folder ID**: leave blank (auto-creates a folder)
   - **Keep How Many Backups**: choose 10 (recommended)
4. Click **Save Settings**

### Step 7 — Run Your First Backup
1. Click **Run Backup Now**
2. Wait 10–30 seconds
3. A success message will appear with the filename and size
4. The backup folder `DiozabethFitness_Backups` will appear in your Google Drive

---

## Viewing Backups in Google Drive
The service account creates files in your Drive under the name you see in the success message. To view them:
1. Go to https://drive.google.com
2. Search for `DiozabethFitness_Backups`
3. Or click **Shared with me** → the service account's email owns the folder

> 💡 **Tip**: To move the folder to "My Drive", right-click it → Add shortcut to Drive.

---

## Restoring a Backup

1. Download the `.sql` file from Google Drive
2. Open **phpMyAdmin** in your InfinityFree control panel
3. Select your database (`gym_system`)
4. Click **Import** → choose the `.sql` file → **Go**

---

## Trigger Options (No Cron on InfinityFree)

Since InfinityFree doesn't support cron jobs, backups are triggered:

| Method | How |
|--------|-----|
| **Manual** | Click "Run Backup Now" on the backup page |
| **Reminder** | A yellow warning appears on backup page if no backup in 24h |

**Recommended**: Run a backup manually once a week, or whenever you make important changes.

---

## Troubleshooting

| Error | Fix |
|-------|-----|
| `service_account.json not found` | Upload the JSON file to the same folder as your PHP files |
| `Invalid service account JSON` | Make sure you downloaded the JSON key (not the p12) |
| `Token exchange failed` | Ensure Google Drive API is enabled in your Cloud project |
| `cURL error` | InfinityFree may be blocking the connection — try again or upgrade plan |
| `Drive upload failed` | Check folder ID is correct, or leave blank for auto-create |

---

## Security Notes
- `service_account.json` is protected by `.htaccess` — browsers cannot access it
- The service account only has `drive.file` scope — it can only access files IT creates
- It cannot read or modify other files in your personal Google Drive
- Backup files are only visible to: you + the service account email

---

*Diozabeth Fitness Gym Management System — Backup System v1.0*
