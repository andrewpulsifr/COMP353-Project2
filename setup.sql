-- setup.sql
-- Purpose: Re-runnable schema + seed data for the COMP353 RENTTRUCK project.
-- Notes: Drops and recreates all tables, triggers, and stored procedures.

USE qwc353_4;

-- Re-runnable setup script.
-- WARNING: This drops and recreates all project tables.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS PAYMENT;
DROP TABLE IF EXISTS INVOICE_LINE;
DROP TABLE IF EXISTS INVOICE;
DROP TABLE IF EXISTS MISSION;
DROP TABLE IF EXISTS RESERVATION;
DROP TABLE IF EXISTS VEHICLE;
DROP TABLE IF EXISTS DRIVER;
DROP TABLE IF EXISTS VEHICLE_RATE;
DROP TABLE IF EXISTS CLIENT;

DROP PROCEDURE IF EXISTS update_mission_details;
DROP PROCEDURE IF EXISTS cancel_mission;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- CREATE TABLES
-- =====================================================

CREATE TABLE CLIENT (
   client_id INT PRIMARY KEY AUTO_INCREMENT,
   client_name VARCHAR(30) NOT NULL,
   client_addr VARCHAR(40) NOT NULL,
   client_phone VARCHAR(10) NOT NULL UNIQUE,
   client_type VARCHAR(20) NOT NULL,
   CONSTRAINT chk_client_type CHECK (client_type IN ('Individual', 'Business'))
) ENGINE=InnoDB;

CREATE TABLE DRIVER (
   driver_id INT PRIMARY KEY AUTO_INCREMENT,
   driver_licence_type CHAR(1) NOT NULL,
   driver_first_name VARCHAR(30) NOT NULL,
   driver_last_name VARCHAR(30) NOT NULL,
   CONSTRAINT chk_driver_licence CHECK (driver_licence_type IN ('T', 'H', 'S'))
) ENGINE=InnoDB;

CREATE TABLE VEHICLE (
   vehicle_id INT PRIMARY KEY AUTO_INCREMENT,
   vehicle_type VARCHAR(20) NOT NULL,
   vehicle_brand VARCHAR(15) NOT NULL,
   CONSTRAINT chk_vehicle_type CHECK (vehicle_type IN ('Tourism', 'Heavyweight', 'SuperHeavyweight'))
) ENGINE=InnoDB;

-- Master data for pricing (3 rows, one per allowed vehicle type)
CREATE TABLE VEHICLE_RATE (
   vehicle_type VARCHAR(20) PRIMARY KEY,
   price_per_day FLOAT NOT NULL,
   price_per_km FLOAT NOT NULL,
   CONSTRAINT chk_vehicle_type_rate CHECK (vehicle_type IN ('Tourism', 'Heavyweight', 'SuperHeavyweight')),
   CONSTRAINT chk_prices CHECK (price_per_day > 0 AND price_per_km > 0)
) ENGINE=InnoDB;

