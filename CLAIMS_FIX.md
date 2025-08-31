# Claims System Fix

## Issue
The claims functionality for both users and admins was not working properly due to a missing `Verification_Answers` column in the `claims` table. This column was added in a later update (`update_claims_table.sql`) but was not included in the original database setup script.

## Fix Applied

1. Created a fix script (`fix_claims_table.php`) that:
   - Checks if the `Verification_Answers` column exists in the `claims` table
   - Adds the column if it doesn't exist
   - Updates existing claims with a default empty JSON object (`{}`)

2. Updated the `setup_database.sql` file to include the `Verification_Answers` column in the `claims` table definition for future installations.

## How to Apply the Fix

Simply navigate to http://localhost/lostfound/fix_claims_table.php in your browser to run the fix script.

## Verification

After applying the fix, you can verify that the claims functionality is working properly by:

1. For users: Visit http://localhost/lostfound/my_claims.php
2. For admins: Visit http://localhost/lostfound/admin_review_claims.php

## Technical Details

The `Verification_Answers` column is used to store JSON-encoded verification answers provided by users when claiming items. These answers are used by item reporters and admins to verify the legitimacy of claims.

The column structure is:
```sql
Verification_Answers TEXT
```

The expected JSON structure is:
```json
{
  "color": "string",
  "brand": "string",
  "distinguishing_features": "string",
  "approximate_value": "string",
  "date_lost": "string"
}
```