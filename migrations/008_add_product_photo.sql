-- Adds an optional product photo, shown in the product list and in the
-- searchable item picker on Add Sale / Add Purchase. Run once via phpMyAdmin
-- against an existing database. A fresh install via schema.sql already
-- includes this.

ALTER TABLE products
  ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER barcode;