CREATE TABLE INVOICE (
   invoice_id INT PRIMARY KEY AUTO_INCREMENT,
   client_id INT NOT NULL,
   invoice_date DATE NOT NULL,
   FOREIGN KEY (client_id) REFERENCES CLIENT(client_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE RESERVATION (
   res_id INT PRIMARY KEY AUTO_INCREMENT,
   client_id INT NOT NULL,
   reservation_date DATE NOT NULL,
   res_status CHAR(1) NOT NULL,
   requested_vehicle_type VARCHAR(20) NOT NULL,
   expected_duration INT NOT NULL,
   rendezvous_location VARCHAR(30) NOT NULL,
   appointment_datetime DATETIME NOT NULL,
   FOREIGN KEY (client_id) REFERENCES CLIENT(client_id) ON DELETE CASCADE ON UPDATE CASCADE,
   FOREIGN KEY (requested_vehicle_type) REFERENCES VEHICLE_RATE(vehicle_type) ON UPDATE CASCADE,
   CONSTRAINT chk_reservation_status CHECK (res_status IN ('P', 'C', 'X')),
   CONSTRAINT chk_expected_duration CHECK (expected_duration > 0 AND expected_duration <= 5)
) ENGINE=InnoDB;

CREATE TABLE MISSION (
   mission_id INT PRIMARY KEY AUTO_INCREMENT,
   res_id INT NOT NULL,
   driver_id INT NOT NULL,
   vehicle_id INT NOT NULL,
   rendezvous_location VARCHAR(30) NOT NULL,
   appointment_datetime DATETIME NOT NULL,
   duration FLOAT NOT NULL,
   actual_start_datetime DATETIME,
   actual_end_datetime DATETIME,
   odometer_start INT,
   odometer_end INT,
   mission_status CHAR(1) NOT NULL,
   FOREIGN KEY (res_id) REFERENCES RESERVATION(res_id) ON DELETE CASCADE ON UPDATE CASCADE,
   FOREIGN KEY (driver_id) REFERENCES DRIVER(driver_id) ON DELETE RESTRICT ON UPDATE CASCADE,
   FOREIGN KEY (vehicle_id) REFERENCES VEHICLE(vehicle_id) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT chk_mission_status CHECK (mission_status IN ('S', 'C')),
   CONSTRAINT chk_mission_duration CHECK (duration > 0 AND duration <= 5),
   CONSTRAINT chk_odometer CHECK (odometer_start IS NULL OR odometer_end IS NULL OR odometer_end >= odometer_start)
) ENGINE=InnoDB;

CREATE TABLE INVOICE_LINE (
   invoice_id INT NOT NULL,
   mission_id INT NOT NULL,
   rental_cost FLOAT NOT NULL,
   PRIMARY KEY (invoice_id, mission_id),
   FOREIGN KEY (invoice_id) REFERENCES INVOICE(invoice_id) ON DELETE CASCADE ON UPDATE CASCADE,
   FOREIGN KEY (mission_id) REFERENCES MISSION(mission_id) ON DELETE CASCADE ON UPDATE CASCADE,
   CONSTRAINT chk_rental_cost CHECK (rental_cost >= 0)
) ENGINE=InnoDB;

CREATE TABLE PAYMENT (
   payment_id INT PRIMARY KEY AUTO_INCREMENT,
   invoice_id INT NOT NULL UNIQUE,
   method VARCHAR(20) NOT NULL,
   pay_status CHAR(1) NOT NULL,
   amount FLOAT NOT NULL,
   pay_date DATE,
   FOREIGN KEY (invoice_id) REFERENCES INVOICE(invoice_id) ON DELETE CASCADE ON UPDATE CASCADE,
   CONSTRAINT chk_payment_method CHECK (method IN ('Credit Card', 'Cash', 'Check')),
   CONSTRAINT chk_payment_status CHECK (pay_status IN ('C', 'P')),
   CONSTRAINT chk_payment_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

-- =====================================================
-- INDEXES
-- =====================================================

CREATE INDEX idx_reservation_client_id ON RESERVATION(client_id);
CREATE INDEX idx_mission_res_id ON MISSION(res_id);
CREATE INDEX idx_mission_driver_id ON MISSION(driver_id);
CREATE INDEX idx_mission_vehicle_id ON MISSION(vehicle_id);
CREATE INDEX idx_invoice_client_id ON INVOICE(client_id);
CREATE INDEX idx_invoice_date ON INVOICE(invoice_date);
CREATE INDEX idx_payment_invoice_id ON PAYMENT(invoice_id);

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

CREATE PROCEDURE update_mission_details(
    IN p_mission_id INT,
    IN p_start_datetime DATETIME,
    IN p_mission_duration FLOAT,
    IN p_odometer_start INT,
    IN p_odometer_end INT,
    IN p_status CHAR(1)
)
BEGIN
   IF p_mission_id IS NULL OR p_mission_id <= 0 THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Invalid mission_id';
   END IF;

   IF p_start_datetime IS NULL THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Start datetime is required';
   END IF;

   IF p_mission_duration IS NULL OR p_mission_duration <= 0 OR p_mission_duration > 5 THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Mission duration must be > 0 and <= 5 days';
   END IF;

   IF p_odometer_start IS NULL OR p_odometer_end IS NULL THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Odometer values are required';
   END IF;

   IF p_odometer_end < p_odometer_start THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'odometer_end must be >= odometer_start';
   END IF;

   START TRANSACTION;

   UPDATE MISSION
   SET actual_start_datetime = p_start_datetime,
       actual_end_datetime = DATE_ADD(p_start_datetime, INTERVAL p_mission_duration DAY),
       duration = p_mission_duration,
       odometer_start = p_odometer_start,
       odometer_end = p_odometer_end,
       mission_status = p_status
   WHERE mission_id = p_mission_id;

   IF ROW_COUNT() = 0 THEN
      ROLLBACK;
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Mission not found';
   END IF;

   COMMIT;
END //

CREATE PROCEDURE cancel_mission(
    IN p_mission_id INT,
    IN p_reason VARCHAR(255)
)
BEGIN
    DELETE FROM MISSION WHERE mission_id = p_mission_id;

    IF ROW_COUNT() > 0 THEN
        SELECT CONCAT('Mission ', p_mission_id, ' cancelled: ', p_reason) AS result;
    ELSE
        SELECT CONCAT('Mission ', p_mission_id, ' not found') AS result;
    END IF;
END //

DELIMITER ;

-- =====================================================
-- SEED DATA
-- =====================================================

INSERT INTO VEHICLE_RATE (vehicle_type, price_per_day, price_per_km) VALUES
('Tourism', 150, 0.25),
('Heavyweight', 350, 0.50),
('SuperHeavyweight', 550, 0.75);

INSERT INTO CLIENT (client_id, client_name, client_addr, client_phone, client_type) VALUES
(1, 'ABC Logistics', '100 Depot Rd', '5140000001', 'Business'),
(2, 'XYZ Transport', '200 Commerce', '4160000002', 'Business'),
(3, 'John Smith', '123 Main St', '5140000003', 'Individual'),
(4, 'Sarah Johnson', '456 Oak Ave', '4160000004', 'Individual'),
(5, 'Mike Chen', '654 Elm St', '6130000005', 'Individual'),
(6, 'Jennifer Brown', '987 Maple Ln', '4030000006', 'Individual'),
(7, 'Global Shipping', '135 Trade Way', '5140000007', 'Business'),
(8, 'Local Movers', '246 Transport', '4160000008', 'Business'),
(9, 'Robert Davis', '369 Pine Rd', '6040000009', 'Individual'),
(10, 'Emma Wilson', '741 Birch Dr', '5140000010', 'Individual');

INSERT INTO DRIVER (driver_id, driver_licence_type, driver_first_name, driver_last_name) VALUES
(1, 'T', 'Alice', 'Nguyen'),
(2, 'H', 'Ben', 'Martin'),
(3, 'S', 'Chloe', 'Patel'),
(4, 'T', 'David', 'Wong'),
(5, 'H', 'Elena', 'Garcia'),
(6, 'S', 'Farah', 'Khan'),
(7, 'T', 'George', 'Brown'),
(8, 'H', 'Hana', 'Ito'),
(9, 'S', 'Ivan', 'Petrov'),
(10, 'T', 'Julia', 'Lopez');

INSERT INTO VEHICLE (vehicle_id, vehicle_type, vehicle_brand) VALUES
(1, 'Tourism', 'Toyota'),
(2, 'Tourism', 'Honda'),
(3, 'Tourism', 'Ford'),
(4, 'Heavyweight', 'Volvo'),
(5, 'Heavyweight', 'Scania'),
(6, 'SuperHeavyweight', 'MAN'),
(7, 'SuperHeavyweight', 'Volvo'),
(8, 'Tourism', 'Nissan'),
(9, 'Tourism', 'Mazda'),
(10, 'Heavyweight', 'GMC'),
(11, 'Tourism', 'GMC'),
(12, 'SuperHeavyweight', 'GMC');

-- 10+ reservations
INSERT INTO RESERVATION (res_id, client_id, reservation_date, res_status, requested_vehicle_type, expected_duration, rendezvous_location, appointment_datetime) VALUES
(1, 1, '2026-01-15', 'P', 'Tourism', 2, 'Airport', '2026-03-11 08:00:00'),
(2, 2, '2026-01-16', 'P', 'Heavyweight', 3, 'Warehouse', '2026-03-12 09:30:00'),
(3, 3, '2026-01-17', 'C', 'Tourism', 2, 'Downtown', '2026-03-13 10:00:00'),
(4, 4, '2026-01-18', 'P', 'SuperHeavyweight', 4, 'Port', '2026-03-14 07:00:00'),
(5, 5, '2026-01-19', 'X', 'Tourism', 1, 'Convention', '2026-03-15 08:30:00'),
(6, 6, '2026-01-20', 'P', 'Heavyweight', 2, 'Industrial', '2026-03-16 09:00:00'),
(7, 7, '2026-01-21', 'P', 'Tourism', 3, 'Airport', '2026-03-17 10:30:00'),
(8, 8, '2026-01-22', 'P', 'SuperHeavyweight', 4, 'Distribution', '2026-03-18 07:30:00'),
(9, 9, '2026-02-01', 'P', 'Tourism', 2, 'Downtown', '2026-03-15 08:00:00'),
(10, 10, '2026-02-05', 'P', 'Tourism', 3, 'Downtown', '2026-03-16 09:30:00');

-- Missions in Mar 11-18 range to satisfy Query 4, plus one high-mileage mission for Query 9
INSERT INTO MISSION (
  mission_id, res_id, driver_id, vehicle_id, rendezvous_location, appointment_datetime,
   duration, actual_start_datetime, actual_end_datetime, odometer_start, odometer_end, mission_status
) VALUES
(1, 1, 1, 1, 'Airport', '2026-03-11 08:00:00', 2, NULL, NULL, NULL, NULL, 'S'),
(2, 2, 2, 4, 'Warehouse', '2026-03-12 09:30:00', 3, '2026-03-12 09:40:00', '2026-03-15 09:40:00', 120000, 120450, 'C'),
(3, 3, 3, 11, 'Downtown', '2026-03-13 10:00:00', 2, '2026-03-13 10:10:00', '2026-03-15 10:10:00', 45000, 45200, 'C'),
(4, 4, 4, 6, 'Port', '2026-03-14 07:00:00', 4, '2026-03-14 07:15:00', '2026-03-18 07:15:00', 80000, 80750, 'C'),
(5, 6, 5, 10, 'Industrial', '2026-03-16 09:00:00', 2, '2026-03-16 09:10:00', '2026-03-18 09:10:00', 62000, 62350, 'C'),
(6, 7, 6, 2, 'Airport', '2026-03-17 10:30:00', 3, NULL, NULL, NULL, NULL, 'S'),
(7, 8, 7, 12, 'Distribution', '2026-03-18 07:30:00', 4, NULL, NULL, NULL, NULL, 'S'),
(8, 9, 8, 3, 'Downtown', '2026-03-15 08:00:00', 2, NULL, NULL, NULL, NULL, 'S'),
(9, 10, 9, 7, 'Downtown', '2026-03-16 09:30:00', 3, '2026-03-16 09:45:00', '2026-03-19 09:45:00', 10000, 18050, 'C'),
(10, 2, 10, 5, 'Warehouse', '2026-03-12 13:00:00', 3, '2026-03-12 13:05:00', '2026-03-15 13:05:00', 90000, 90100, 'C');

-- 10 invoices
INSERT INTO INVOICE (invoice_id, client_id, invoice_date) VALUES
(1, 1, '2026-03-10'),
(2, 2, '2026-03-10'),
(3, 3, '2026-03-10'),
(4, 4, '2026-03-10'),
(5, 5, '2026-03-11'),
(6, 6, '2026-03-11'),
(7, 7, '2026-03-11'),
(8, 8, '2026-03-12'),
(9, 9, '2026-03-12'),
(10, 10, '2026-03-12');

-- Invoice lines (ensure some invoices sum > 1000 for Query 7)
INSERT INTO INVOICE_LINE (invoice_id, mission_id, rental_cost) VALUES
(1, 2, 1200.00),
(1, 3, 450.00),
(2, 4, 2200.00),
(3, 5, 1050.00),
(4, 10, 350.00),
(5, 9, 1750.00),
(6, 6, 450.00),
(7, 7, 2200.00),
(8, 8, 300.00),
(9, 1, 300.00);

-- Payments (some pending for Query 5)
INSERT INTO PAYMENT (payment_id, invoice_id, method, pay_status, amount, pay_date) VALUES
(1, 1, 'Credit Card', 'C', 1650.00, '2026-03-10'),
(2, 2, 'Cash', 'P', 2200.00, NULL),
(3, 3, 'Check', 'C', 1050.00, '2026-03-10'),
(4, 4, 'Credit Card', 'C', 350.00, '2026-03-10'),
(5, 5, 'Credit Card', 'P', 1750.00, NULL),
(6, 6, 'Cash', 'C', 450.00, '2026-03-11'),
(7, 7, 'Check', 'C', 2200.00, '2026-03-11'),
(8, 8, 'Credit Card', 'C', 300.00, '2026-03-12'),
(9, 9, 'Credit Card', 'C', 300.00, '2026-03-12'),
(10, 10, 'Cash', 'C', 0.00, '2026-03-12');

-- =====================================================
-- EXTRA DEMO / BUFFER DATA
-- Purpose:
-- 1) Keep counts above minimum after update/delete demos
-- 2) Add rows that do NOT match some query filters, so results stand out
-- =====================================================

-- Extra clients
INSERT INTO CLIENT (client_id, client_name, client_addr, client_phone, client_type) VALUES
(11, 'Northline Cargo', '852 Harbor St', '4380000011', 'Business'),
(12, 'Olivia Moore', '159 Cedar Ave', '4380000012', 'Individual'),
(13, 'Prairie Freight', '963 Route 7', '8190000013', 'Business');

-- Extra drivers
INSERT INTO DRIVER (driver_id, driver_licence_type, driver_first_name, driver_last_name) VALUES
(11, 'H', 'Kevin', 'Roy'),
(12, 'T', 'Laura', 'Mills'),
(13, 'S', 'Noah', 'Singh');

-- Extra vehicles
INSERT INTO VEHICLE (vehicle_id, vehicle_type, vehicle_brand) VALUES
(13, 'Tourism', 'Hyundai'),
(14, 'Heavyweight', 'Kenworth'),
(15, 'SuperHeavyweight', 'Freightliner');

-- Extra reservations:
-- some inside the general project timeline, some outside Mar 11-18 so Query 4 visibly filters them out
INSERT INTO RESERVATION (res_id, client_id, reservation_date, res_status, requested_vehicle_type, expected_duration, rendezvous_location, appointment_datetime) VALUES
(11, 11, '2026-01-25', 'P', 'Heavyweight', 2, 'Harbor', '2026-03-20 08:00:00'),
(12, 12, '2026-01-28', 'C', 'Tourism', 1, 'Museum', '2026-02-20 11:00:00'),
(13, 13, '2026-02-02', 'P', 'SuperHeavyweight', 5, 'Refinery', '2026-03-25 06:30:00'),
(14, 3, '2026-02-07', 'X', 'Tourism', 2, 'Stadium', '2026-03-22 14:00:00'),
(15, 7, '2026-02-10', 'P', 'Tourism', 1, 'Airport', '2026-03-31 09:00:00');

-- Extra missions:
-- outside Mar 11-18 for Query 4 filtering,
-- one low-mileage completed mission so Query 9 excludes it,
-- one >7000 km completed mission so Query 9 still clearly works even after deleting another qualifying row
INSERT INTO MISSION (
  mission_id, res_id, driver_id, vehicle_id, rendezvous_location, appointment_datetime,
  duration, actual_start_datetime, actual_end_datetime, odometer_start, odometer_end, mission_status
) VALUES
(11, 11, 11, 14, 'Harbor', '2026-03-20 08:00:00', 2, '2026-03-20 08:10:00', '2026-03-22 08:10:00', 30000, 30420, 'C'),
(12, 12, 12, 13, 'Museum', '2026-02-20 11:00:00', 1, '2026-02-20 11:05:00', '2026-02-21 11:05:00', 41000, 41110, 'C'),
(13, 13, 13, 15, 'Refinery', '2026-03-25 06:30:00', 5, NULL, NULL, NULL, NULL, 'S'),
(14, 15, 1, 8, 'Airport', '2026-03-31 09:00:00', 1, '2026-03-31 09:15:00', '2026-04-01 09:15:00', 150000, 158200, 'C');

-- Extra invoices
INSERT INTO INVOICE (invoice_id, client_id, invoice_date) VALUES
(11, 11, '2026-03-22'),
(12, 12, '2026-02-21'),
(13, 13, '2026-03-26'),
(14, 7, '2026-04-01');

-- Extra invoice lines
-- includes one invoice total under 1000 and one well over 1000 for Query 7 contrast
INSERT INTO INVOICE_LINE (invoice_id, mission_id, rental_cost) VALUES
(11, 11, 900.00),
(12, 12, 200.00),
(13, 13, 3200.00),
(14, 14, 2400.00);

-- Extra payments
-- mix of completed and pending so Query 5 still has contrast after demos
INSERT INTO PAYMENT (payment_id, invoice_id, method, pay_status, amount, pay_date) VALUES
(11, 11, 'Cash', 'C', 900.00, '2026-03-22'),
(12, 12, 'Credit Card', 'C', 200.00, '2026-02-21'),
(13, 13, 'Check', 'P', 3200.00, NULL),
(14, 14, 'Credit Card', 'C', 2400.00, '2026-04-01');
