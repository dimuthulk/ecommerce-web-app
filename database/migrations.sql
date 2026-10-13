-- E-Commerce tables (MySQL / MariaDB, InnoDB, utf8mb4)
-- NOTE: No CREATE DATABASE / USE here. The database is chosen by the config file
-- (local: ecommerce_web_app, remote: if0_43161606_ecommerce_web_app).

-- 1. USERS (customers + admins)
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(80)  NOT NULL,
  last_name     VARCHAR(80)  NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  phone         VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  address_line1 VARCHAR(190),
  address_line2 VARCHAR(190),
  city          VARCHAR(100),
  district      VARCHAR(100),
  postal_code   VARCHAR(20),
  country       VARCHAR(100) DEFAULT 'Sri Lanka',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PRODUCTS
CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku          VARCHAR(60)  NOT NULL UNIQUE,
  name         VARCHAR(190) NOT NULL,
  slug         VARCHAR(210) NOT NULL UNIQUE,
  description  TEXT,
  category     VARCHAR(80)  NOT NULL,
  gender       ENUM('men','women','unisex','kids') NOT NULL DEFAULT 'unisex',
  material     VARCHAR(120),
  price        DECIMAL(10,2) NOT NULL,
  sale_price   DECIMAL(10,2) NULL,
  colors       JSON,
  stock        JSON,
  images       JSON,
  is_featured  TINYINT(1) NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_category (category),
  FULLTEXT KEY ft_products (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ORDERS (shipping + payment included)
CREATE TABLE IF NOT EXISTS orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number     VARCHAR(30) NOT NULL UNIQUE,
  user_id          INT UNSIGNED NULL,
  status           ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded')
                   NOT NULL DEFAULT 'pending',
  subtotal         DECIMAL(10,2) NOT NULL,
  discount_amount  DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code      VARCHAR(40),
  shipping_fee     DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount     DECIMAL(10,2) NOT NULL,
  customer_name    VARCHAR(160) NOT NULL,
  customer_email   VARCHAR(190) NOT NULL,
  customer_phone   VARCHAR(20)  NOT NULL,
  ship_line1       VARCHAR(190) NOT NULL,
  ship_line2       VARCHAR(190),
  ship_city        VARCHAR(100) NOT NULL,
  ship_district    VARCHAR(100),
  ship_postal_code VARCHAR(20),
  ship_country     VARCHAR(100) NOT NULL DEFAULT 'Sri Lanka',
  tracking_number  VARCHAR(80),
  notes            TEXT,
  payment_method   ENUM('cod','card','bank_transfer','payhere') NOT NULL,
  payment_status   ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  transaction_ref  VARCHAR(120),
  paid_at          DATETIME NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ORDER_ITEMS
CREATE TABLE IF NOT EXISTS order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  sku          VARCHAR(60)  NOT NULL,
  size         VARCHAR(20),
  color        VARCHAR(40),
  unit_price   DECIMAL(10,2) NOT NULL,
  quantity     INT UNSIGNED NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;