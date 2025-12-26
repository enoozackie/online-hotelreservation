-- ============================================================================
-- Monbela Hotel Management System - Complete Database Setup
-- ============================================================================
-- This script creates the complete database structure for the hotel system
-- Execute this script in phpMyAdmin or MySQL command line
-- ============================================================================

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `monbela` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `monbela`;

-- ============================================================================
-- Table: tblaccomodation (Accommodation Types)
-- ============================================================================
CREATE TABLE `tblaccomodation` (
    `ACCOMID` INT NOT NULL AUTO_INCREMENT,
    `ACCOMODATION` VARCHAR(30) NOT NULL,
    `ACCOMDESC` VARCHAR(90) NOT NULL,
    PRIMARY KEY (`ACCOMID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default accommodation types
INSERT INTO `tblaccomodation` (`ACCOMID`, `ACCOMODATION`, `ACCOMDESC`) VALUES
(12, 'Standard Room', 'max 22hrs.'),
(13, 'Travelers Time', 'max of 12hrs.'),
(15, 'Bayanihan Room', 'max 22hrs.');

-- ============================================================================
-- Table: tblroom (Rooms)
-- ============================================================================
CREATE TABLE tblroom_history (
    HISTORYID INT AUTO_INCREMENT PRIMARY KEY,
    ROOMID INT NOT NULL,
    ACTION VARCHAR(100) NOT NULL,
    DETAILS TEXT,
    USERID INT,
    USERNAME VARCHAR(100),
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ROOMID) REFERENCES tblroom(ROOMID) ON DELETE CASCADE,
    INDEX idx_room_history (ROOMID, CREATED_AT)
);
CREATE TABLE `tblroom` (
    `ROOMID` INT NOT NULL AUTO_INCREMENT,
    `ROOMNUM` INT NOT NULL,
    `ACCOMID` INT NOT NULL,
    `ROOM` VARCHAR(30) NOT NULL,
    `ROOMDESC` VARCHAR(255) NOT NULL DEFAULT '',
    `NUMPERSON` INT NOT NULL DEFAULT 1,
    `PRICE` DOUBLE NOT NULL DEFAULT 0,
    `ROOMIMAGE` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`ROOMID`),
    KEY `ACCOMID` (`ACCOMID`),
    CONSTRAINT `tblroom_ibfk_1` FOREIGN KEY (`ACCOMID`) 
        REFERENCES `tblaccomodation` (`ACCOMID`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample rooms
INSERT INTO `tblroom` (`ROOMID`, `ROOMNUM`, `ACCOMID`, `ROOM`, `ROOMDESC`, `NUMPERSON`, `PRICE`, `ROOMIMAGE`) VALUES
(11, 101, 12, 'Wing A', 'Without TV, Standard Room', 1, 615, 'rooms/room101.jpg'),
(12, 102, 12, 'Wing A', 'Without TV, Standard Room', 2, 725, 'rooms/room102.jpg'),
(13, 103, 13, 'Wing A', 'Without TV, Travelers Time', 1, 445, 'rooms/room103.jpg'),
(14, 104, 13, 'Wing A', 'Without TV, Travelers Time', 2, 495, 'rooms/room104.jpg'),
(15, 105, 15, 'Wing A', 'Group Room - minimum 5 pax - 250php per person', 5, 1250, 'rooms/room105.jpg'),
(16, 201, 12, 'Wing B', 'With TV, Standard Room', 1, 725, 'rooms/room201.jpg'),
(17, 202, 12, 'Wing B', 'With TV, Standard Room', 2, 835, 'rooms/room202.jpg'),
(18, 203, 13, 'Wing B', 'With TV, Travelers Time', 1, 555, 'rooms/room203.jpg'),
(19, 204, 13, 'Wing B', 'Without TV, Travelers Time', 2, 605, 'rooms/room204.jpg'),
(20, 205, 12, 'Wing B', 'Twin Beds with TV', 2, 845, 'rooms/room205.jpg');

-- ============================================================================
-- Table: tblguest (Guest Information)
-- ============================================================================
CREATE TABLE `tblguest` (
    `GUESTID` INT NOT NULL AUTO_INCREMENT,
    `REFNO` VARCHAR(30) NOT NULL DEFAULT '',
    `G_FNAME` VARCHAR(30) NOT NULL,
    `G_LNAME` VARCHAR(30) NOT NULL,
    `G_CITY` VARCHAR(90) NOT NULL DEFAULT '',
    `G_ADDRESS` VARCHAR(90) NOT NULL,
    `DBIRTH` DATE NOT NULL,
    `G_PHONE` VARCHAR(20) NOT NULL,
    `G_NATIONALITY` VARCHAR(30) NOT NULL DEFAULT '',
    `G_COMPANY` VARCHAR(90) NOT NULL DEFAULT '',
    `G_CADDRESS` VARCHAR(90) NOT NULL DEFAULT '',
    `G_TERMS` TINYINT(4) NOT NULL DEFAULT 0,
    `G_UNAME` VARCHAR(255) NOT NULL,
    `G_PASS` VARCHAR(255) NOT NULL,
    `ZIP` VARCHAR(20) NOT NULL DEFAULT '',
    `LOCATION` VARCHAR(125) NOT NULL DEFAULT '',
    `G_PROFILEIMAGE` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`GUESTID`),
    UNIQUE KEY `G_UNAME` (`G_UNAME`),
    UNIQUE KEY `G_PHONE` (`G_PHONE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tblreservation` (
    `RESERVEID` INT NOT NULL AUTO_INCREMENT,
    `CONFIRMATIONCODE` VARCHAR(50) NOT NULL,
    `TRANSDATE` DATE NOT NULL,
    `ROOMID` INT NOT NULL,
    `ARRIVAL` DATETIME NOT NULL,
    `DEPARTURE` DATETIME NOT NULL,
    `RPRICE` DOUBLE NOT NULL DEFAULT 0,
    `GUESTID` INT NOT NULL,
    `PRORPOSE` VARCHAR(30) NOT NULL DEFAULT 'Leisure',
    `STATUS` VARCHAR(30) NOT NULL DEFAULT 'Pending',
    `BOOKDATE` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `REMARKS` TEXT,
    `USERID` INT DEFAULT NULL,
    `PAYMENT_PROOF` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`RESERVEID`),
    UNIQUE KEY `CONFIRMATIONCODE` (`CONFIRMATIONCODE`),
    KEY `ROOMID` (`ROOMID`),
    KEY `GUESTID` (`GUESTID`),
    KEY `STATUS` (`STATUS`),
    CONSTRAINT `tblreservation_ibfk_1` FOREIGN KEY (`ROOMID`) 
        REFERENCES `tblroom` (`ROOMID`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `tblreservation_ibfk_2` FOREIGN KEY (`GUESTID`) 
        REFERENCES `tblguest` (`GUESTID`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Check if column exists
SHOW COLUMNS FROM tblroom LIKE 'amenities';

-- If it doesn't exist, add it
ALTER TABLE tblroom 
ADD COLUMN amenities TEXT DEFAULT NULL 
AFTER ROOMIMAGE;

-- Optional: If you want to see the table structure
DESCRIBE tblroom;
-- ============================================================================
-- Table: tblpayment (Payment Records)
-- ============================================================================
CREATE TABLE `tblpayment` (
    `SUMMARYID` INT NOT NULL AUTO_INCREMENT,
    `TRANSDATE` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `CONFIRMATIONCODE` VARCHAR(30) NOT NULL,
    `PQTY` INT NOT NULL DEFAULT 1,
    `GUESTID` INT NOT NULL,
    `SPRICE` DOUBLE NOT NULL DEFAULT 0,
    `MSGVIEW` TINYINT(1) NOT NULL DEFAULT 0,
    `STATUS` VARCHAR(30) NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (`SUMMARYID`),
    UNIQUE KEY `CONFIRMATIONCODE` (`CONFIRMATIONCODE`),
    KEY `GUESTID` (`GUESTID`),
    CONSTRAINT `tblpayment_ibfk_1` FOREIGN KEY (`GUESTID`) 
        REFERENCES `tblguest` (`GUESTID`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: admins (Admin Users - Modern Authentication)
-- ============================================================================
CREATE TABLE `admins` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin account
-- Username: admin
-- Password: admin123
-- IMPORTANT: Change this password after first login!
INSERT INTO `admins` (`username`, `password`, `fullname`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator');

-- ============================================================================
-- Table: tblamenities (Hotel Amenities - Optional)
-- ============================================================================
CREATE TABLE `tblamenities` (
    `AMENID` INT NOT NULL AUTO_INCREMENT,
    `AMENNAME` VARCHAR(125) NOT NULL,
    `AMENDECS` VARCHAR(125) NOT NULL,
    `AMENIMAGE` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`AMENID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: tbluseraccount (Legacy User Account - Optional)
-- ============================================================================
CREATE TABLE `tbluseraccount` (
    `USERID` INT NOT NULL AUTO_INCREMENT,
    `UNAME` VARCHAR(30) NOT NULL,
    `USER_NAME` VARCHAR(30) NOT NULL,
    `UPASS` VARCHAR(90) NOT NULL,
    `ROLE` VARCHAR(30) NOT NULL DEFAULT 'Staff',
    `PHONE` VARCHAR(20) NOT NULL DEFAULT '',
    PRIMARY KEY (`USERID`),
    UNIQUE KEY `USER_NAME` (`USER_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Indexes for Performance
-- ============================================================================
CREATE INDEX idx_guest_uname ON tblguest(G_UNAME);
CREATE INDEX idx_guest_phone ON tblguest(G_PHONE);
CREATE INDEX idx_reservation_dates ON tblreservation(ARRIVAL, DEPARTURE);
CREATE INDEX idx_reservation_status ON tblreservation(STATUS);
CREATE INDEX idx_payment_status ON tblpayment(STATUS);

-- ============================================================================
-- Database Information
-- ============================================================================
-- Database: monbela
-- Character Set: utf8mb4
-- Collation: utf8mb4_unicode_ci
--debug for room names
 UPDATE tblroom SET ROOM = 'Bayanihan Suite' WHERE ROOMID = 31;
UPDATE tblroom SET ROOM = 'Standard Room' WHERE ROOMID = 32;
UPDATE tblroom SET ROOM = 'Travelers Time' WHERE ROOMID = 30;
-- Default Admin Credentials:
-- Username: admin
-- Password: admin123
-- 
-- SECURITY NOTICE:
-- 1. Change the default admin password immediately after installation
-- 2. Use strong passwords with PASSWORD_DEFAULT in PHP
-- 3. All passwords in the system use bcrypt hashing
-- ============================================================================