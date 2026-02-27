# 🚀 Hostinger Deployment Guide - NIELIT Bhubaneswar

Complete step-by-step guide to deploy your application on Hostinger shared hosting.

---

## 📋 Pre-Deployment Checklist

### ✅ What You Need
- [ ] Hostinger account with active hosting plan
- [ ] Domain name (or use Hostinger subdomain)
- [ ] FTP client (FileZilla) or use Hostinger File Manager
- [ ] Your database export file
- [ ] All project files

---

## 🗄️ Step 1: Export Your Database

### Method 1: Using the Export Script (Recommended)

1. Open your browser and go to:
   ```
   http://localhost/public_html/export_database.php
   ```

2. The script will automatically download a file named:
   ```
   nielit_database_export_YYYY-MM-DD_HH-MM-SS.sql
   ```

3. Save this file - you'll need it for Hostinger

### Method 2: Using phpMyAdmin

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database (usually `nielit_db`)
3. Click "Export" tab
4. Choose "Quick" export method
5. Format: SQL
6. Click "Go" to download

---

## 📦 Step 2: Prepare Your Files

### Files to Upload
```
public_html/
├── admin/
├── assets/
├── config/
├── includes/
├── public/
├── student/
├── uploads/
├── index.php
└── (all other PHP files)
```

### Files to EXCLUDE (Don't Upload)
- ❌ `export_database.php` (security risk)
- ❌ `test_*.php` files
- ❌ `check_*.php` files
- ❌ `*.md` documentation files (optional)
- ❌ `.git` folder (if exists)

### Create a ZIP File
1. Select all necessary files and folders
2. Right-click → Send to → Compressed (zipped) folder
3. Name it: `nielit_project.zip`

---

## 🌐 Step 3: Upload to Hostinger

### Option A: Using File Manager (Easier)

1. **Login to Hostinger**
   - Go to: https://hpanel.hostinger.com
   - Login with your credentials

2. **Access File Manager**
   - Click on your hosting plan
   - Click "File Manager"

3. **Navigate to public_html**
   - Open the `public_html` folder
   - Delete any default files (index.html, etc.)

4. **Upload Your ZIP File**
   - Click "Upload" button
   - Select `nielit_project.zip`
   - Wait for upload to complete

5. **Extract the ZIP**
   - Right-click on `nielit_project.zip`
   - Click "Extract"
   - Select "Extract Here"
   - Delete the ZIP file after extraction

### Option B: Using FTP (FileZilla)

1. **Get FTP Credentials**
   - In Hostinger hPanel
   - Go to "FTP Accounts"
   - Note: Hostname, Username, Password, Port

2. **Connect with FileZilla**
   - Host: Your FTP hostname
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21
   - Click "Quickconnect"

3. **Upload Files**
   - Navigate to `/public_html` on remote side
   - Drag and drop all your files from local to remote

---

## 🗃️ Step 4: Create Database on Hostinger

1. **Access MySQL Databases**
   - In Hostinger hPanel
   - Click "MySQL Databases"

2. **Create New Database**
   - Click "Create New Database"
   - Database Name: `u123456789_nielit` (example)
   - Click "Create"

3. **Create Database User**
   - Username: `u123456789_admin` (example)
   - Password: Create a strong password
   - Click "Create"

4. **Assign User to Database**
   - Select the database
   - Select the user
   - Grant "All Privileges"
   - Click "Add"

5. **Note Your Credentials**
   ```
   Database Host: localhost
   Database Name: u123456789_nielit
   Database User: u123456789_admin
   Database Password: [your password]
   ```

---

## 📥 Step 5: Import Database

1. **Access phpMyAdmin**
   - In Hostinger hPanel
   - Click "phpMyAdmin"
   - Login automatically

2. **Select Your Database**
   - Click on your database name in left sidebar

3. **Import SQL File**
   - Click "Import" tab
   - Click "Choose File"
   - Select your exported SQL file
   - Scroll down and click "Go"
   - Wait for import to complete

4. **Verify Import**
   - Check if all tables are created
   - Should see: students, courses, admins, announcements, etc.

---

## ⚙️ Step 6: Update Configuration

1. **Edit config.php**
   - In File Manager, navigate to `config/config.php`
   - Right-click → Edit

2. **Update Database Credentials**
   ```php
   <?php
   // Database Configuration
   define('DB_HOST', 'localhost');
   define('DB_USER', 'u123456789_admin');  // Your Hostinger DB user
   define('DB_PASS', 'your_password_here'); // Your Hostinger DB password
   define('DB_NAME', 'u123456789_nielit');  // Your Hostinger DB name
   
   // Application URL
   define('APP_URL', 'https://yourdomain.com'); // Your actual domain
   
   // Email Configuration (if using)
   define('SMTP_HOST', 'smtp.hostinger.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'noreply@yourdomain.com');
   define('SMTP_PASS', 'your_email_password');
   define('SMTP_FROM', 'noreply@yourdomain.com');
   define('SMTP_FROM_NAME', 'NIELIT Bhubaneswar');
   
   // Connect to database
   $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
   
   if ($conn->connect_error) {
       die("Connection failed: " . $conn->connect_error);
   }
   
   $conn->set_charset("utf8mb4");
   ?>
   ```

