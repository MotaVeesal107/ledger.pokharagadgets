-- Adds a per-line-item discount percentage to purchases and sales, so a
-- line's total reflects qty * price * (1 - discount% / 100) instead of
-- always the full price. Run once via phpMyAdmin against an existing
-- database. A fresh install via schema.sql already includes this.

ALTER TABLE purchase_items
  ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER cost_price;

ALTER TABLE sale_items
  ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER sell_price;
