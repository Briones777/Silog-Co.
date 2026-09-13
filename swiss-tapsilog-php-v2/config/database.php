<?php
declare(strict_types=1);
$host = '127.0.0.1';
$db = 'swiss_tapsilog';
$user = 'root';
$pass = '7777777';
$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  exit('Database connection failed. Check config/database.php and make sure MySQL is running.');
}
// Small compatibility upgrade for databases created by the first conversion.
try {
  // Password recovery: one outstanding code per account.
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
      user_id INT UNSIGNED NOT NULL,
      code_hash CHAR(64) NOT NULL,
      channel ENUM('email','sms') NOT NULL,
      destination VARCHAR(190) NOT NULL,
      attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (user_id),
      CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
  $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('customer','vendor') NOT NULL DEFAULT 'customer' AFTER is_guest");
  }
  $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER role");
  }
  // Customers can sign in with phone instead of email.
  $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL AFTER email");
    $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_phone (phone)");
  }
  // Menu photos: per-item image plus customer-page cover photos.
  $cols = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_url'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_url VARCHAR(255) NULL AFTER available");
  }
  $cols = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'cover_url'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE menu_items ADD COLUMN cover_url VARCHAR(255) NULL AFTER image_url");
  }
  $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'former_guest_user_id'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN former_guest_user_id INT UNSIGNED NULL AFTER user_id");
  }
  // GCash payments: chosen method, customer-submitted reference number,
  // and vendor verification timestamp.
  $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method ENUM('cash','gcash') NOT NULL DEFAULT 'cash' AFTER status");
  }
  $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_ref'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN payment_ref VARCHAR(40) NULL AFTER payment_method");
  }
  $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_verified_at'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN payment_verified_at DATETIME NULL AFTER payment_ref");
  }
  // Customer favorites: one-tap re-ordering of usual dishes.
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS favorites (
      user_id INT UNSIGNED NOT NULL,
      menu_item_id INT UNSIGNED NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (user_id, menu_item_id),
      CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT fk_fav_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
  // Editable store settings (GCash number, account name, QR image).
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS app_settings (
      k VARCHAR(60) PRIMARY KEY,
      v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
  $pdo->exec("UPDATE users SET role='vendor' WHERE email='vendor@silog.local' AND role='customer'");
} catch (Throwable $e) { /* Fresh database.sql already contains the current schema. */
}
