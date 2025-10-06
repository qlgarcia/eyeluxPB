-- EyeLux - Complete Database Schema and Repair Script
-- Usage: Import in phpMyAdmin. Safe to run multiple times (uses IF NOT EXISTS and idempotent ALTERs).

-- Create database and use it
CREATE DATABASE IF NOT EXISTS eyelux_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eyelux_db;

-- =====================
-- CORE TABLES
-- =====================

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  google_id VARCHAR(255) NULL,
  auth_provider VARCHAR(50) NULL DEFAULT 'local',
  phone VARCHAR(20) NULL,
  email_verified TINYINT(1) DEFAULT 0,
  verification_code VARCHAR(10) NULL,
  verification_code_expires DATETIME NULL,
  email_verification_token VARCHAR(255) NULL,
  password_reset_token VARCHAR(255) NULL,
  password_reset_expires TIMESTAMP NULL,
  profile_picture VARCHAR(255) NULL,
  profile_picture_url VARCHAR(255) NULL,
  status ENUM('active','banned','warned') DEFAULT 'active',
  ban_reason TEXT NULL,
  ban_date TIMESTAMP NULL,
  warning_count INT DEFAULT 0,
  last_warning_date TIMESTAMP NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_email (email),
  INDEX idx_user_status (status)
);

-- Ensure required columns exist (repairs)
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(50) NULL DEFAULT 'local';
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_code VARCHAR(10) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_code_expires DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture_url VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','banned','warned') DEFAULT 'active';
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_reason TEXT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_date TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS warning_count INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_warning_date TIMESTAMP NULL;

CREATE TABLE IF NOT EXISTS categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  product_id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  product_name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) NULL,
  stock_quantity INT DEFAULT 0,
  sku VARCHAR(50) UNIQUE NULL,
  brand VARCHAR(100) NULL,
  model VARCHAR(100) NULL,
  color VARCHAR(50) NULL,
  size VARCHAR(20) NULL,
  material VARCHAR(100) NULL,
  gender ENUM('unisex','men','women','kids') NULL,
  image_url VARCHAR(255) NULL,
  additional_images LONGTEXT NULL,
  specifications LONGTEXT NULL,
  is_featured TINYINT(1) DEFAULT 0,
  is_new_arrival TINYINT(1) DEFAULT 0,
  rating DECIMAL(3,2) DEFAULT 0.00,
  review_count INT DEFAULT 0,
  sales_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category_id),
  INDEX idx_featured (is_featured),
  INDEX idx_price (price),
  INDEX idx_sales_count (sales_count),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Repair: add missing columns if table already existed
ALTER TABLE products ADD COLUMN IF NOT EXISTS sales_count INT DEFAULT 0 AFTER review_count;

