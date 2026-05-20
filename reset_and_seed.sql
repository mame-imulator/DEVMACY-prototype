SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE Customer_Return;
TRUNCATE TABLE Product_Symptom;
TRUNCATE TABLE Promotion;
TRUNCATE TABLE Sale_Item_Batch;
TRUNCATE TABLE Sale_Item;
TRUNCATE TABLE Sale;
TRUNCATE TABLE Stock_Movement;
TRUNCATE TABLE Supplier_Return;
TRUNCATE TABLE Stock;
TRUNCATE TABLE Product_Unit_Price;
TRUNCATE TABLE Product;
TRUNCATE TABLE Symptom;
TRUNCATE TABLE Unit_Size;
TRUNCATE TABLE Supplier;
SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO Supplier (supplier_id, supplier_name) VALUES (1, 'PharmaCorp'), (2, 'MedSupply Inc');
INSERT IGNORE INTO Unit_Size (unit_size_id, size_description) VALUES (1, 'Bottle (Liquid)'), (2, 'Box (Blister Pack)'), (3, 'Tube (Cream)');
INSERT IGNORE INTO Symptom (symptom_id, symptom_name) VALUES (1, 'Fever'), (2, 'Pain'), (3, 'Inflammation'), (4, 'General Pain Relief'), (5, 'Diabetes');
INSERT IGNORE INTO Product (product_id, product_name, supplier_id) VALUES (1, 'Amoxicillin 500mg', 1), (2, 'Azithromycin 500mg', 2), (3, 'Hydrocortisone Cream', 1);
INSERT IGNORE INTO Product_Unit_Price (barcode, product_id, unit_size_id, price_per_unit) VALUES ('AMX-500', 1, 1, 45.00), ('AZI-500', 2, 2, 18.50), ('HYD-30G', 3, 3, 10.00);

-- ==========================================
-- RICH SAMPLE DATA FOR DEVMACY DASHBOARD
-- Run this script to populate the dashboard with realistic analytics!
-- ==========================================

-- 1. SEED MORE PRODUCTS & SYMPTOMS (To make it interesting)
INSERT IGNORE INTO Unit_Size (unit_size_id, size_description) VALUES
(4, 'Pack (100 count)'),
(5, 'Single Unit');
INSERT IGNORE INTO Symptom (symptom_id, symptom_name) VALUES 
(6, 'Allergies'),
(7, 'Cough'),
(8, 'Muscle Pain'),
(9, 'Stomach Ache'),
(10, 'Sore Throat'),
(11, 'Headache'),
(12, 'Heartburn'),
(13, 'Wound Care'),
(14, 'Immunity'),
(15, 'Eye Irritation');

INSERT IGNORE INTO Product (product_id, product_name, supplier_id) VALUES 
(4, 'Loratadine 10mg (Claritin)', 1),
(5, 'Ibuprofen 400mg', 2),
(6, 'Cough Syrup 200ml', 1),
(7, 'Antacid Tablets', 2),
(8, 'Throat Lozenges', 1),
(9, 'Paracetamol 500mg', 2),
(10, 'Omeprazole 20mg', 1),
(11, 'Band-Aids (Assorted)', 2),
(12, 'Vitamin C 1000mg', 1),
(13, 'Eye Drops 15ml', 2),
(14, 'Syringe 1ml (Insulin)', 1),
(15, 'Alcohol Swabs', 2),
(16, 'Digital Thermometer', 1),
(17, 'Medical Tape (Roll)', 2),
(18, 'Cotton Balls', 2),
(19, 'DayQuil Cold & Flu', 1),
(20, 'Benadryl Allergy & Sinus', 2),
(21, 'Naproxen Sodium (Aleve)', 2);

INSERT IGNORE INTO Product_Symptom (product_id, symptom_id) VALUES 
(4, 6), -- Loratadine for Allergies
(5, 4), -- Ibuprofen for Pain Relief
(5, 8), -- Ibuprofen for Muscle Pain
(6, 7), -- Cough Syrup for Cough
(7, 9), -- Antacid for Stomach Ache
(8, 10),-- Lozenges for Sore Throat
(9, 11),-- Paracetamol for Headache
(9, 4), -- Paracetamol for Pain Relief
(10, 12),-- Omeprazole for Heartburn
(11, 13),-- Band-Aids for Wound Care
(12, 14),-- Vitamin C for Immunity
(13, 15),-- Eye Drops for Eye Irritation
(14, 5), -- Syringe for Diabetes
(15, 5), -- Alcohol Swabs for Diabetes
(15, 13),-- Alcohol Swabs for Wound Care
(16, 1), -- Thermometer for Fever
(17, 13),-- Medical Tape for Wound Care
(18, 13),-- Cotton Balls for Wound Care
(19, 1), -- DayQuil for Fever
(19, 2), -- DayQuil for Pain
(19, 7), -- DayQuil for Cough
(19, 10),-- DayQuil for Sore Throat
(19, 11),-- DayQuil for Headache
(20, 6), -- Benadryl for Allergies
(20, 11),-- Benadryl for Headache
(21, 2), -- Naproxen for Pain
(21, 3), -- Naproxen for Inflammation
(21, 8), -- Naproxen for Muscle Pain
(21, 11);-- Naproxen for Headache

