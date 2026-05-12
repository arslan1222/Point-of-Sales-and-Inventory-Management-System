CREATE DATABASE IF NOT EXISTS pos_inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_inventory_db;

-- -----------------------------------------------
-- Table: users
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100)    NOT NULL,
    username    VARCHAR(50)     NOT NULL UNIQUE,
    email       VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    role        ENUM('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin user (password: admin123)
INSERT INTO users (full_name, username, email, password, role) VALUES
('Administrator', 'admin', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
-- ('Store Manager', 'manager', 'manager@pos.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager'),
('Cashier One', 'cashier1', 'cashier1@pos.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier');

-- -----------------------------------------------
-- Table: categories
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL UNIQUE,
    description TEXT,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, description) VALUES
('Electronics', 'Electronic gadgets and accessories'),
('Clothing', 'Apparel and fashion items'),
('Food & Beverages', 'Consumable food and drink products'),
('Stationery', 'Office and school supplies'),
('Hardware', 'Tools and hardware equipment');

-- -----------------------------------------------
-- Table: products
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT             NOT NULL,
    name            VARCHAR(150)    NOT NULL,
    sku             VARCHAR(50)     NOT NULL UNIQUE,
    barcode         VARCHAR(100),
    description     TEXT,
    unit            VARCHAR(30)     DEFAULT 'pcs',
    cost_price      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    selling_price   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    stock_qty       INT             NOT NULL DEFAULT 0,
    low_stock_alert INT             NOT NULL DEFAULT 10,
    image           VARCHAR(255),
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- Sample products
INSERT INTO products (category_id, name, sku, cost_price, selling_price, stock_qty, low_stock_alert) VALUES
(1, 'USB Charging Cable', 'ELEC-001', 2.50, 5.99, 150, 20),
(1, 'Wireless Mouse', 'ELEC-002', 8.00, 15.99, 60, 10),
(1, 'Bluetooth Earphones', 'ELEC-003', 12.00, 24.99, 45, 10),
(2, 'Cotton T-Shirt (M)', 'CLO-001', 4.00, 9.99, 80, 15),
(2, 'Denim Jeans (32)', 'CLO-002', 12.00, 29.99, 40, 10),
(3, 'Mineral Water 500ml', 'FB-001', 0.30, 0.75, 300, 50),
(3, 'Energy Drink 250ml', 'FB-002', 0.80, 1.99, 200, 50),
(4, 'A4 Notebook', 'STA-001', 1.00, 2.49, 120, 25),
(4, 'Ball Pen (Pack of 10)', 'STA-002', 0.80, 1.99, 90, 20),
(5, 'Screwdriver Set', 'HW-001', 5.00, 12.99, 35, 5);

-- -----------------------------------------------
-- Table: customers
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    phone       VARCHAR(20),
    email       VARCHAR(100),
    address     TEXT,
    is_default  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
);

-- Default walk-in customer
INSERT INTO customers (name, phone, is_default) VALUES
('Walk-in Customer', '0000000000', 1);

-- -----------------------------------------------
-- Table: sales
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no      VARCHAR(30)     NOT NULL UNIQUE,
    customer_id     INT             NOT NULL,
    user_id         INT             NOT NULL,
    subtotal        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    discount_type   ENUM('fixed','percent') DEFAULT 'fixed',
    discount_value  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    tax_percent     DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    amount_paid     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    change_amount   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    payment_method  ENUM('cash','card','mobile') NOT NULL DEFAULT 'cash',
    status          ENUM('completed','refunded','voided') NOT NULL DEFAULT 'completed',
    notes           TEXT,
    sale_date       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- -----------------------------------------------
-- Table: sale_items
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS sale_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sale_id     INT             NOT NULL,
    product_id  INT             NOT NULL,
    qty         INT             NOT NULL DEFAULT 1,
    unit_price  DECIMAL(12,2)   NOT NULL,
    subtotal    DECIMAL(12,2)   NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- -----------------------------------------------
-- Table: stock_adjustments
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT             NOT NULL,
    user_id     INT             NOT NULL,
    type        ENUM('in','out','adjustment') NOT NULL DEFAULT 'adjustment',
    qty         INT             NOT NULL,
    reason      VARCHAR(255),
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- -----------------------------------------------
-- View: daily_sales_summary
-- -----------------------------------------------
CREATE OR REPLACE VIEW daily_sales_summary AS
SELECT
    DATE(sale_date)         AS sale_day,
    COUNT(id)               AS total_transactions,
    SUM(subtotal)           AS gross_sales,
    SUM(discount_amount)    AS total_discounts,
    SUM(tax_amount)         AS total_tax,
    SUM(total_amount)       AS net_sales
FROM sales
WHERE status = 'completed'
GROUP BY DATE(sale_date)
ORDER BY sale_day DESC;