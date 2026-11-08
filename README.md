# Campus Lost & Found System

A web application where university students report lost belongings, report items they have found, search listings, message each other, and recover items through a verified claim and handover process. Custodians (staff) verify ownership and hand items over. Administrators manage users, content and the platform.

Built for the **Web Technologies** course using Core PHP, MySQL, HTML, CSS and vanilla JavaScript on an Ubuntu LAMP stack.

**Authors:** Ali Hamza, Waleed
**Department:** Computer Science
**Submitted to:** Nouman Shafi

---

## Table of contents

1. [Features](#features)
2. [Technology stack](#technology-stack)
3. [Roles](#roles)
4. [Project structure](#project-structure)
5. [Database](#database)
6. [Local development setup (Ubuntu)](#local-development-setup-ubuntu)
7. [Configuration](#configuration)
8. [Creating the first admin](#creating-the-first-admin)
9. [Business rules and security design](#business-rules-and-security-design)
10. [Testing](#testing)
11. [Production deployment](#production-deployment)
12. [Backups and maintenance](#backups-and-maintenance)
13. [Development history (modules)](#development-history-modules)
14. [Optional features not built](#optional-features-not-built)
15. [Known limitations](#known-limitations)

---

## Features

**Accounts**
- Registration restricted to the university email domain (checked on the server). Registration always creates a student.
- Email verification with single-use, expiring tokens (only a SHA-256 hash is stored).
- Login and logout with throttling, profile updates, password change.
- Unverified or suspended users can browse but cannot post, claim, message or report.

**Listings and search**
- Lost and found listings with title, category, campus location, date and description.
- Up to 4 images per listing, validated by real content type, re-encoded, stored under random names.
- Private hidden detail on found items (for example a serial number), used only for verification.
- Edit while open, close, or mark lost items recovered.
- Search by keyword and filter by type, category, location, date range and status, with pagination and an image gallery.
- Duplicate listing warning and block, plus single-use form tokens against double submission.

**Claims and evidence**
- Verified students (not the poster) claim found items with a private statement and optional proof file.
- Claimants can track and withdraw pending claims.
- Custodians and admins review all claims for an item together, then approve one or reject with a written reason.
- Approval runs in one locked database transaction and automatically rejects the other claims.
- Private evidence has fixed viewers, is stored outside the web root, is served only by an authorised PHP endpoint, and every successful view is audit logged.

**Handovers**
- Custodians schedule and reschedule handovers, confirm the claimant's ID in person, and complete the handover (item becomes recovered).
- Written ID-check notes are required for high-value items or items with several claims.
- An approval can be revoked before completion (for example a no-show), which reopens the item.

**Messaging and notifications**
- Private conversations per listing under a strict permission rule.
- Notifications for claims, decisions, messages, handovers, recovery and report updates, with unread counts and mark-as-read.

**Moderation and administration**
- Report listings, messages and users. Admins dismiss, resolve, remove content or suspend users.
- Admin dashboard with real counts, user management (suspend, reactivate, change role), custodian creation, category and location management, listing removal, and a read-only audit log viewer.
- Suspending a user withdraws their pending claims. The last active admin cannot be suspended or demoted. An admin cannot moderate a report about themselves.

---

## Technology stack

| Layer | Technology |
|---|---|
| Server | Ubuntu Linux, Apache 2 |
| Backend | Core PHP 8.x, PDO (prepared statements), PHP sessions |
| Database | MySQL 8.0.16+ (InnoDB, utf8mb4, CHECK constraints) |
| Frontend | HTML5, CSS3, vanilla JavaScript |
| Tools | VS Code, Git, GitHub |

Not used, by design: PHP frameworks, front-end frameworks, Node.js backend, ORMs, UI frameworks, Firebase or Supabase.

---

## Roles

| Role | Can do |
|---|---|
| **Guest** | View the home page, register, log in |
| **Student** | After verification: post listings, search, claim found items, message, report, manage profile |
| **Custodian** | Everything a verified user can, except claiming. Reviews claims and evidence, approves/rejects/revokes, schedules and completes handovers |
| **Admin** | Custodian abilities plus user management, custodian creation, role changes, categories and locations, report moderation, content removal, audit logs |

Custodian and admin accounts can only be created by an admin (or the one-time CLI script for the first admin).

---

## Project structure

```
campus-lost-found/
├── public/                  The ONLY web-accessible folder (Apache DocumentRoot)
│   ├── index.php            Front controller
│   ├── .htaccess            URL rewriting
│   ├── assets/css|js        Stylesheet and scripts
│   └── uploads/items/       Public listing images (script execution blocked)
├── app/
│   ├── bootstrap.php        Error handling, headers, session, autoloading of app files
│   ├── helpers.php          Config, escaping, CSRF, flash messages, views
│   ├── database.php         PDO connection
│   ├── router.php, routes.php
│   ├── controllers/         Request handling
│   ├── models/              SQL queries (explicit columns)
│   ├── services/            Business rules and transactions
│   └── views/               Escaped PHP templates
├── config/                  app.php, limits.php (all rate limits in one place)
├── database/                schema.sql, seed.sql, audit_triggers.sql
├── scripts/                 create_admin.php, cleanup.php, backup.sh, send_test_mail.php
├── storage/
│   ├── evidence/            Private files, OUTSIDE the web root
│   └── logs/                Application and mail logs
├── tests/                   official.sh (T-01..T-20), helper and CLI test scripts
├── .env                     Secrets (never committed)
└── .env.example
```

---

## Database

14 InnoDB tables, created in this order: `users`, `email_verifications`, `categories`, `campus_locations`, `items`, `item_images`, `claims`, `private_item_evidence`, `handovers`, `messages`, `notifications`, `reports`, `audit_logs`, `rate_limits`.

Highlights:
- All foreign keys use `ON DELETE RESTRICT`. Rows are never hard-deleted.
- `UNIQUE (item_id, claimant_id)` on claims: one claim per user per item.
- CHECK constraints: reviewer is not the claimant, evidence type matches claim link, completed handover has a completion time, no self-messages, reports name a target.
- `UNIQUE (claim_id)` on handovers: one handover per claim.
- `audit_logs` has no foreign key on its target, and optional triggers make it append-only.
- `rate_limits` is standalone, so guests and unknown emails can be throttled.

---

## Local development setup (Ubuntu)

```bash
# Packages
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-mysql php-mbstring php-xml php-gd git mysql-server
sudo a2enmod rewrite headers

# Database and user
sudo mysql -e "CREATE DATABASE clf_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'clf_user'@'localhost' IDENTIFIED BY 'CHOOSE_A_PASSWORD';
GRANT ALL PRIVILEGES ON clf_db.* TO 'clf_user'@'localhost'; FLUSH PRIVILEGES;"

# Code
git clone https://github.com/YOUR_USERNAME/campus-lost-and-found.git /var/www/clf
cd /var/www/clf
mkdir -p storage/evidence storage/logs public/uploads/items

# Schema and seed data
mysql -u clf_user -p clf_db < database/schema.sql
mysql -u clf_user -p clf_db < database/seed.sql
sudo mysql clf_db < database/audit_triggers.sql      # optional: read-only audit log

# Environment
cp .env.example .env && nano .env                    # set DB_PASS and APP_URL
```

Apache virtual host (`/etc/apache2/sites-available/clf.conf`):

```apache
<VirtualHost *:80>
    ServerName clf.local
    DocumentRoot /var/www/clf/public
    <Directory /var/www/clf/public>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/clf_error.log
    CustomLog ${APACHE_LOG_DIR}/clf_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite clf.conf && sudo systemctl reload apache2
echo "127.0.0.1 clf.local" | sudo tee -a /etc/hosts
```

PHP settings (`/etc/php/<version>/apache2/conf.d/99-clf.ini`):

```ini
upload_max_filesize=3M
post_max_size=16M
max_file_uploads=10
pcre.jit=0        ; only needed where the OS blocks JIT memory
```

Permissions: you own the files, Apache's group reads them, and only `storage/` and `public/uploads/` are writable by Apache.

```bash
sudo chown -R $USER:www-data /var/www/clf
sudo find /var/www/clf -type d -exec chmod 750 {} \;
sudo find /var/www/clf -type f -exec chmod 640 {} \;
sudo find storage public/uploads -type d -exec chmod 2770 {} \;
sudo find storage public/uploads -type f -exec chmod 660 {} \;
```

Open `http://clf.local/`. In development, emails are written to `storage/logs/mail.log`.

---

## Configuration

All settings come from `.env` (see `.env.example`):

| Setting | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG` | Use `production` and `false` in production (hides error details and `/health`) |
| `APP_URL` | Public base URL, no trailing slash |
| `APP_TIMEZONE` | Used for dates and handover times (default `Asia/Karachi`) |
| `ALLOWED_EMAIL_DOMAIN` | Registration domain (default `pucit.edu.pk`) |
| `DB_*` | Database connection |
| `MAIL_DRIVER` | `log` (development) or `smtp` |
| `MAIL_HOST/PORT/ENCRYPTION/USER/PASS/FROM` | SMTP settings |
| `TRUSTED_PROXIES` | Comma-separated proxy addresses. Leave empty without a proxy |

Rate limits live in `config/limits.php`:

| Action | Limit |
|---|---|
| Failed logins | 5 per 15 minutes, per email and per IP |
| Registrations | 5 per hour per IP |
| Verification emails | 3 per hour |
| Listings | 5 per day per user |
| Claims | 10 per day per user |
| Messages | 20 per hour per user |
| Reports | 10 per day per user |

---

## Creating the first admin

Registration only creates students, so the first admin is created from the terminal:

```bash
php scripts/create_admin.php
```

It asks for the email, name and a hidden password, stores only the hash, and writes an audit row. After that, admins create custodians from **Admin, Create custodian**.

---

## Business rules and security design

| Area | Implementation |
|---|---|
| Passwords | `password_hash()` / `password_verify()`, rehash on login |
| Sessions | HttpOnly, SameSite=Lax, Secure over HTTPS, ID regenerated on login/logout/password change, 30-minute idle timeout |
| Fresh account state | Role, verification and suspension are re-read from the database on every request, never trusted from the session |
| CSRF | Token on every POST form, verified server-side |
| Double submit | Single-use form tokens |
| SQL injection | PDO prepared statements only, whitelisted values |
| XSS | All output escaped with `e()`, Content-Security-Policy without inline code |
| Uploads | Real MIME check, size and pixel limits, GD re-encoding, random names, script execution blocked in the uploads folder |
| Private evidence | Outside the web root, served by an authorised endpoint, denied requests return 404, every successful view audit logged before output |
| Race safety | `SELECT ... FOR UPDATE` on the item, guarded `UPDATE ... WHERE status = ...`, exactly-one-affected-row checks, rollback on any failure |
| Audit | Written in the same transaction as the change, optional database triggers make the table append-only, only admins can read it |
| Rate limiting | Database-backed counters in `rate_limits`, with expired rows purged |
| Errors | Generic message to users, details only in `storage/logs/app.log` |

Approval of a claim follows the project documentation exactly: begin, lock the item, verify it is a found item that is open or claim pending, verify the reviewer is staff and is neither claimant nor poster, approve only if the claim is still pending or under review, reject the other active claims, set the item to ready for handover, write audit rows and notifications, commit.

---

## Testing

The official test cases T-01 to T-20 from the project documentation are automated:

```bash
bash tests/official.sh
```

It creates its own test users and listings, runs each case against the running site and database, and prints `PASS` or `FAIL` per check with a summary. Supporting scripts:

| Script | Purpose |
|---|---|
| `tests/db_constraints.sql` | Proves the database constraints reject bad data |
| `tests/csrf_sweep.sh` | Sends every POST route without a CSRF token (all must return 403) |
| `tests/approve_cli.php`, `handover_cli.php`, `admin_cli.php`, `moderation_cli.php` | Call the real service functions from the terminal, used for concurrency tests |
| `tests/helpers.sh` | curl login helpers |

Do not run `official.sh` against production: it creates test accounts and targets the development URL.

Record your own results before claiming completion. This README does not state test results.

---

## Production deployment

Summary (full commands were provided in Module 11):

1. Install Apache, PHP, MySQL, Certbot and UFW. Enable `rewrite`, `headers`, `ssl`.
2. Clone the repository into `/var/www/clf-prod` and create `storage/evidence`, `storage/logs`, `public/uploads/items`.
3. Create a fresh `clf_prod` database. Load `schema.sql`, `seed.sql` and `audit_triggers.sql` as MySQL root.
4. Create the least-privilege user `clf_app` (`SELECT, INSERT, UPDATE, DELETE` only) and a read-only `clf_backup` user.
5. Write a production `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, SMTP settings).
6. Apply file permissions (code read-only for Apache, only `storage/` and `public/uploads/` writable).
7. Set PHP production settings (`display_errors=Off`, `log_errors=On`, `expose_php=Off`, upload limits, session lifetime).
8. Configure the Apache virtual host with `DocumentRoot` set to `public/`, enable HTTPS (Let's Encrypt) with HTTP redirect, and hide server version details.
9. Create the real admin with `php scripts/create_admin.php`.
10. Install backups and cron (below), log rotation, and verify end to end.

Updating production later:

```bash
cd /var/www/clf-prod
git pull origin main
# re-apply ownership and permissions, then:
sudo systemctl reload apache2
```

Take a backup before every update. Schema changes are applied as MySQL root, never by the application user.

---

## Backups and maintenance

- `scripts/backup.sh` is installed root-owned as `/usr/local/sbin/clf-backup`. It dumps the database (with triggers) and archives `storage/evidence` and `public/uploads/items` to `/var/backups/clf`, keeping 14 days.
- Cron (`/etc/cron.d/clf`): backup at 02:30, `scripts/cleanup.php` (expired rate-limit rows) at 03:15.
- Backups contain private evidence. Protect them and copy them off the server.
- **Restore test:** load the latest dump into a scratch database, compare row counts and triggers with production, then drop it.

```bash
sudo mysql -e "CREATE DATABASE clf_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
zcat /var/backups/clf/db-YYYYMMDD-HHMMSS.sql.gz | sudo mysql clf_restore_test
sudo mysql -e "DROP DATABASE clf_restore_test;"
```

- Logs: `storage/logs/app.log`, `mail.log`, `cron.log`, rotated weekly. Apache logs are in `/var/log/apache2/`.

---

## Development history (modules)

| Module | Content |
|---|---|
| 0 | Ubuntu LAMP environment and Git |
| 1 | Project foundation (config, PDO, router, layout, helpers) |
| 2 | Database: 14 tables, constraints, seed data |
| 3 | Accounts: registration, verification, login, throttling, profile |
| 4 | Listings, images, hidden detail, duplicate detection, form tokens |
| 5 | Search, filters, pagination, item details, dashboard |
| 6 | Claims, race-safe approval, private evidence |
| 7 | Custodian handovers, revocation, completion |
| 8 | Messaging and notifications |
| 9 | Admin tools, suspension, roles, lookups, audit viewer, reports and moderation |
| 10 | Hardening and the official test run |
| 11 | Production deployment |

Use `git log --oneline --graph --decorate` to see the real commit history.

---

## Optional features not built

Per the project documentation these are enhancements beyond the MVP:

- **FR-05** Password reset by emailed token
- **FR-25** Suggested matches between lost and found listings
- **FR-26** Auto-close found items after 90 days and delete evidence files 30 days after recovery
- **FR-27** Basic statistics page for admins

---

## Known limitations

- Rate limiting uses fixed windows, so a burst across a window edge can briefly exceed a limit.
- Verification emails depend on SMTP configuration and on SPF/DKIM DNS records for deliverability.
- The built-in SMTP sender does not retry failed messages.
- Backups live on the same server unless copied elsewhere.
- Removed listings keep their image files on disk (rows and files are never hard-deleted).
- There is no deactivate-account screen; the `deactivated` status exists only in the schema.
- Notifications and messages update on page load, not live.
- Concurrency tests call the same service functions as the website via the CLI rather than using two browsers.
