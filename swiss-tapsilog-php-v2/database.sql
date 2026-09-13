-- ============================================================
-- SWISS TAPSILOG DATABASE
-- Clean MySQL Schema + Initial Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS swiss_tapsilog
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE swiss_tapsilog;


-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NULL,

    email VARCHAR(190) NULL UNIQUE,

    phone VARCHAR(32) NULL UNIQUE,

    is_guest TINYINT(1) NOT NULL DEFAULT 0,

    role ENUM('customer', 'vendor')
        NOT NULL DEFAULT 'customer',

    password_hash VARCHAR(255) NULL,

    guest_token_hash CHAR(64) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_guest_token (guest_token_hash),

    UNIQUE KEY uq_users_phone (phone),

    INDEX idx_users_guest_role (is_guest, role)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- MENU ITEMS
-- ============================================================

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    description TEXT NOT NULL,

    price DECIMAL(10,2) NOT NULL,

    category VARCHAR(80) NOT NULL,

    featured TINYINT(1) NOT NULL DEFAULT 0,

    sort_order INT NOT NULL DEFAULT 0,

    available TINYINT(1) NOT NULL DEFAULT 1,

    image_url VARCHAR(255) NULL,

    cover_url VARCHAR(255) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_menu_sort (sort_order),

    INDEX idx_menu_category (category),

    INDEX idx_menu_available (available)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ORDERS
-- ============================================================

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    former_guest_user_id INT UNSIGNED NULL,

    customer_name VARCHAR(120) NOT NULL,

    phone VARCHAR(50) NOT NULL,

    address TEXT NOT NULL,

    notes TEXT NULL,

    subtotal DECIMAL(10,2) NOT NULL,

    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 45.00,

    total DECIMAL(10,2) NOT NULL,

    status ENUM(
        'pending',
        'confirmed',
        'preparing',
        'out_for_delivery',
        'delivered',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    payment_method ENUM('cash', 'gcash') NOT NULL DEFAULT 'cash',

    payment_ref VARCHAR(40) NULL,

    payment_verified_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_orders_user (user_id),

    INDEX idx_orders_status (status),

    INDEX idx_orders_created (created_at),

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- APP SETTINGS (editable store configuration)
-- ============================================================

CREATE TABLE IF NOT EXISTS app_settings (
    k VARCHAR(60) PRIMARY KEY,

    v TEXT NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ORDER ITEMS
-- ============================================================

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id INT UNSIGNED NOT NULL,

    menu_item_id INT UNSIGNED NOT NULL,

    item_name VARCHAR(120) NOT NULL,

    unit_price DECIMAL(10,2) NOT NULL,

    qty INT UNSIGNED NOT NULL,

    CONSTRAINT fk_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_items_menu
        FOREIGN KEY (menu_item_id)
        REFERENCES menu_items(id)
        ON DELETE RESTRICT,

    INDEX idx_order_items_order (order_id),

    INDEX idx_order_items_menu (menu_item_id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- INITIAL MENU DATA
-- Only insert if menu_items is currently empty.
-- ============================================================

INSERT INTO menu_items
(
    name,
    description,
    price,
    category,
    featured,
    sort_order
)
SELECT
    seed.name,
    seed.description,
    seed.price,
    seed.category,
    seed.featured,
    seed.sort_order
FROM
(
    SELECT
        'Tapsilog' AS name,
        'Classic beef tapa, garlic fried rice, sunny-side-up egg, house atchara.' AS description,
        139.00 AS price,
        'Silog Meals' AS category,
        1 AS featured,
        1 AS sort_order

    UNION ALL

    SELECT
        'Longsilog',
        'Sweet pork longganisa, garlic fried rice, egg, spiced vinegar dip.',
        129.00,
        'Silog Meals',
        1,
        2

    UNION ALL

    SELECT
        'Tocilog',
        'Pork tocino caramelized on the flat-top, garlic fried rice, egg.',
        129.00,
        'Silog Meals',
        1,
        3

    UNION ALL

    SELECT
        'Sisigsilog',
        'Sizzling pork sisig, calamansi, garlic fried rice, egg.',
        159.00,
        'Silog Meals',
        1,
        4

    UNION ALL

    SELECT
        'Bangsilog',
        'Boneless daing na bangus, garlic fried rice, egg, spiced vinegar.',
        149.00,
        'Silog Meals',
        0,
        5

    UNION ALL

    SELECT
        'Cornsilog',
        'Corned beef brisket, garlic fried rice, egg.',
        135.00,
        'Silog Meals',
        0,
        6

    UNION ALL

    SELECT
        'Chickensilog',
        'Inasal-style marinated chicken, garlic fried rice, egg.',
        135.00,
        'Silog Meals',
        0,
        7

    UNION ALL

    SELECT
        'Spamsilog',
        'Seared spam slice, garlic fried rice, egg.',
        149.00,
        'Silog Meals',
        0,
        8

    UNION ALL

    SELECT
        'Extra Egg',
        'One more sunny-side-up.',
        20.00,
        'Extras & Drinks',
        0,
        9

    UNION ALL

    SELECT
        'Extra Garlic Rice',
        'One more cup of sinangag.',
        25.00,
        'Extras & Drinks',
        0,
        10

    UNION ALL

    SELECT
        'Extra Tapa',
        '100 g of beef tapa on the side.',
        65.00,
        'Extras & Drinks',
        0,
        11

    UNION ALL

    SELECT
        'Iced Barako',
        'Kapeng barako over ice, lightly sweetened.',
        60.00,
        'Extras & Drinks',
        0,
        12

    UNION ALL

    SELECT
        'Bottled Soda',
        'Sarsi, calamansi soda or cola. 355 ml.',
        35.00,
        'Extras & Drinks',
        0,
        13

) AS seed

WHERE NOT EXISTS (
    SELECT 1
    FROM menu_items
);


CREATE TABLE IF NOT EXISTS favorites (
    user_id INT UNSIGNED NOT NULL,

    menu_item_id INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, menu_item_id),

    CONSTRAINT fk_fav_user FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_fav_item FOREIGN KEY (menu_item_id)
        REFERENCES menu_items(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PASSWORD RESETS
-- One outstanding 6-digit code per account; consumed on use.
-- ============================================================

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

    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- DEFAULT VENDOR ACCOUNT
-- Only create if vendor email does not already exist.
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
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa7wHf7J7p1J8r1fXj7mY0qQ9mK'
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE email = 'vendor@silog.local'
);


-- ============================================================
-- VERIFY INSTALLATION
-- ============================================================

SELECT
    id,
    name,
    price,
    category,
    featured,
    available,
    sort_order
FROM menu_items
ORDER BY sort_order;

SELECT
    id,
    name,
    email,
    role,
    is_guest
FROM users
ORDER BY id;
