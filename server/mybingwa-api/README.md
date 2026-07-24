# My Bingwa payment API (cPanel PHP)

Four small PHP endpoints that let the app run M-Pesa STK Push **without keeping any
Daraja secret inside the APK**. The app calls `stk.php` and polls `status.php`;
Daraja posts the result to `callback.php`. Your Daraja credentials live only here,
on your cPanel.

```
app  ──POST stk.php────────►  starts STK, returns CheckoutRequestID
customer enters M-Pesa PIN
Daraja ──POST callback.php─►  saves paid/failed + receipt
app  ──GET  status.php─────►  paid / failed / still checking
```

Files:
- `stk.php`, `status.php`, `callback.php` — the 3 public endpoints
- `config.php` — your credentials (fill on the server, never commit real values)
- `lib.php`, `db.php`, `offers.php` — shared code (blocked from the web by `.htaccess`)
- `schema.sql` — the one database table

---

## What you need first

1. Your cPanel login (from your host).
2. A domain already on that cPanel.
3. A Safaricom **Daraja** account with: Consumer Key, Consumer Secret, Passkey,
   your Short Code / Till number. For real money you must have completed **Go Live**
   in the Daraja portal. Use **sandbox** first to test.

---

## Step 1 — Create a subdomain

cPanel → **Domains** (or **Subdomains**) → Create.
- Subdomain: `api`
- Domain: your domain
- It fills a **Document Root** like `api.yourdomain.co.ke` or `public_html/api`.
  Note that folder — your files go there.

## Step 2 — Turn on HTTPS (required)

cPanel → **Security → SSL/TLS Status** → tick the new subdomain → **Run AutoSSL**.
Wait a few minutes until it shows a padlock. Daraja will not call an `http://` URL.

## Step 3 — Create the database

cPanel → **MySQL Databases**:
1. Create a database, e.g. `mybingwa` (cPanel prefixes it, e.g. `user_mybingwa`).
2. Create a user + password (save them).
3. Under **Add User To Database**, add the user with **All Privileges**.
   Write down the final DB name, user and password.

## Step 4 — Create the table

cPanel → **phpMyAdmin** → click your database → **Import** → choose `schema.sql` →
**Go**. (Or open the **SQL** tab and paste the contents of `schema.sql`.)

## Step 5 — Upload the files

cPanel → **File Manager** → open your subdomain's Document Root (from Step 1) →
**Upload**. Upload all of these into that folder:
`stk.php`, `status.php`, `callback.php`, `config.php`, `lib.php`, `db.php`,
`offers.php`, `.htaccess`.
(You do not need to upload `schema.sql` or this README, but it's harmless if you do —
`.htaccess` blocks them.)

> If File Manager hides `.htaccess`, click **Settings** (top-right) → tick
> **Show Hidden Files**.

## Step 6 — Fill in config.php

In File Manager, right-click `config.php` → **Edit**. Replace every `PUT_...`:
- `app_key` → invent a long random string (keep it; the app needs the same one).
- `daraja_env` → `sandbox` to test, later `production`.
- `consumer_key`, `consumer_secret`, `passkey`, `business_shortcode` → from Daraja.
- `party_b` → your Till number (the number that receives the money).
- `transaction_type` → `CustomerBuyGoodsOnline` for a Till.
- `callback_url` → `https://api.yourdomain.co.ke/callback.php` (your real subdomain).
- `db_name`, `db_user`, `db_pass` → from Step 3.
Save.

## Step 7 — Tell Daraja your callback

In the Daraja portal for your app/short code, set the **STK CallbackURL** to
`https://api.yourdomain.co.ke/callback.php`.

## Step 8 — Point the app at your API

Add two **GitHub repository secrets** (GitHub → repo → Settings → Secrets and
variables → Actions → New repository secret):
- `PAYMENTS_BASE_URL` = `https://api.yourdomain.co.ke/`  (keep the trailing slash)
- `PAYMENTS_APP_KEY`  = the same `app_key` you set in `config.php`

The CI build reads these and bakes the **URL** (not any Daraja secret) into the app.
The next debug APK from Actions will call your API. With no secrets set, the app
falls back to the built-in simulation.

## Step 9 — Test

1. In a browser open `https://api.yourdomain.co.ke/status.php`. You should see a
   JSON error like `{"status":"PAYMENT_FAILED","errorCode":"UNAUTHORISED"}`.
   That error is **good** — it proves the endpoint is live and the app-key guard works.
2. Install the new debug APK, choose an offer for **your own number**, pay. Your
   phone should get the M-Pesa prompt; after you enter your PIN the app shows
   **Payment received**. Check the `payments` table in phpMyAdmin to see the row.

---

## Sandbox vs production
- **Sandbox** uses Safaricom test credentials and test phone numbers (no real money).
- Switch `daraja_env` to `production` and use your live credentials only after Go Live.

## Managing offers later
`offers.php` is the price list the server trusts. When you want to manage prices from
cPanel, replace its array with a `SELECT` from an `offers` table — the endpoints don't
change. Buy-for-another can be added the same way later.

## If something fails
- `TOKEN_FAILED` → wrong consumer key/secret, or wrong `daraja_env`.
- `STK_REJECTED` → wrong shortcode/passkey/till, or not Go-Live for production.
- App stuck on "Still checking" → callback not reaching you; check the CallbackURL is
  exactly your `callback.php` and HTTPS works. `status.php` also falls back to a direct
  Daraja query, so it should still resolve within ~30s.
- `DB_UNAVAILABLE` → wrong db name/user/password in `config.php`.
