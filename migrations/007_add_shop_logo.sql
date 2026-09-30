-- Adds an optional shop logo shown in the sidebar and on printed invoices.
-- Run once via phpMyAdmin against an existing database. A fresh install via
-- schema.sql already includes this.

ALTER TABLE settings
  ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL AFTER shop_name;
