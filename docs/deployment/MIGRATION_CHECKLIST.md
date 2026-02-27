# ✅ Migration Checklist

## Pre-Migration

- [ ] **Read all documentation**
  - [ ] QUICK_START.md
  - [ ] PROJECT_STRUCTURE.md
  - [ ] MIGRATION_GUIDE.md
  - [ ] REORGANIZATION_SUMMARY.md

- [ ] **Create backup**
  - [ ] Full project backup
  - [ ] Database backup
  - [ ] Note backup location: _______________

- [ ] **Test current system**
  - [ ] Student registration works
  - [ ] Student login works
  - [ ] Admin login works
  - [ ] Email sending works
  - [ ] PDF generation works

## Phase 1: Configuration Setup ✅ (Already Done)

- [x] Create config/ folder
- [x] Create config/config.php
- [x] Create config/database.php
- [x] Create config/email.php
- [x] Create config/app.php
- [x] Create includes/ folder
- [x] Create includes/header.php
- [x] Create includes/navbar.php
- [x] Create includes/footer.php
- [x] Create includes/head.php
- [x] Create includes/scripts.php
- [x] Create includes/helpers.php

## Phase 2: Create Folder Structure

- [ ] Create admin/ folder
- [ ] Create student/ folder
- [ ] Create public/ folder
- [ ] Create assets/ folder
- [ ] Create assets/css/ folder
- [ ] Create assets/js/ folder
- [ ] Create assets/images/ folder
- [ ] Create assets/images/banners/ folder
- [ ] Create libraries/ folder
- [ ] Create uploads/documents/ folder
- [ ] Create uploads/photos/ folder
- [ ] Create uploads/signatures/ folder
- [ ] Create uploads/receipts/ folder
- [ ] Create storage/logs/ folder
- [ ] Create storage/cache/ folder

## Phase 3: Move Files

### Admin Files
- [ ] Move admin.php → admin/login.php
- [ ] Move admin_dashboard.php → admin/dashboard.php
- [ ] Move student.admin.php → admin/students.php
- [ ] Move add_admin.php → admin/add_admin.php
- [ ] Move reset_password.php → admin/reset_password.php
- [ ] Move admin_logout.php → admin/logout.php
- [ ] Move edit_course.php → admin/edit_course.php
- [ ] Move edit_student.php → admin/edit_student.php
- [ ] Move manage_batches.php → admin/manage_batches.php

### Student Files
- [ ] Move register.php → student/register.php
- [ ] Move login.php → student/login.php
- [ ] Move student.portal.php → student/portal.php
- [ ] Move logout.php → student/logout.php
- [ ] Move generate_pdf.php → student/download_form.php

### Public Files
- [ ] Move courses_offered.php → public/courses.php
- [ ] Move contact.php → public/contact.php
- [ ] Move management.php → public/management.php
- [ ] Move news.php → public/news.php

### Assets
- [ ] Move style.css → assets/css/style.css
- [ ] Move script.js → assets/js/main.js
- [ ] Move bhubaneswar_logo.png → assets/images/
- [ ] Move National-Emblem.png → assets/images/
- [ ] Move favicon.ico → assets/images/
- [ ] Move banner images → assets/images/banners/
- [ ] Move images/logo1.png → assets/images/
- [ ] Move images/logo2.png → assets/images/

### Libraries
- [ ] Move PHPMailer/ → libraries/PHPMailer/
- [ ] Move tcpdf/ → libraries/tcpdf/
- [ ] Move PhpSpreadsheet-master/ → libraries/PhpSpreadsheet/

## Phase 4: Update File Includes

### Root Files
- [ ] Update index.php
  - [ ] Replace db_connection.php include
  - [ ] Update image paths
  - [ ] Update CSS/JS paths

### Admin Files (admin/*.php)
- [ ] Update admin/login.php
  - [ ] Replace db_connection.php with ../config/config.php
  - [ ] Update PHPMailer path
  - [ ] Update email config to use constants
  - [ ] Update image paths
  
- [ ] Update admin/dashboard.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  - [ ] Update paths
  
- [ ] Update admin/students.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  - [ ] Update paths
  
- [ ] Update admin/add_admin.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  
- [ ] Update admin/reset_password.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  
- [ ] Update admin/edit_course.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  
- [ ] Update admin/edit_student.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  
- [ ] Update admin/manage_batches.php
  - [ ] Replace db_connection.php
  - [ ] Add login check

### Student Files (student/*.php)
- [ ] Update student/register.php
  - [ ] Replace db_connection.php
  - [ ] Update PHPMailer path
  - [ ] Update email config
  - [ ] Use helper functions
  - [ ] Update upload paths
  
- [ ] Update student/login.php
  - [ ] Replace db_connection.php
  - [ ] Use helper functions
  
- [ ] Update student/portal.php
  - [ ] Replace db_connection.php
  - [ ] Add login check
  - [ ] Update paths
  
- [ ] Update student/download_form.php
  - [ ] Replace db_connection.php
  - [ ] Update TCPDF path
  - [ ] Add login check

### Public Files (public/*.php)
- [ ] Update public/courses.php
  - [ ] Replace db_connection.php
  - [ ] Update paths
  
- [ ] Update public/contact.php
  - [ ] Replace db_connection.php
  - [ ] Update paths
  
