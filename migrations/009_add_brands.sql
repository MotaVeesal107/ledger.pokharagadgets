-- Adds a "brands" table so a brand can carry an optional photo/logo, browsed
-- from a Brands gallery. products.brand stays a plain text column (matched
-- by name) so nothing about existing products changes. Run once via
-- phpMyAdmin against an existing database. A fresh install via schema.sql
-- already includes this.

CREATE TABLE brands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  photo_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_brands_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