3. **Save the file**

---

## 🔐 Step 7: Set Folder Permissions

1. **Set Upload Folder Permissions**
   - Navigate to `uploads` folder
   - Right-click → Permissions
   - Set to: `755` or `777`
   - Check "Recurse into subdirectories"
   - Apply

2. **Set QR Codes Folder Permissions**
   - Navigate to `assets/qr_codes`
   - Right-click → Permissions
   - Set to: `755` or `777`
   - Apply

---

## 🔒 Step 8: Enable SSL (HTTPS)

1. **In Hostinger hPanel**
   - Go to "SSL" section
   - Enable "Free SSL Certificate"
   - Wait 10-15 minutes for activation

2. **Force HTTPS (Optional)**
   - Create/edit `.htaccess` file in `public_html`
   - Add this code:
   ```apache
   # Force HTTPS
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   
   # PHP Settings
   php_value upload_max_filesize 10M
   php_value post_max_size 10M
   php_value max_execution_time 300
   php_value memory_limit 256M
   ```

---

## ✅ Step 9: Test Your Website

### Test Checklist

1. **Homepage**
   - [ ] Visit: `https://yourdomain.com`
   - [ ] Check if page loads correctly
   - [ ] Verify images and CSS load

2. **Admin Login**
   - [ ] Go to: `https://yourdomain.com/admin/login.php`
   - [ ] Login with your admin credentials
   - [ ] Check dashboard loads

3. **Student Portal**
   - [ ] Go to: `https://yourdomain.com/student/login.php`
   - [ ] Test login functionality

4. **Registration**
   - [ ] Test student registration form
   - [ ] Check if files upload correctly
   - [ ] Verify email notifications (if configured)

5. **Database Operations**
   - [ ] Add a test announcement
   - [ ] Edit a course
   - [ ] View student list

---

## 🐛 Common Issues & Solutions

### Issue 1: Database Connection Error
**Error:** "Connection failed: Access denied"

**Solution:**
- Double-check database credentials in `config/config.php`
- Ensure database user has all privileges
- Verify database name is correct

### Issue 2: 500 Internal Server Error
**Error:** White page or 500 error

**Solution:**
- Check file permissions (755 for folders, 644 for files)
- Review `.htaccess` file for syntax errors
- Check PHP error logs in Hostinger hPanel

### Issue 3: Images/CSS Not Loading
**Error:** Broken images or no styling

**Solution:**
- Update `APP_URL` in `config/config.php`
- Check file paths are correct
- Clear browser cache (Ctrl+F5)

### Issue 4: File Upload Fails
**Error:** "Failed to upload file"

**Solution:**
- Set `uploads/` folder permission to 777
- Check `.htaccess` upload size limits
- Verify disk space in Hostinger

### Issue 5: Email Not Sending
**Error:** Registration emails not received

**Solution:**
- Configure SMTP settings in `config/config.php`
- Use Hostinger's SMTP server
- Check spam folder

---

## 📧 Email Configuration for Hostinger

1. **Create Email Account**
   - In Hostinger hPanel → Email
   - Create: `noreply@yourdomain.com`

2. **SMTP Settings**
   ```php
   SMTP Host: smtp.hostinger.com
   SMTP Port: 587
   SMTP User: noreply@yourdomain.com
   SMTP Pass: [your email password]
   Encryption: TLS
   ```

---

## 🔧 Post-Deployment Tasks

### Security Checklist
- [ ] Delete `export_database.php` from server
- [ ] Delete all test files (`test_*.php`)
- [ ] Change default admin password
- [ ] Enable SSL certificate
- [ ] Set up regular database backups
- [ ] Configure error logging

### Performance Optimization
- [ ] Enable caching in Hostinger
- [ ] Optimize images
- [ ] Minify CSS/JS (optional)
- [ ] Enable Gzip compression

---

## 📞 Support Resources

### Hostinger Support
- Live Chat: Available 24/7 in hPanel
- Knowledge Base: https://support.hostinger.com
- Email: support@hostinger.com

### Application Issues
- Check error logs in Hostinger hPanel
- Review PHP error logs
- Test locally first before deploying fixes

---

## 🎉 Deployment Complete!

Your NIELIT Bhubaneswar application is now live on Hostinger!

**Your URLs:**
- Homepage: `https://yourdomain.com`
- Admin Panel: `https://yourdomain.com/admin/login.php`
- Student Portal: `https://yourdomain.com/student/login.php`

**Next Steps:**
1. Test all functionality thoroughly
2. Set up regular backups
3. Monitor error logs
4. Update content and announcements
5. Train staff on admin panel usage

---

**Need Help?** Contact Hostinger support or review this guide again.

**Good luck with your deployment! 🚀**
