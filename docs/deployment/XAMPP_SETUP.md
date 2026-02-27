# 🚀 XAMPP Setup Guide - NIELIT Bhubaneswar

## 📋 Prerequisites

- ✅ XAMPP installed on Windows
- ✅ Apache and MySQL running in XAMPP Control Panel

---

## 🔧 Step-by-Step Setup

### Step 1: Place Project in XAMPP

1. **Copy your project folder** to XAMPP's `htdocs` directory:
   ```
   C:\xampp\htdocs\nielit_bhubaneswar\
   ```

2. **Verify folder structure:**
   ```
   C:\xampp\htdocs\nielit_bhubaneswar\
   ├── config/
   ├── includes/
   ├── index.php
   ├── register.php
   └── ... (other files)
   ```

---

### Step 2: Create Database

1. **Open phpMyAdmin:**
   - Start XAMPP Control Panel
   - Start Apache and MySQL
   - Open browser: `http://localhost/phpmyadmin`

2. **Create new database:**
   - Click "New" in left sidebar
   - Database name: `nielit_bhubaneswar`
   - Collation: `utf8mb4_general_ci`
   - Click "Create"

3. **Import database:**
   - Select `nielit_bhubaneswar` database
   - Click "Import" tab
   - Choose file: `nielit_bhubaneswar.sql`
   - Click "Go"

---

### Step 3: Configure Database Connection

The database config is already set for XAMPP! ✅

**File:** `config/database.php`

```php
// XAMPP Default Settings (Already configured)
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');          // XAMPP default
define('DB_PASSWORD', '');              // XAMPP default (empty)
define('DB_NAME', 'nielit_bhubaneswar');
```

**No changes needed!** 🎉

---

### Step 4: Update Application URL

**File:** `config/app.php`

Update the APP_URL to match your XAMPP setup:

```php
// If your project is in: C:\xampp\htdocs\nielit_bhubaneswar\
define('APP_URL', 'http://localhost/nielit_bhubaneswar');

// If your project is directly in htdocs: C:\xampp\htdocs\
define('APP_URL', 'http://localhost');
```

**Already set to:** `http://localhost/nielit_bhubaneswar` ✅

---

### Step 5: Set Folder Permissions (Windows)

1. **Right-click on project folder**
2. **Properties → Security**
3. **Edit → Add → Everyone**
4. **Allow: Full Control**
5. **Apply → OK**

Or simply ensure XAMPP has write access to:
- `uploads/` folder
- `course_pdf/` folder
- `storage/` folder

---

### Step 6: Test the Setup

1. **Start XAMPP:**
   - Open XAMPP Control Panel
   - Start Apache
   - Start MySQL

2. **Open browser:**
   ```
   http://localhost/nielit_bhubaneswar/
   ```

3. **You should see the homepage!** 🎉

---

## 🧪 Testing Checklist

### Basic Tests
- [ ] Homepage loads: `http://localhost/nielit_bhubaneswar/`
- [ ] Images display correctly
- [ ] CSS loads properly
- [ ] Database connection works

### Module Tests
- [ ] Student registration page loads
- [ ] Can submit registration form
- [ ] Student login works
- [ ] Admin login works
- [ ] File uploads work

---

## 🔍 Common XAMPP Issues & Solutions

### Issue 1: Port 80 Already in Use

**Error:** Apache won't start

**Solution:**
1. Open XAMPP Control Panel
2. Click "Config" next to Apache
3. Select "httpd.conf"
4. Find: `Listen 80`
5. Change to: `Listen 8080`
6. Save and restart Apache
7. Access site: `http://localhost:8080/nielit_bhubaneswar/`

### Issue 2: MySQL Port 3306 in Use

**Error:** MySQL won't start

**Solution:**
1. Open XAMPP Control Panel
2. Click "Config" next to MySQL
3. Select "my.ini"
4. Find: `port=3306`
5. Change to: `port=3307`
6. Update `config/database.php`:
   ```php
   define('DB_HOST', 'localhost:3307');
   ```

### Issue 3: Database Connection Failed

**Error:** "Connection failed" message

**Check:**
1. MySQL is running in XAMPP
2. Database name is correct: `nielit_bhubaneswar`
3. Username is: `root`
4. Password is: empty (blank)

**Fix:**
```php
// In config/database.php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');  // Empty for XAMPP
define('DB_NAME', 'nielit_bhubaneswar');
```

### Issue 4: Images Not Loading

**Error:** Broken image icons

**Solution:**
1. Check image paths in code
2. Ensure images are in correct folder
3. Use relative paths or APP_URL constant

