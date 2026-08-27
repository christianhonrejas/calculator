<?php
/**
 * gdrive_backup.php — Google Drive Backup Engine v2
 * ===================================================
 * Uses OAuth 2.0 with stored refresh_token (personal Google Drive).
 * InfinityFree-safe: pure PHP + cURL only. No exec/shell/service account.
 */

if (!defined('DB_HOST')) { header('HTTP/1.0 403 Forbidden'); exit; }

// ── cURL helper ──────────────────────────────────────────────────────────────
function gdrive_curl(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT      => 'DiozabethFitness-Backup/2.0',
    ] + $opts);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new RuntimeException("cURL error: $err");
    return ['body' => $body];
}

// ─────────────────────────────────────────────────────────────────────────────
class GDriveBackup {

    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    // ── Setting helpers ───────────────────────────────────────────────────────
    public function getSetting(string $k, string $d = ''): string {
        $r = $this->conn->query("SELECT setting_value FROM backup_settings WHERE setting_key = '"
            . $this->conn->real_escape_string($k) . "'");
        return ($r && $r->num_rows) ? ($r->fetch_assoc()['setting_value'] ?? $d) : $d;
    }
    public function saveSetting(string $k, string $v): void {
        $ek = $this->conn->real_escape_string($k);
        $ev = $this->conn->real_escape_string($v);
        $this->conn->query("INSERT INTO backup_settings (setting_key,setting_value) VALUES ('$ek','$ev')
            ON DUPLICATE KEY UPDATE setting_value='$ev'");
    }

    // ── 1. Build Google OAuth2 Authorize URL ──────────────────────────────────
    public function getAuthUrl(): string {
        $clientId    = $this->getSetting('oauth_client_id');
        $redirectUri = $this->getSetting('oauth_redirect_uri');
        if (!$clientId || !$redirectUri) {
            throw new RuntimeException('OAuth Client ID and Redirect URI must be configured first.');
        }
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/drive.file',
            'access_type'   => 'offline',
            'prompt'        => 'consent',  // always returns refresh_token
        ]);
    }

    // ── 2. Exchange auth code for tokens ──────────────────────────────────────
    public function handleOAuthCallback(string $code): void {
        $res = gdrive_curl('https://oauth2.googleapis.com/token', [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code'          => $code,
                'client_id'     => $this->getSetting('oauth_client_id'),
                'client_secret' => $this->getSetting('oauth_client_secret'),
                'redirect_uri'  => $this->getSetting('oauth_redirect_uri'),
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $data = json_decode($res['body'], true);
        if (empty($data['refresh_token'])) {
            throw new RuntimeException('No refresh_token received. Try disconnecting and reconnecting. Error: '
                . ($data['error_description'] ?? $res['body']));
        }
        $this->saveSetting('oauth_refresh_token', $data['refresh_token']);
        $this->saveSetting('oauth_connected_at',  date('Y-m-d H:i:s'));
        $this->saveSetting('drive_folder_id', ''); // reset folder so it auto-creates fresh
    }

    // ── 3. Get a fresh access_token ────────────────────────────────────────────
    public function getAccessToken(): string {
        $refreshToken = $this->getSetting('oauth_refresh_token');
        if (!$refreshToken) {
            throw new RuntimeException('Google Drive not connected. Please click "Connect Google Drive" first.');
        }
        $res = gdrive_curl('https://oauth2.googleapis.com/token', [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'refresh_token' => $refreshToken,
                'client_id'     => $this->getSetting('oauth_client_id'),
                'client_secret' => $this->getSetting('oauth_client_secret'),
                'grant_type'    => 'refresh_token',
            ]),
        ]);
        $data = json_decode($res['body'], true);
        if (empty($data['access_token'])) {
            $this->saveSetting('oauth_refresh_token', ''); // clear bad token
            throw new RuntimeException('Token refresh failed — please reconnect Google Drive. ('
                . ($data['error_description'] ?? 'unknown error') . ')');
        }
        return $data['access_token'];
    }

    // ── 4. Get connected account info ─────────────────────────────────────────
    public function getAccountEmail(string $token): string {
        try {
            $res  = gdrive_curl('https://www.googleapis.com/oauth2/v2/userinfo',
                [CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]);
            $data = json_decode($res['body'], true);
            return $data['email'] ?? '';
        } catch (Throwable $e) { return ''; }
    }

    // ── 5. Generate full SQL dump (pure PHP — no mysqldump/exec) ──────────────
    public function generateSQLDump(): string {
        $conn = $this->conn;
        $out  = "-- Diozabeth Fitness Gym Management System\n";
        $out .= "-- Database: " . DB_NAME . "  |  Generated: " . date('Y-m-d H:i:s') . " (Asia/Manila)\n\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $conn->query("SHOW TABLES");
        while ($tableRow = $tables->fetch_array()) {
            $tbl = $tableRow[0];
            $cr  = $conn->query("SHOW CREATE TABLE `$tbl`");
            $crRow = $cr->fetch_array();
            $out .= "DROP TABLE IF EXISTS `$tbl`;\n" . $crRow[1] . ";\n\n";

            $data  = $conn->query("SELECT * FROM `$tbl`");
            $batch = [];
            if ($data && $data->num_rows > 0) {
                while ($row = $data->fetch_row()) {
                    $vals = array_map(function($v) use ($conn) {
                        return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                    }, $row);
                    $batch[] = '(' . implode(',', $vals) . ')';
                    if (count($batch) >= 50) {
                        $out .= "INSERT INTO `$tbl` VALUES\n" . implode(",\n", $batch) . ";\n";
                        $batch = [];
                    }
                }
                if ($batch) $out .= "INSERT INTO `$tbl` VALUES\n" . implode(",\n", $batch) . ";\n";
                $out .= "\n";
            }
        }
        $out .= "SET FOREIGN_KEY_CHECKS=1;\n-- End of backup\n";
        return $out;
    }

    // ── 6. Find or create Drive folder ────────────────────────────────────────
    public function getOrCreateFolder(string $token, string $name): string {
        $q   = "name='" . addslashes($name) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";
        $res = gdrive_curl(
            'https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(id)',
            [CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]
        );
        $data = json_decode($res['body'], true);
        if (!empty($data['files'][0]['id'])) return $data['files'][0]['id'];

        $res2 = gdrive_curl('https://www.googleapis.com/drive/v3/files', [
            CURLOPT_POST       => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder']),
        ]);
        $data2 = json_decode($res2['body'], true);
        if (empty($data2['id'])) throw new RuntimeException('Could not create Drive folder: ' . $res2['body']);
        return $data2['id'];
    }

    // ── 7. Upload file (multipart) ────────────────────────────────────────────
    public function uploadFile(string $token, string $content, string $filename, string $folderId): array {
        $bnd  = '----GymBnd' . md5(microtime());
        $meta = json_encode(['name' => $filename, 'parents' => [$folderId]]);
        $body = "--$bnd\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
              . "--$bnd\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n"
              . "--$bnd--";

        $res  = gdrive_curl(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,size',
            [
                CURLOPT_POST       => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $token",
                    "Content-Type: multipart/related; boundary=$bnd",
                    "Content-Length: " . strlen($body),
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT    => 300,
            ]
        );
        $data = json_decode($res['body'], true);
        if (empty($data['id'])) throw new RuntimeException('Drive upload failed: ' . $res['body']);
        return $data;
    }

    // ── 8. Delete old backups, keep N most recent ──────────────────────────────
    public function pruneOldBackups(string $token, string $folderId, int $keep = 10): void {
        $res   = gdrive_curl(
            'https://www.googleapis.com/drive/v3/files?' . http_build_query([
                'q'       => "'$folderId' in parents and trashed=false",
                'fields'  => 'files(id,name,createdTime)',
                'orderBy' => 'createdTime asc',
            ]),
            [CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]
        );
        $files = json_decode($res['body'], true)['files'] ?? [];
        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $f) {
            gdrive_curl("https://www.googleapis.com/drive/v3/files/{$f['id']}", [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER    => ["Authorization: Bearer $token"],
            ]);
        }
    }

    // ── 9. Disconnect (revoke tokens) ─────────────────────────────────────────
    public function disconnect(): void {
        $rt = $this->getSetting('oauth_refresh_token');
        if ($rt) {
            try { gdrive_curl('https://oauth2.googleapis.com/revoke?token=' . urlencode($rt),
                [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '']);
            } catch (Throwable $e) { /* ignore */ }
        }
        $this->saveSetting('oauth_refresh_token', '');
        $this->saveSetting('oauth_connected_at',  '');
        $this->saveSetting('drive_folder_id',     '');
    }

    // ── 10. Full backup run ───────────────────────────────────────────────────
    public function runBackup(int $keepCount = 10): array {
        set_time_limit(300);
        ini_set('memory_limit', '128M');

        $sql      = $this->generateSQLDump();
        $filename = 'gym_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $token    = $this->getAccessToken();

        $folderId = $this->getSetting('drive_folder_id');
        if (!$folderId) {
            $folderId = $this->getOrCreateFolder($token, 'DiozabethFitness_Backups');
            $this->saveSetting('drive_folder_id', $folderId);
        }

        $uploaded = $this->uploadFile($token, $sql, $filename, $folderId);
        $this->pruneOldBackups($token, $folderId, $keepCount);

        return [
            'success'   => true,
            'filename'  => $filename,
            'file_id'   => $uploaded['id'],
            'file_size' => strlen($sql),
            'folder_id' => $folderId,
        ];
    }
}
