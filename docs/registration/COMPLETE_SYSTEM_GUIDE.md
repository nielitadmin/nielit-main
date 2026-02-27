# 📚 Complete Registration System Guide
## NIELIT Bhubaneswar - Student Registration Workflow

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Course Creation Process](#course-creation-process)
3. [Registration Link Generation](#registration-link-generation)
4. [QR Code System](#qr-code-system)
5. [Student Registration Flow](#student-registration-flow)
6. [Student ID Generation](#student-id-generation)
7. [Password Generation](#password-generation)
8. [Email Notification System](#email-notification-system)
9. [Database Structure](#database-structure)
10. [File Locations](#file-locations)
11. [Testing Guide](#testing-guide)

---

## 🎯 System Overview

The NIELIT Bhubaneswar Registration System is a complete end-to-end solution for managing student registrations. It includes:

- **Admin Panel**: Course creation and management
- **Registration Links**: Unique URLs for each course
- **QR Codes**: Scannable codes for easy registration access
- **Auto Student ID**: Sequential ID generation (NIELIT/YYYY/ABBR/####)
- **Auto Password**: Secure 16-character password generation
- **Email Notifications**: Automated credential delivery
- **Course Locking**: Pre-selected courses from registration links

### Key Features

✅ Hierarchical 3-level registration form  
✅ Course-specific registration links  
✅ Automatic QR code generation  
✅ Sequential student ID generation per course/year  
✅ Secure password generation and hashing  
✅ Professional HTML email notifications  
✅ Locked course selection from registration links  
✅ Mobile-responsive design  

---

## 🏗️ Course Creation Process

### Step 1: Admin Creates Course

**File**: `admin/manage_courses.php`

Admin fills in course details:

```php
// Required Fields
- Course Name: "Python Programming Internship"
- Course Code: "PPI-2026" (for display/reference)
- Student ID Code: "PPI" (for ID generation)
- Course Type: Regular/Internship/Bootcamp/Workshop
- Training Center: NIELIT Bhubaneswar / NIELIT Balasore
- Duration: "6 months"
- Fees: 15000
- Description: Course details
```

### Step 2: Course Code vs Student ID Code

**Important Distinction**:

- **Course Code** (`course_code`): Display identifier (e.g., "PPI-2026", "DBC15")
- **Student ID Code** (`course_abbreviation`): Used in student IDs (e.g., "PPI", "DBC")

Example:
```
Course Code: PPI-2026
Student ID Code: PPI
Generated Student ID: NIELIT/2026/PPI/0001
```

### Step 3: Database Insert

```sql
INSERT INTO courses (
    course_name, 
    course_code, 
    course_abbreviation,
    course_type,
    training_center,
    duration,
    fees,
    description,
    status
) VALUES (
    'Python Programming Internship',
    'PPI-2026',
    'PPI',
    'Internship',
    'NIELIT BHUBANESWAR CENTER',
    '6 months',
    15000,
    'Learn Python programming...',
    'active'
);
```

---

## 🔗 Registration Link Generation

### Method 1: During Course Creation

**File**: `admin/manage_courses.php`

When admin clicks "Generate Link" button:

```javascript
// JavaScript triggers AJAX call
function generateApplyLink('add') {
    // Sends course_name and course_code to generate_link_qr.php
}
```

### Method 2: During Course Editing

**File**: `admin/edit_course.php`

Admin can generate/regenerate link:

```javascript
function generateApplyLinkEdit() {
    // AJAX call to generate_link_qr.php
    // Automatically generates QR code too
}
```

### Link Generation Logic

**File**: `admin/generate_link_qr.php`

```php
// Generate registration link
$baseUrl = "https://nielitbhubaneswar.in";
$apply_link = $baseUrl . '/student/register.php?course_id=' . $course_id;

// Example output:
// https://nielitbhubaneswar.in/student/register.php?course_id=5
```

### Link Format

```
Base URL + /student/register.php?course_id={ID}

Examples:
- https://nielitbhubaneswar.in/student/register.php?course_id=1
- https://nielitbhubaneswar.in/student/register.php?course_id=5
- https://nielitbhubaneswar.in/student/register.php?course_id=12
```

---

## 📱 QR Code System

### Automatic QR Generation

**File**: `includes/qr_helper.php`

QR codes are generated automatically when:
1. Admin clicks "Generate Link" button
2. Course is saved with a registration link
3. Course is updated (only if QR doesn't exist)

### QR Code Generation Process

```php
function generateCourseQRCode($course_id, $course_code) {
    // 1. Create QR directory if needed
    $qr_dir = __DIR__ . '/../assets/qr_codes/';
    
    // 2. Generate registration URL
    $registration_url = "https://nielitbhubaneswar.in/student/register.php?course_id=" . $course_id;
    
    // 3. Create filename
    $filename = 'qr_' . $course_code . '_' . $course_id . '.png';
    
    // 4. Generate QR code image
    QRcode::png($registration_url, $qr_file_path, QR_ECLEVEL_L, 10, 2);
    
    // 5. Return path
    return 'assets/qr_codes/' . $filename;
}
```

### QR Code Storage

```
Location: /assets/qr_codes/
Format: qr_{COURSE_CODE}_{COURSE_ID}.png

Examples:
- qr_PPI-2026_5.png
- qr_DBC15_12.png
- qr_BOOTCAMP13_8.png
```

### QR Code Features

✅ **One-time Generation**: QR codes are NOT regenerated on every update  
✅ **Persistent**: Stored as PNG files in assets/qr_codes/  
✅ **Downloadable**: Admin can download QR codes  
✅ **Scannable**: Students scan to access registration form  
✅ **Course-Locked**: Scanning QR pre-selects the course  

---

## 👨‍🎓 Student Registration Flow

### Step 1: Student Access

Students can access registration via:

1. **QR Code Scan** → Opens registration form with locked course
2. **Registration Link** → Opens registration form with locked course
3. **Direct URL** → Opens registration form (course selectable)

### Step 2: Registration Form

**File**: `student/register.php`

**3-Level Hierarchical Structure**:

#### Level 1: Course & Personal Information
- Course Selection (locked if from link/QR)
- Training Center (locked if from link/QR)
- Full Name
- Father's Name, Mother's Name
- Date of Birth, Age (auto-calculated)
- Gender, Marital Status

#### Level 2: Contact & Address Information
- Mobile Number
- Email Address
- Aadhar Number
- Nationality
- Religion, Category
- State, City, Pincode
- Address

#### Level 3: Educational & Documents
- Educational Qualifications (dynamic table)
- College Name
- Document Uploads:
  - Educational Documents (PDF)
  - Passport Photo (JPG/PNG)
  - Signature (JPG/PNG)
  - Payment Receipt (JPG/PNG/PDF)
- UTR Number

### Step 3: Course Locking Mechanism

```php
// Check if course_id parameter exists
$selected_course_id = $_GET['course_id'] ?? '';

if (!empty($selected_course_id)) {
    // Fetch course details
    $course_details = getCourseDetails($selected_course_id);
    
    // Display locked fields
    echo '<input type="text" value="' . $course_name . '" readonly>';
    echo '<input type="hidden" name="course_id" value="' . $course_id . '">';
}
```

**Visual Indicators**:
- 🔒 Lock icon displayed
- Blue background on locked fields
- "Locked by registration link" message
- Fields are read-only

---

## 🆔 Student ID Generation

### Format Structure

```
NIELIT / YYYY / ABBR / ####

Components:
- NIELIT: Institute name (fixed)
- YYYY: Current year (e.g., 2026)
- ABBR: Course abbreviation (e.g., PPI, DBC, BC13)
- ####: Sequential number (0001, 0002, 0003...)
```

### Generation Logic

**File**: `includes/student_id_helper.php`

```php
function generateStudentID($course_id, $conn) {
    // 1. Get course abbreviation
    $course = getCourse($course_id);
    $abbreviation = $course['course_abbreviation']; // e.g., "PPI"
    
    // 2. Get current year
    $year = date('Y'); // e.g., "2026"
    
    // 3. Build prefix
    $prefix = "NIELIT/{$year}/{$abbreviation}/"; // "NIELIT/2026/PPI/"
    
    // 4. Find last student ID with this prefix
    $last_id = getLastStudentID($prefix);
    // e.g., "NIELIT/2026/PPI/0005"
    
    // 5. Extract and increment sequence
    $last_sequence = extractSequence($last_id); // 5
    $next_sequence = $last_sequence + 1; // 6
    
    // 6. Format new ID
    $student_id = sprintf("%s%04d", $prefix, $next_sequence);
    // Result: "NIELIT/2026/PPI/0006"
    
    return $student_id;
}
```

### Sequential Numbering

**Per Course + Per Year**:

```
Course: Python Programming (PPI)
Year: 2026

Student 1: NIELIT/2026/PPI/0001
Student 2: NIELIT/2026/PPI/0002
Student 3: NIELIT/2026/PPI/0003
...
Student 100: NIELIT/2026/PPI/0100
```

**Different Course**:

```
Course: Data Science Bootcamp (DBC)
Year: 2026

Student 1: NIELIT/2026/DBC/0001
Student 2: NIELIT/2026/DBC/0002
```

**Next Year**:

```
Course: Python Programming (PPI)
Year: 2027

Student 1: NIELIT/2027/PPI/0001  ← Resets to 0001
Student 2: NIELIT/2027/PPI/0002
```

### Race Condition Protection

```php
function getNextStudentID($course_id, $conn, $max_retries = 5) {
    for ($i = 0; $i < $max_retries; $i++) {
        $student_id = generateStudentID($course_id, $conn);
        
        // Check if ID already exists
        if (!studentIDExists($student_id, $conn)) {
            return $student_id; // Unique ID found
        }
        
        // ID exists, retry after 100ms
        usleep(100000);
    }
    
    return null; // Failed after retries
}
```

---

## 🔐 Password Generation

### Auto-Generated Password

**File**: `submit_registration.php`

```php
// Generate 16-character random password
$password = bin2hex(random_bytes(8));

// Example outputs:
// "a3f7b2c9d4e1f6a8"
// "9c2e5f8b1d4a7c3e"
// "f1a8c3e7b2d9f4a6"
```

### Password Characteristics

- **Length**: 16 characters
- **Type**: Hexadecimal (0-9, a-f)
- **Randomness**: Cryptographically secure
- **Uniqueness**: Different for each student

### Password Hashing

```php
// Hash password before storing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Store in database
INSERT INTO students (student_id, password) 
VALUES ('NIELIT/2026/PPI/0001', '$2y$10$...');
```

### Password Security

✅ **Plain text sent to student** (via email and success page)  
✅ **Hashed in database** (bcrypt algorithm)  
✅ **Cannot be retrieved** (one-way hash)  
✅ **Secure verification** (password_verify function)  

---

## 📧 Email Notification System

### Email Trigger

**File**: `submit_registration.php`

```php
// After successful registration
$email_sent = sendRegistrationEmail(
    $email,           // student@example.com
    $name,            // "John Doe"
    $student_id,      // "NIELIT/2026/PPI/0001"
    $password,        // "a3f7b2c9d4e1f6a8"
    $course_name,     // "Python Programming Internship"
    $training_center  // "NIELIT BHUBANESWAR CENTER"
);
```

### Email Configuration

**File**: `config/email.php`

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@nielitbhubaneswar.in');
define('SMTP_FROM_NAME', 'NIELIT Bhubaneswar');
```

### Email Template

**File**: `includes/email_helper.php`

Professional HTML email includes:

1. **Header**: Blue gradient with NIELIT branding
2. **Greeting**: Personalized with student name
3. **Credentials Box**: 
   - Student ID
   - Password
   - Course Name
   - Training Center
4. **Important Notice**: Warning to save credentials
5. **Login Button**: Direct link to student portal
6. **Contact Information**: Support email and phone
7. **Footer**: Copyright and automated message notice

### Email Content Example

```
Subject: Registration Successful - NIELIT Bhubaneswar

Dear John Doe,

Congratulations! Your registration has been successfully completed.

YOUR LOGIN CREDENTIALS:
========================
Student ID: NIELIT/2026/PPI/0001
Password: a3f7b2c9d4e1f6a8
Course: Python Programming Internship
Training Center: NIELIT BHUBANESWAR CENTER

[Login to Student Portal Button]

© 2026 NIELIT Bhubaneswar. All rights reserved.
```

---


## 🗄️ Database Structure

### Courses Table

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(255) NOT NULL,
    course_code VARCHAR(50),              -- Display code (e.g., "PPI-2026")
    course_abbreviation VARCHAR(10),      -- For student ID (e.g., "PPI")
    course_type VARCHAR(50),
    training_center VARCHAR(255),
    duration VARCHAR(100),
    fees DECIMAL(10,2),
    description TEXT,
    apply_link VARCHAR(500),              -- Registration URL
    qr_code_path VARCHAR(255),            -- QR code file path
    qr_generated_at DATETIME,             -- QR generation timestamp
    link_published TINYINT(1) DEFAULT 0,  -- Show/hide on website
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Students Table

```sql
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) UNIQUE NOT NULL,  -- NIELIT/2026/PPI/0001
    password VARCHAR(255) NOT NULL,           -- Hashed password
    course VARCHAR(255),
    course_id INT,
    training_center VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    father_name VARCHAR(255),
    mother_name VARCHAR(255),
    dob DATE,
    age INT,
    mobile VARCHAR(15),
    aadhar VARCHAR(12),
    gender VARCHAR(10),
    religion VARCHAR(50),
    marital_status VARCHAR(20),
    category VARCHAR(50),
    nationality VARCHAR(50),
    email VARCHAR(255),
    state VARCHAR(100),
    city VARCHAR(100),
    pincode VARCHAR(10),
    address TEXT,
    college_name VARCHAR(255),
    education_details TEXT,               -- JSON encoded
    documents VARCHAR(255),               -- File path
    passport_photo VARCHAR(255),          -- File path
    signature VARCHAR(255),               -- File path
    payment_receipt VARCHAR(255),         -- File path
    utr_number VARCHAR(100),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);
```

### Key Relationships

```
courses.id → students.course_id (Foreign Key)
courses.course_abbreviation → Used in student_id generation
courses.apply_link → Registration URL with course_id parameter
courses.qr_code_path → QR code image file
```

---

## 📁 File Locations

### Admin Files

```
admin/
├── manage_courses.php          # Course CRUD operations
├── edit_course.php             # Edit course details
├── generate_link_qr.php        # AJAX endpoint for link/QR generation
├── course_links.php            # View all registration links
├── students.php                # View registered students
└── dashboard.php               # Admin dashboard
```

### Student Files

```
student/
├── register.php                # Registration form (3-level hierarchy)
├── login.php                   # Student login
└── portal.php                  # Student dashboard
```

### Core Files

```
/
├── submit_registration.php     # Form processing
├── registration_success.php    # Success page with credentials
└── index.php                   # Public homepage
```

### Helper Files

```
includes/
├── student_id_helper.php       # Student ID generation functions
├── email_helper.php            # Email sending functions
├── qr_helper.php               # QR code generation functions
└── helpers.php                 # General utility functions
```

### Configuration Files

```
config/
├── config.php                  # Main configuration
├── database.php                # Database connection
└── email.php                   # Email SMTP settings
```

### Asset Directories

```
assets/
├── qr_codes/                   # Generated QR code images
│   ├── qr_PPI-2026_5.png
│   ├── qr_DBC15_12.png
│   └── ...
└── css/
    ├── admin-theme.css         # Admin panel styling
    ├── public-theme.css        # Public website styling
    └── toast-notifications.css # Toast notification styling
```

### Upload Directories

```
uploads/                        # Student uploaded documents
course_pdf/                     # Course description PDFs
```

---

## 🧪 Testing Guide

### Test 1: Create Course

1. Login to admin panel
2. Go to "Manage Courses"
3. Click "Add New Course"
4. Fill in details:
   - Course Name: "Test Course"
   - Course Code: "TEST-2026"
   - Student ID Code: "TEST"
   - Training Center: Select one
   - Other required fields
5. Click "Generate Link" button
6. Verify:
   - ✅ Registration link appears
   - ✅ QR code is generated
   - ✅ Success message shows
7. Save course

### Test 2: Registration Link

1. Copy registration link from admin panel
2. Open in new browser/incognito window
3. Verify:
   - ✅ Registration form opens
   - ✅ Course is pre-selected and locked
   - ✅ Training center is locked
   - ✅ Lock icon and message appear
   - ✅ Cannot change course/center

### Test 3: QR Code Scan

1. Download QR code from admin panel
2. Scan with mobile device
3. Verify:
   - ✅ Opens registration form
   - ✅ Course is locked
   - ✅ Form is mobile-responsive

### Test 4: Student Registration

1. Fill out registration form completely
2. Upload required documents
3. Submit form
4. Verify:
   - ✅ Success page appears
   - ✅ Student ID is displayed (NIELIT/2026/TEST/0001)
   - ✅ Password is displayed
   - ✅ Copy buttons work
   - ✅ Email notification sent

### Test 5: Student ID Sequence

1. Register first student → Check ID: NIELIT/2026/TEST/0001
2. Register second student → Check ID: NIELIT/2026/TEST/0002
3. Register third student → Check ID: NIELIT/2026/TEST/0003
4. Verify:
   - ✅ Sequential numbering
   - ✅ No duplicate IDs
   - ✅ Correct format

### Test 6: Email Notification

1. Complete registration with valid email
2. Check email inbox (and spam folder)
3. Verify email contains:
   - ✅ Student ID
   - ✅ Password
   - ✅ Course name
   - ✅ Training center
   - ✅ Login button
   - ✅ Professional formatting

### Test 7: Database Verification

```sql
-- Check course record
SELECT id, course_name, course_code, course_abbreviation, 
       apply_link, qr_code_path 
FROM courses 
WHERE course_code = 'TEST-2026';

-- Check student records
SELECT student_id, name, email, course, registration_date 
FROM students 
WHERE course_id = [COURSE_ID]
ORDER BY student_id;

-- Verify student ID format
SELECT student_id FROM students 
WHERE student_id LIKE 'NIELIT/2026/TEST/%';
```

### Test 8: File System Verification

```bash
# Check QR code file exists
ls -la assets/qr_codes/qr_TEST-2026_*.png

# Check uploaded documents
ls -la uploads/

# Verify file permissions
ls -ld assets/qr_codes/
ls -ld uploads/
```

---

## 🔄 Complete Workflow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN CREATES COURSE                      │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ 1. Fill course details                                │  │
│  │ 2. Set course_code (PPI-2026)                        │  │
│  │ 3. Set course_abbreviation (PPI)                     │  │
│  │ 4. Click "Generate Link"                             │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              SYSTEM GENERATES LINK & QR CODE                 │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ • Creates URL: /student/register.php?course_id=5     │  │
│  │ • Generates QR code image                            │  │
│  │ • Saves to: assets/qr_codes/qr_PPI-2026_5.png       │  │
│  │ • Updates database with paths                        │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                 STUDENT ACCESSES REGISTRATION                │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Option A: Scans QR code                              │  │
│  │ Option B: Clicks registration link                   │  │
│  │ Option C: Direct URL access                          │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              REGISTRATION FORM OPENS (3 LEVELS)              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ LEVEL 1: Course & Personal Info                      │  │
│  │   • Course: LOCKED (if from link/QR)                 │  │
│  │   • Training Center: LOCKED (if from link/QR)        │  │
│  │   • Name, DOB, Gender, etc.                          │  │
│  │                                                       │  │
│  │ LEVEL 2: Contact & Address                           │  │
│  │   • Mobile, Email, Aadhar                            │  │
│  │   • State, City, Address                             │  │
│  │                                                       │  │
│  │ LEVEL 3: Education & Documents                       │  │
│  │   • Educational qualifications                       │  │
│  │   • Document uploads                                 │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  STUDENT SUBMITS FORM                        │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ submit_registration.php processes data                │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                 SYSTEM GENERATES CREDENTIALS                 │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ 1. Generate Student ID                               │  │
│  │    • Get course abbreviation: "PPI"                  │  │
│  │    • Get current year: "2026"                        │  │
│  │    • Find last sequence: 0005                        │  │
│  │    • Increment: 0006                                 │  │
│  │    • Result: NIELIT/2026/PPI/0006                    │  │
│  │                                                       │  │
│  │ 2. Generate Password                                 │  │
│  │    • Create 16-char random: "a3f7b2c9d4e1f6a8"       │  │
│  │    • Hash for database: bcrypt                       │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   SAVE TO DATABASE                           │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ INSERT INTO students (                               │  │
│  │   student_id,    // NIELIT/2026/PPI/0006            │  │
│  │   password,      // $2y$10$... (hashed)             │  │
│  │   name,          // John Doe                         │  │
│  │   email,         // john@example.com                 │  │
│  │   course_id,     // 5                                │  │
│  │   ...            // other fields                     │  │
│  │ )                                                     │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  SEND EMAIL NOTIFICATION                     │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ To: john@example.com                                 │  │
│  │ Subject: Registration Successful                     │  │
│  │ Body: HTML email with credentials                    │  │
│  │   • Student ID: NIELIT/2026/PPI/0006                 │  │
│  │   • Password: a3f7b2c9d4e1f6a8                       │  │
│  │   • Course: Python Programming Internship            │  │
│  │   • Login button                                     │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   SHOW SUCCESS PAGE                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ ✅ Registration Successful!                           │  │
│  │                                                       │  │
│  │ Student ID: NIELIT/2026/PPI/0006 [Copy]              │  │
│  │ Password: a3f7b2c9d4e1f6a8 [Copy]                    │  │
│  │                                                       │  │
│  │ ⚠️ Save these credentials!                            │  │
│  │ 📧 Email sent to john@example.com                     │  │
│  │                                                       │  │
│  │ [Login to Portal] [Go to Home]                       │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎨 UI/UX Features

### Hierarchical 3-Level Design

**Level 1**: Blue gradient badge  
**Level 2**: Gray gradient badge  
**Level 3**: Cyan gradient badge  

Each level has:
- Animated header with badge
- Level title and subtitle
- Grouped form sections
- Smooth transitions

### Course Locking Visual Indicators

When accessed via registration link/QR:
- 🔒 Lock icon displayed
- Light blue background (#f0f9ff)
- Blue border (#90caf9)
- Bold blue text (#0d47a1)
- "Locked by registration link" message
- Read-only fields

### Modern Form Elements

- Rounded corners (border-radius: 10-16px)
- Gradient backgrounds
- Box shadows for depth
- Hover effects
- Focus states with blue glow
- Smooth transitions (0.3s ease)

### Responsive Design

- Desktop: Multi-column layouts
- Tablet: Adjusted columns
- Mobile: Single column, stacked elements
- Touch-friendly buttons
- Readable font sizes

---

## 🔧 Configuration Requirements

### 1. Database Setup

```sql
-- Run these SQL files in order:
1. database_add_missing_columns.sql
2. database_add_course_abbreviation.sql
3. database_qr_system_update.sql
```

### 2. Email Configuration

Edit `config/email.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');  // Gmail App Password
define('SMTP_FROM_EMAIL', 'noreply@nielitbhubaneswar.in');
define('SMTP_FROM_NAME', 'NIELIT Bhubaneswar');
```

### 3. Directory Permissions

```bash
chmod 755 assets/qr_codes/
chmod 755 uploads/
chmod 755 course_pdf/
```

### 4. PHP Extensions Required

- GD Library (for QR code generation)
- MySQLi (for database)
- OpenSSL (for secure password generation)
- cURL (for email sending)

---

## 📊 Statistics & Monitoring

### View Student ID Statistics

```php
require_once 'includes/student_id_helper.php';

$stats = getStudentIDStatistics($conn);

echo "Total Students (2026): " . $stats['total_this_year'];

foreach ($stats['courses'] as $course) {
    echo $course['course_name'] . ": " . $course['student_count'] . " students\n";
}
```

### Monitor QR Code Usage

```sql
-- Count registrations per course
SELECT 
    c.course_name,
    c.course_code,
    COUNT(s.id) as total_registrations
FROM courses c
LEFT JOIN students s ON s.course_id = c.id
WHERE c.qr_code_path IS NOT NULL
GROUP BY c.id
ORDER BY total_registrations DESC;
```

### Check Email Delivery

```sql
-- Students registered today
SELECT 
    student_id,
    name,
    email,
    registration_date
FROM students
WHERE DATE(registration_date) = CURDATE()
ORDER BY registration_date DESC;
```

---

## 🚀 Deployment Checklist

### Pre-Deployment

- [ ] Database tables created
- [ ] Email SMTP configured and tested
- [ ] Directory permissions set
- [ ] PHP extensions installed
- [ ] Course abbreviations set for all courses
- [ ] Test registration completed successfully

### Post-Deployment

- [ ] Generate registration links for all active courses
- [ ] Download and distribute QR codes
- [ ] Test email notifications
- [ ] Verify student ID generation
- [ ] Check file uploads working
- [ ] Test mobile responsiveness
- [ ] Monitor error logs

---

## 📞 Support & Troubleshooting

### Common Issues

**Issue**: QR code not generating  
**Solution**: Check GD library installed, verify directory permissions

**Issue**: Email not sending  
**Solution**: Verify SMTP credentials, check firewall, enable "Less secure apps" or use App Password

**Issue**: Student ID duplicate  
**Solution**: Check course_abbreviation is set, verify database constraints

**Issue**: Course not locking  
**Solution**: Ensure course_id parameter in URL, check JavaScript not disabled

**Issue**: File upload failing  
**Solution**: Check upload directory permissions, verify file size limits

### Debug Mode

Enable error reporting in `config/config.php`:

```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

### Log Files

Check these locations:
- PHP error log: `/var/log/php_errors.log`
- Apache error log: `/var/log/apache2/error.log`
- Application log: `storage/logs/app.log`

---

## 📝 Summary

The NIELIT Bhubaneswar Registration System provides a complete, automated workflow for student registrations:

1. **Admin** creates courses with unique codes
2. **System** generates registration links and QR codes automatically
3. **Students** access via QR/link with pre-selected courses
4. **System** generates sequential student IDs (NIELIT/YYYY/ABBR/####)
5. **System** creates secure random passwords
6. **System** sends professional email notifications
7. **Students** receive credentials instantly

All components work together seamlessly to provide a modern, efficient, and user-friendly registration experience.

---

**Document Version**: 1.0  
**Last Updated**: February 11, 2026  
**System**: NIELIT Bhubaneswar Student Management  
**Status**: Production Ready ✅