**Example:**
```php
// Instead of:
<img src="bhubaneswar_logo.png">

// Use:
<img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png">
```

### Issue 5: File Upload Fails

**Error:** "Failed to upload file"

**Solution:**
1. Check folder permissions
2. Ensure `uploads/` folder exists
3. Check PHP upload settings

**Update php.ini:**
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
```

Location: `C:\xampp\php\php.ini`

### Issue 6: Email Not Sending

**Note:** XAMPP doesn't send real emails by default

**Options:**

**Option A: Use Gmail SMTP (Recommended for testing)**
Already configured in `config/email.php`

**Option B: Use Fake SMTP for testing**
Install: https://github.com/rnwood/smtp4dev

**Option C: Disable email temporarily**
Comment out email sending code during development

---

## 📁 XAMPP Directory Structure

```
C:\xampp\
├── htdocs\
│   └── nielit_bhubaneswar\          ← Your project here
│       ├── config\
│       ├── includes\
│       ├── uploads\
│       ├── index.php
│       └── ...
├── mysql\
│   └── data\
│       └── nielit_bhubaneswar\      ← Your database
├── php\
│   └── php.ini                      ← PHP configuration
└── apache\
    └── conf\
        └── httpd.conf               ← Apache configuration
```

---

## 🎯 Quick Start Commands

### Start XAMPP
1. Open XAMPP Control Panel
2. Click "Start" for Apache
3. Click "Start" for MySQL

### Access Your Site
```
http://localhost/nielit_bhubaneswar/
```

### Access phpMyAdmin
```
http://localhost/phpmyadmin/
```

### View Error Logs
- Apache: `C:\xampp\apache\logs\error.log`
- PHP: `C:\xampp\php\logs\php_error_log`

---

## 🔐 Security Notes for XAMPP

⚠️ **XAMPP is for development only!**

For production deployment:
1. Change database credentials
2. Set strong passwords
3. Disable directory listing
4. Enable HTTPS
5. Update `config/app.php`:
   ```php
   define('APP_ENV', 'production');
   ```

---

## 📝 Development Workflow

### Daily Workflow
1. Start XAMPP (Apache + MySQL)
2. Open project: `http://localhost/nielit_bhubaneswar/`
3. Make changes to files
4. Refresh browser to see changes
5. Check error logs if issues occur

### Database Changes
1. Open phpMyAdmin
2. Select `nielit_bhubaneswar` database
3. Make changes
4. Export database (for backup)

### File Changes
1. Edit files in: `C:\xampp\htdocs\nielit_bhubaneswar\`
2. Save changes
3. Refresh browser
4. No restart needed (unless changing config)

---

## 🛠️ Useful XAMPP Tools

### phpMyAdmin
- URL: `http://localhost/phpmyadmin/`
- Manage databases
- Run SQL queries
- Import/Export data

### XAMPP Control Panel
- Start/Stop services
- View logs
- Configure ports
- Access config files

### PHP Info
Create file: `C:\xampp\htdocs\info.php`
```php
<?php phpinfo(); ?>
```
Access: `http://localhost/info.php`

---

## 📚 Additional Resources

### XAMPP Documentation
- Official: https://www.apachefriends.org/
- FAQ: https://www.apachefriends.org/faq_windows.html

### PHP Documentation
- Official: https://www.php.net/manual/en/

### MySQL Documentation
- Official: https://dev.mysql.com/doc/

---

## ✅ Setup Complete!

Your XAMPP environment is ready! 🎉

**Next Steps:**
1. Test the homepage
2. Try student registration
3. Test admin login
4. Start development

**Need help?** Check the error logs:
- `C:\xampp\apache\logs\error.log`
- `C:\xampp\php\logs\php_error_log`

---

## 🎓 Pro Tips

1. **Use Virtual Hosts** for cleaner URLs:
   - Instead of: `http://localhost/nielit_bhubaneswar/`
   - Use: `http://nielit.local/`
   - Guide: https://httpd.apache.org/docs/2.4/vhosts/

2. **Enable Error Display** during development:
   ```php
   // Already set in config/app.php when APP_ENV = 'development'
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

3. **Use Browser DevTools** (F12):
   - Check console for JavaScript errors
   - Check network tab for failed requests
   - Inspect elements

4. **Keep XAMPP Updated**:
   - Download latest version
   - Backup database before updating
   - Test after update

5. **Regular Backups**:
   - Export database weekly
   - Copy project folder
   - Store in safe location

---

**Happy Coding! 🚀**
