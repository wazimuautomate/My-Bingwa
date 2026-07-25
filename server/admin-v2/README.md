# My Bingwa Admin V2

A clean, layered PHP admin for the My Bingwa app: offers, billboard adverts, notification
campaigns, Safaricom message templates, payments (read-only), support/payment details,
remote app configuration, version/update rules, an append-only audit log, RBAC with
2FA-ready auth, and a **draft → publish → rollback** workflow that produces immutable,
signed configuration snapshots served by a versioned **sync API**.

Built to run on plain cPanel PHP 8.2+ with **no Composer/Node dependency at runtime** —
upload the folder, add `config.php`, open the installer.

## Layout

```
admin-v2/
  index.php              front controller (all routes)
  config/config.sample.php
  app/
    Core/                kernel: Router, Request, Response, View, Auth, Rbac, Csrf,
                         Session, Database, Config, Crypto, Totp, Signer, Snapshot, Audit
    Controllers/         one per sidebar module + Api/SyncController
    Repositories/        Offer + Payment (read-only legacy payments)
    Services/            Publishing, Billboard(+personalisation), TemplateMatcher,
                         ImageUploader, Gateway, RateLimiter, Settings, RollbackRestorer
    Views/               server-rendered pages (+ partials, layout)
    Support/             helpers, Icons (inline SVG), Csv
  database/
    migrations/*.sql     schema (mb_ prefixed)   migrate.php   seed.php   seed_data.php
  assets/                pre-built css + js (self-drawn SVG charts, no chart lib)
  uploads/               billboard images (non-executable)
  cutover/               opt-in bridges for the legacy payment API
  tests/run.php          dependency-free pure-logic tests
  bin/import_legacy.php  legacy → mb_* importer (dry-run + apply)
```

## Key properties

- **Coexists** with `server/mybingwa-api` in the same DB via the `mb_` prefix; reads the
  legacy `payments` table read-only; never modifies legacy data.
- **Draft/publish/rollback:** working tables → validated → immutable
  `mb_configuration_releases` (versioned, SHA-256 checksum, RSA signature) → audit.
- **Sync API** (`/api/v1/app/*`) serves published, app-safe data only; ETag/`304`;
  rate-limited; signed. Backward-compatible offers/config/templates shapes included.
- **Security:** session auth, bcrypt/argon2 hashing, CSRF on every write, TOTP 2FA,
  login throttling, session rotation, re-auth for payment routes/rollback/forced updates,
  server-side RBAC, output escaping, prepared SQL, safe image re-encoding, CSP + security
  headers, secrets encrypted at rest.

## Run

See `docs/ADMIN_V2_DEPLOYMENT.md` (deploy), `docs/APP_SYNC_CONTRACT.md` (Android handoff),
`docs/MIGRATION_CUTOVER.md` (import/cutover/rollback + the payment-gateway bridge).

```bash
php database/migrate.php && php database/seed.php   # or open /admin/install
php tests/run.php
```
