<?php
/**
 * Drivew - Folder management.
 * Handles create / rename / delete / color via POST (with CSRF).
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $name = trim((string)($_POST['name'] ?? ''));
            $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            if ($name === '' || mb_strlen($name) > 180) {
                flash('danger', 'Folder name is required (max 180 chars).', 'circle-exclamation');
                break;
            }
            // If a parent is given, verify ownership + not deleted + not circular
            if ($parentId !== null) {
                $parent = \Drivew\F::folderFor($parentId, $uid);
                if ($parent === null || $parent['is_deleted']) {
                    flash('danger', 'Parent folder not found.', 'circle-exclamation');
                    break;
                }
            }
            // Prevent duplicate name in same parent
            $chk = drivew_db()->prepare('SELECT id FROM folders WHERE user_id = ? AND parent_id <=> ? AND name = ? AND is_deleted = 0');
            $chk->execute([$uid, $parentId, $name]);
            if ($chk->fetch()) {
                flash('warning', 'A folder with that name already exists here.', 'circle-exclamation');
                break;
            }
            $ins = drivew_db()->prepare(
                'INSERT INTO folders (user_id, parent_id, name, color, is_starred, is_deleted, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, 0, 0, NOW(), NOW())'
            );
            $ins->execute([$uid, $parentId, $name]);
            \Drivew\F::log($uid, 'create_folder', 'folder', (int)drivew_db()->lastInsertId(), 'Created folder "' . $name . '"');
            flash('success', 'Folder created.', 'folder-plus');
            break;

        case 'rename':
            $fid = (int)($_POST['id'] ?? 0);
            $folder = \Drivew\F::folderFor($fid, $uid);
            if ($folder === null) { flash('danger', 'Folder not found.', 'circle-exclamation'); break; }
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 180) {
                flash('danger', 'Invalid folder name.', 'circle-exclamation'); break;
            }
            $stmt = drivew_db()->prepare('UPDATE folders SET name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$name, $fid, $uid]);
            \Drivew\F::log($uid, 'rename_folder', 'folder', $fid, 'Renamed folder to "' . $name . '"');
            flash('success', 'Folder renamed.', 'pen');
            break;

        case 'delete':
            $fid = (int)($_POST['id'] ?? 0);
            $folder = \Drivew\F::folderFor($fid, $uid);
            if ($folder === null) { flash('danger', 'Folder not found.', 'circle-exclamation'); break; }
            // Soft-delete the folder and all descendants + contained files
            $stmt = drivew_db()->prepare('UPDATE folders SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?');
            $queue = [$fid];
            $childStmt = drivew_db()->prepare('SELECT id FROM folders WHERE parent_id = ? AND user_id = ?');
            $fileStmt = drivew_db()->prepare('UPDATE files SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE parent_folder_id = ? AND user_id = ?');
            while ($queue) {
                $c = array_shift($queue);
                $stmt->execute([$c, $uid]);
                $fileStmt->execute([$c, $uid]);
                $childStmt->execute([$c, $uid]);
                foreach ($childStmt->fetchAll() as $child) { $queue[] = (int)$child['id']; }
            }
            \Drivew\F::log($uid, 'delete_folder', 'folder', $fid, 'Deleted folder "' . $folder['name'] . '"');
            flash('success', 'Folder moved to trash.', 'trash-can');
            break;

        case 'color':
            $fid = (int)($_POST['id'] ?? 0);
            $folder = \Drivew\F::folderFor($fid, $uid);
            if ($folder === null) break;
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? (string)$_POST['color'] : null;
            $stmt = drivew_db()->prepare('UPDATE folders SET color = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$color, $fid, $uid]);
            break;

        case 'star':
            $fid = (int)($_POST['id'] ?? 0);
            $folder = \Drivew\F::folderFor($fid, $uid);
            if ($folder === null) break;
            $stmt = drivew_db()->prepare('UPDATE folders SET is_starred = 1 - is_starred, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$fid, $uid]);
            break;
    }
    header('Location: files.php');
    exit;
}

// GET: just render redirect to files.php (folder management lives in the explorer)
header('Location: files.php');
exit;
