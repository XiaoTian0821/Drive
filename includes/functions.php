<?php
/**
 * Drivew - Shared domain helpers.
 * Loaded via bootstrap's autoloader (Drivew\Functions namespace).
 *
 * Helpers cover: HTML escaping, human size, folder breadcrumbs,
 * file/folder CRUD, tag operations, sharing, audit logging,
 * physical storage resolution, and ZIP creation.
 */

declare(strict_types=1);

namespace Drivew;

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Ajax.php';

final class F
{
    /** HTML-escape for safe output. */
    public static function esc(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /** Format bytes into a human string. */
    public static function humanSize(int|float $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['KB', 'MB', 'GB', 'TB'];
        $i = -1;
        do {
            $bytes /= 1024;
            $i++;
        } while ($bytes >= 1024 && $i < count($units) - 1);
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /** Get a file row and verify it belongs to the given user. */
    public static function fileFor(int $fileId, int $userId): ?array
    {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM files WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$fileId, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Get a folder row and verify it belongs to the given user. */
    public static function folderFor(int $folderId, int $userId): ?array
    {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM folders WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$folderId, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Resolve physical file path, ensuring it stays inside UPLOAD_ROOT. */
    public static function resolvePath(array $fileRow): ?string
    {
        $base = rtrim(UPLOAD_ROOT, '/\\');
        // stored_path is always "user_<id>/<random>_<original-ext>"
        $rel = $fileRow['stored_path'];
        $full = $base . '/' . str_replace('\\', '/', $rel);
        // Normalize and verify it stays under base
        $real = realpath(dirname($full));
        if ($real === false) return null;
        if (strpos($full, $real) !== 0) return null;
        return is_file($full) ? $full : null;
    }

    /** Get the full breadcrumb chain for a folder id. */
    public static function breadcrumb(int $folderId, int $userId): array
    {
        $chain = [];
        $stmt = drivew_db()->prepare(
            'SELECT id, name FROM folders WHERE id = ? AND user_id = ? AND is_deleted = 0'
        );
        $stmt->execute([$folderId, $userId]);
        $current = $stmt->fetch();
        while ($current) {
            array_unshift($chain, $current);
            if ($current['parent_id'] === null) break;
            $stmt->execute([$current['parent_id'], $userId]);
            $current = $stmt->fetch();
        }
        return $chain;
    }

    /** Get a folder's path string for display. */
    public static function folderPath(int $folderId, int $userId): string
    {
        $chain = self::breadcrumb($folderId, $userId);
        $names = array_map(fn($f) => $f['name'], $chain);
        return 'My Storage' . ($names ? '/' . implode('/', $names) : '');
    }

    /** Log an audit event. */
    public static function log(int $userId, string $action, string $resourceType = '', int $resourceId = 0, string $description = '', ?string $ip = null): void
    {
        $stmt = drivew_db()->prepare(
            'INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId, $action, $resourceType, $resourceId,
            $description, $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')
        ]);
    }

    /** Get system settings as a key/value array. */
    public static function settings(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $stmt = drivew_db()->query('SELECT setting_key, setting_value FROM settings');
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['setting_key']] = $row['setting_value'];
        }
        $cache = $map;
        return $map;
    }

    /** Get or set a single setting. */
    public static function setting(string $key, ?string $default = null): string
    {
        $s = self::settings();
        return $s[$key] ?? ($default ?? '');
    }

    /** Update a single setting. */
    public static function setSetting(string $key, string $value, int $adminId): void
    {
        $stmt = drivew_db()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([$key, $value, $adminId]);
        self::log($adminId, 'update_settings', 'settings', 0, "Set {$key}");
        // Clear settings cache for subsequent calls
        // (static cache is per-request; next request will re-read)
    }

    /** Compute a user's total storage used. */
    public static function storageUsed(int $userId): int
    {
        $stmt = drivew_db()->prepare(
            'SELECT COALESCE(SUM(size), 0) AS total FROM files WHERE user_id = ? AND is_deleted = 0'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return (int)$row['total'];
    }

    /** Compute a user's quota in bytes. */
    public static function storageQuota(int $userId): int
    {
        $stmt = drivew_db()->prepare('SELECT storage_quota FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return (int)($row['storage_quota'] ?? 0);
    }

    /** Storage used as percent of quota. */
    public static function storagePercent(int $userId): int
    {
        $quota = self::storageQuota($userId);
        if ($quota <= 0) return 0;
        $used = self::storageUsed($userId);
        return (int)min(100, round(($used / $quota) * 100));
    }

    /** Check if a user has enough storage for $incomingBytes. */
    public static function hasStorage(int $userId, int $incomingBytes): bool
    {
        $quota = self::storageQuota($userId);
        if ($quota <= 0) return true; // 0 = unlimited
        $used = self::storageUsed($userId);
        return ($used + $incomingBytes) <= $quota;
    }

    /** Get a share row by token. */
    public static function shareByToken(string $token): ?array
    {
        $stmt = drivew_db()->prepare('SELECT * FROM shares WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /** Get the file/folder a share points to. */
    public static function shareResource(array $share): ?array
    {
        if ($share['resource_type'] === 'file') {
            $stmt = drivew_db()->prepare(
                'SELECT * FROM files WHERE id = ? AND is_deleted = 0 LIMIT 1'
            );
            $stmt->execute([$share['resource_id']]);
            return $stmt->fetch() ?: null;
        }
        if ($share['resource_type'] === 'folder') {
            $stmt = drivew_db()->prepare(
                'SELECT * FROM folders WHERE id = ? AND is_deleted = 0 LIMIT 1'
            );
            $stmt->execute([$share['resource_id']]);
            return $stmt->fetch() ?: null;
        }
        return null;
    }

    /** Check if a share is currently accessible. */
    public static function isShareActive(array $share, array $resource): bool
    {
        if (!$share['is_active']) return false;
        if (empty($resource)) return false;
        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) return false;
        return true;
    }

    /** Verify a share password (empty password = no password). */
    public static function verifySharePassword(array $share, string $password): bool
    {
        if (empty($share['password_hash'])) return true; // no password
        return password_verify($password, $share['password_hash']);
    }

    /** Create a ZIP archive of a folder's files and return temp path. */
    public static function createFolderZip(array $folder, int $userId): ?string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'drivew_zip_') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::OVERWRITE | \ZipArchive::CREATE) !== true) {
            return null;
        }
        // Collect all files in this folder (recursive)
        $files = self::collectFolderFiles($folder['id'], $userId);
        // Build a folder-name-relative path per file so the ZIP mirrors the folder tree.
        $parentMap = [];
        // Map parent folder id -> its breadcrumb path (used for relative paths in the zip)
        $folderChain = self::breadcrumb((int)$folder['id'], $userId);
        $topName = $folderChain ? $folderChain[count($folderChain) - 1]['name'] : $folder['name'];
        foreach ($files as $f) {
            $phys = self::resolvePath($f);
            if ($phys === null) continue;
            // Relative path: folderName[/subfolder]/original_name
            $rel = $topName;
            if ($f['parent_folder_id'] !== null) {
                $subChain = self::breadcrumb((int)$f['parent_folder_id'], $userId);
                if ($subChain) {
                    // Find path under topName
                    $names = array_map(fn($x) => $x['name'], $subChain);
                    $key = array_search($topName, $names);
                    if ($key !== false) {
                        $under = array_slice($names, $key + 1);
                        if (!empty($under)) $rel = $topName . '/' . implode('/', $under);
                    }
                }
            }
            $zip->addFile($phys, $rel . '/' . $f['original_name']);
        }
        $zip->close();
        return $tmpFile;
    }

