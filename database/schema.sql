-- Database: fly_water_h2o
-- Fly Water H2O Supply Management System Schema

CREATE DATABASE IF NOT EXISTS `fly_water_h2o` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `fly_water_h2o`;

-- -----------------------------------------------------------
-- 1. Users table (Admin Login)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(200) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`),
    KEY `voucher_no` (`voucher_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 2. Customers table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_code` VARCHAR(10) NOT NULL,
    `customer_name` VARCHAR(200) NOT NULL,
    `mobile` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `security_deposit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `empty_bottles_balance` INT(11) NOT NULL DEFAULT 0,
    `outstanding_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `salesman` VARCHAR(200) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customer_code` (`customer_code`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 4. Customer Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `debit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `running_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reference_id` INT(11) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 5. Customer Payments table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `voucher_no` VARCHAR(30) DEFAULT NULL,
    `customer_id` INT(11) NOT NULL,
    `payment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_type` VARCHAR(50) DEFAULT 'Cash',
    `notes` TEXT DEFAULT NULL,
    `payment_datetime` DATETIME NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `voucher_no` (`voucher_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 6. Water Deliveries table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `water_deliveries` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `voucher_no` VARCHAR(30) DEFAULT NULL,
    `customer_id` INT(11) NOT NULL,
    `product_id` INT(11) DEFAULT NULL,
    `bottles_delivered` INT(11) NOT NULL DEFAULT 0,
    `empty_bottles_returned` INT(11) NOT NULL DEFAULT 0,
    `bottle_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `salesman` VARCHAR(200) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `delivery_datetime` DATETIME NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `product_id` (`product_id`),
    KEY `voucher_no` (`voucher_no`),
    KEY `delivery_datetime` (`delivery_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 7. Bottle Tracking table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bottle_tracking` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `tracking_date` DATE NOT NULL,
    `bottles_delivered` INT(11) NOT NULL DEFAULT 0,
    `bottles_returned` INT(11) NOT NULL DEFAULT 0,
    `bottles_broken` INT(11) NOT NULL DEFAULT 0,
    `pending_empties` INT(11) NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `tracking_date` (`tracking_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 8. Products table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_code` VARCHAR(10) NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_stock` INT(11) NOT NULL DEFAULT 0,
    `min_stock_level` INT(11) NOT NULL DEFAULT 10,
    `track_empty_bottles` TINYINT(1) NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_code` (`product_code`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 9. Stock In (Production) table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_in` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 0,
    `stock_date` DATE NOT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 10. Stock Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `transaction_type` VARCHAR(20) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `quantity_in` INT(11) NOT NULL DEFAULT 0,
    `quantity_out` INT(11) NOT NULL DEFAULT 0,
    `running_stock` INT(11) NOT NULL DEFAULT 0,
    `description` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    KEY `transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 11. Expense Categories table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_code` VARCHAR(10) DEFAULT NULL,
    `category_name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_category_code` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 12. Expenses table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `voucher_no` VARCHAR(30) DEFAULT NULL,
    `expense_date` DATE NOT NULL,
    `expense_category` INT(11) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `receipt_no` VARCHAR(100) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `expense_category` (`expense_category`),
    KEY `expense_date` (`expense_date`),
    KEY `idx_expenses_voucher` (`voucher_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 13. Cashbook table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cashbook` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_date` DATETIME NOT NULL,
    `transaction_type` VARCHAR(20) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `transaction_date` (`transaction_date`),
    KEY `transaction_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 14. Suppliers table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `supplier_code` VARCHAR(10) NOT NULL,
    `supplier_name` VARCHAR(200) NOT NULL,
    `contact_person` VARCHAR(200) DEFAULT NULL,
    `mobile` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(200) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `ntn_no` VARCHAR(100) DEFAULT NULL,
    `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_supplier_code` (`supplier_code`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 15. Supplier Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `supplier_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `debit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `running_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reference_id` INT(11) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 16. Supplier Payments table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `voucher_no` VARCHAR(30) DEFAULT NULL,
    `supplier_id` INT(11) NOT NULL,
    `purchase_id` INT(11) NOT NULL,
    `payment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_type` VARCHAR(50) DEFAULT 'Cash',
    `cheque_no` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `payment_datetime` DATETIME NOT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`),
    KEY `purchase_id` (`purchase_id`),
    KEY `voucher_no` (`voucher_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 17. Raw Materials table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_materials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `material_code` VARCHAR(10) DEFAULT NULL,
    `material_name` VARCHAR(200) NOT NULL,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'Pieces',
    `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `opening_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_stock_level` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_material_code` (`material_code`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 18. Raw Material Purchases table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_purchases` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `voucher_no` VARCHAR(30) DEFAULT NULL,
    `purchase_date` DATETIME NOT NULL,
    `invoice_no` VARCHAR(100) DEFAULT NULL,
    `supplier_id` INT(11) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Credit',
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 19. Raw Material Purchase Items table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_purchase_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_id` INT(11) NOT NULL,
    `material_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `purchase_id` (`purchase_id`),
    KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 20. Raw Material Issuance table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_issuance` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `issuance_date` DATETIME NOT NULL,
    `material_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `purpose` VARCHAR(200) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- 21. Raw Material Stock Ledger table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raw_material_stock_ledger` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) NOT NULL,
    `transaction_date` DATETIME NOT NULL,
    `transaction_type` VARCHAR(50) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `quantity_in` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity_out` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `running_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `description` TEXT DEFAULT NULL,
    `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------
-- Default Data
-- -----------------------------------------------------------

-- Default admin user (username: admin, password: admin123)
INSERT INTO `users` (`username`, `password`, `full_name`) VALUES
('admin', 'admin123', 'Administrator');

-- Default expense categories
INSERT INTO `expense_categories` (`category_name`) VALUES
('Electricity Bill'),
('Water Bill'),
('Employee Salary'),
('Transportation'),
('Maintenance'),
('Office Supplies'),
('Marketing'),
('Other');
