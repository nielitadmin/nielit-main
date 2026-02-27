# NIELIT BHUBANESWAR - Current System Structure & Modules

## 📋 Overview
This document provides a complete overview of all files, folders, and modules currently in use in the NIELIT Bhubaneswar Student Management System.

---

## 🗂️ Main Directory Structure

```
nielit_bhubaneswar/
├── admin/                  # Admin Panel Module
├── student/                # Student Portal Module
├── public/                 # Public Website Pages
├── assets/                 # CSS, JS, Images, QR Codes
├── config/                 # Configuration Files
├── includes/               # Helper Functions & Utilities
├── libraries/              # Third-party Libraries
├── uploads/                # Student Documents & Files
├── course_pdf/             # Course Description PDFs
├── course_flyers/          # Course Flyer Images
├── phpqrcode/              # QR Code Generation Library
└── Root Files              # Main entry points
```

---

## 📁 ADMIN MODULE (`admin/`)

### Core Admin Files
- `dashboard.php` - Main admin dashboard (current version)
- `dashboard_modern.php` - Modern dashboard version
- `dashboard_new.php` - New dashboard version
- `dashboard_old_backup.php` - Backup of old dashboard
- `login_new.php` - Admin login page (current)
- `login.php` - Original login page
- `login_old_backup.php` - Backup login page
- `logout.php` - Admin logout handler
- `reset_password.php` - Password reset functionality

### Student Management
- `students.php` - View and manage all students
- `edit_student.php` - Edit student details
- `download_student_form.php` - Download student registration form as PDF
- `view_student_documents.php` - View uploaded student documents

### Course Management
- `manage_courses.php` - Add, edit, delete courses
- `edit_course.php` - Edit individual course details
- `course_links.php` - Manage course registration links
- `generate_qr.php` - Generate QR codes for courses
- `generate_link_qr.php` - Generate both link and QR code

### Other Admin Features
- `manage_batches.php` - Manage student batches
- `manage_announcements.php` - Create and manage announcements
- `add_admin.php` - Add new admin users with OTP verification

---

## 👨‍🎓 STUDENT PORTAL MODULE (`student/`)

### Main Student Files
- `login.php` - Student login page
- `logout.php` - Student logout handler
- `register.php` - Student registration form
- `dashboard.php` - Student dashboard
- `portal.php` - Student portal main page

### Student Features
- `profile.php` - View and edit profile
- `attendance.php` - View attendance records
- `fees.php` - View fee details and payment status
- `certificates.php` - View and download certificates
- `support.php` - Support and help desk
- `download_form.php` - Download registration form
- `change_password.php` - Change password

### Student Includes
- `student/includes/header.php` - Student portal header

---

## 🌐 PUBLIC WEBSITE MODULE (`public/`)

### Public Pages
- `courses.php` - Display all available courses
- `contact.php` - Contact page
- `news.php` - News and updates
- `management.php` - Management team information

---

## 🎨 ASSETS MODULE (`assets/`)

### CSS Files (`assets/css/`)
- `admin-theme.css` - Admin panel styling
- `student-portal.css` - Student portal styling
- `public-theme.css` - Public website styling
- `style.css` - General styles
- `toast-notifications.css` - Toast notification styles

### JavaScript Files (`assets/js/`)
- `main.js` - Main JavaScript functions
- `student-portal.js` - Student portal scripts
- `toast-notifications.js` - Toast notification system

### Images (`assets/images/`)
- `bhubaneswar_logo.png` - NIELIT Bhubaneswar logo
- `logo1.png`, `logo2.png` - Additional logos
- `National-Emblem.png` - National emblem
- `favicon.ico` - Website favicon
- `banners/` - Banner images for website

### QR Codes (`assets/qr_codes/`)
- Contains generated QR codes for course registration links
- Format: `qr_[course_code]_[id].png`

---

## ⚙️ CONFIGURATION MODULE (`config/`)

### Config Files
- `database.php` - Database connection settings
- `config.php` - Main configuration file
- `app.php` - Application settings
- `email.php` - Email configuration (PHPMailer)

---

## 🔧 INCLUDES MODULE (`includes/`)

### Helper Files
- `helpers.php` - General helper functions
- `email_helper.php` - Email sending functions
- `qr_helper.php` - QR code generation functions
- `student_id_helper.php` - Student ID generation

### Common Includes
- `header.php` - Common header
- `footer.php` - Common footer
- `navbar.php` - Navigation bar
- `head.php` - HTML head section
- `scripts.php` - Common scripts

---

## 📚 LIBRARIES MODULE (`libraries/`)

### Third-party Libraries
- `PHPMailer/` - Email sending library
- `tcpdf/` - PDF generation library
- `PhpSpreadsheet/` - Excel file handling

---

## 📄 ROOT FILES

### Main Entry Points
- `index.php` - Main website homepage
- `submit_registration.php` - Handle registration form submission
- `registration_success.php` - Registration success page

### Registration Files
- `internship_register.php` - Internship registration
- `internship_register_updated.php` - Updated internship form
- `internship_register_insert.php` - Insert internship data
- `internship_register_payment.php` - Payment handling
- `internship_register_test.php` - Test registration