    /** Recursively collect all non-deleted files under a folder. */
    public static function collectFolderFiles(int $folderId, int $userId): array
    {
        $result = [];
        $queue = [$folderId];
        $stmtFiles = drivew_db()->prepare(
            'SELECT * FROM files WHERE parent_folder_id = ? AND user_id = ? AND is_deleted = 0'
        );
        $stmtChildren = drivew_db()->prepare(
            'SELECT id FROM folders WHERE parent_id = ? AND user_id = ? AND is_deleted = 0'
        );
        while (!empty($queue)) {
            $fid = array_shift($queue);
            $stmtFiles->execute([$fid, $userId]);
            foreach ($stmtFiles->fetchAll() as $f) {
                $result[] = $f;
            }
            $stmtChildren->execute([$fid, $userId]);
            foreach ($stmtChildren->fetchAll() as $c) {
                $queue[] = (int)$c['id'];
            }
        }
        return $result;
    }

    /** File-type icon key based on extension. */
    public static function fileIcon(string $ext): string
    {
        $map = [
            'pdf' => 'pdf', 'doc' => 'doc', 'docx' => 'doc', 'xls' => 'xls',
            'xlsx' => 'xls', 'csv' => 'xls', 'ppt' => 'ppt', 'pptx' => 'ppt',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image',
            'gif' => 'image', 'webp' => 'image', 'svg' => 'image',
            'bmp' => 'image', 'tiff' => 'image',
            'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'flac' => 'audio',
            'mp4' => 'video', 'webm' => 'video', 'avi' => 'video',
            'mkv' => 'video', 'mov' => 'video',
            'zip' => 'zip', 'rar' => 'zip', '7z' => 'zip', 'tar' => 'zip',
            'gz' => 'zip',
            'txt' => 'text', 'md' => 'text', 'log' => 'text',
            'json' => 'code', 'js' => 'code', 'css' => 'code',
            'html' => 'code', 'php' => 'code', 'xml' => 'code',
            'py' => 'code', 'java' => 'code', 'c' => 'code', 'cpp' => 'code',
        ];
        $ext = strtolower($ext);
        return $map[$ext] ?? 'file';
    }

