-- Adds an "other_income" log for money coming in that isn't a sale
-- (commission, rent, refunds, etc.) — symmetric to expenses, and it adds to
-- an account's balance and the profit estimate rather than subtracting.
-- Run once via phpMyAdmin against an existing database. A fresh install via
-- schema.sql already includes this.

CREATE TABLE other_income (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  income_date DATE NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'other',
  amount DECIMAL(12,2) NOT NULL,
  note TEXT,
  receipt_path VARCHAR(255) DEFAULT NULL,
  account_id INT UNSIGNED DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_inc_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  INDEX idx_inc_date (income_date),
  INDEX idx_inc_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
