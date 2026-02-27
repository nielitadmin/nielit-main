# 🚀 Quick Start Guide

## What Was Done?

I've created a **centralized configuration system** and **organized folder structure** for your NIELIT Bhubaneswar project.

## 📦 New Files Created

### Configuration Files (config/)
1. **config/config.php** - Master loader (include this in all files)
2. **config/database.php** - Single DB connection (replaces db_connection.php)
3. **config/email.php** - Email settings
4. **config/app.php** - Application settings

### Reusable Components (includes/)
1. **includes/header.php** - Common header
2. **includes/navbar.php** - Navigation menu
3. **includes/footer.php** - Common footer
4. **includes/head.php** - HTML head section
5. **includes/scripts.php** - JavaScript includes
6. **includes/helpers.php** - Useful functions

### Documentation
1. **PROJECT_STRUCTURE.md** - Folder structure explanation
2. **MIGRATION_GUIDE.md** - Step-by-step migration
3. **REORGANIZATION_SUMMARY.md** - Overview
4. **QUICK_START.md** - This file
5. **migrate.php** - Migration helper script

## 🎯 Main Benefits

### Before ❌
- Multiple `db_connection.php` files
- Hardcoded credentials everywhere
- Duplicate header/footer code
- No helper functions
- Messy file structure

### After ✅
- **Single config file** for everything
- **Centralized credentials**
- **Reusable components**
- **Helper functions** for common tasks
- **Organized structure** (admin/, student/, public/)

## 🔧 How to Use Right Now

### 1. In ANY PHP file, replace this:
```php
<?php
include('db_connection.php');
```

### With this:
```php
<?php
require_once __DIR__ . '/config/config.php';
```

That's it! Now you have:
- ✅ Database connection (`$conn`)
- ✅ All configuration constants
- ✅ Helper functions

### 2. Example: Using Helper Functions

```php
<?php
require_once __DIR__ . '/config/config.php';

// Calculate age
$age = calculate_age('1995-05-15');

// Generate password
$password = generate_password(8);

// Generate OTP
$otp = generate_otp(6);

// Show alert
echo show_alert('Success!', 'success');

// Redirect
redirect('student/portal.php');

// Check login
if (!is_logged_in()) {
    redirect('student/login.php');
}
```

### 3. Example: Using Include Components

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Page</title>
    <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container">
        <h1>My Content</h1>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
```

## 📋 Next Steps (Optional but Recommended)

### Step 1: Test the New Config
1. Open any PHP file (e.g., `register.php`)
2. Replace `include('db_connection.php')` with `require_once __DIR__ . '/config/config.php';`
3. Test if it works

### Step 2: Organize Files (When Ready)
Follow **MIGRATION_GUIDE.md** to move files into organized folders:
- Admin files → `admin/`
- Student files → `student/`
- Public files → `public/`
- Images → `assets/images/`
- CSS → `assets/css/`

### Step 3: Use Helper Functions
Replace repetitive code with helper functions from `includes/helpers.php`

## 🎓 Examples

### Example 1: Update register.php

**Before:**
```php
<?php
include('db_connection.php');

// Calculate age manually
$dob_date = new DateTime($dob);
$current_date = new DateTime();
$age = $dob_date->diff($current_date)->y;

// Generate password manually
$password = bin2hex(random_bytes(4));
```

**After:**
```php
<?php
require_once __DIR__ . '/config/config.php';

// Use helper functions
$age = calculate_age($dob);
$password = generate_password(8);
```

### Example 2: Update login.php

**Before:**
```php
<?php
session_start();
include('db_connection.php');

if (isset($_SESSION['student_id'])) {
    header("Location: student.portal.php");
    exit;
}
```

**After:**
```php
<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('student/portal.php');
}
```

### Example 3: Update admin.php

**Before:**
```php
<?php
$mail->Host = 'smtp.hostinger.com';
$mail->Username = 'admin@nielitbhubaneswar.in';
$mail->Password = 'Nielitbbsr@2025';
```

**After:**
```php
<?php
require_once __DIR__ . '/config/config.php';

$mail->Host = SMTP_HOST;
$mail->Username = SMTP_USERNAME;
$mail->Password = SMTP_PASSWORD;
```

## 🔍 Available Helper Functions

```php
// Sanitize input
$clean_data = sanitize_input($_POST['name']);

// Generate password
$password = generate_password(8);

// Generate OTP
$otp = generate_otp(6);

// Calculate age
$age = calculate_age('1995-05-15');

// Format date
$formatted = format_date('2025-01-15', 'd-m-Y');

// Check login status
if (is_logged_in()) { }
if (is_admin_logged_in()) { }

// Redirect
redirect('page.php');

// Show alert
echo show_alert('Message', 'success'); // or 'danger', 'warning', 'info'

// Validate file
$result = validate_file_upload($_FILES['file'], ALLOWED_IMAGE_TYPES);

// Upload file
$result = upload_file($_FILES['file'], UPLOAD_DIR);

// Generate student ID
$student_id = generate_student_id($conn, 'DBC18');
```

## 📞 Need Help?

### If something doesn't work:
1. Check if `config/config.php` exists
2. Verify the path in `require_once` is correct
3. Check file permissions (755 for folders, 644 for files)
4. Look at error logs

### File Path Reference:
- Root file: `require_once __DIR__ . '/config/config.php';`
- One level deep: `require_once __DIR__ . '/../config/config.php';`
- Two levels deep: `require_once __DIR__ . '/../../config/config.php';`

## ✅ What to Do Now

### Option 1: Quick Test (5 minutes)
1. Open `register.php`
2. Replace `include('db_connection.php')` with `require_once __DIR__ . '/config/config.php';`
3. Test if registration still works
4. If yes, you're good to go!

### Option 2: Full Migration (1-2 hours)
1. Read **MIGRATION_GUIDE.md**
2. Create backup
3. Run `php migrate.php`
4. Move files to new structure
5. Update all includes
6. Test everything

### Option 3: Gradual Migration (Recommended)
1. Start using new config in new files
2. Update old files one by one
3. Test after each update
4. Move files when comfortable

## 🎉 Summary

You now have:
- ✅ Single configuration file
- ✅ Reusable components
- ✅ Helper functions
- ✅ Better organization
- ✅ Easier maintenance

**Start using it by replacing `include('db_connection.php')` with `require_once __DIR__ . '/config/config.php';`**

That's it! 🚀
