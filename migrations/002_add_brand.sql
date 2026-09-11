-- Adds a Brand field to products (e.g. "Sony", "boAt", "JBL") separate from
-- Category (e.g. "Headphone") and Device Model, so items can be filtered by
-- brand within a category regardless of which phone/device they're for.
--
-- Run this once via phpMyAdmin (SQL tab) against an existing database that
-- was created before this column existed. A fresh install via schema.sql
-- already includes it and does not need this file.

ALTER TABLE products
  ADD COLUMN brand VARCHAR(100) DEFAULT NULL AFTER category;

ALTER TABLE products
  ADD INDEX idx_products_brand (brand);
