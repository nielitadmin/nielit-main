# NIELIT Bhubaneswar - Project Structure

## 📁 New Organized Folder Structure

```
nielit_bhubaneswar/
│
├── config/                          # Configuration files
│   ├── config.php                   # Master config loader (include this in all files)
│   ├── app.php                      # Application settings
│   ├── database.php                 # Database connection (single source)
│   └── email.php                    # Email/SMTP configuration
│
├── includes/                        # Reusable components
│   ├── header.php                   # Common header
│   ├── navbar.php                   # Navigation menu
│   ├── footer.php                   # Common footer
│   ├── head.php                     # HTML head section
│   ├── scripts.php                  # JavaScript includes
│   └── helpers.php                  # Helper functions
│
├── admin/                           # Admin section
│   ├── login.php                    # Admin login with OTP
│   ├── dashboard.php                # Admin dashboard
│   ├── students.php                 # Student management
│   ├── courses.php                  # Course management
│   ├── batches.php                  # Batch management
│   ├── add_admin.php                # Add new admin
│   ├── reset_password.php           # Reset student password
│   └── logout.php                   # Admin logout
│
├── student/                         # Student section
│   ├── register.php                 # Student registration
│   ├── login.php                    # Student login
│   ├── portal.php                   # Student dashboard
│   ├── profile.php                  # View/Edit profile
│   ├── download_form.php            # Download application form
│   └── logout.php                   # Student logout
│
├── public/                          # Public pages
│   ├── courses.php                  # Courses offered
│   ├── contact.php                  # Contact page
│   ├── management.php               # Management info
│   └── news.php                     # News section
│
├── assets/                          # Static assets
│   ├── css/
│   │   └── style.css                # Main stylesheet
│   ├── js/
│   │   └── main.js                  # Main JavaScript
│   └── images/                      # Images, logos, banners
│       ├── bhubaneswar_logo.png
│       ├── National-Emblem.png
│       ├── favicon.ico
│       ├── logo1.png
│       ├── logo2.png
│       └── banners/
│
├── uploads/                         # Student uploaded documents
│   ├── documents/
│   ├── photos/
│   ├── signatures/
│   └── receipts/
│
├── course_pdf/                      # Course brochures/PDFs
│
├── libraries/                       # Third-party libraries
│   ├── PHPMailer/                   # Email library
│   ├── tcpdf/                       # PDF generation
│   └── PhpSpreadsheet-master/       # Excel handling
│
├── Membership_Form/                 # Separate membership module
│
├── storage/                         # Application storage
│   ├── logs/                        # Error logs
│   └── cache/                       # Cache files
│
├── index.php                        # Homepage
├── .htaccess                        # Apache configuration
└── README.md                        # Project documentation
```

## 🔧 How to Use New Structure

### 1. Include Configuration in All PHP Files

**Old way:**
```php
include('db_connection.php');
```

**New way:**
```php
require_once __DIR__ . '/config/config.php';
// Now you have access to $conn, all constants, and helper functions
```

### 2. Use Helper Functions

```php
// Calculate age
$age = calculate_age($dob);

// Generate student ID
$student_id = generate_student_id($conn, 'DBC18');

// Show alert
echo show_alert('Registration successful!', 'success');

// Redirect
redirect('student/portal.php');
```

### 3. Include Common Components

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Page Title</title>
    <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <!-- Your content here -->
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
```

## 📝 Migration Steps

1. **Backup current files** (already done)
2. **Move files to new structure**
3. **Update all includes** to use new config
4. **Test each module** thoroughly
5. **Update .htaccess** for clean URLs (optional)

## 🔐 Security Improvements

- Single database config file (easier to manage)
- Separated public and admin sections
- Helper functions for validation
- Centralized error handling
- Environment-based error reporting

## 📚 Configuration Files

### config/config.php
Master loader - include this in all files

### config/database.php
- Database credentials
- Connection handling
- Error logging

### config/email.php
- SMTP settings
- Email templates paths

### config/app.php
- Application settings
- File upload limits
- Session timeout
- Timezone

## 🎯 Next Steps

1. Move existing files to new structure
2. Update all `include('db_connection.php')` to `require_once 'config/config.php'`
3. Move images to `assets/images/`
4. Move CSS to `assets/css/`
5. Test all functionality
6. Update documentation
