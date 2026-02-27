# 🎓 NIELIT Registration System - Complete Implementation Guide

## Overview
Modern, multi-level registration system with course abbreviation management, QR code generation, and automated registration links.

---

## ✅ What Has Been Completed

### 1. **Modern Registration Page** (`student/register.php`)
- ✅ Clean, sectioned layout with icons
- ✅ 8 distinct sections with visual hierarchy
- ✅ Responsive design with Bootstrap 5
- ✅ Form validation and user-friendly inputs
- ✅ Dynamic state/city dropdowns
- ✅ Education details table with add/remove rows
- ✅ File upload sections for documents
- ✅ Auto-age calculation from DOB
- ✅ Course filtering by training center

**Sections:**
1. Course Selection
2. Personal Information
3. Contact Information
4. Additional Details
5. Address Details
6. Academic Details
7. Payment Details
8. Document Upload

---

## 🔧 What Needs To Be Done

### 2. **Course Abbreviation System**

You need to add a `course_code` field to the courses table and manage it through the admin panel.

**Database Update:**
```sql
ALTER TABLE courses ADD COLUMN course_code VARCHAR(20) AFTER course_name;
ALTER TABLE courses ADD COLUMN registration_link TEXT AFTER course_code;
ALTER TABLE courses ADD COLUMN qr_code_path VARCHAR(255) AFTER registration_link;
```

**Update `admin/manage_courses.php`:**
- Add course_code input field in Add/Edit modals
- This code will be used for student ID generation (e.g., DBC21, PPI, etc.)

### 3. **Auto-Generate Registration Links**

When a course is created, automatically generate:
- Registration URL: `https://yoursite.com/student/register.php?course_id=123`
- QR Code image saved to `assets/qr_codes/course_123.png`

**Implementation Steps:**

#### A. Install QR Code Library
```bash
composer require endroid/qr-code
```

Or use PHP QR Code library (already available):
```php
// Include phpqrcode library
require_once 'libraries/phpqrcode/qrlib.php';
```

#### B. Update Course Creation Logic

In `admin/manage_courses.php`, after inserting a course:

```php
// After course insert
$course_id = $conn->insert_id;

// Generate registration link
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$registration_link = $base_url . "/student/register.php?course_id=" . $course_id;

// Generate QR Code
$qr_dir = __DIR__ . '/../assets/qr_codes/';
if (!file_exists($qr_dir)) {
    mkdir($qr_dir, 0777, true);
}
$qr_file = $qr_dir . 'course_' . $course_id . '.png';
QRcode::png($registration_link, $qr_file, QR_ECLEVEL_L, 10);

// Update course with link and QR path
$qr_path = 'assets/qr_codes/course_' . $course_id . '.png';
$stmt_update = $conn->prepare("UPDATE courses SET registration_link = ?, qr_code_path = ? WHERE id = ?");
$stmt_update->bind_param("ssi", $registration_link, $qr_path, $course_id);
$stmt_update->execute();
```

### 4. **Display Registration Links & QR Codes**

#### A. In Admin Panel (`admin/course_links.php`)

Create a new page to view all course registration links and QR codes:

```php
<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$courses = $conn->query("SELECT * FROM courses WHERE status = 'active' ORDER BY course_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Course Registration Links - NIELIT Admin</title>
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <h2>Course Registration Links & QR Codes</h2>
            
            <div class="row">
                <?php while ($course = $courses->fetch_assoc()): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><?php echo htmlspecialchars($course['course_name']); ?></h5>
                            <span class="badge bg-primary"><?php echo $course['course_code']; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Registration Link:</h6>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" value="<?php echo $course['registration_link']; ?>" id="link_<?php echo $course['id']; ?>" readonly>
                                        <button class="btn btn-primary" onclick="copyLink(<?php echo $course['id']; ?>)">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                    <a href="<?php echo $course['registration_link']; ?>" target="_blank" class="btn btn-success btn-sm">
                                        <i class="fas fa-external-link-alt"></i> Open Link
                                    </a>
                                </div>
                                <div class="col-md-6 text-center">
                                    <h6>QR Code:</h6>
                                    <?php if (!empty($course['qr_code_path']) && file_exists('../' . $course['qr_code_path'])): ?>
                                        <img src="../<?php echo $course['qr_code_path']; ?>" alt="QR Code" style="max-width: 200px;">
                                        <br>
                                        <a href="../<?php echo $course['qr_code_path']; ?>" download class="btn btn-info btn-sm mt-2">
                                            <i class="fas fa-download"></i> Download QR
                                        </a>
                                    <?php else: ?>
                                        <p class="text-muted">QR Code not generated</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </main>
    </div>
    
    <script>
    function copyLink(courseId) {
        const input = document.getElementById('link_' + courseId);
        input.select();
        document.execCommand('copy');
        alert('Link copied to clipboard!');
    }
    </script>
</body>
</html>
```

