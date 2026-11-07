# White Gold Farm — PHP/cPanel edition

Separate PHP migration of the Next.js shop. The original Next.js project is unchanged. This application uses PHP 8.4+, PDO MySQL, MySQL 8.4 or a compatible supported MariaDB release, HTML/CSS/JavaScript and PHPMailer. No Node.js process, Supabase account or JavaScript build is needed in production.

## Hosting requirements

- Latest patched PHP 8.4 or 8.5, PDO MySQL, fileinfo, OpenSSL, mbstring and sessions.
- MySQL/MariaDB with InnoDB and utf8mb4. Use a currently supported database version supplied by the hosting provider.
- HTTPS, Apache/LiteSpeed rewrite support and outbound SMTP on port 465 or 587.
- PHP `upload_max_filesize` at least 5M and `post_max_size` at least 8M.
- Writable `storage/uploads` (typically 0750/0755 depending on host; never 0777).

## Installation on cPanel

1. Back up the existing WordPress site and database. First deploy to a staging subdomain.
2. Upload this entire project OUTSIDE public_html, for example `/home/ACCOUNT/white-gold-farm`. Set the subdomain document root to its `public` folder. Only public contents may be internet-accessible.
3. If the host fixes the document root to `public_html`, keep `app`, `storage`, `vendor`, and `config.php` in a separate private directory. Copy `public/*` (including `.htaccess`) into `public_html` and adjust the four `require` paths in index.php to that private directory. Never place the whole project inside public_html.
4. Create a new database and database user through cPanel. Import `database/schema.sql` through phpMyAdmin ONCE into an empty database. It includes sample products, not the current live Supabase catalog.
5. Copy `config.example.php` to private `config.php`. Set the domain URL, database credentials, SMTP account and owner notification email. Generate `app_key` with `php -r "echo bin2hex(random_bytes(32));"`. Keep this file out of GitHub and ZIPs. Give it restrictive filesystem permissions.
6. Dependencies are pinned in composer.lock. Run `composer install --no-dev --prefer-dist --optimize-autoloader` when deploying from Git. The deployment ZIP includes vendor for hosts without Composer.
7. Create the admin using `php bin/create-admin.php`. It prompts for email and password; do this in a private terminal. For hosts without terminal access, generate a hash using PHP `password_hash()` locally and insert the email/hash into the `admins` table with phpMyAdmin. Do not upload a public installer or store a plaintext password.
8. Open `/admin/login`. Add/edit products, prices, photos, categories and stock. Move products up/down to control storefront order. Deleting a category requires moving/removing its products first.
9. Test checkout, SMTP, delivery limits, admin permissions, image uploads and mobile layouts on staging. Point the domain only after these tests pass. Keep a rollback backup.

## Feature mapping

- Server-rendered storefront, home hero slideshow every 5 seconds, category pages, product pages, search/filter, quick view, cart drawer and localStorage cart.
- Add to basket closes quick view. Basket includes continue shopping and WhatsApp summary.
- Product prices refresh every 60 seconds and after returning to the tab. Checkout always reads current prices and stock under database locks.
- Owner login uses hashed passwords, HttpOnly SameSite cookies, session regeneration and 30-minute inactivity expiry. All mutations require CSRF tokens; admin authorization is checked server-side.
- COD checkout validates Pakistani mobile numbers, uses a honeypot and database-backed per-IP rate limits. All prices/totals are computed server-side in integer paisa. Orders/items/daily references save atomically. Idempotent request IDs prevent duplicate saves on retry.
- References use `WG-YYMMDD-001`, Pakistan date, and a transaction-protected daily sequence. Above 999 orders/day the suffix grows instead of wrapping.
- ANY basket containing category Butter, Makhan, Makhan (Butter) or Desi Ghee has no distance limit, including mixed milk orders. Other baskets require a supplied pin within 5 km straight-line distance of 33.5667,73.1499. Customer-supplied pins still need address confirmation.
- HTML and plain-text notification email; an SMTP failure marks `email_status=failed` but retains the order. Admin displays latest orders and email status.
- SEO titles/descriptions, canonical/OpenGraph tags, product schema, business schema, dynamic `/sitemap.xml` and `/robots.txt`.
- Uploads accept JPG/PNG/WebP only, validated by MIME and image inspection, up to 5 MB. Uploads are stored outside the public directory and served through `/media/` as images.

## Google Maps

Geolocation works on HTTPS and localhost. Without a Maps key the delivery coordinates have an external Google Maps link. To show an embedded map, enable Google Maps Embed API and set `maps_key`. Restrict that browser-visible key to the Maps Embed API and your HTTPS domain (plus your local test origin if needed). The server's distance check does not depend on Google Maps.

## Existing Supabase data

Do not delete Supabase before migrating and checking totals. Export these tables as JSON arrays: `categories`, `products`, `orders`, `order_items`. Combine into one private JSON object with those four keys. Run `php bin/import-supabase.php /private/path/export.json` against a new database (schema installed, no orders). The importer replaces sample products/categories inside a transaction, preserves product/order UUIDs and existing reference numbers, and initializes daily counters. A failed import rolls back. Back up both systems first.

Supabase Auth passwords cannot be transferred: create a fresh PHP admin password. Product images already in `/images/` are included. Uploaded Supabase image URLs remain external after import: download/re-upload those images using the PHP admin before retiring Supabase. Historical orders and their items retain product snapshots even when products are deleted.

## Local development

Install PHP and MySQL/MariaDB. Create the test database, import schema, copy the example config, and use a localhost URL. Run `php -S 127.0.0.1:8080 -t public router.php`. This development server is not for production.

## Operations and handover

- Owner receives admin URL/email/password; hosting/Supabase credentials are not needed for daily product edits.
- Back up the MySQL database and `storage/uploads` daily, plus private config separately. Confirm the host's restore policy.
- Keep PHP, database and Composer dependencies patched; run `composer audit` when updating.
- Logs must remain private. No credentials are included in frontend responses. Configure PHP error logging on the host with display_errors disabled.
- The bundled ZIP excludes config.php, test data, credentials, logs and existing customer information.
- Before launch, review business copy and photo licensing/accuracy, verify email delivery, and submit sitemap.xml to Search Console. No SEO ranking or Lighthouse score is guaranteed.
