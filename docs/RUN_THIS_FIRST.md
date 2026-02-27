# 🔧 Dashboard Error Fix - Step by Step

## Step 1: Run the Diagnostic Script

Open your browser and go to:
```
http://localhost/public_html/check_database_structure.php
```

This will show you:
- ✓ Which columns exist in your database
- ✗ Which columns are missing
- 📋 The exact SQL you need to run

## Step 2: Follow the Instructions

The diagnostic page will give you:
1. A list of missing columns (if any)
2. The exact SQL to copy
3. Step-by-step instructions

## Step 3: Run the SQL in phpMyAdmin

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click on `nielit_bhubaneswar` database (left sidebar)
3. Click the **SQL** tab at the top
4. Paste the SQL from the diagnostic page
5. Click **Go**
6. Wait for "X rows affected" success message

## Step 4: Verify the Fix

1. Refresh the diagnostic page: http://localhost/public_html/check_database_structure.php
2. All columns should show ✓ (green checkmarks)
3. Try the dashboard: http://localhost/public_html/admin/dashboard.php

## Still Getting Errors?

If you still see the error after running the SQL:

### Check 1: Did the SQL actually run?
- Look for a green success message in phpMyAdmin
- If you see a red error, copy the error message

### Check 2: Are you on the right database?
- Make sure you selected `nielit_bhubaneswar` database
- Not `information_schema` or `mysql` or `phpmyadmin`

### Check 3: Clear your browser cache
- Press `Ctrl + Shift + Delete`
- Clear cached images and files
- Or try in an Incognito/Private window

### Check 4: Restart XAMPP
Sometimes MySQL needs a restart:
1. Open XAMPP Control Panel
2. Click **Stop** next to MySQL
3. Wait 5 seconds
4. Click **Start** next to MySQL

## Need More Help?

Share the output from the diagnostic page and I can help further!
