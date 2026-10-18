# Form-Shield

Form-Shield is a PHP contact-form application designed to detect and reduce spam submissions. It combines server-side validation, a honeypot, rate limiting, rule-based spam scoring, and known-template checks to help identify suspicious messages. An admin dashboard allows authorized users to review submissions and manage spam logs.

> **Development status:** This project is a starter implementation. Test it locally with sample messages before using it with real customer data. SMTP delivery, GeoIP lookups, and the admin actions depend on your local configuration and should be tested in your environment.

## Features

- Contact form with server-side validation
- Honeypot field and rate limiting
- Explainable spam scoring for URLs, SEO phrases, and template-like messages
- Messages scoring as suspicious or spam are held in `spam_logs` instead of being sent through the normal contact flow, unless the optional enabled AI classifier explicitly classifies an ambiguous message as legitimate
- URL display redaction: URLs in dashboard message previews are displayed as `[URL]` and are not clickable; the original message remains in the database for moderation
- Encrypted IP addresses and IP hashes, using separate secrets
- Optional country lookup through `ipwho.is` for public IP addresses
- Known-template checks stored in MySQL
- Admin dashboard with message counts, sender IP/country when available, and separate clear-suspicious / clear-all actions
- Optional SMTP notification email
- Optional AI classifier code, disabled by default

## Requirements

- PHP 8.2 or newer recommended
- MySQL or MariaDB
- Composer
- PHP extensions required by your installed dependencies and configuration, including PDO MySQL, OpenSSL, mbstring, and cURL for the GeoIP API
- A working SMTP account if you want email notifications

## Installation on XAMPP

1. Copy the project into `C:\xampp\htdocs\form`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open a terminal in the project root and install dependencies:

   ```bat
   cd C:\xampp\htdocs\form
   composer install
   ```

4. Create a local environment file by copying `.env.example` to `.env`.
5. Generate strong, separate secrets for `IP_HASH_SECRET` and `IP_ENCRYPTION_KEY` in `.env`. Do not use the example placeholders as real secrets.
6. Copy `form-shield/config/config.example.php` to `form-shield/config/config.php`. The config loads the root `.env` file through `vlucas/phpdotenv`.
7. Create the database and tables by importing `form-shield/database/schema.sql` in phpMyAdmin. The schema creates and selects the `form_shield` database.
8. Optionally import `form-shield/database/seed.sql` to add clearly labelled demo records and example known templates. Do not import demo seed data into a production database.
9. Create an admin account using the instructions below.
10. Open `http://localhost/form/` for the contact form and `http://localhost/form/form-shield/admin/login.php` for the admin login.

## Environment variables

The `.env.example` file documents the supported settings. Copy it to `.env` and edit the local copy.

| Variable | Purpose |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD` | Database connection |
| `IP_HASH_SECRET` | Secret used to create non-reversible IP hashes for rate limiting |
| `IP_ENCRYPTION_KEY` | Separate secret used to encrypt IP addresses stored for authorized moderation |
| `AI_ENABLED` | Keep `false` unless you have deliberately configured and tested AI classification |
| `OPENAI_API_KEY`, `OPENAI_MODEL` | Optional AI configuration; unused unless AI integration is enabled and wired up |
| `MAIL_ENABLED` | Set to `true` to enable SMTP notification attempts |
| `SMTP_HOST`, `SMTP_PORT` | SMTP server and port |
| `SMTP_ENCRYPTION` | `tls` for STARTTLS (commonly port 587), `ssl` for implicit TLS (commonly port 465), or `none` for local testing only |
| `SMTP_USERNAME`, `SMTP_PASSWORD` | SMTP credentials, if the server requires authentication |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender address and display name. The address should be accepted by your SMTP provider. |
| `MAIL_TO_ADDRESS` | Address that receives contact-form notifications |

Keep `.env` and `form-shield/config/config.php` private. Never commit actual credentials or secrets. If you change the IP encryption key later, existing encrypted IP addresses may no longer be decryptable; back up the key securely.

## Create an admin account

Use `form-shield/admin/create_admin.example.php` as a reference for creating a password hash and inserting an admin record. Follow the script's instructions, use a strong unique password, and remove or disable any temporary account-creation script after use. Never expose an unauthenticated admin-creation endpoint on a public server.

The admin login uses PHP sessions. Use HTTPS and secure session-cookie settings in production.

## How spam scoring works

`form-shield/src/Rules.php` currently applies these signals:

- One detected URL: +15 points
- Three or more detected URLs: an additional +25 points
- SEO / backlink promotional phrases: +30 points
- Template-like outreach phrases: +20 points

Scores are interpreted as:

- `0–29`: normal flow
- `30–59`: suspicious; held from normal delivery and logged for moderation
- `60+`: spam; held from normal delivery and logged for moderation

These are behavioral signals, not proof that a message is malicious. Scores of 30–59 are sent to the optional AI classifier only if it is enabled and configured; an explicit legitimate result allows delivery, while unavailable AI leaves the message quarantined for review. Test legitimate messages containing links and business terminology to tune the thresholds for your use case. The current URL detector looks for `http://`, `https://`, and `www.` patterns; it does not claim to identify every possible bare domain or obfuscated link.

