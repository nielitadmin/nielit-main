# 🚀 Registration System - Quick Reference Card

## 📋 One-Page Overview

### System Components

```
┌─────────────────────────────────────────────────────────┐
│                   ADMIN PANEL                            │
│  • Create courses with codes                            │
│  • Generate registration links                          │
│  • Auto-generate QR codes                               │
│  • View registered students                             │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│              REGISTRATION LINKS & QR CODES               │
│  Format: /student/register.php?course_id=X              │
│  QR Code: assets/qr_codes/qr_COURSE_ID.png             │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│              STUDENT REGISTRATION FORM                   │
│  • 3-Level hierarchical structure                       │
│  • Course locked from link/QR                           │
│  • Personal, contact, education info                    │
│  • Document uploads                                     │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│              AUTO-GENERATED CREDENTIALS                  │
│  Student ID: NIELIT/2026/PPI/0001                       │
│  Password: a3f7b2c9d4e1f6a8 (16-char random)            │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│              EMAIL NOTIFICATION                          │
│  • Professional HTML template                           │
│  • Contains credentials                                 │
│  • Login button included                                │
└─────────────────────────────────────────────────────────┘
```

---

## 🔑 Key Files

| File | Purpose |
|------|---------|
| `admin/manage_courses.php` | Create/edit courses |
| `admin/generate_link_qr.php` | Generate links & QR codes |
| `student/register.php` | Registration form (3 levels) |
| `submit_registration.php` | Process registration |
| `registration_success.php` | Show credentials |
| `includes/student_id_helper.php` | Generate student IDs |
| `includes/email_helper.php` | Send emails |
| `includes/qr_helper.php` | Generate QR codes |

---

## 🆔 Student ID Format

```
NIELIT / YYYY / ABBR / ####
  │      │      │      │
  │      │      │      └─ Sequential number (0001-9999)
  │      │      └──────── Course abbreviation (PPI, DBC, etc.)
  │      └─────────────── Current year (2026, 2027, etc.)
  └────────────────────── Institute name (fixed)

Examples:
• NIELIT/2026/PPI/0001
• NIELIT/2026/DBC/0015
• NIELIT/2027/PPI/0001  ← Resets each year
```

---

## 🔐 Password System

```
Generation:  bin2hex(random_bytes(8))
Format:      16 hexadecimal characters
Example:     "a3f7b2c9d4e1f6a8"
Storage:     Hashed with bcrypt
Delivery:    Email + Success page
```

---

## 📧 Email Configuration

```php
// config/email.php
SMTP_HOST:     smtp.gmail.com
SMTP_PORT:     587
SMTP_USERNAME: your-email@gmail.com
SMTP_PASSWORD: your-app-password
```

---

## 🎨 3-Level Form Structure

```
┌─────────────────────────────────────────┐
│  LEVEL 1: Course & Personal Info        │
│  Badge: Blue gradient                   │
│  • Course selection (locked if from QR) │
│  • Name, DOB, Gender                    │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  LEVEL 2: Contact & Address             │
│  Badge: Gray gradient                   │
│  • Mobile, Email, Aadhar                │
│  • State, City, Address                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  LEVEL 3: Education & Documents         │
│  Badge: Cyan gradient                   │
│  • Educational qualifications           │
│  • Document uploads                     │
└─────────────────────────────────────────┘
```

---

## 🔒 Course Locking

When student accesses via registration link or QR code:

```
✅ Course field: READ-ONLY
✅ Training center: READ-ONLY
✅ Visual indicators:
   • 🔒 Lock icon
   • Blue background
   • "Locked by registration link" message
```

---

## 🧪 Quick Test Steps

1. **Create Course**
   - Login to admin
   - Add course with abbreviation
   - Click "Generate Link"
   - Verify QR code appears

2. **Test Registration**
   - Copy registration link
   - Open in new browser
   - Verify course is locked
   - Complete form
   - Submit

3. **Verify Results**
   - Check success page shows credentials
   - Verify student ID format
   - Check email received
   - Confirm database entry

---

## 📊 Database Queries

```sql
-- View all courses with links
SELECT course_name, course_code, course_abbreviation, 
       apply_link, qr_code_path 
FROM courses 
WHERE status = 'active';

-- View students by course
SELECT student_id, name, email, registration_date 
FROM students 
WHERE course_id = X 
ORDER BY student_id;

-- Count students per course (current year)
SELECT c.course_name, COUNT(s.id) as total
FROM courses c
LEFT JOIN students s ON s.course_id = c.id 
  AND s.student_id LIKE 'NIELIT/2026/%'
GROUP BY c.id;
```

---

## 🚨 Troubleshooting

| Issue | Solution |
|-------|----------|
| QR not generating | Check GD library, directory permissions |
| Email not sending | Verify SMTP settings, use App Password |
| Duplicate student ID | Check course_abbreviation is set |
| Course not locking | Verify course_id in URL parameter |
| Upload failing | Check directory permissions (755) |

---

## 📁 Directory Structure

```
/
├── admin/
│   ├── manage_courses.php
│   ├── edit_course.php
│   └── generate_link_qr.php
├── student/
│   ├── register.php
│   └── login.php
├── includes/
│   ├── student_id_helper.php
│   ├── email_helper.php
│   └── qr_helper.php
├── assets/
│   └── qr_codes/          ← QR images stored here
├── uploads/               ← Student documents
├── submit_registration.php
└── registration_success.php
```

---

## ✅ Pre-Deployment Checklist

- [ ] Database tables created
- [ ] Email SMTP configured
- [ ] Directory permissions: 755
- [ ] PHP GD library installed
- [ ] Course abbreviations set
- [ ] Test registration completed
- [ ] QR codes generated
- [ ] Email notifications working

---

## 🎯 Key Features

✅ Auto-generated student IDs (sequential per course/year)  
✅ Auto-generated secure passwords (16-char random)  
✅ Automatic QR code generation  
✅ Course locking from registration links  
✅ Professional email notifications  
✅ 3-level hierarchical form design  
✅ Mobile-responsive interface  
✅ Document upload support  
✅ Real-time age calculation  
✅ State/City API integration  

---

## 📞 Quick Support

**Email Issues**: Check `config/email.php` settings  
**QR Issues**: Verify GD library: `php -m | grep gd`  
**ID Issues**: Check course has `course_abbreviation` set  
**Lock Issues**: Ensure URL has `?course_id=X` parameter  

---

**Version**: 1.0  
**Status**: Production Ready ✅  
**Last Updated**: February 11, 2026
