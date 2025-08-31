-- Add Profile_Photo column to user table
ALTER TABLE user ADD COLUMN Profile_Photo VARCHAR(255) AFTER Contact_No;
