-- Add verification fields to items table
ALTER TABLE items ADD COLUMN Color VARCHAR(50) AFTER Description;
ALTER TABLE items ADD COLUMN Brand VARCHAR(100) AFTER Color;
ALTER TABLE items ADD COLUMN Distinguishing_Features TEXT AFTER Brand;
ALTER TABLE items ADD COLUMN Approximate_Value VARCHAR(100) AFTER Distinguishing_Features;
ALTER TABLE items ADD COLUMN Date_Lost DATE AFTER Approximate_Value;

-- Update existing items to have empty values for new fields
UPDATE items SET Color = '', Brand = '', Distinguishing_Features = '', Approximate_Value = '', Date_Lost = NULL WHERE Color IS NULL;
