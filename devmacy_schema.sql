-- DEVMACY Pharmacy System 3NF Schema
-- Ensure proper relational integrity based on specifications

CREATE TABLE Supplier (
    supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_name VARCHAR(100) NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    contact_name VARCHAR(100)
);

CREATE TABLE Product (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(100) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    FOREIGN KEY (supplier_id) REFERENCES Supplier(supplier_id)
);

CREATE TABLE Unit_Size (
    unit_size_id INT PRIMARY KEY AUTO_INCREMENT,
    size_description VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE Product_Unit_Price (
    barcode VARCHAR(50) PRIMARY KEY,
    product_id INT NOT NULL,
    unit_size_id INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES Product(product_id),
    FOREIGN KEY (unit_size_id) REFERENCES Unit_Size(unit_size_id)
);

CREATE TABLE Sale (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Completed'
);

CREATE TABLE Sale_Item (
    sale_item_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    unit_size_id INT NOT NULL,
    units_sold INT NOT NULL CHECK (units_sold > 0),
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES Sale(sale_id),
    FOREIGN KEY (product_id) REFERENCES Product(product_id),
    FOREIGN KEY (unit_size_id) REFERENCES Unit_Size(unit_size_id)
);

CREATE TABLE Stock (
    stock_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    unit_size_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity >= 0),
    location ENUM('Front', 'Back') NOT NULL DEFAULT 'Back',
    expiry_date DATE NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
                 ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES Product(product_id),
    FOREIGN KEY (unit_size_id) REFERENCES Unit_Size(unit_size_id)
);


CREATE TABLE Sale_Item_Batch (
    sale_item_id INT NOT NULL,
    stock_id INT NOT NULL,
    quantity_taken INT NOT NULL,
    PRIMARY KEY (sale_item_id, stock_id),
    FOREIGN KEY (sale_item_id) REFERENCES Sale_Item(sale_item_id),
    FOREIGN KEY (stock_id) REFERENCES Stock(stock_id)
);

CREATE TABLE Symptom (
    symptom_id INT PRIMARY KEY AUTO_INCREMENT,
    symptom_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Product_Symptom (
    product_id INT NOT NULL,
    symptom_id INT NOT NULL,
    PRIMARY KEY (product_id, symptom_id),
    FOREIGN KEY (product_id) REFERENCES Product(product_id)
        ON DELETE CASCADE,
    FOREIGN KEY (symptom_id) REFERENCES Symptom(symptom_id)
        ON DELETE CASCADE
);

CREATE TABLE Role (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (role_id) REFERENCES Role(role_id)
);

CREATE TABLE Stock_Movement (
    movement_id INT PRIMARY KEY AUTO_INCREMENT,
    stock_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('Purchase', 'Sale', 'Adjustment', 'Cancellation', 'Refund', 'SupplierReturn') NOT NULL,
    quantity_change INT NOT NULL,
    balance_after INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES Stock(stock_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

CREATE TABLE Customer_Return (
    return_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_item_id INT NOT NULL,
    quantity INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_item_id) REFERENCES Sale_Item(sale_item_id)
);

CREATE TABLE Supplier_Return (
    return_id INT PRIMARY KEY AUTO_INCREMENT,
    stock_id INT NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES Stock(stock_id)
);


-- Seed Initial Roles
INSERT INTO Role (role_name) VALUES 
('Admin'), 
('Pharmacist'), 
('Cashier');

-- Seed Default Admin Account 
-- NOTE: If you already imported this schema, use reset.php to fix your live database.
INSERT INTO Users (username, password_hash, full_name, role_id, is_active) VALUES 
('admin', '$2y$10$UoW3sUCL9nOoIFRjV1lWVu5.oY5Z.O4iV3.Vb1oUjOoIFRjV1lWVu', 'System Administrator', 1, 1);

-- ==========================================
-- SAMPLE DATA FOR INTEGRATED TESTING
-- ==========================================

-- 1. SEED SUPPLIERS
INSERT INTO Supplier (supplier_name, phone_number, contact_name) VALUES 
('EuroHealth Pharma', '+44-20-7946-0958', 'Marcello Rossi'),
('Pacific Meds', '+61-2-5550-1234', 'Sarah Chen');
SET @supplier_euro = 1;
SET @supplier_pac = 2;

-- 2. SEED UNIT SIZES
INSERT INTO Unit_Size (size_description) VALUES 
('Vial (Plastic)'),
('Box (12 count)'),
('Tube (30g)');
SET @unit_vial = 1;
SET @unit_box = 2;
SET @unit_tube = 3;

-- 3. SEED SYMPTOMS
INSERT INTO Symptom (symptom_name) VALUES 
('Fever'),
('Bacterial Infection'),
('Skin Rash'),
('Pain Relief'),
('Diabetes');
SET @symp_fever = 1;
SET @symp_infect = 2;
SET @symp_rash = 3;
SET @symp_pain = 4;
SET @symp_diabetes = 5;

-- 4. SEED PRODUCTS
INSERT INTO Product (product_name, supplier_id) VALUES 
('Insulin Glargine', @supplier_euro),
('Azithromycin 500mg', @supplier_euro),
('Hydrocortisone Cream', @supplier_pac);
SET @prod_insulin = 1;
SET @prod_azith = 2;
SET @prod_hydro = 3;

-- 8. SEED DRUG-SYMPTOM ASSOCIATIONS (Clinical Helper Data)
INSERT INTO Product_Symptom (product_id, symptom_id) VALUES 
(@prod_insulin, @symp_diabetes),
(@prod_azith, @symp_infect),
(@prod_azith, @symp_fever),
(@prod_hydro, @symp_rash);

-- 5. SEED PRICING & BARCODES
INSERT INTO Product_Unit_Price (barcode, product_id, unit_size_id, price_per_unit) VALUES 
('INS-001', @prod_insulin, @unit_vial, 45.00),
('AZI-500', @prod_azith, @unit_box, 18.50),
('HYD-30G', @prod_hydro, @unit_tube, 12.00);


-- 6. SEED INITIAL STOCK
INSERT INTO Stock (product_id, unit_size_id, quantity, expiry_date) VALUES (@prod_insulin, @unit_vial, 50, '2026-10-30');
SET @stock1 = LAST_INSERT_ID();
INSERT INTO Stock (product_id, unit_size_id, quantity, expiry_date) VALUES (@prod_azith, @unit_box, 100, '2025-12-31');
SET @stock2 = LAST_INSERT_ID();

-- 7. LOG INITIAL PURCHASE MOVEMENTS (For Stock Card visibility)
INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) VALUES 
(@stock1, 1, 'Purchase', 50, 50, 'Initial Opening Balance'),
(@stock2, 1, 'Purchase', 100, 100, 'Initial Opening Balance');

-- ==========================================
-- MIGRATIONS / UPDATES
-- ==========================================
-- To add Multi-Inventory support to an existing database, run:
-- ALTER TABLE Stock ADD COLUMN location ENUM('Front', 'Back') NOT NULL DEFAULT 'Back';
-- UPDATE Stock SET location = 'Back';

