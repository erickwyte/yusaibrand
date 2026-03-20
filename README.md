# Yusai Brand – LPG & Appliance Marketplace

Modern PHP/MySQL site for LPG cylinder sales, emergency delivery, referrals, and an admin back office. Built to run on XAMPP/LAMP with minimal dependencies.

## Quick Start
1) Clone or download the repo.  
2) Create a database and import `database.sql`.  
3) Copy `.env.example` to `.env` (see Environment) and fill credentials.  
4) Ensure `php.ini` has `extension=mysqli` enabled.  
5) Run locally (example for XAMPP): place the project in `htdocs` and open `http://localhost/yusai%20101/`.

## Environment
Create `.env` (root) with:
```
DB_HOST=localhost
DB_NAME=yusai_db
DB_USER=root
DB_PASS=

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_USERNAME=info@example.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=info@example.com
MAIL_FROM_NAME="Yusai Brand"
```
There is a separate `admin/.env` if you want distinct credentials; mirror the same keys.

## Project Structure
- `index.php` – landing with slideshow, products CTA.  
- `products.php`, `view-product.php` – catalog and detail.  
- `cart.php`, `transactions/checkout.php` – cart and checkout (MPESA hooks under `transactions/`).  
- `profile.php`, `edit-profile.php`, `referral.php` – user account and referral flows.  
- `Emergency_delivery.php` – rush delivery request.  
- `contact_us.php`, `about_us.php`, `FAQ.php`, `privacy_policy.php`, `terms&conditions.php`.  
- `include/header.php`, `include/footer.php` – shared chrome (responsive, active nav, JSON-LD).  
- `partials/product-card.php` – reusable product tile.  
- `admin/` – admin dashboard, product/black-market management, deliveries, referrals, newsletters, messages.  
- `Uploads/` – user-uploaded assets (excluded from git).  
- `css/` – page-specific styles.

## Admin Panel
- Entry: `admin/admin_dashboard.php` (requires session auth).  
- Sidebar is responsive with active-page highlighting.  
- Protect with strong credentials via your login implementation (`log_in.php` + roles).

## Dependencies
- PHP 8.0+ with mysqli, openssl, curl.  
- MySQL 5.7+/MariaDB 10+.  
- Composer (optional) for `transactions/` and `admin/` submodules:
  ```
  composer install
  cd admin && composer install
  cd ../transactions && composer install
  ```

## Database
- Import `database.sql` to provision tables for users, products, orders, referrals, rewards, messages, subscribers, slides, black-market items, and MPESA transaction logs.
- Update DB credentials in `db.php`, `admin/db.php`, and `transactions/db.php` (or point them to `.env` if you centralize loading).

## MPESA / Payments
- Callback endpoints: `transactions/b2c/b2c_callback_url.php`, timeout handlers, and `transactions/stk_push.php`.  
- Configure Safaricom credentials and URLs in the relevant transaction scripts and secure them behind HTTPS in production.

## SEO & Accessibility Notes
- Canonical/meta tags already present on `index.php`.  
- JSON-LD Organization schema injected via `include/header.php`.  
- Header includes skip link, active-nav aria-current, and accessible mobile menu toggle.
- Add descriptive `alt` text to any new images you upload to `images/`.

## Security Checklist
- Never commit `.env` (already in `.gitignore`).  
- Set proper file/folder permissions on uploads.  
- Sanitize any new SQL inputs; prefer prepared statements (existing code uses mysqli prepared queries in many areas).  
- Configure HTTPS and HSTS in production (`.htaccess` is included).

## Local Development Tips
- Error logs: `error_log`, `admin/error_log`, `transactions/error_log` (ignored by git).  
- To reset carts during testing, clear localStorage keys `cart` and `blackMarketCart`.  
- For responsive checks: header collapses at 790px; admin sidebar collapses at 768px.

## Deployment
- Point your virtual host root to the project directory.  
- Ensure `my-favicon/` and `Uploads/` are web-readable.  
- Set correct `upload_max_filesize`/`post_max_size` for product/media uploads.  
- Run `git pull` for updates and keep `vendor/` excluded; install dependencies on the server via Composer when needed.

## Contributing
- Branch from `main`, keep PHP style consistent, prefer prepared statements, and avoid inline secrets.  
- PR checklist: run `php -l` on touched files, verify header/footer included once per page, test mobile nav and admin sidebar toggles.
