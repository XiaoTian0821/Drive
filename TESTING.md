# Drivew - Testing Script

A structured end-to-end test plan. Perform these against a fresh install.
Each item lists **Given / When / Then** so the expected outcome is explicit.

## 1. Installation
- **When** I open `/install/` on a clean copy → **Then** the 4-step wizard renders.
- **When** I connect with valid DB credentials → **Then** schema + settings are created and `install.lock` appears.
- **When** I reopen the installer afterwards → **Then** it is refused (403) until I delete the lock.

## 2. Authentication
| # | Action | Expected |
|---|--------|----------|
| 1 | Log in as admin with correct password | Redirected to admin dashboard |
| 2 | Log in as a regular user | Redirected to dashboard |
| 3 | Wrong password | "Incorrect email or password." |
| 4 | Inactive user | "This account is deactivated." |
| 5 | Log out, revisit a protected page | Bounced back to login |
| 6 | Regular user visits `admin/users.php` | 403 / redirected away from admin |
| 7 | No public registration exists | No register endpoint reachable |

## 3. File lifecycle
1. Upload a PDF → appears under My Files with icon, size, date.
2. Download it → correct bytes, `Content-Disposition` with original name.
3. Preview it (PDF/image/text) → renders inline; unsupported type shows fallback + download.
4. Rename → display name changes; physical stored file unchanged.
5. Move into a folder → breadcrumb + location update; original location empty.
6. Star → appears under Starred; unstar removes it.
7. Delete → moves to Trash (physical file intact); Restore brings it back.
8. Permanent delete from Trash → physical file + DB row gone; quota frees up.

## 4. Folders
1. Create root + nested folder → navigation works, breadcrumbs correct.
2. Rename folder, color it → visual + metadata update.
3. Delete a folder with contents → folder + descendants soft-deleted; restore brings all back.

## 5. Storage quota
1. Set a small quota (e.g. 1 MB) on a user.
2. Upload within quota → succeeds; progress + "X of Y" meter updates.
3. Upload that would exceed → rejected with "Storage quota exceeded."
4. Admin raises quota → upload now succeeds.

## 6. Search / Recent / Starred / Trash
- Search returns only the current user's items; folder + file results grouped.
- Recent ordered by most-recent activity; Starred lists both files & folders; Trash lists both with Restore/Delete.

## 7. Tags
- Create colored tag; attach to a file; filter view shows that file; detach; delete tag.
- User A can never see or use User B's tags.

## 8. Versions
1. Upload `report.pdf` → v1.
2. Re-upload a same-named `report.pdf` → v2 (v1 archived to `file_versions`).
3. View history → both listed; download v1; roll back to v1 → current becomes v1 contents, old current archived.

## 9. Sharing
1. Create share link (no password) → public page shows name, size, owner, views; download works.
2. Create password-protected share → wrong password rejected, correct one unlocks.
3. Set 1-day expiration → link works now; after expiry, page shows "expired."
4. Disable a share → public page shows "access disabled."
5. Folder share → lists contents; "Download folder (ZIP)" produces a valid archive.
6. **Security**: manually editing `?file_id=` does nothing; only the random token grants access.

## 10. Admin
- Create user (email + quota), edit, activate/deactivate, reset password (user can log in with it), delete.
- Changing a user's quota affects their upload capacity.
- **Protection**: the current admin cannot delete/deactivate the last admin or themselves.
- Settings (app name, max upload, allowed extensions, trash retention) save and take effect.
- Activity log captures logins, uploads, deletes, share ops, admin changes.

## 11. Security attacks (should all fail safely)
- **SQLi**: search for `'; DROP TABLE files; --` → returns 0 rows, DB intact.
- **XSS**: filename `<script>alert(1)</script>.txt` → rendered escaped, no script runs.
- **CSRF**: POST to upload/rename without a valid token → 419 rejected.
- **Path traversal**: `download.php?id=` with crafted path → 404 / no file leaked.
- **Unauth file access**: user B requests user A's `download.php?id=A` → 404.
- **Upload dir exec**: upload a `.php`, request it → blocked by `.htaccess` (403/404).

## 12. Responsive
- At mobile width the sidebar collapses to an off-canvas menu; tables scroll; file cards reflow; modals fit.

---

### Result table (fill in during testing)

| Area | Pass | Fail | Notes |
|------|:----:|:----:|-------|
| Install |  |  | |
| Auth |  |  | |
| File lifecycle |  |  | |
| Folders |  |  | |
| Quota |  |  | |
| Search/Recent/Starred/Trash |  |  | |
| Tags |  |  | |
| Versions |  |  | |
| Sharing |  |  | |
| Admin |  |  | |
| Security |  |  | |
| Responsive |  |  | |
