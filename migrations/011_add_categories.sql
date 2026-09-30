-- Adds a "categories" table so a category/case-style (e.g. "Silicone Case",
-- "Ultra Case", "Kitty Case", "Vibe Case") can carry an optional photo,
-- browsed from a Categories gallery — mirrors the "brands" table. Matched to
-- products by name (products.category stays a plain text column, not a
-- foreign key), so nothing about existing products changes. Run once via
-- phpMyAdmin against an existing database. A fresh install via schema.sql
-- already includes this.

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  photo_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