- [ ] Update public/management.php
  - [ ] Replace db_connection.php
  - [ ] Update paths
  
- [ ] Update public/news.php
  - [ ] Replace db_connection.php
  - [ ] Update paths

## Phase 5: Update HTML Templates

### Use Include Components
- [ ] Update admin files to use includes
  - [ ] includes/head.php
  - [ ] includes/header.php
  - [ ] includes/navbar.php
  - [ ] includes/footer.php
  - [ ] includes/scripts.php
  
- [ ] Update student files to use includes
  - [ ] includes/head.php
  - [ ] includes/header.php
  - [ ] includes/navbar.php
  - [ ] includes/footer.php
  - [ ] includes/scripts.php
  
- [ ] Update public files to use includes
  - [ ] includes/head.php
  - [ ] includes/header.php
  - [ ] includes/navbar.php
  - [ ] includes/footer.php
  - [ ] includes/scripts.php

## Phase 6: Update Configuration Values

### Database Config
- [ ] Verify DB_HOST in config/database.php
- [ ] Verify DB_USERNAME in config/database.php
- [ ] Verify DB_PASSWORD in config/database.php
- [ ] Verify DB_NAME in config/database.php

### Email Config
- [ ] Verify SMTP_HOST in config/email.php
- [ ] Verify SMTP_PORT in config/email.php
- [ ] Verify SMTP_USERNAME in config/email.php
- [ ] Verify SMTP_PASSWORD in config/email.php
- [ ] Verify email logo paths

### App Config
- [ ] Set APP_ENV (development/production)
- [ ] Verify APP_URL
- [ ] Verify upload directories
- [ ] Verify file size limits

## Phase 7: Testing

### Basic Functionality
- [ ] Homepage loads
- [ ] All images display
- [ ] CSS loads correctly
- [ ] JavaScript works
- [ ] Navigation links work

### Student Module
- [ ] Registration form loads
- [ ] Can submit registration
- [ ] Email is sent
- [ ] Student ID is generated
- [ ] Can login with credentials
- [ ] Student portal displays data
- [ ] Can view documents
- [ ] Can download PDF

### Admin Module
- [ ] Admin login page loads
- [ ] Can login with credentials
- [ ] OTP is sent via email
- [ ] Can verify OTP
- [ ] Dashboard loads
- [ ] Can view students
- [ ] Can edit students
- [ ] Can delete students
- [ ] Can add courses
- [ ] Can edit courses
- [ ] Can delete courses
- [ ] Can manage batches

### File Operations
- [ ] Document upload works
- [ ] Photo upload works
- [ ] Signature upload works
- [ ] Receipt upload works
- [ ] Files are stored correctly
- [ ] Files can be viewed
- [ ] Files can be downloaded

### Email Functionality
- [ ] Registration email sent
- [ ] OTP email sent
- [ ] Emails have correct content
- [ ] Logos display in emails

### PDF Generation
- [ ] Can generate application form
- [ ] PDF has correct data
- [ ] Images display in PDF
- [ ] PDF can be downloaded

## Phase 8: Optimization

- [ ] Remove old db_connection.php
- [ ] Remove old image folders (after moving)
- [ ] Clean up unused files
- [ ] Optimize database queries
- [ ] Add error logging
- [ ] Add security headers
- [ ] Update .htaccess

## Phase 9: Documentation

- [ ] Update README.md
- [ ] Document new structure
- [ ] Document helper functions
- [ ] Document configuration options
- [ ] Create user manual (if needed)

## Phase 10: Deployment

- [ ] Test on staging server
- [ ] Fix any issues
- [ ] Create deployment checklist
- [ ] Deploy to production
- [ ] Monitor for errors
- [ ] Keep backup for 30 days

## Post-Migration

- [ ] Monitor error logs
- [ ] Check email delivery
- [ ] Verify all features work
- [ ] Get user feedback
- [ ] Fix any reported issues
- [ ] Update documentation as needed

## Rollback Plan (If Needed)

- [ ] Stop web server
- [ ] Restore from backup
- [ ] Restore database
- [ ] Test restored version
- [ ] Start web server
- [ ] Investigate issues
- [ ] Plan retry

## Notes

**Migration Start Date:** _______________
**Migration End Date:** _______________
**Issues Encountered:**
- 
- 
- 

**Resolved Issues:**
- 
- 
- 

**Pending Items:**
- 
- 
- 

---

## Quick Reference

### Include Paths by Location

| File Location | Config Include |
|--------------|----------------|
| Root (index.php) | `require_once __DIR__ . '/config/config.php';` |
| admin/*.php | `require_once __DIR__ . '/../config/config.php';` |
| student/*.php | `require_once __DIR__ . '/../config/config.php';` |
| public/*.php | `require_once __DIR__ . '/../config/config.php';` |

### Helper Functions Available

- `sanitize_input($data)`
- `generate_password($length)`
- `generate_otp($length)`
- `calculate_age($dob)`
- `format_date($date, $format)`
- `is_logged_in()`
- `is_admin_logged_in()`
- `redirect($url)`
- `show_alert($message, $type)`
- `validate_file_upload($file, $types, $max_size)`
- `upload_file($file, $destination)`
- `generate_student_id($conn, $course_abbr)`

---

**Remember:** Test after each phase! Don't move to the next phase until current phase is working.
