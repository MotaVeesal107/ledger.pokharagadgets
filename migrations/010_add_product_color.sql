-- Adds a Color field to products, so variants of the same item (e.g. an
-- iPhone 11 case in Black vs Blue) can be tracked as separate products with
-- their own stock, price, and photo. Run once via phpMyAdmin against an
-- existing database. A fresh install via schema.sql already includes this.

ALTER TABLE products
  ADD COLUMN color VARCHAR(60) DEFAULT NULL AFTER device_model,
  ADD INDEX idx_products_color (color);
