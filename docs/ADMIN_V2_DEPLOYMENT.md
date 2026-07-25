# My Bingwa Admin V2 — cPanel Deployment Guide

Admin V2 is plain PHP 8.2+ with **no runtime Composer/Node dependency**. You upload the
folder, create `config.php`, and open the installer. CSS/JS are pre-built and committed.

---

## 0. What you upload

The whole `server/admin-v2/` folder. It coexists with the existing `server/mybingwa-api/`
in the **same MySQL database** using the `mb_` table prefix, and it reads the existing
`payments` table read-only. Nothing in the legacy app is modified.

---

## 1. Requirements

- PHP **8.2+** with PDO MySQL, OpenSSL and GD (for billboard images).
- MySQL 8 / MariaDB 10.4+.
- HTTPS (cPanel AutoSSL is fine).

---

## 2. Upload

1. In cPanel **File Manager**, upload `server/admin-v2/` into `public_html`, e.g. as
   `public_html/admin`. You will open it at `https://your-domain/admin/`.
2. Confirm the folder contains `index.php`, `.htaccess`, `app/`, `config/`, `database/`,
   `assets/`, `uploads/`, `tests/`.
3. Ensure `uploads/` and `storage/` are writable by PHP (755 is usually fine on cPanel;
   the app creates `uploads/` on first image upload).

If mod_rewrite is unavailable on your host, tell your host to enable it, or open the app
at `https://your-domain/admin/index.php/…` (the router still works via `SCRIPT_NAME`).

---

## 3. Configure

1. Copy `config/config.sample.php` to `config/config.php`.
2. Fill in:
   - `app_key` — a long random string (`bin2hex(random_bytes(32))`). **Never change it
     casually** — it encrypts stored secrets (2FA, SMS key).
   - `db.*` — the **same** database used by the payment API. Keep `prefix` = `mb_`.
   - `bootstrap_admin` — your name/email; leave `password` blank to get a generated one
     shown once during install.
   - `environment` — `production`.

`config/config.php` is git-ignored and blocked from the web by `.htaccess`. For extra
safety you may place it **outside** `public_html` and point the env var
`MYBINGWA_ADMIN_CONFIG` at its absolute path.

---

## 4. Install (create tables + first Super Admin)

- **Web:** open `https://your-domain/admin/install` and click **Install now**. Save the
  one-time Super Admin password shown. Then sign in at `/admin/login`.
- **CLI (SSH), optional:** `php database/migrate.php` then `php database/seed.php`.

The installer is safe to re-run; it only creates the Super Admin when none exists.

---

## 5. Enable snapshot signing (recommended before publishing)

The app verifies published config with a public key. Generate the keypair **once**,
outside the repo, ideally outside the web root:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out mybingwa_admin_private.pem
openssl rsa -in mybingwa_admin_private.pem -pubout -out mybingwa_admin_public.pem
```

Point `signing.private_key_path` (and `public_key_path` for the health check) at the PEM
files in `config/config.php`. Keep an encrypted offline backup of the private key. Embed
**only** the public key in the Android app. Without a key, publishing still works
(checksum-only, marked **unsigned**).

---

## 6. First publish

1. Sign in → **Offers / Support / App configuration** are pre-seeded to match the app.
2. Go to **Review & publish** → **Publish now**. This creates **v1**, an immutable signed
   snapshot the sync API serves.

---

## 7. Verify

- `https://your-domain/admin/api/v1/health` → `{ ok: true, configVersion: 1, signed: … }`.
- `https://your-domain/admin/api/v1/app/manifest` → the manifest with an `ETag`.
- Sign out / permissions / 2FA all reachable under **Settings**.

---

## 8. Hardening checklist

- [ ] `config/config.php` present, not downloadable (`/admin/config/config.php` → 403).
- [ ] `/admin/app/…`, `/admin/database/…`, `/admin/storage/…` → 403.
- [ ] Uploads dir serves images but not scripts (`/admin/uploads/x.php` → denied).
- [ ] HTTPS enforced; cookies are `Secure`, `HttpOnly`, `SameSite=Lax`.
- [ ] Super Admin has 2FA enabled (Settings → Two-factor).
- [ ] Signing key configured and backed up.
- [ ] `app_key` is long and unique.

---

## 9. Updating later

Upload the changed files, then run migrations from **Settings → Run DB migrations**
(Super Admin) or `php database/migrate.php`. Migrations are additive and idempotent.

---

## 10. Tests (CI or SSH)

```bash
php tests/run.php
```

Pure-logic tests (canonical JSON, signing checksum, TOTP, crypto, regex safety,
billboard scoring, publish validation, CSV safety, masking) run without a database.
