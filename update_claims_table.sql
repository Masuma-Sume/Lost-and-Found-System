-- Add Verification_Answers column to claims table
ALTER TABLE claims ADD COLUMN Verification_Answers TEXT AFTER Claim_Description;

-- Update existing claims to have an empty JSON object for Verification_Answers
UPDATE claims SET Verification_Answers = '{}' WHERE Verification_Answers IS NULL;