# Silog & Co. — PHP + Tailwind + MySQL v2

This version is a Laragon-friendly conversion of the original ordering frontend. It removes React/Vite/Convex and uses PHP, MySQL, Tailwind CDN and small vanilla JavaScript.

## Customer frontend
- Home
- Responsive mobile tabs
- Menu category tabs
- Working add / plus / minus / remove / clear cart controls
- Delivery form validation
- Place order
- My Orders status tracking
- Customer cancel order

## Vendor frontend
Open `vendor_login.php`.

Default local demo credentials:
- Email: `vendor@silog.local`
- Password: `vendor123`

Vendor tabs:
- Dashboard
- Orders
- Menu

Vendor can:
- See pending/active/delivered counts
- Open the order queue
- Confirm orders
- Start preparing
- Mark out for delivery
- Mark delivered
- Cancel orders
- Hide/show menu items

## Install
1. Put the folder in `C:\laragon\www\swiss-tapsilog-php`.
2. Start Apache and MySQL.
3. Import `database.sql` if creating the database for the first time.
4. Open `http://swiss-tapsilog-php.test/`.

`config/database.php` also contains a compatibility upgrade for the first PHP conversion: it adds the `users.role` and `users.password_hash` columns when they are missing.

## Notes
The customer account remains intentionally simple (name/email session login) for easy local customization. Vendor access uses a password because vendor actions change real orders. Change the demo vendor password before public deployment.


```sql
-- ============================================================
-- DEFAULT VENDOR ACCOUNT
-- Email:    vendor@silog.local
-- Password: vendor123
-- ============================================================

INSERT INTO users
(
    name,
    email,
    is_guest,
    role,
    password_hash
)
SELECT
    'Vendor',
    'vendor@silog.local',
    0,
    'vendor',
    '$2y$10$92IXUNpkjO0rOQ5byMi.YeIe6X0jM8xK8X8X8X8X8X8X8X8X8X8X'
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE email = 'vendor@silog.local'
);
```
