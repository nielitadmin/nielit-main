# Fix Dashboard Error - Missing Database Columns

## Problem
You're getting this error:
```
Fatal error: Call to a member function bind_param() on bool in dashboard.php:91
```

This happens because the `courses` table is missing several required columns.

## Solution

### Step 1: Open phpMyAdmin
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Login with your MySQL credentials
3. Select the `nielit_bhubaneswar` database from the left sidebar

### Step 2: Run the SQL Migration
1. Click on the **SQL** tab at the top
2. Copy and paste the following SQL code:

```sql
-- Add missing columns to courses table
ALTER TABLE `courses` 
ADD COLUMN `course_code` VARCHAR(20) DEFAULT NULL AFTER `course_name`,
ADD COLUMN `course_abbreviation` VARCHAR(10) DEFAULT NULL AFTER `course_code`,
ADD COLUMN `apply_link` VARCHAR(500) DEFAULT NULL AFTER `description_pdf`,
ADD COLUMN `course_coordinator` VARCHAR(255) DEFAULT NULL AFTER `apply_link`,
ADD COLUMN `training_center` VARCHAR(255) DEFAULT 'NIELIT BHUBANESWAR CENTER' AFTER `course_coordinator`,
ADD COLUMN `link_published` TINYINT(1) DEFAULT 0 AFTER `training_center`,
ADD COLUMN `qr_code_path` VARCHAR(255) DEFAULT NULL AFTER `link_published`,
ADD COLUMN `qr_generated_at` DATETIME DEFAULT NULL AFTER `qr_code_path`;
```

3. Click the **Go** button to execute the SQL
4. You should see a success message: "8 rows affected"

### Step 3: Verify the Fix
1. In phpMyAdmin, click on the `courses` table
2. Click the **Structure** tab
3. Verify that these new columns are present:
   - `course_code`
   - `course_abbreviation`
   - `apply_link`
   - `course_coordinator`
   - `training_center`
   - `link_published`
   - `qr_code_path`
   - `qr_generated_at`

### Step 4: Test the Dashboard
1. Go to: `http://localhost/public_html/admin/dashboard.php`
2. The error should be gone
3. Try adding a new course to verify everything works

## What These Columns Do

- **course_code**: Unique identifier for the course (e.g., "PPI-2026")
- **course_abbreviation**: Short code used in student IDs (e.g., "PPI")
- **apply_link**: Registration link for students
- **course_coordinator**: Name of the person coordinating the course
- **training_center**: Location where training is conducted
- **link_published**: Whether the registration link is visible on the website (0 or 1)
- **qr_code_path**: Path to the generated QR code image
- **qr_generated_at**: Timestamp when QR code was created

## Alternative: Use the SQL File
You can also import the SQL file directly:
1. In phpMyAdmin, select the `nielit_bhubaneswar` database
2. Click **Import** tab
3. Click **Choose File** and select `database_add_missing_columns.sql`
4. Click **Go**

## Need Help?
If you still see errors after running this SQL, check:
1. Make sure you're running the SQL on the correct database
2. Check that your MySQL user has ALTER TABLE permissions
3. Verify XAMPP MySQL service is running