### Utility Files
- `get_courses.php` - API to fetch courses
- `verify_payment.php` - Payment verification
- `resend_otp.php` - Resend OTP functionality
- `db_connection.php` - Database connection
- `razorpay_config.php` - Razorpay payment config

### Database Setup Files
- `check_database_structure.php` - Check database structure
- `add_missing_columns.php` - Add missing database columns
- `setup_student_portal.php` - Setup student portal tables
- `regenerate_all_qr_codes.php` - Regenerate all QR codes
- `export_database.php` - Export database
- `verify_database_import.php` - Verify database import

### Testing Files
- `test_config.php` - Test configuration
- `test_qrcode.php` - Test QR code generation
- `test_student_id_generation.php` - Test student ID generation
- `test_form_submission.php` - Test form submission
- `test_register.php` - Test registration
- Various other test files

---

## 📦 UPLOAD DIRECTORIES

### Upload Folders
- `uploads/` - Main uploads directory
  - `uploads/documents/` - Student documents
  - `uploads/photos/` - Student photos
  - `uploads/signatures/` - Student signatures
  - `uploads/receipts/` - Payment receipts
- `course_pdf/` - Course description PDFs
- `course_flyers/` - Course flyer images

---

## 🗄️ DATABASE STRUCTURE

### Main Tables
1. `courses` - Course information
2. `students` - Student records
3. `admins` - Admin users
4. `batches` - Student batches
5. `announcements` - System announcements
6. `student_portal_users` - Student portal login
7. `attendance` - Attendance records
8. `fees` - Fee records
9. `certificates` - Certificate records

---

## 🎯 KEY FEATURES IMPLEMENTED

### 1. Admin Panel
- ✅ Modern dashboard with statistics
- ✅ Student management (add, edit, view, delete)
- ✅ Course management with QR codes
- ✅ Registration link generation
- ✅ Batch management
- ✅ Announcements system
- ✅ Document viewing
- ✅ PDF form download
- ✅ OTP-based admin creation

### 2. Student Portal
- ✅ Student login system
- ✅ Dashboard with overview
- ✅ Profile management
- ✅ Attendance viewing
- ✅ Fee details
- ✅ Certificate access
- ✅ Support system
- ✅ Form download

### 3. Public Website
- ✅ Course listing
- ✅ Contact page
- ✅ News section
- ✅ Management information
- ✅ Modern responsive design

### 4. Registration System
- ✅ Multi-step registration form
- ✅ Auto student ID generation (NIELIT/2026/XXX/####)
- ✅ Document upload (photo, signature, certificates)
- ✅ QR code-based registration
- ✅ Link-based registration
- ✅ Email notifications
- ✅ PDF form generation

### 5. Course Management
- ✅ Course codes and abbreviations
- ✅ Registration link generation
- ✅ QR code generation
- ✅ Course flyer upload
- ✅ Course PDF upload
- ✅ Publish/unpublish courses
- ✅ Training center selection

---

## 🎨 THEME & STYLING

### Color Scheme
- Primary: `#0d47a1` (Blue)
- Secondary: `#1976d2` (Light Blue)
- Success: `#4caf50` (Green)
- Warning: `#ff9800` (Orange)
- Danger: `#f44336` (Red)

### Design Features
- Modern card-based layout
- Responsive design (mobile-friendly)
- Toast notifications
- Modal dialogs
- Smooth animations
- Professional typography

---

## 📊 SYSTEM STATISTICS

### File Count Summary
- Admin Files: 18
- Student Portal Files: 11
- Public Pages: 4
- Config Files: 4
- Helper Files: 8
- CSS Files: 5
- JavaScript Files: 3
- Libraries: 3 major libraries
- Total Documentation: 200+ MD files

---

## 🔐 SECURITY FEATURES

- ✅ Session-based authentication
- ✅ Password hashing
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection
- ✅ CSRF protection
- ✅ File upload validation
- ✅ OTP verification for admin creation
- ✅ Secure password reset

---

## 📱 RESPONSIVE DESIGN

- ✅ Mobile-friendly interface
- ✅ Tablet optimization
- ✅ Desktop layout
- ✅ Touch-friendly buttons
- ✅ Adaptive navigation

---

## 🚀 DEPLOYMENT

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- PHPMailer for emails
- GD Library for QR codes
- TCPDF for PDF generation

### Hosting
- Compatible with shared hosting
- Hostinger deployment ready
- XAMPP local development

---

## 📝 NOTES

This system is fully functional and production-ready with:
- Complete admin panel
- Student portal
- Public website
- Registration system
- QR code integration
- Email notifications
- PDF generation
- Document management

**Last Updated:** February 2026
**Version:** 2.0
**Status:** Production Ready ✅

---

## 🎯 NEXT STEPS

Ready to add new modules! Tell me what features you want to add:
- New admin features?
- New student portal features?
- New public website pages?
- Additional functionality?

I'm ready to help you expand the system! 🚀