The admin dashboard redacts detected URLs in displayed message text as `[URL]`. It does not change the original stored message or the text used by spam detection. All user content is HTML-escaped before being rendered, and displayed URLs are not linked.

## IP and country information

The app encrypts the detected `REMOTE_ADDR` IP for authorized admin display and separately hashes it for rate limiting. It does not trust forwarded proxy headers by default; this avoids accepting spoofed `X-Forwarded-For` values. If deployed behind a reverse proxy, configure trusted-proxy handling deliberately before using client IPs for security decisions.

Country lookup uses `ipwho.is` for public IP addresses. Local/private addresses such as `127.0.0.1` cannot be meaningfully geolocated by this public lookup, so country can be blank during local development. Provider availability, privacy terms, and usage limits may change; review them before production deployment. GeoIP failure should not prevent form submission.

IP addresses and contact messages are personal data in many jurisdictions. Collect only what you need, restrict admin access, set retention limits, and update your privacy notice as appropriate.

## Email notifications

SMTP notifications are disabled by default. To enable them:

1. Configure the `SMTP_*` and `MAIL_*` values in your private `.env`.
2. Set `MAIL_ENABLED=true`.
3. Use the SMTP provider's recommended host, port, and encryption setting. Prefer TLS or SSL; do not use unencrypted SMTP over an untrusted network.
4. Submit a test contact message from `http://localhost/form/`.
5. Check the recipient inbox, spam folder, and the PHP/Apache error log if delivery fails.

A legitimate submission is saved before email is attempted. If SMTP fails, the visitor sees that the message was saved but the notification could not be sent; detailed SMTP errors are written to the server error log rather than displayed publicly. The current SMTP sender supports plain-text notifications and one configured recipient; it is not a bulk-mail system.

## Admin dashboard

The dashboard shows up to 50 newest legitimate submissions and 50 newest suspicious/spam logs.

- **Clear suspicious messages** deletes rows from `spam_logs` only.
- **Clear all messages** deletes rows from both `spam_logs` and `submissions` in a transaction.
- Both actions require an authenticated admin session, POST, a CSRF token, and browser confirmation.
- These actions do not delete admin accounts or rate-limit records.

The dashboard decrypts and displays an IP only when an encrypted IP was saved and the correct `IP_ENCRYPTION_KEY` is configured. Country codes are ISO 3166-1 alpha-2 codes (for example, `EE` or `US`). Values can be absent if the request IP was unavailable or the lookup failed.

## Database files and GitHub

The repository includes a schema and optional demo seed file:

- `form-shield/database/schema.sql` — table definitions only
- `form-shield/database/seed.sql` — example data; inspect before importing

The `.gitignore` explicitly allows these two files even though SQL files are otherwise ignored. If Git still ignores one, run from the repository root:

```bat
git check-ignore -v form-shield/database/schema.sql
git status --short --ignored
```

The `git check-ignore` output identifies the matching rule. If a file was already tracked, `.gitignore` does not remove it from Git history. Do not force-add private database exports containing real messages or personal data.

## Security checklist before deployment

- [ ] Replace example secrets with unique, randomly generated values.
- [ ] Verify `.env` and `form-shield/config/config.php` are not staged or committed.
- [ ] Use HTTPS and a strong admin password.
- [ ] Keep the admin area private and use current PHP/dependencies.
- [ ] Keep CSRF protection on all state-changing admin actions.
- [ ] Do not render user-supplied content as raw HTML or clickable links.
- [ ] Review retention for legitimate submissions, spam logs, and IP data.
- [ ] Test SMTP and GeoIP failure behavior.
- [ ] Test with representative legitimate messages to reduce false positives.
- [ ] Back up the database and encryption key securely.
- [ ] Remove debug output and demo seed records before production.

## Troubleshooting

**IP address is blank in the dashboard**

Check that the incoming request has `REMOTE_ADDR`, that the `ip_encrypted` column is populated, and that the same `IP_ENCRYPTION_KEY` is used to encrypt and decrypt. Previously stored rows with `NULL` IP data cannot be reconstructed. If the key changed, old encrypted values may not decrypt.

**Country is blank**

This is expected for local/private IPs and can also happen if the API or network is unavailable. Confirm PHP cURL is enabled if testing a public IP. Country lookup is optional metadata.

**Email is not delivered**

Confirm `MAIL_ENABLED=true`, SMTP host/port/encryption, credentials, sender authorization, recipient address, and network access. Check the server error log; never paste SMTP passwords into public issue reports.

**The SQL files don't appear in GitHub**

Run `git check-ignore -v` on the exact file path, then inspect `git status --short --ignored`. Confirm you are running commands in the repository root and that the file is not a private export.