    /** Check if file type is previewable. */
    public static function isPreviewable(string $ext): bool
    {
        $previewable = [
            'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
            'txt', 'md', 'log', 'json', 'csv', 'js', 'css', 'html',
            'php', 'xml', 'yml', 'yaml', 'ini', 'conf',
            'mp4', 'webm', 'ogg', 'mp3', 'wav', 'flac',
        ];
        return in_array(strtolower($ext), $previewable, true);
    }

    /** Determine preview type. */
    public static function previewType(string $ext): string
    {
        $ext = strtolower($ext);
        if ($ext === 'pdf') return 'pdf';
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg'], true)) return 'image';
        if (in_array($ext, ['mp4','webm','ogg','mp3','wav','flac'], true)) return 'media';
        if (in_array($ext, ['txt','md','log','json','csv','js','css','html','php','xml','yml','yaml','ini','conf'], true)) return 'text';
        return 'unsupported';
    }

    /** Get all tags for a user's files (distinct). */
    public static function userTags(int $userId): array
    {
        $stmt = drivew_db()->prepare(
            'SELECT t.* FROM tags t WHERE t.user_id = ? ORDER BY t.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Get tags attached to a file. */
    public static function fileTags(int $fileId, int $userId): array
    {
        $stmt = drivew_db()->prepare(
            'SELECT t.* FROM tags t
             INNER JOIN file_tags ft ON ft.tag_id = t.id
             WHERE ft.file_id = ? AND t.user_id = ?
             ORDER BY t.name'
        );
        $stmt->execute([$fileId, $userId]);
        return $stmt->fetchAll();
    }

    /** Get file versions. */
    public static function fileVersions(int $fileId, int $userId): array
    {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM file_versions WHERE file_id = ? AND user_id = ? ORDER BY version_num DESC'
        );
        $stmt->execute([$fileId, $userId]);
        return $stmt->fetchAll();
    }

    /** Get share links for a file. */
    public static function fileShares(int $fileId, int $userId): array
    {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM shares WHERE resource_type = "file" AND resource_id = ? AND user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$fileId, $userId]);
        return $stmt->fetchAll();
    }

    /** Get share links for a folder. */
    public static function folderShares(int $folderId, int $userId): array
    {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM shares WHERE resource_type = "folder" AND resource_id = ? AND user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$folderId, $userId]);
        return $stmt->fetchAll();
    }

    /** Get recent files for a user (last N). */
    public static function recentFiles(int $userId, int $limit = 10): array
    {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM files WHERE user_id = ? AND is_deleted = 0
             ORDER BY GREATEST(updated_at, created_at) DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /** Get starred files + folders combined. */
    public static function starredItems(int $userId): array
    {
        $files = drivew_db()->prepare(
            'SELECT * FROM files WHERE user_id = ? AND is_deleted = 0 AND is_starred = 1'
        );
        $files->execute([$userId]);
        $folders = drivew_db()->prepare(
            'SELECT * FROM folders WHERE user_id = ? AND is_deleted = 0 AND is_starred = 1'
        );
        $folders->execute([$userId]);
        return ['files' => $files->fetchAll(), 'folders' => $folders->fetchAll()];
    }

    /** Trash items for a user. */
    public static function trashItems(int $userId): array
    {
        $files = drivew_db()->prepare(
            'SELECT * FROM files WHERE user_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC'
        );
        $files->execute([$userId]);
        $folders = drivew_db()->prepare(
            'SELECT * FROM folders WHERE user_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC'
        );
        $folders->execute([$userId]);
        return ['files' => $files->fetchAll(), 'folders' => $folders->fetchAll()];
    }

    /** Total storage used across all users. */
    public static function totalStorageUsed(): int
    {
        $row = drivew_db()->query('SELECT COALESCE(SUM(size),0) AS total FROM files WHERE is_deleted = 0')->fetch();
        return (int)$row['total'];
    }

    /** Count of files for a user. */
    public static function countFiles(int $userId): int
    {
        $stmt = drivew_db()->prepare('SELECT COUNT(*) AS c FROM files WHERE user_id = ? AND is_deleted = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['c'];
    }

    /** Count of folders for a user. */
    public static function countFolders(int $userId): int
    {
        $stmt = drivew_db()->prepare('SELECT COUNT(*) AS c FROM folders WHERE user_id = ? AND is_deleted = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['c'];
    }

    /** Relative time string. */
    public static function humanTime(string $datetime): string
    {
        $ts = strtotime($datetime);
        $diff = time() - $ts;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
        if ($diff < 86400 * 7) return floor($diff / 86400) . ' day(s) ago';
        return date('M j, Y', $ts);
    }
}
