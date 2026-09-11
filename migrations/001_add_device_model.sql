-- Adds the device_model field to products (e.g. "iPhone 13") separate from
-- the product's own name (e.g. "Clear Silicone Case"), so cases can be
-- filtered/reported by phone model across all their designs.
--
-- Run this once via phpMyAdmin (SQL tab) against an existing database that
-- was created before this column existed. A fresh install via schema.sql
-- already includes it and does not need this file.

ALTER TABLE products
  ADD COLUMN device_model VARCHAR(100) DEFAULT NULL AFTER category;

ALTER TABLE products
  ADD INDEX idx_products_device_model (device_model);
