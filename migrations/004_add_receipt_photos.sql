-- Adds an optional receipt photo/PDF path to standalone party payments and
-- expenses. Run once via phpMyAdmin against an existing database. A fresh
-- install via schema.sql already includes it.

ALTER TABLE party_payments
  ADD COLUMN receipt_path VARCHAR(255) DEFAULT NULL AFTER note;

ALTER TABLE expenses
  ADD COLUMN receipt_path VARCHAR(255) DEFAULT NULL AFTER note;
