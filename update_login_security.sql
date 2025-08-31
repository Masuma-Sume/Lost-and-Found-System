-- Add login security columns to user table
ALTER TABLE user
ADD COLUMN Login_Attempts INT DEFAULT 0,
ADD COLUMN Last_Login_Attempt DATETIME DEFAULT NULL;

-- Create table for remember me tokens
CREATE TABLE IF NOT EXISTS user_tokens (
    Token_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID VARCHAR(50) NOT NULL,
    Token VARCHAR(64) NOT NULL,
    Expires INT NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES user(User_ID) ON DELETE CASCADE
); 