CREATE TABLE IF NOT EXISTS addresses (
  address_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  address_type ENUM('shipping','billing') DEFAULT 'shipping',
  first_name VARCHAR(50) NULL,
  last_name VARCHAR(50) NULL,
  company VARCHAR(100) NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NULL,
  province VARCHAR(100) NULL,
  postal_code VARCHAR(20) NOT NULL,
  country VARCHAR(100) DEFAULT 'USA',
  phone VARCHAR(20) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  is_default TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_addr_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Repairs
ALTER TABLE addresses ADD COLUMN IF NOT EXISTS province VARCHAR(100) NULL AFTER state;
ALTER TABLE addresses ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER phone;
ALTER TABLE addresses ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude;

CREATE TABLE IF NOT EXISTS cart (
  cart_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  session_id VARCHAR(255) NULL,
  product_id INT NULL,
  quantity INT DEFAULT 1,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_cart_item (user_id, session_id, product_id),
  INDEX idx_cart_user (user_id),
  INDEX idx_cart_product (product_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(product_id)
);

CREATE TABLE IF NOT EXISTS orders (
  order_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  order_number VARCHAR(20) UNIQUE,
  order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  total_amount DECIMAL(10,2) NOT NULL,
  tax_amount DECIMAL(10,2) DEFAULT 0.00,
  shipping_amount DECIMAL(10,2) DEFAULT 0.00,
  discount_amount DECIMAL(10,2) DEFAULT 0.00,
  shipping_address_id INT NULL,
  billing_address_id INT NULL,
  payment_method VARCHAR(50) NULL,
  payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  tracking_number VARCHAR(100) NULL,
  notes TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_order_user (user_id),
  INDEX idx_order_status (status),
  INDEX idx_order_num (order_number),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_orders_ship_addr FOREIGN KEY (shipping_address_id) REFERENCES addresses(address_id),
  CONSTRAINT fk_orders_bill_addr FOREIGN KEY (billing_address_id) REFERENCES addresses(address_id)
);

CREATE TABLE IF NOT EXISTS order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NULL,
  product_id INT NULL,
  product_name VARCHAR(255) NULL,
  product_sku VARCHAR(50) NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  INDEX idx_oi_order (order_id),
  INDEX idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(product_id)
);

CREATE TABLE IF NOT EXISTS wishlist (
  wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  product_id INT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_wishlist_item (user_id, product_id),
  INDEX idx_wl_user (user_id),
  CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_wl_product FOREIGN KEY (product_id) REFERENCES products(product_id)
);

CREATE TABLE IF NOT EXISTS reviews (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  product_id INT NULL,
  order_id INT NULL,
  rating INT NOT NULL,
  title VARCHAR(255) NULL,
  comment TEXT NULL,
  is_verified TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rev_product (product_id),
  INDEX idx_rev_user (user_id),
  INDEX idx_rev_rating (rating),
  CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_rev_product FOREIGN KEY (product_id) REFERENCES products(product_id),
  CONSTRAINT fk_rev_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

-- =====================
-- NOTIFICATIONS
-- =====================

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NULL,
  type ENUM('order_status','promotion','system','review','refund_request','refund_update','warning','ban','unban','user_concern','concern_response') DEFAULT 'system',
  order_id INT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user (user_id),
  INDEX idx_notif_type (type),
  INDEX idx_notif_read (is_read),
  INDEX idx_notif_created (created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS user_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(255) NULL,
  message TEXT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_un_user (user_id)
);

CREATE TABLE IF NOT EXISTS order_status (
  status_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NULL,
  status VARCHAR(50) NOT NULL,
  message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_os_order (order_id),
  CONSTRAINT fk_os_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

CREATE TABLE IF NOT EXISTS review_notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  order_id INT NULL,
  product_id INT NULL,
  notification_type ENUM('delivery_confirmed','review_completed') DEFAULT 'delivery_confirmed',
  is_read TINYINT(1) DEFAULT 0,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rn_user (user_id),
  INDEX idx_rn_expires (expires_at),
  CONSTRAINT fk_rn_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_rn_order FOREIGN KEY (order_id) REFERENCES orders(order_id),
  CONSTRAINT fk_rn_product FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- =====================
-- ADMIN TABLES
-- =====================

CREATE TABLE IF NOT EXISTS admin_users (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100) NULL,
  first_name VARCHAR(50) NULL,
  last_name VARCHAR(50) NULL,
  role ENUM('super_admin','admin','moderator') DEFAULT 'admin',
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_active (is_active)
);

CREATE TABLE IF NOT EXISTS admin_actions (
  action_id INT AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT NULL,
  target_user_id INT NULL,
  action_type ENUM('warning','ban','unban','delete_user','product_update','order_update') NOT NULL,
  reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_action_admin (admin_user_id),
  INDEX idx_action_target (target_user_id)
);

-- =====================
-- REFUND SYSTEM
-- =====================

CREATE TABLE IF NOT EXISTS refund_requests (
  refund_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  order_id INT NULL,
  product_id INT NULL,
  order_item_id INT NULL,
  refund_amount DECIMAL(10,2) NOT NULL,
  refund_reason TEXT NOT NULL,
  customer_message TEXT NULL,
  status ENUM('pending','approved','declined','processing','completed') DEFAULT 'pending',
  admin_id INT NULL,
  admin_message TEXT NULL,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rr_user (user_id),
  INDEX idx_rr_status (status)
);

-- =====================
-- USER CONCERNS
-- =====================

CREATE TABLE IF NOT EXISTS user_concerns (
  concern_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  subject VARCHAR(500) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','in_progress','resolved','closed') DEFAULT 'new',
  admin_reply TEXT NULL,
  admin_replied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_uc_status (status)
);

-- =====================
-- HERO CONTENT
-- =====================

CREATE TABLE IF NOT EXISTS hero_content (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  subtitle TEXT NOT NULL,
  button_text VARCHAR(100) NOT NULL,
  button_link VARCHAR(255) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hero_carousel (
  id INT AUTO_INCREMENT PRIMARY KEY,
  image_url VARCHAR(255) NOT NULL,
  title VARCHAR(255) NULL,
  subtitle TEXT NULL,
  button_text VARCHAR(100) NULL,
  button_link VARCHAR(255) NULL,
  display_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_hc_display_order (display_order)
);

-- =====================
-- SAMPLE DATA (SAFE)
-- =====================

-- Seed admin user (admin / admin123). Change password after login.
INSERT INTO admin_users (username, password, email, first_name, last_name, role, is_active)
SELECT 'admin', '$2y$10$QfMyiGPMMqRiXVzhkUnDSO/6cP11mkkA5rl6NzziKFglhUT4FZ22.', 'admin@eyelux.local', 'Admin', 'User', 'super_admin', 1
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username='admin');

-- Minimal categories
INSERT INTO categories (category_name, description)
SELECT 'Sunglasses','Default category' WHERE NOT EXISTS (SELECT 1 FROM categories);

-- Default hero content
INSERT INTO hero_content (title, subtitle, button_text, button_link, is_active)
SELECT 'Discover Your Perfect Eyewear','From classic aviators to modern frames, find the perfect pair.','Shop Now','products.php',1
WHERE NOT EXISTS (SELECT 1 FROM hero_content);

-- =====================
-- REPAIR HINTS (do not fail if FKs missing)
-- =====================
-- Some foreign keys are intentionally omitted for tables that may already hold data to avoid migration failures.
-- This schema ensures columns required by the PHP code exist and common indexes are present.

-- Done
SELECT 'EyeLux schema loaded successfully' AS Status;


