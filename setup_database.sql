-- Create database if not exists
CREATE DATABASE IF NOT EXISTS lost_and_found;
USE lost_and_found;

-- User table
CREATE TABLE IF NOT EXISTS user (
    User_ID VARCHAR(7) PRIMARY KEY,
    Email VARCHAR(100) UNIQUE NOT NULL,
    Name VARCHAR(100) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Contact_No VARCHAR(15),
    Role ENUM('user', 'admin') DEFAULT 'user',
    Login_Attempts INT DEFAULT 0,
    Last_Login_Attempt DATETIME,
    Last_Login DATETIME,
    Account_Status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Category table
CREATE TABLE IF NOT EXISTS categories (
    Category_ID INT AUTO_INCREMENT PRIMARY KEY,
    Category_Name VARCHAR(50) UNIQUE NOT NULL
);

-- Items table
CREATE TABLE IF NOT EXISTS items (
    Item_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID VARCHAR(7), -- can be NULL for anonymous found items
    Item_Name VARCHAR(100) NOT NULL,
    Item_Type ENUM('lost', 'found') NOT NULL,
    Category_ID INT,
    Location VARCHAR(255) NOT NULL,
    Latitude DECIMAL(10, 8),
    Longitude DECIMAL(11, 8),
    Description TEXT,
    Date_Reported DATETIME DEFAULT CURRENT_TIMESTAMP,
    Date_Lost_Found DATETIME,
    Status ENUM('open', 'claimed', 'closed', 'expired') DEFAULT 'open',
    Approval_Status ENUM('pending','approved','rejected') DEFAULT 'pending',
    Image_URL VARCHAR(255),
    FOREIGN KEY (User_ID) REFERENCES user(User_ID) ON DELETE SET NULL,
    FOREIGN KEY (Category_ID) REFERENCES categories(Category_ID) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    Notification_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID VARCHAR(7),
    Item_ID INT,
    Type ENUM('claim', 'match', 'status_update', 'reward', 'system') NOT NULL,
    Message TEXT NOT NULL,
    Is_Read BOOLEAN DEFAULT FALSE,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES user(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Item_ID) REFERENCES items(Item_ID) ON DELETE CASCADE
);

-- Claims table
CREATE TABLE IF NOT EXISTS claims (
    Claim_ID INT AUTO_INCREMENT PRIMARY KEY,
    Item_ID INT NOT NULL,
    Claimant_ID VARCHAR(7) NOT NULL,
    Claim_Status ENUM('pending', 'verified', 'rejected', 'approved') DEFAULT 'pending',
    Claim_Description TEXT,
    Verification_Answers TEXT,
    Verification_Score INT DEFAULT 0,
    Admin_Notes TEXT,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (Item_ID) REFERENCES items(Item_ID) ON DELETE CASCADE,
    FOREIGN KEY (Claimant_ID) REFERENCES user(User_ID) ON DELETE CASCADE
);

-- Security questions table
CREATE TABLE IF NOT EXISTS security_questions (
    Question_ID INT AUTO_INCREMENT PRIMARY KEY,
    Item_ID INT NOT NULL,
    Question TEXT NOT NULL,
    Answer VARCHAR(255) NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Item_ID) REFERENCES items(Item_ID) ON DELETE CASCADE
);

-- Badges table
CREATE TABLE IF NOT EXISTS badges (
    Badge_ID INT AUTO_INCREMENT PRIMARY KEY,
    Badge_Name VARCHAR(50) UNIQUE NOT NULL,
    Badge_Description TEXT
);

-- Rewards table
CREATE TABLE IF NOT EXISTS rewards (
    Reward_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID VARCHAR(7) NOT NULL,
    Item_ID INT NOT NULL,
    Points INT NOT NULL DEFAULT 0,
    Badge_ID INT,
    Status ENUM('pending', 'awarded', 'expired') DEFAULT 'pending',
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES user(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Item_ID) REFERENCES items(Item_ID) ON DELETE CASCADE,
    FOREIGN KEY (Badge_ID) REFERENCES badges(Badge_ID) ON DELETE SET NULL
);

-- User rewards summary table
CREATE TABLE IF NOT EXISTS user_rewards (
    User_ID VARCHAR(7) NOT NULL,
    Total_Points INT DEFAULT 0,
    Last_Updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (User_ID),
    FOREIGN KEY (User_ID) REFERENCES user(User_ID) ON DELETE CASCADE
);

-- Location hotspots table
CREATE TABLE IF NOT EXISTS location_hotspots (
    Hotspot_ID INT AUTO_INCREMENT PRIMARY KEY,
    Location_Name VARCHAR(100) NOT NULL,
    Latitude DECIMAL(10, 8) NOT NULL,
    Longitude DECIMAL(11, 8) NOT NULL,
    Item_Count INT DEFAULT 0,
    Last_Updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admin logs table
CREATE TABLE IF NOT EXISTS admin_logs (
    Log_ID INT AUTO_INCREMENT PRIMARY KEY,
    Admin_ID VARCHAR(7) NOT NULL,
    Action VARCHAR(50) NOT NULL,
    Description TEXT,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Admin_ID) REFERENCES user(User_ID) ON DELETE CASCADE
);

-- Monthly reports table
CREATE TABLE IF NOT EXISTS monthly_reports (
    Report_ID INT AUTO_INCREMENT PRIMARY KEY,
    `Year` INT NOT NULL,
    `Month` INT NOT NULL,
    Total_Items INT DEFAULT 0,
    Lost_Items INT DEFAULT 0,
    Found_Items INT DEFAULT 0,
    Resolved_Cases INT DEFAULT 0,
    Active_Cases INT DEFAULT 0,
    Generated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY year_month (`Year`, `Month`)
);

-- Insert default admin user
INSERT INTO user (User_ID, Email, Name, Password, Role) 
VALUES ('ADM001', 'admin@bracu.ac.bd', 'System Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE Role = 'admin';

-- Insert default categories
INSERT INTO categories (Category_Name) VALUES 
('Electronics'), ('Books'), ('Accessories'), ('Documents'), ('Clothing'), ('Others')
ON DUPLICATE KEY UPDATE Category_Name = VALUES(Category_Name);

-- Insert default badges
INSERT INTO badges (Badge_Name, Badge_Description) VALUES
('Honest Finder', 'Awarded for returning a lost item'),
('Super Helper', 'Awarded for 5 successful returns'),
('Campus Hero', 'Awarded for 10+ successful returns')
ON DUPLICATE KEY UPDATE Badge_Name = VALUES(Badge_Name);