INSERT IGNORE INTO Product_Unit_Price (barcode, product_id, unit_size_id, price_per_unit) VALUES 
('LOR-10M', 4, 2, 15.00), -- Box
('IBU-400', 5, 2, 8.50),  -- Box
('CSY-200', 6, 1, 12.50), -- Vial (Pretend it's a bottle)
('ANT-TAB', 7, 2, 5.00),  -- Box
('LOZ-100', 8, 2, 4.00),  -- Box
('PAR-500', 9, 2, 6.00),  -- Box
('OME-20M', 10, 2, 22.00),-- Box
('BND-AST', 11, 2, 4.50), -- Box
('VIT-C1K', 12, 2, 18.00),-- Box
('EYE-DRP', 13, 3, 9.50), -- Tube (Pretend it's a drop bottle)
('SYR-1ML', 14, 5, 0.50), -- Single Unit
('ALC-SWB', 15, 4, 3.50), -- Pack
('D-THERM', 16, 5, 12.00),-- Single Unit
('MED-TAP', 17, 5, 2.50), -- Single Unit
('COT-BAL', 18, 4, 1.50), -- Pack
('DAY-COLD', 19, 2, 14.50), -- Box
('BEN-SINUS', 20, 2, 9.99), -- Box
('NAP-ALEVE', 21, 2, 11.25); -- Box

INSERT IGNORE INTO Stock (stock_id, product_id, unit_size_id, quantity, location, expiry_date) VALUES 
(3, 4, 2, 150, 'Front', DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
(4, 5, 2, 200, 'Front', DATE_ADD(CURDATE(), INTERVAL 2 YEAR)),
(5, 6, 1, 80, 'Back', DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
(6, 7, 2, 300, 'Front', DATE_ADD(CURDATE(), INTERVAL 3 YEAR)),
(7, 8, 2, 500, 'Front', DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
(8, 9, 2, 400, 'Front', DATE_ADD(CURDATE(), INTERVAL 2 YEAR)),
(9, 10, 2, 120, 'Back', DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
(10, 11, 2, 1000, 'Front', DATE_ADD(CURDATE(), INTERVAL 5 YEAR)),
(11, 12, 2, 250, 'Front', DATE_ADD(CURDATE(), INTERVAL 2 YEAR)),
(12, 13, 3, 90, 'Front', DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
(13, 14, 5, 1000, 'Front', DATE_ADD(CURDATE(), INTERVAL 5 YEAR)),
(14, 15, 4, 300, 'Front', DATE_ADD(CURDATE(), INTERVAL 3 YEAR)),
(15, 16, 5, 50, 'Front', DATE_ADD(CURDATE(), INTERVAL 10 YEAR)),
(16, 17, 5, 200, 'Front', DATE_ADD(CURDATE(), INTERVAL 5 YEAR)),
(17, 18, 4, 150, 'Back', DATE_ADD(CURDATE(), INTERVAL 5 YEAR)),
(18, 19, 2, 150, 'Front', DATE_ADD(CURDATE(), INTERVAL 2 YEAR)),
(19, 20, 2, 180, 'Front', DATE_ADD(CURDATE(), INTERVAL 2 YEAR)),
(20, 21, 2, 220, 'Front', DATE_ADD(CURDATE(), INTERVAL 3 YEAR));

-- 2. SEED ACTIVE PROMOTIONS (To test Promo Performance)
CREATE TABLE IF NOT EXISTS Promotion (
    promo_id INT PRIMARY KEY AUTO_INCREMENT,
    promo_name VARCHAR(100) NOT NULL,
    barcode VARCHAR(50) NOT NULL,
    discount_type ENUM('Percentage', 'Fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barcode) REFERENCES Product_Unit_Price(barcode) ON DELETE CASCADE
);

DELETE FROM Promotion WHERE promo_name LIKE 'Summer Wellness%' OR promo_name LIKE 'Pain Relief Promo%';

INSERT INTO Promotion (promo_name, barcode, discount_type, discount_value, start_date, end_date) VALUES 
('Summer Wellness Sale', 'LOR-10M', 'Percentage', 20.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY)),
('Summer Wellness Sale', 'HYD-30G', 'Fixed', 2.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY)),
('Pain Relief Promo', 'IBU-400', 'Percentage', 15.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
('Throat Care Promo', 'LOZ-100', 'Percentage', 50.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY));


-- 3. MASSIVE SEED OF SALES & TRANSACTIONS 
-- Today's Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (101, CURDATE(), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(101, 1, 1, 2, 45.00), (101, 4, 2, 1, 12.00);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (102, CURDATE(), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(102, 5, 2, 3, 7.22);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (103, CURDATE(), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(103, 7, 2, 2, 5.00), (103, 8, 2, 4, 2.00);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (104, CURDATE(), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(104, 2, 2, 1, 18.50);

-- Yesterday's Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (105, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(105, 2, 2, 1, 18.50), (105, 3, 3, 2, 10.00);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (106, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(106, 8, 2, 5, 2.00), (106, 5, 2, 2, 7.22);

-- 2 Days Ago Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (107, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(107, 6, 1, 2, 12.50), (107, 7, 2, 3, 5.00);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (108, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(108, 1, 1, 3, 45.00);

-- 3 Days Ago Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (109, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(109, 1, 1, 1, 45.00), (109, 5, 2, 2, 7.22);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (110, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(110, 8, 2, 10, 2.00);

-- 4 Days Ago Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (111, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(111, 4, 2, 2, 12.00), (111, 7, 2, 1, 5.00);

-- 5 Days Ago Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (112, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(112, 3, 3, 5, 10.00);

-- 6 Days Ago Sales
INSERT INTO Sale (sale_id, sale_date, status) VALUES (113, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(113, 2, 2, 2, 18.50);

-- Earlier This Month (For yearly/monthly totals)
INSERT INTO Sale (sale_id, sale_date, status) VALUES (114, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(114, 1, 1, 4, 45.00), (114, 5, 2, 5, 7.22);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (115, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(115, 6, 1, 1, 12.50), (115, 8, 2, 3, 4.00);

-- Earlier This Year
INSERT INTO Sale (sale_id, sale_date, status) VALUES (116, DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(116, 1, 1, 5, 45.00), (116, 2, 2, 3, 18.50);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (117, DATE_SUB(CURDATE(), INTERVAL 90 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(117, 7, 2, 10, 5.00), (117, 4, 2, 2, 15.00);

INSERT INTO Sale (sale_id, sale_date, status) VALUES (118, DATE_SUB(CURDATE(), INTERVAL 120 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(118, 3, 3, 15, 12.00);

-- Medical Equipment Sales (Today)
INSERT INTO Sale (sale_id, sale_date, status) VALUES (119, CURDATE(), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(119, 16, 5, 1, 12.00), (119, 18, 4, 2, 1.50);

-- Medical Equipment Sales (Yesterday)
INSERT INTO Sale (sale_id, sale_date, status) VALUES (120, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(120, 14, 5, 10, 0.50), (120, 15, 4, 1, 3.50);

-- Medical Equipment Sales (3 Days Ago)
INSERT INTO Sale (sale_id, sale_date, status) VALUES (121, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(121, 17, 5, 2, 2.50), (121, 11, 2, 1, 4.50);

-- Medical Equipment Sales (10 Days Ago)
INSERT INTO Sale (sale_id, sale_date, status) VALUES (122, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(122, 16, 5, 2, 12.00);

-- Medical Equipment Sales (Last Month)
INSERT INTO Sale (sale_id, sale_date, status) VALUES (123, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'Completed');
INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES 
(123, 14, 5, 50, 0.50), (123, 15, 4, 5, 3.50);


-- 4. SEED CUSTOMER RETURNS
INSERT INTO Customer_Return (sale_item_id, quantity, refund_amount, reason, created_at) 
SELECT sale_item_id, 1, 7.22, 'Customer bought wrong dosage', NOW() 
FROM Sale_Item WHERE sale_id = 102 AND product_id = 5 LIMIT 1;

INSERT INTO Customer_Return (sale_item_id, quantity, refund_amount, reason, created_at) 
SELECT sale_item_id, 2, 4.00, 'Defective packaging', NOW() 
FROM Sale_Item WHERE sale_id = 103 AND product_id = 8 LIMIT 1;


-- 5. LOG STOCK MOVEMENTS
INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason, created_at) VALUES 
(3, 1, 'Sale', -1, 149, 'POS Sale', NOW()),
(4, 1, 'Sale', -3, 197, 'POS Sale', NOW()),
(4, 1, 'Refund', 1, 198, 'Customer Return', NOW());