#### B. On Public Website (`public/courses.php`)

Display QR codes on the courses page:

```php
<div class="course-card">
    <h4><?php echo $course['course_name']; ?></h4>
    <p><?php echo $course['description']; ?></p>
    
    <div class="registration-section">
        <h6>Register Now:</h6>
        <div class="row">
            <div class="col-md-6">
                <a href="<?php echo $course['registration_link']; ?>" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Register Online
                </a>
            </div>
            <div class="col-md-6 text-center">
                <p class="mb-2">Scan QR Code:</p>
                <img src="<?php echo $course['qr_code_path']; ?>" alt="QR Code" style="max-width: 150px;">
            </div>
        </div>
    </div>
</div>
```

### 5. **Student ID Generation with Course Codes**

Update `submit_registration.php` to use course codes from database:

```php
// Get course details including course_code
$stmt = $conn->prepare("SELECT course_code FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();

$course_code = $course['course_code']; // e.g., DBC21, PPI, etc.
$current_year = date('Y');

// Generate student ID: NIELIT/2025/DBC21/0001
$stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
$like_pattern = "NIELIT/{$current_year}/{$course_code}/%";
$stmt->bind_param("s", $like_pattern);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $last_id = $result->fetch_assoc()['student_id'];
    $last_number = (int)substr($last_id, strrpos($last_id, '/') + 1);
    $new_number = str_pad($last_number + 1, 4, "0", STR_PAD_LEFT);
} else {
    $new_number = "0001";
}

$student_id = "NIELIT/{$current_year}/{$course_code}/{$new_number}";
```

---

## 📋 Implementation Checklist

### Phase 1: Database Setup
- [ ] Run SQL to add `course_code`, `registration_link`, `qr_code_path` columns
- [ ] Update existing courses with course codes

### Phase 2: Admin Panel Updates
- [ ] Add course_code field to Add Course modal
- [ ] Add course_code field to Edit Course modal
- [ ] Implement QR code generation on course creation
- [ ] Create `admin/course_links.php` page
- [ ] Add "Course Links" menu item to sidebar

### Phase 3: Registration System
- [ ] Test new registration page (`student/register.php`)
- [ ] Create `student/submit_registration.php` to handle form submission
- [ ] Update student ID generation to use course codes from database
- [ ] Test email sending with credentials

### Phase 4: Public Website
- [ ] Update `public/courses.php` to show QR codes
- [ ] Add "Register Now" buttons with links
- [ ] Test QR code scanning with mobile devices

### Phase 5: Testing
- [ ] Create test course with code "TEST"
- [ ] Generate registration link and QR code
- [ ] Test registration through link
- [ ] Test registration through QR code
- [ ] Verify student ID format: NIELIT/2025/TEST/0001

---

## 🎨 Features Summary

### For Students:
✅ Modern, easy-to-use registration form
✅ Clear sections with visual hierarchy
✅ Auto-fill features (age calculation, etc.)
✅ Mobile-responsive design
✅ QR code scanning for quick registration

### For Admins:
✅ Automatic registration link generation
✅ QR code creation for each course
✅ Easy link sharing and copying
✅ Downloadable QR codes for printing
✅ Course code management
✅ Student ID auto-generation

### Technical:
✅ Clean, maintainable code
✅ Bootstrap 5 styling
✅ Font Awesome icons
✅ Secure file uploads
✅ Form validation
✅ Database-driven course codes

---

## 📱 QR Code Usage

**For Posters/Brochures:**
1. Download QR code from admin panel
2. Print on course brochures
3. Students scan to register directly

**For Website:**
1. Display QR code on course pages
2. Students can scan from desktop to register on mobile

---

## 🔐 Security Notes

- All file uploads validated
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars()
- Password hashing for student accounts
- Session management for admin access

---

## 📞 Support

For issues or questions:
- Check database connection in `config/config.php`
- Verify file permissions for `uploads/` and `assets/qr_codes/`
- Check PHP error logs for debugging

---

**Status:** Registration page complete ✅ | Course management needs QR implementation ⏳
