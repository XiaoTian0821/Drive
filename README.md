# Drivew

A secure, responsive, web-based **digital file management & storage system** built with native PHP 8, MySQL/MariaDB (PDO), Bootstrap 5, and vanilla JavaScript. No framework required — clean, modular PHP that runs on XAMPP, bare Apache + MySQL, or cPanel shared hosting.

This project is a fresh implementation: its own branding, database schema, architecture, and UI. It is intended to be suitable as a college / senior-year project while following professional security and software-engineering practice.

---

## Features

**User**
- Secure login / logout (hashed passwords, session hardening, no public registration)
- Dashboard with stat cards, storage meter, recent files, and quick actions
- Upload (single + multiple, drag & drop, progress, MIME + extension + size + quota validation)
- Secure storage (random physical filenames, uploads outside the app, `.htaccess` script-blocking)
- Create / nest / rename / star / color / delete folders with breadcrumb navigation
- File explorer with grid and list views, move, rename, star, download, delete
- Preview (PDF, images, text, audio/video where the browser supports)
- Search across own files + folders
- Recent, Starred, and Trash (restore / permanent delete / empty)
- Tags (create, color, attach, filter)
- File version history (auto on re-upload, rollback to older versions)
- Public sharing with random tokens, optional password, expiration, view counts, and ZIP download of folders
- Profile and password management
- Per-user storage quotas

**Admin**
- Admin dashboard (users, storage, recent uploads, security events)
- User management: create, edit, activate/deactivate, delete, reset password, set quota
- System-wide file inspection
- Full audit / activity log
- System settings: app name, max upload, default quota, allowed extensions, trash retention
- Self-protection: an admin cannot delete or deactivate the last / current admin

**Security**
- PDO prepared statements everywhere (SQL-injection safe)
- `password_hash()` / `password_verify()`
- CSRF tokens on all state-changing forms
- XSS escaping on all dynamic output
- Ownership checks on every user-owned resource (never trust client IDs)
- Path-traversal protection (server-side resolution, no user-supplied paths)
- Upload-directory script execution blocked
- Session regeneration on login

---

## Technology

| Layer | Choice |
|-------|--------|
| Language | PHP 8.1+ (native, no framework) |
| Database | MySQL 5.7+ / MariaDB 10.3+ via PDO |
| UI | Bootstrap 5.3, Font Awesome 6, Google Fonts (Inter) |
| JS | Vanilla ES6+, Fetch API / XHR for uploads |
| Server | Apache (XAMPP / cPanel) |

---

## Installation (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/). Start **Apache** and **MySQL**.
2. Copy this folder into your web root, e.g. `C:\xampp\htdocs\Drive`.
3. Open the installer: `http://localhost/Drive/install/`
4. Follow the four steps:
   - **Environment** – verify PHP/extension/permissions.
   - **Database** – enter credentials (XAMPP default: host `127.0.0.1`, user `root`, empty password), pick a DB name (default `drivew_db`).
   - **Admin** – set display name, email, password, and a default storage quota.
   - **Done** – the installer writes `config/database.php`, seeds settings, and creates `install.lock`.
5. Log in at `http://localhost/Drive/login.php` with the admin you created.

> The installer refuses to run a second time unless you delete `install.lock` and pass `?force=1`. For production, delete the entire `/install` directory after installing.

## Installation (cPanel)

1. Upload the project (zip it, upload via **File Manager**, then extract).
2. **Databases → MySQL Databases**: create a database and a user, grant **All Privileges**.
3. **PHP Settings** (or a `php.ini`): confirm PHP 8.1+ and required extensions (`pdo_mysql`, `zip`).
4. Run the installer at `https://yourdomain/Drive/install/`.
5. When finished, **delete the `/install` folder** (the lock file already blocks reuse, but removal is best practice).

---

## Project structure

```
Drive/
├── index.php            # entry redirect
├── login.php / logout.php
├── dashboard.php        # user dashboard
├── files.php            # file explorer (grid/list, actions)
├── folders.php          # folder CRUD handler
├── upload.php           # upload UI + logic
├── download.php         # secure download / version download
├── preview.php          # file preview + details + share
├── search.php / recent.php / starred.php / trash.php
├── tags.php / versions.php
├── share.php            # share management
├── public_share.php / public_download.php / public_preview.php
├── profile.php
├── admin/               # admin panel (users, files, logs, settings)
├── config/              # config.php + database.php (installer-generated)
├── includes/            # Auth, Csrf, Ajax, functions, layouts, flash
├── assets/              # css + js
├── uploads/             # physical storage (+ .htaccess hardening)
├── install/             # installer + schema.sql
└── logs/                # server-side error/audit logs
```

---

## Default / demo setup

- **No credentials are hard-coded or shipped.** The installer asks for the initial admin password, so there is nothing to "brute-force" out of the source.
- After install, you log in with the admin account you defined. Create regular users from **Admin → Users → Add user**; their initial password is set by you (admin) and can be reset any time.
- If you need sample data, generate it yourself — do not reuse real or third-party sample records, and never commit real passwords.

---

## Security checklist (verify before release)

- [x] Authentication (admin + user login, wrong password, inactive user, logout, session protection)
- [x] Admin authorization (user pages blocked for non-admins)
- [x] User data isolation (every query filters by owner)
- [x] Password hashing (bcrypt via `password_hash`)
- [x] CSRF protection (all POST forms + AJAX)
- [x] SQL injection (PDO prepared statements only)
- [x] XSS (all output escaped)
- [x] Upload validation (MIME + extension + size + quota + dangerous-type block)
- [x] Upload dir cannot execute PHP (`.htaccess` + random names)
- [x] Path traversal prevention (server-side resolution, basename, realpath checks)
- [x] Unauthorized download / preview blocked (token + ownership checks)
- [x] Share token security, expiration, password, disable
- [x] Storage quota enforced pre-upload
- [x] Trash lifecycle (soft delete, restore, permanent, empty, retention)
- [x] Version history (archive + rollback, version download)
- [x] Audit logging of all significant actions

## Testing checklist

Run end-to-end across: **Authentication → Upload/Download/Preview → Folders → Quota → Search/Recent/Starred/Trash → Tags/Versions → Sharing → Admin.** Test on desktop + mobile widths, and confirm empty states render.

See `TESTING.md` for a full, structured test script.
