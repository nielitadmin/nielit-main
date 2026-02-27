# Fix Admission Order Save Issue

## Problem
When you edit the admission order details (Ref, Dated, Location, Examination Month, Time, Faculty Name, Scheme/Project Incharge, Copy To) and click "Save Changes & Regenerate", the changes are not being saved.

## Root Cause
The database columns required to store these values don't exist in the `batches` table yet.

## Solution - Quick Fix (Recommended)

### Option 1: Use the Auto-Fix Script
1. Open your browser and navigate to:
   ```
   http://localhost/nielit/batch_module/admin/check_and_fix_admission_order.php
   ```
   (Replace `localhost/nielit` with your actual site URL)

2. The script will check which columns are missing

3. Click the "Fix Database Now" button

4. Done! Go back to the admission order page and try saving again

### Option 2: Run SQL Manually
If you prefer to run SQL directly:

1. Open phpMyAdmin or your MySQL client

2. Select your database

3. Run this SQL:
   ```sql
   ALTER TABLE `batches` 
   ADD COLUMN `admission_order_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `scheme_id`,
   ADD COLUMN `admission_order_date` DATE NULL DEFAULT NULL AFTER `admission_order_ref`,
   ADD COLUMN `examination_month` VARCHAR(100) NULL DEFAULT NULL AFTER `admission_order_date`,
   ADD COLUMN `class_time` VARCHAR(100) NULL DEFAULT NULL AFTER `examination_month`,
   ADD COLUMN `scheme_incharge` VARCHAR(255) NULL DEFAULT NULL AFTER `class_time`,
   ADD COLUMN `copy_to_list` TEXT NULL DEFAULT NULL AFTER `scheme_incharge`,
   ADD COLUMN `location` VARCHAR(255) NULL DEFAULT 'NIELIT Bhubaneswar' AFTER `copy_to_list`;
   ```

4. Done! Go back and try saving again

## How to Test

1. Go to any batch's "Generate Admission Order" page

2. Edit any field in the "Edit Order Details" section:
   - Change the Ref number
   - Change the date
   - Select a different location
   - Modify examination month
   - Change time
   - Update faculty name
   - Update scheme incharge
   - Add/modify copy to recipients

3. Click "Save Changes & Regenerate"

4. You should see a green success message: "Changes saved successfully!"

5. The preview should update immediately with your changes

6. Refresh the page - your changes should persist

## What Gets Saved

After the fix, these fields will be saved to the database:
- ✓ Reference number
- ✓ Order date
- ✓ Location (NIELIT Bhubaneswar or NIELIT Balasore)
- ✓ Examination month
- ✓ Class time
- ✓ Faculty name (stored as batch_coordinator)
- ✓ Scheme/Project Incharge
- ✓ Copy to recipients list

## Troubleshooting

### If you still see errors after running the fix:

1. Check browser console (F12) for JavaScript errors

2. Check if the save file exists:
   ```
   batch_module/admin/save_admission_order_details.php
   ```

3. Make sure your database user has ALTER TABLE permissions

4. Try clearing your browser cache and refreshing

### If the "Save Changes & Regenerate" button doesn't work:

1. Open browser console (F12)
2. Look for any red error messages
3. Make sure JavaScript is enabled
4. Try a different browser

## Files Involved

- `batch_module/admin/generate_admission_order.php` - Main page with save button
- `batch_module/admin/generate_admission_order_ajax.php` - Generates the preview with editable fields
- `batch_module/admin/save_admission_order_details.php` - Saves the data to database
- `batch_module/admin/check_and_fix_admission_order.php` - Auto-fix script (NEW)
- `batch_module/add_admission_order_columns.sql` - SQL to add columns

---

**Quick Start:** Just visit `check_and_fix_admission_order.php` and click the fix button!
