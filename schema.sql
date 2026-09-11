-- ============================================================================
-- Ledger — Inventory & Ledger Management System for Pokhara Gadgets
-- Import this whole file via phpMyAdmin (SQL tab) on an empty database.
-- MySQL 5.7+/MariaDB 10.2+, InnoDB, utf8mb4.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- users — login accounts. role gates cost-price / financial-report visibility.
-- ----------------------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- settings — single-row shop configuration.
-- ----------------------------------------------------------------------------
CREATE TABLE settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  shop_name VARCHAR(150) NOT NULL DEFAULT 'Pokhara Gadgets',
  pan_number VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  vat_enabled TINYINT(1) NOT NULL DEFAULT 0,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 13.00,
  show_cost_to_staff TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- parties — suppliers & customers, one shared model with a type flag.
-- opening_balance sign convention (used everywhere balances are computed):
--   positive = the party owes the shop money (receivable)
--   negative = the shop owes the party money (payable)
-- ----------------------------------------------------------------------------
CREATE TABLE parties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('supplier','customer') NOT NULL,
  name VARCHAR(150) NOT NULL,
  business_name VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  pan_number VARCHAR(30) DEFAULT NULL,
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_parties_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- products — current stock is NEVER stored here. It is always derived by
-- summing stock_movements at read time (see includes/stock.php).
-- ----------------------------------------------------------------------------
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sku VARCHAR(60) DEFAULT NULL,
  barcode VARCHAR(60) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  low_stock_threshold INT NOT NULL DEFAULT 0,
  warranty_period VARCHAR(40) DEFAULT NULL,
  is_serialized TINYINT(1) NOT NULL DEFAULT 0,
  cost_price_ref DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sell_price_ref DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_sku (sku),
  INDEX idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- purchases (stock-in header) — always against a supplier party.
-- ----------------------------------------------------------------------------
CREATE TABLE purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  party_id INT UNSIGNED NOT NULL,
  bill_ref VARCHAR(60) DEFAULT NULL,
  purchase_date DATE NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_purchases_party FOREIGN KEY (party_id) REFERENCES parties(id),
  CONSTRAINT fk_purchases_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_purchases_date (purchase_date),
  INDEX idx_purchases_party (party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- purchase_items — one row per line; for serialized products each physical
-- unit is its own row (qty = 1) carrying its own serial_no, so a single
-- purchase can bring in many units of the same product with different IMEIs.
-- status tracks the lifecycle of that specific serialized unit only; it is a
-- convenience index for "which serials are currently sellable" — it is NOT
-- the source of truth for stock counts (stock_movements is).
-- ----------------------------------------------------------------------------
CREATE TABLE purchase_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL,
  serial_no VARCHAR(100) DEFAULT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  status ENUM('in_stock','sold','damaged','lost') NOT NULL DEFAULT 'in_stock',
  CONSTRAINT fk_pitems_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_pitems_product FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_pitems_product_serial (product_id, serial_no),
  INDEX idx_pitems_product (product_id),
  INDEX idx_pitems_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- invoice_counters — backs atomic per-year invoice numbering (INV-YYYY-000X).
-- ----------------------------------------------------------------------------
CREATE TABLE invoice_counters (
  year_val INT UNSIGNED PRIMARY KEY,
  last_seq INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- sales (stock-out header) — party_id nullable for walk-in customers.
-- ----------------------------------------------------------------------------
CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL,
  party_id INT UNSIGNED DEFAULT NULL,
  walkin_name VARCHAR(150) DEFAULT NULL,
  sale_date DATE NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sales_party FOREIGN KEY (party_id) REFERENCES parties(id),
  CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_sales_invoice_no (invoice_no),
  INDEX idx_sales_date (sale_date),
  INDEX idx_sales_party (party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- sale_items — one row per unit sold for serialized products (serial_no set).
-- ----------------------------------------------------------------------------
CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  sell_price DECIMAL(12,2) NOT NULL,
  serial_no VARCHAR(100) DEFAULT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_sitems_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_sitems_product FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_sitems_product (product_id),
  INDEX idx_sitems_serial (product_id, serial_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- sale_payments — split payments per sale; amounts must sum to sales.total.
-- ----------------------------------------------------------------------------
CREATE TABLE sale_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  method ENUM('cash','esewa','khalti','fonepay','bank','due') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_spay_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  INDEX idx_spay_sale (sale_id),
  INDEX idx_spay_method (method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- party_payments — standalone settlements not tied to a specific purchase/sale.
-- ----------------------------------------------------------------------------
CREATE TABLE party_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  party_id INT UNSIGNED NOT NULL,
  direction ENUM('paid_to_supplier','received_from_customer') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method VARCHAR(30) DEFAULT NULL,
  payment_date DATE NOT NULL,
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ppay_party FOREIGN KEY (party_id) REFERENCES parties(id),
  CONSTRAINT fk_ppay_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ppay_party (party_id),
  INDEX idx_ppay_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- stock_movements — the ONLY source of truth for stock. Auto-generated only,
-- never manually edited. Current stock for a product = SUM(qty WHERE type='in')
-- - SUM(qty WHERE type='out').
-- ----------------------------------------------------------------------------
CREATE TABLE stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('in','out') NOT NULL,
  qty INT UNSIGNED NOT NULL,
  ref_type ENUM('purchase','sale','adjustment','return') NOT NULL,
  ref_id INT UNSIGNED DEFAULT NULL,
  movement_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stockmv_product FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_stockmv_product (product_id),
  INDEX idx_stockmv_ref (ref_type, ref_id),
  INDEX idx_stockmv_date (movement_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- returns_adjustments — customer returns and damaged/lost write-offs. These
-- are excluded from normal purchase/sale totals in reports and are the only
-- other events (besides purchases/sales) allowed to touch stock_movements.
-- ----------------------------------------------------------------------------
CREATE TABLE returns_adjustments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('customer_return','damaged','lost') NOT NULL,
  qty INT NOT NULL,
  serial_no VARCHAR(100) DEFAULT NULL,
  related_sale_id INT UNSIGNED DEFAULT NULL,
  restock TINYINT(1) NOT NULL DEFAULT 0,
  adjustment_date DATE NOT NULL,
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ret_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_ret_sale FOREIGN KEY (related_sale_id) REFERENCES sales(id) ON DELETE SET NULL,
  CONSTRAINT fk_ret_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ret_product (product_id),
  INDEX idx_ret_date (adjustment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- expenses — simple operating expense log, feeds the profit estimate.
-- ----------------------------------------------------------------------------
CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category ENUM('rent','salary','electricity','other') NOT NULL DEFAULT 'other',
  amount DECIMAL(12,2) NOT NULL,
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_exp_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_exp_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Seed data: default settings row + one demo admin login.
-- Username: admin@pokharagadgets.com   Password: admin123
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN (Settings > Users).
-- ----------------------------------------------------------------------------
INSERT INTO settings (id, shop_name, pan_number, vat_enabled, vat_rate, show_cost_to_staff)
VALUES (1, 'Pokhara Gadgets', NULL, 0, 13.00, 0);

INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@pokharagadgets.com', '$2y$12$5fjPxtdZAa5WrhlRZCxSZO.O4M/ZfGZhCIJ6zu3wevWtwtOJ1vyh6', 'admin');
