# Migration Guide - NIELIT Bhubaneswar

## 🚀 Step-by-Step Migration Process

### Phase 1: Backup (CRITICAL - DO THIS FIRST!)
```bash
# Create backup of entire project
cp -r /path/to/project /path/to/project_backup_$(date +%Y%m%d)
```

### Phase 2: Create New Folder Structure

**Already Created:**
- ✅ `config/` folder with all config files
- ✅ `includes/` folder with reusable components
- ✅ Helper functions

**To Create:**
```bash
mkdir -p admin
mkdir -p student
mkdir -p public
mkdir -p assets/css
mkdir -p assets/js
mkdir -p assets/images
mkdir -p assets/images/banners
mkdir -p libraries
mkdir -p uploads/documents
mkdir -p uploads/photos
mkdir -p uploads/signatures
mkdir -p uploads/receipts
```

### Phase 3: Move Files to New Structure

#### Move Admin Files
```bash
# Move to admin/ folder
mv admin.php admin/login.php
mv admin_dashboard.php admin/dashboard.php
mv student.admin.php admin/students.php
mv add_admin.php admin/
mv reset_password.php admin/
mv admin_logout.php admin/logout.php
mv edit_course.php admin/
mv edit_student.php admin/
mv manage_batches.php admin/
```

#### Move Student Files
```bash
# Move to student/ folder
mv register.php student/
mv login.php student/
mv student.portal.php student/portal.php
mv logout.php student/
mv generate_pdf.php student/download_form.php
```

#### Move Public Files
```bash
# Move to public/ folder
mv courses_offered.php public/courses.php
mv contact.php public/
mv management.php public/
mv news.php public/
```

#### Move Assets
```bash
# Move images
mv bhubaneswar_logo.png assets/images/
mv National-Emblem.png assets/images/
mv favicon.ico assets/images/
mv bhubaneswar_banner*.jpg assets/images/banners/
mv images/logo1.png assets/images/
mv images/logo2.png assets/images/

# Move CSS
mv style.css assets/css/

# Move JS
mv script.js assets/js/main.js
```

#### Move Libraries
```bash
# Move to libraries/ folder
mv PHPMailer libraries/
mv tcpdf libraries/
mv PhpSpreadsheet-master libraries/
```

### Phase 4: Update File References

#### Files to Update (Replace old includes with new config)

**Pattern to Find:**
```php
include('db_connection.php');
// OR
require 'db_connection.php';
```

**Replace With:**
```php
require_once __DIR__ . '/../config/config.php';
// Note: Adjust ../ based on file location depth
```

#### Update Image Paths

**Old:**
```html
<img src="bhubaneswar_logo.png">
```

**New:**
```html
<img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png">
```

#### Update CSS/JS Paths

**Old:**
```html
<link href="style.css" rel="stylesheet">
```

**New:**
```html
<link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
```

### Phase 5: Update Each File

#### 1. Admin Files (admin/*.php)

**admin/login.php:**
```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
// Rest of code...
```

**admin/dashboard.php:**
```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!is_admin_logged_in()) {
    redirect('../admin/login.php');
}
// Rest of code...
```

#### 2. Student Files (student/*.php)

**student/register.php:**
```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
// Rest of code...
```

**student/portal.php:**
```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!is_logged_in()) {
    redirect('../student/login.php');
}
// Rest of code...
```

#### 3. Public Files (public/*.php)

**public/courses.php:**
```php
<?php
require_once __DIR__ . '/../config/config.php';
// Rest of code...
```

#### 4. Root Files

**index.php:**
```php
<?php
require_once __DIR__ . '/config/config.php';
// Rest of code...
```

### Phase 6: Update HTML Templates

#### Use Include Files

**Before:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Page Title</title>
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Header HTML here -->
    <!-- Navbar HTML here -->
    
    <!-- Content -->
    
    <!-- Footer HTML here -->
</body>
</html>
```

**After:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Page Title - NIELIT Bhubaneswar</title>
    <?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    
    <!-- Your content here -->
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <?php include __DIR__ . '/../includes/scripts.php'; ?>
</body>
</html>
```

### Phase 7: Update .htaccess (Optional - for clean URLs)

Create/Update `.htaccess`:
```apache
RewriteEngine On
RewriteBase /

# Redirect old URLs to new structure
RewriteRule ^admin\.php$ admin/login.php [L,R=301]
RewriteRule ^login\.php$ student/login.php [L,R=301]
RewriteRule ^register\.php$ student/register.php [L,R=301]
RewriteRule ^courses_offered\.php$ public/courses.php [L,R=301]

# Prevent direct access to config
RewriteRule ^config/ - [F,L]

# Prevent directory listing
Options -Indexes
```

### Phase 8: Testing Checklist

- [ ] Homepage loads correctly
- [ ] Student registration works
- [ ] Student login works
- [ ] Student portal displays data
- [ ] PDF generation works
- [ ] Email sending works
- [ ] Admin login with OTP works
- [ ] Admin dashboard loads
- [ ] Course management works
- [ ] Student management works
- [ ] Batch management works
- [ ] File uploads work
- [ ] All images display correctly
- [ ] All links work
- [ ] Mobile responsive

### Phase 9: Database Updates (if needed)

```sql
-- Add any new columns if required
-- ALTER TABLE students ADD COLUMN new_field VARCHAR(255);

-- Update any paths in database if stored
-- UPDATE students SET documents = REPLACE(documents, 'uploads/', 'uploads/documents/');
```

### Phase 10: Cleanup

After successful migration and testing:

```bash
# Remove old files
rm db_connection.php
rm -rf images/  # After moving to assets/images/

# Keep backup for 30 days before deleting
```

## 🔍 Quick Reference

### File Location Mapping

| Old Location | New Location |
|-------------|-------------|
| `db_connection.php` | `config/database.php` |
| `admin.php` | `admin/login.php` |
| `admin_dashboard.php` | `admin/dashboard.php` |
| `student.admin.php` | `admin/students.php` |
| `register.php` | `student/register.php` |
| `login.php` | `student/login.php` |
| `student.portal.php` | `student/portal.php` |
| `courses_offered.php` | `public/courses.php` |
| `contact.php` | `public/contact.php` |
| `style.css` | `assets/css/style.css` |
| `script.js` | `assets/js/main.js` |
| `bhubaneswar_logo.png` | `assets/images/bhubaneswar_logo.png` |

### Include Path Reference

| File Location | Config Include Path |
|--------------|-------------------|
| Root (`index.php`) | `require_once __DIR__ . '/config/config.php';` |
| Admin (`admin/*.php`) | `require_once __DIR__ . '/../config/config.php';` |
| Student (`student/*.php`) | `require_once __DIR__ . '/../config/config.php';` |
| Public (`public/*.php`) | `require_once __DIR__ . '/../config/config.php';` |

## 📞 Support

If you encounter issues during migration:
1. Check error logs in `storage/logs/`
2. Verify file permissions (755 for directories, 644 for files)
3. Ensure all paths are correct
4. Test one module at a time

## ✅ Post-Migration Benefits

- ✨ Single configuration file
- 🔒 Better security (separated admin/student/public)
- 📁 Organized file structure
- 🔧 Reusable components
- 🚀 Easier maintenance
- 📚 Better code organization
- 🎯 Helper functions for common tasks
