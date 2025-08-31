-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `lost_and_found` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lost_and_found`;

-- User table
CREATE TABLE IF NOT EXISTS `user` (
    `User_ID` VARCHAR(7) PRIMARY KEY,
    `Email` VARCHAR(100) UNIQUE NOT NULL,
    `Name` VARCHAR(100) NOT NULL,
    `Password` VARCHAR(255) NOT NULL,
    `Contact_No` VARCHAR(15),
    `Role` ENUM('user', 'admin') DEFAULT 'user',
    `Login_Attempts` INT DEFAULT 0,
    `Last_Login_Attempt` DATETIME,
    `Last_Login` DATETIME,
    `Account_Status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Category table
CREATE TABLE IF NOT EXISTS `categories` (
    `Category_ID` INT AUTO_INCREMENT PRIMARY KEY,
    `Category_Name` VARCHAR(50) UNIQUE NOT NULL
);

-- Items table
CREATE TABLE IF NOT EXISTS `items` (
    `Item_ID` INT AUTO_INCREMENT PRIMARY KEY,
    `User_ID` VARCHAR(7),
    `Item_Name` VARCHAR(100) NOT NULL,
    `Item_Type` ENUM('lost', 'found') NOT NULL,
    `Category_ID` INT,
    `Location` VARCHAR(255) NOT NULL,
    `Latitude` DECIMAL(10, 8),
    `Longitude` DECIMAL(11, 8),
    `Description` TEXT,
    `Date_Reported` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `Date_Lost_Found` DATETIME,
    `Status` ENUM('open', 'claimed', 'closed', 'expired') DEFAULT 'open',
    `Image_URL` VARCHAR(255),
    FOREIGN KEY (`User_ID`) REFERENCES `user`(`User_ID`) ON DELETE SET NULL,
    FOREIGN KEY (`Category_ID`) REFERENCES `categories`(`Category_ID`) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `Notification_ID` INT AUTO_INCREMENT PRIMARY KEY,
    `User_ID` VARCHAR(7),
    `Item_ID` INT,
    `Type` ENUM('claim', 'match', 'status_update', 'reward', 'system') NOT NULL,
    `Message` TEXT NOT NULL,
    `Is_Read` TINYINT(1) DEFAULT 0,
    `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`User_ID`) REFERENCES `user`(`User_ID`) ON DELETE CASCADE,
    FOREIGN KEY (`Item_ID`) REFERENCES `items`(`Item_ID`) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO `user` (`User_ID`, `Email`, `Name`, `Password`, `Role`) 
VALUES ('ADM001', 'admin@bracu.ac.bd', 'System Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE `Role` = 'admin';

-- Insert default categories
INSERT INTO `categories` (`Category_Name`) VALUES 
('Electronics'), ('Books'), ('Accessories'), ('Documents'), ('Clothing'), ('Others')
ON DUPLICATE KEY UPDATE `Category_Name` = VALUES(`Category_Name`);
