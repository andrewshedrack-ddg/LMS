SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS Laundry_db;
CREATE DATABASE Laundry_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE Laundry_db;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Table: Customer
-- Note: Customer_phone is VARCHAR(20), not INT as written in
-- Deliverable 2, so that leading zeros in phone numbers work.
-- Customer_password is an addition to the D2 schema so that
-- customers can log in. It is one of only two added columns.
-- ------------------------------------------------------------
CREATE TABLE Customer (
    Customer_id INT AUTO_INCREMENT PRIMARY KEY,
    Customer_name VARCHAR(100) NOT NULL,
    Customer_phone VARCHAR(20) UNIQUE,
    Customer_email VARCHAR(100) UNIQUE,
    Customer_address VARCHAR(100) UNIQUE,
    Customer_password VARCHAR(255) NOT NULL,
    Created_at DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Branch
-- ------------------------------------------------------------
CREATE TABLE Branch (
    Branch_id INT AUTO_INCREMENT PRIMARY KEY,
    Branch_name VARCHAR(100) NOT NULL,
    Branch_address VARCHAR(50) NOT NULL,
    Branch_phone VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Employee
-- Employee_password is an addition to the D2 schema so that
-- staff and admin can log in. It is one of only two added columns.
-- Employee_role is 'Staff' or 'Admin'.
-- ------------------------------------------------------------
CREATE TABLE Employee (
    Employee_id INT AUTO_INCREMENT PRIMARY KEY,
    Branch_id INT,
    Employee_name VARCHAR(100) NOT NULL,
    Employee_phone VARCHAR(20) NOT NULL,
    Employee_role VARCHAR(50) NOT NULL,
    Employee_password VARCHAR(255) NOT NULL,
    FOREIGN KEY (Branch_id) REFERENCES Branch (Branch_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Laundry_Order
-- Order_status: Pending, Picked Up, Washing, Ready, Collected.
-- 'Cancelled' is also used when a customer cancels a Pending order.
-- Employee_id is NULL until an employee is assigned.
-- ------------------------------------------------------------
CREATE TABLE Laundry_Order (
    Order_id INT AUTO_INCREMENT PRIMARY KEY,
    Customer_id INT NOT NULL,
    Employee_id INT NULL,
    Branch_id INT NOT NULL,
    Order_date DATE NOT NULL,
    Due_date DATE NOT NULL,
    Order_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    Total_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (Customer_id) REFERENCES Customer (Customer_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (Employee_id) REFERENCES Employee (Employee_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY (Branch_id) REFERENCES Branch (Branch_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Service (price list, TZS per kg)
-- ------------------------------------------------------------
CREATE TABLE Service (
    Service_id INT AUTO_INCREMENT PRIMARY KEY,
    Service_name VARCHAR(100) NOT NULL,
    Service_description VARCHAR(255) NOT NULL,
    Price_per_unit DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Item_Type
-- ------------------------------------------------------------
CREATE TABLE Item_Type (
    Item_type_id INT AUTO_INCREMENT PRIMARY KEY,
    Item_name VARCHAR(100) NOT NULL,
    Item_category VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Order_Item
-- Order_notes holds the pickup time slot, e.g. "Time slot: 08:00-10:00".
-- ------------------------------------------------------------
CREATE TABLE Order_Item (
    Order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    Order_id INT NOT NULL,
    Service_id INT NOT NULL,
    Item_type_id INT NOT NULL,
    Order_quantity INT NOT NULL,
    Unit_price DECIMAL(10,2) NOT NULL,
    Order_notes VARCHAR(255),
    FOREIGN KEY (Order_id) REFERENCES Laundry_Order (Order_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (Service_id) REFERENCES Service (Service_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (Item_type_id) REFERENCES Item_Type (Item_type_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Payment
-- Payment_status is only 'Paid' or 'Unpaid'.
-- Payments are recorded manually. There is NO real M-Pesa
-- integration and no real money is taken.
-- ------------------------------------------------------------
CREATE TABLE Payment (
    Payment_id INT AUTO_INCREMENT PRIMARY KEY,
    Order_id INT NOT NULL,
    Payment_amount DECIMAL(10,2) NOT NULL,
    Payment_method VARCHAR(100) NOT NULL,
    Payment_time DATETIME NOT NULL,
    Payment_status VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
    Mpesa_checkout_id VARCHAR(64) NULL,
    Mpesa_receipt VARCHAR(32) NULL,
    FOREIGN KEY (Order_id) REFERENCES Laundry_Order (Order_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: Delivery
-- Delivery_type is 'Pickup' or 'Delivery'.
-- Delivery_status: Scheduled, Out for Delivery, Delivered, Collected.
-- Employee_id is NULL until an employee is assigned.
-- ------------------------------------------------------------
CREATE TABLE Delivery (
    Delivery_id INT AUTO_INCREMENT PRIMARY KEY,
    Order_id INT NOT NULL,
    Employee_id INT NULL,
    Delivery_type VARCHAR(100) NOT NULL,
    Delivery_address VARCHAR(100) NOT NULL,
    Delivery_status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
    Delivery_schedule DATE NOT NULL,
    FOREIGN KEY (Order_id) REFERENCES Laundry_Order (Order_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (Employee_id) REFERENCES Employee (Employee_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Sample data (so the app can be demoed right after import)
-- Demo logins (passwords are hashed with PHP password_hash):
--   Customer: customer@test.com / customer123
--   Staff:    0700000011 / staff123
--   Admin:    0700000010 / admin123
-- ============================================================

INSERT INTO Branch (Branch_name, Branch_address, Branch_phone) VALUES
('Main Branch', 'Strathmore University, Nairobi', '0700000001'),
('Westlands Branch', 'Westlands, Nairobi', '0700000002');

INSERT INTO Service (Service_name, Service_description, Price_per_unit) VALUES
('Wash Only', 'Washing only, per kg', 500.00),
('Iron Only', 'Ironing only, per kg', 300.00),
('Wash and Iron', 'Washing and ironing, per kg', 700.00);

INSERT INTO Item_Type (Item_name, Item_category) VALUES
('Shirt', 'Clothing'),
('Trouser', 'Clothing'),
('Bedsheet', 'Bedding');

-- Password hashes below are filled in by the build (PHP password_hash).
INSERT INTO Employee (Branch_id, Employee_name, Employee_phone, Employee_role, Employee_password) VALUES
(1, 'Admin User', '0700000010', 'Admin', '$2y$10$Q.z785B4BxZPvaTMC2rOZ.1aVsb0.cXul.rv/cecqintAJiIQK3P2'),
(1, 'Staff User', '0700000011', 'Staff', '$2y$10$Fwdbzs/pPwK2ol63UoKRwOjnFP6GfOsByW3uxCt7owDaZmX6NMxSK');

INSERT INTO Customer (Customer_name, Customer_phone, Customer_email, Customer_address, Customer_password, Created_at) VALUES
('Test Customer', '0700000020', 'customer@test.com', 'Hostel Block A, Room 12', '$2y$10$QctCRIc6rLs4nXp1sZtByeoM1D3rqO0LwF6hacRn5vsrp/rvYVIYa', '2026-11-09');

INSERT INTO Laundry_Order (Customer_id, Employee_id, Branch_id, Order_date, Due_date, Order_status, Total_amount) VALUES
(1, NULL, 1, '2026-11-09', '2026-11-11', 'Pending', 3500.00),
(1, 2, 1, '2026-11-08', '2026-11-10', 'Washing', 1500.00);

INSERT INTO Order_Item (Order_id, Service_id, Item_type_id, Order_quantity, Unit_price, Order_notes) VALUES
(1, 3, 1, 5, 700.00, 'Time slot: 08:00-10:00'),
(2, 2, 2, 3, 300.00, 'Time slot: 10:00-12:00');

INSERT INTO Payment (Order_id, Payment_amount, Payment_method, Payment_time, Payment_status) VALUES
(2, 1500.00, 'Cash', '2026-11-08 10:30:00', 'Paid');

INSERT INTO Delivery (Order_id, Employee_id, Delivery_type, Delivery_address, Delivery_status, Delivery_schedule) VALUES
(2, 2, 'Delivery', 'Hostel Block A, Room 12', 'Scheduled', '2026-11-10');
