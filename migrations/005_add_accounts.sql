-- Adds Cash/Bank/Wallet account tracking. A new "accounts" table plus an
-- optional account_id link on sale_payments, party_payments, and expenses,
-- so each real money movement can (optionally) be tied to a specific named
-- account and that account's running balance stays accurate. Run once via
-- phpMyAdmin against an existing database. A fresh install via schema.sql
-- already includes this.

CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('cash','bank','wallet') NOT NULL DEFAULT 'cash',
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO accounts (name, type, opening_balance) VALUES ('Cash', 'cash', 0.00);

ALTER TABLE sale_payments
  ADD COLUMN account_id INT UNSIGNED DEFAULT NULL AFTER amount,
  ADD CONSTRAINT fk_spay_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  ADD INDEX idx_spay_account (account_id);

ALTER TABLE party_payments
  ADD COLUMN account_id INT UNSIGNED DEFAULT NULL AFTER receipt_path,
  ADD CONSTRAINT fk_ppay_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  ADD INDEX idx_ppay_account (account_id);

ALTER TABLE expenses
  ADD COLUMN account_id INT UNSIGNED DEFAULT NULL AFTER receipt_path,
  ADD CONSTRAINT fk_exp_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  ADD INDEX idx_exp_account (account_id);
