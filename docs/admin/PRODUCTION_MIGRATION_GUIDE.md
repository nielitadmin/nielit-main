# Production Migration Guide - Fix Dashboard Error

## ❌ Current Error
```
Fatal error: Call to a member function bind_param() on bool in 
/home/u664913565/domains/nielitbhubaneswar.in/public_html/admin/dashboard.php:248
```

## 🔧 Root Cause
The `registration_token` column doesn't exist in the production database, causing the SQL `prepare()` to fail and return `false`.

## ✅ Solution Steps

### Step 1: Upload Migration File
Upload this file to your production server:
- **Local file:** `migrations/add_registration_token_production.php`
- **Production path:** `/migrations/add_registration_token_production.php`

### Step 2: Run Migration
Open your browser and visit:
```
https://nielitbhubaneswar.in/migrations/add_registration_token_production.php
```

You should see a page with:
- ✅ Database connection successful
- ✅ Column added successfully
- ✅ Index created
- ✅ Tokens generated for existing courses

### Step 3: Delete Migration File
**IMMEDIATELY** delete the file from production:
```
/migrations/add_registration_token_production.php
```

⚠️ **Security:** This file contains database credentials and must be deleted after use!

### Step 4: Test
Try adding a new course from the dashboard:
1. Go to: https://nielitbhubaneswar.in/admin/dashboard.php
2. Click "Add New Course"
3. Fill in the form
4. Click "Add Course"
5. Should succeed without errors ✅

## 📋 What The Migration Does

1. **Adds Column:**
   ```sql
   ALTER TABLE courses 
   ADD COLUMN registration_token VARCHAR(255) DEFAULT NULL
   ```

2. **Creates Index:**
   ```sql
   ALTER TABLE courses 
   ADD INDEX idx_registration_token (registration_token)
   ```

3. **Generates Tokens:**
   - Creates 8-character random tokens for all existing courses
   - Example: `Ab3Xk9Qz`, `Xy2Mn4Pq`

## 🔍 Verification

After migration, check:
- All existing courses have tokens
- New courses get tokens automatically
- Registration links use tokens: `?token=Ab3Xk9Qz` instead of `?course=SASW-2026`

## 📝 Notes

- **Local database:** Already has this column (migration was run locally)
- **Production database:** Needed this migration
- **Future:** All course additions will automatically generate tokens
- **Backward compatible:** Old course-code based links still work

## 🚨 Troubleshooting

**If migration fails:**
1. Check database credentials in the migration file
2. Ensure you have ALTER table permissions
3. Check if column already exists: `SHOW COLUMNS FROM courses LIKE 'registration_token'`

**If error persists after migration:**
1. Clear PHP cache/opcache on production server
2. Restart Apache/PHP-FPM if possible
3. Check error logs for new error messages

## 📂 Files Modified

- `migrations/add_registration_token_production.php` - New migration file
- `admin/dashboard.php` - Already using registration_token (line 248)

## ✨ Expected Result

Before: ❌ Fatal error when adding course
After: ✅ Course added successfully with token-based registration link
