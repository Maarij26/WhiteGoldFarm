# Validation record

Local runtime: PHP 8.4.27 and MariaDB 11.4.8 on an isolated test database.

Passed in local testing:
- PHP and JavaScript syntax checks.
- Home, catalog, product/category pages, contact, about, sitemap and robots HTTP checks.
- Admin login/logout and unauthenticated redirects.
- CSRF rejection; private configuration unavailable over HTTP.
- Product/category creation, price/stock editing, reorder and deletion; used categories cannot be deleted.
- Out-of-stock order rejection; milk-only orders outside 5 km rejected.
- Mixed milk + ghee order accepted without radius restriction.
- Server-calculated Rs. 2,280 total despite a different browser-supplied total.
- Duplicate submission reuses the saved order/reference.
- Saved order survives intentional SMTP failure and is marked failed.
- Composer audit during dependency installation reported no advisories. A later repeat could not reach Packagist because DNS resolution failed.

Before launch:
- Test real SMTP; no real email was sent in local testing.
- Test cPanel routing, TLS, uploads and Google Maps key on staging.
- Verify desktop/mobile appearance and keyboard flows in a real browser. Automated browser preview was unavailable; no visual QA or Lighthouse score is claimed.
- Export/import and reconcile real Supabase data. The importer has not been run against live customer data.

The deployment archive contains sample products and no credentials or customer records. Final syntax checks passed; repeat HTTP checks after the session resumed were blocked by local network permissions.
