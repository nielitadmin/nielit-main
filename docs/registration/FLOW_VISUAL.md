# Registration System - Visual Flow Diagram

## Complete Registration Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    STUDENT REGISTRATION FLOW                     │
└─────────────────────────────────────────────────────────────────┘

STEP 1: Student Accesses Registration
═══════════════════════════════════════

Option A: Direct Access
┌──────────────────────────────┐
│ URL: register.php            │
│                              │
│ ✓ All fields editable        │
│ ✓ Can select any course      │
│ ✓ Normal dropdown behavior   │
└──────────────────────────────┘

Option B: From Registration Link
┌──────────────────────────────┐
│ URL: register.php?course_id=5│
│                              │
│ 🔒 Course LOCKED             │
│ 🔒 Training Center LOCKED    │
│ ✓ Blue background            │
│ ✓ Lock icon displayed        │
└──────────────────────────────┘

                ↓

STEP 2: Student Fills Form
═══════════════════════════

┌─────────────────────────────────────┐
│ LEVEL 1: Course & Personal Info    │
│ ├─ Course Selection (locked/open)  │
│ ├─ Training Center (locked/open)   │
│ ├─ Name, Father, Mother            │
│ └─ DOB, Gender, Category            │
│                                     │
│ LEVEL 2: Contact & Address         │
│ ├─ Mobile, Email                   │
│ ├─ State, City, Pincode            │
│ └─ Address                          │
│                                     │
│ LEVEL 3: Academic & Documents      │
│ ├─ Education Details (table)       │
│ ├─ Payment Details (UTR)           │
│ └─ Document Uploads                 │
└─────────────────────────────────────┘

                ↓

STEP 3: Form Submission
════════════════════════

┌─────────────────────────────────────┐
│ submit_registration.php             │
│                                     │
│ 1. Validate form data               │
│ 2. Get course details               │
│    ├─ Course name                   │
│    └─ Course abbreviation (PPI)     │
│                                     │
│ 3. Generate Student ID              │
│    ├─ Format: NIELIT/2026/PPI/0001 │
│    ├─ Check last ID for course     │
│    ├─ Increment sequence            │
│    └─ Validate uniqueness           │
│                                     │
│ 4. Generate Password                │
│    ├─ 16 random characters          │
│    ├─ Cryptographically secure      │
│    └─ Hash with bcrypt              │
│                                     │
│ 5. Insert into database             │
│    ├─ Student ID (plain)            │
│    ├─ Password (hashed)             │
│    └─ All form data                 │
│                                     │
│ 6. Send confirmation email          │
│    ├─ To: student email             │
│    ├─ Contains: ID & password       │
│    └─ Professional HTML template    │
│                                     │
│ 7. Store in session                 │
│    ├─ student_id                    │
│    ├─ student_password              │
│    ├─ student_email                 │
│    ├─ course_name                   │
│    └─ training_center               │
│                                     │
│ 8. Redirect to success page         │
└─────────────────────────────────────┘

                ↓

STEP 4: Success Page Display
═════════════════════════════

┌─────────────────────────────────────┐
│ registration_success.php            │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │   🎓 Registration Successful!   │ │
│ │   Welcome to NIELIT Bhubaneswar │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ YOUR CREDENTIALS                │ │
│ │                                 │ │
│ │ Student ID: NIELIT/2026/PPI/0001│ │
│ │             [Copy Button]       │ │
│ │                                 │ │
│ │ Password:   a1b2c3d4e5f6g7h8    │ │
│ │             [Copy Button]       │ │
│ │                                 │ │
│ │ Course:     Post Graduate...    │ │
│ │ Center:     NIELIT Bhubaneswar  │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ ✉️ Email Sent                   │ │
│ │ Confirmation sent to:           │ │
│ │ student@example.com             │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ ⚠️ Important                    │ │
│ │ Save these credentials securely │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Login to Portal] [Go to Homepage]  │
└─────────────────────────────────────┘

                ↓

STEP 5: Email Confirmation
═══════════════════════════

┌─────────────────────────────────────┐
│ Email sent to: student@example.com  │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ 🎓 Registration Successful!     │ │
│ │ NIELIT Bhubaneswar              │ │
│ ├─────────────────────────────────┤ │
│ │                                 │ │
│ │ Dear John Doe,                  │ │
│ │                                 │ │
│ │ Congratulations! Your           │ │
│ │ registration has been           │ │
│ │ successfully completed.         │ │
│ │                                 │ │
│ │ ┌─────────────────────────────┐ │ │
│ │ │ Student ID:  NIELIT/2026/   │ │ │
│ │ │              PPI/0001        │ │ │
│ │ │ Password:    a1b2c3d4e5f6   │ │ │
│ │ │ Course:      Post Graduate  │ │ │
│ │ │ Center:      NIELIT Bhub... │ │ │
│ │ └─────────────────────────────┘ │ │
│ │                                 │ │
│ │ ⚠️ Important: Save credentials  │ │
│ │                                 │ │
│ │ [Login to Student Portal]       │ │
│ │                                 │ │
│ │ Contact: admin@nielit...        │ │
│ ├─────────────────────────────────┤ │
│ │ © 2026 NIELIT Bhubaneswar      │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘

                ↓

STEP 6: Student Login
══════════════════════

┌─────────────────────────────────────┐
│ student/login.php                   │
│                                     │
│ Student ID: NIELIT/2026/PPI/0001    │
│ Password:   ****************        │
│                                     │
│ [Login]                             │
└─────────────────────────────────────┘

                ↓

STEP 7: Student Portal
══════════════════════

┌─────────────────────────────────────┐
│ Student Dashboard                   │
│                                     │
│ Welcome, John Doe!                  │
│ Student ID: NIELIT/2026/PPI/0001    │
│                                     │
│ ├─ My Profile                       │
│ ├─ My Courses                       │
│ ├─ Certificates                     │
│ └─ Settings                         │
└─────────────────────────────────────┘
```

---

## Student ID Generation Flow

```
┌─────────────────────────────────────────────────────────────┐
│              STUDENT ID GENERATION PROCESS                   │
└─────────────────────────────────────────────────────────────┘

INPUT: Course ID = 5
       ↓
┌─────────────────────────────────────┐
│ 1. Get Course Details               │
│    Query: SELECT * FROM courses     │
│           WHERE id = 5              │
│                                     │
│    Result:                          │
│    ├─ course_name: "Post Graduate"  │
│    └─ course_abbreviation: "PPI"    │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 2. Build Prefix                     │
│    Institute: NIELIT (fixed)        │
│    Year: 2026 (current year)        │
│    Course: PPI (from database)      │
│                                     │
│    Prefix: "NIELIT/2026/PPI/"       │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 3. Find Last Student ID             │
│    Query: SELECT student_id         │
│           FROM students             │
│           WHERE student_id LIKE     │
│           'NIELIT/2026/PPI/%'       │
│           ORDER BY student_id DESC  │
│           LIMIT 1                   │
│                                     │
│    Result: "NIELIT/2026/PPI/0005"   │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 4. Extract Sequence Number          │
│    Last ID: NIELIT/2026/PPI/0005    │
│    Split by '/': [NIELIT, 2026,     │
│                   PPI, 0005]        │
│    Last part: "0005"                │
│    Convert to int: 5                │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 5. Increment Sequence               │
│    Last sequence: 5                 │
│    Next sequence: 6                 │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 6. Format with Padding              │
│    sprintf("%s%04d", prefix, seq)   │
│    = "NIELIT/2026/PPI/" + "0006"    │
│    = "NIELIT/2026/PPI/0006"         │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 7. Validate Uniqueness              │
│    Check if ID already exists       │
│    If exists: Retry (max 5 times)   │
│    If unique: Return ID             │
└─────────────────────────────────────┘
       ↓
OUTPUT: "NIELIT/2026/PPI/0006"
```

---

## Password Generation Flow

```
┌─────────────────────────────────────────────────────────────┐
│              PASSWORD GENERATION PROCESS                     │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────┐
│ 1. Generate Random Bytes            │
│    random_bytes(8)                  │
│    = [0xA1, 0xB2, 0xC3, ...]        │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 2. Convert to Hexadecimal           │
│    bin2hex([0xA1, 0xB2, ...])       │
│    = "a1b2c3d4e5f6g7h8"             │
│    Length: 16 characters            │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 3. Store Plain Text (Temporary)     │
│    $password = "a1b2c3d4e5f6g7h8"   │
│    (For email and success page)     │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 4. Hash for Database                │
│    password_hash($password,         │
│                  PASSWORD_DEFAULT)  │
│    = "$2y$10$abcdef..."             │
│    (Bcrypt with cost 10)            │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 5. Store in Database                │
│    INSERT INTO students             │
│    (password) VALUES                │
│    ('$2y$10$abcdef...')             │
│    (Hashed version only)            │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 6. Send to User                     │
│    ├─ Show on success page          │
│    └─ Send in email                 │
│    (Plain text: "a1b2c3d4e5f6g7h8") │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 7. Clear from Memory                │
│    unset($password)                 │
│    (Plain text removed)             │
└─────────────────────────────────────┘
```

---

## Email Sending Flow

```
┌─────────────────────────────────────────────────────────────┐
│                EMAIL CONFIRMATION PROCESS                    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────┐
│ 1. Prepare Email Data               │
│    ├─ To: student@example.com       │
│    ├─ Name: John Doe                │
│    ├─ Student ID: NIELIT/2026/...   │
│    ├─ Password: a1b2c3d4...         │
│    ├─ Course: Post Graduate...      │
│    └─ Center: NIELIT Bhubaneswar    │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 2. Initialize PHPMailer             │
│    $mail = new PHPMailer(true);     │
│    $mail->isSMTP();                 │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 3. Configure SMTP                   │
│    Host: smtp.hostinger.com         │
│    Port: 587 (STARTTLS)             │
│    Username: admin@nielit...        │
│    Password: Nielitbbsr@2025        │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 4. Set Recipients                   │
│    From: admin@nielitbhubaneswar.in │
│    To: student@example.com          │
│    Reply-To: admin@nielit...        │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 5. Build Email Content              │
│    Subject: "Registration Success..." │
│    Body: HTML template with:        │
│    ├─ Gradient header               │
│    ├─ Credentials box               │
│    ├─ Security warning              │
│    ├─ Login button                  │
│    └─ Footer                        │
│    AltBody: Plain text version      │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 6. Send Email                       │
│    $mail->send();                   │
│    ├─ Connect to SMTP server        │
│    ├─ Authenticate                  │
│    ├─ Send message                  │
│    └─ Close connection              │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ 7. Handle Result                    │
│    Success:                         │
│    ├─ Return true                   │
│    └─ Show confirmation notice      │
│                                     │
│    Failure:                         │
│    ├─ Log error                     │
│    ├─ Return false                  │
│    └─ Show warning (email failed)   │
└─────────────────────────────────────┘
```

---

## Database Flow

```
┌─────────────────────────────────────────────────────────────┐
│                  DATABASE OPERATIONS                         │
└─────────────────────────────────────────────────────────────┘

BEFORE REGISTRATION:
┌─────────────────────────────────────┐
│ courses table                       │
│ ├─ id: 5                            │
│ ├─ course_name: "Post Graduate..."  │
│ ├─ course_abbreviation: "PPI"       │
│ └─ training_center: "NIELIT Bhub..."│
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ students table                      │
│ ├─ Last ID: NIELIT/2026/PPI/0005    │
│ └─ Count: 5 students                │
└─────────────────────────────────────┘

       ↓ REGISTRATION ↓

AFTER REGISTRATION:
┌─────────────────────────────────────┐
│ students table (NEW RECORD)         │
│ ├─ id: 6 (auto-increment)           │
│ ├─ student_id: NIELIT/2026/PPI/0006 │
│ ├─ password: $2y$10$abcdef...       │
│ ├─ name: John Doe                   │
│ ├─ email: student@example.com       │
│ ├─ course: Post Graduate...         │
│ ├─ course_id: 5                     │
│ ├─ training_center: NIELIT Bhub...  │
│ ├─ registration_date: 2026-02-11... │
│ └─ ... (other fields)               │
└─────────────────────────────────────┘
```

---

## Error Handling Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    ERROR HANDLING                            │
└─────────────────────────────────────────────────────────────┘

ERROR 1: Course Not Found
┌─────────────────────────────────────┐
│ Check: Course ID exists?            │
│ If NO:                              │
│ ├─ Set error message                │
│ ├─ Redirect to form                 │
│ └─ Display error                    │
└─────────────────────────────────────┘

ERROR 2: No Course Abbreviation
┌─────────────────────────────────────┐
│ Check: Abbreviation set?            │
│ If NO:                              │
│ ├─ Cannot generate ID               │
│ ├─ Set error message                │
│ └─ Redirect to form                 │
└─────────────────────────────────────┘

ERROR 3: Duplicate Student ID
┌─────────────────────────────────────┐
│ Check: ID already exists?           │
│ If YES:                             │
│ ├─ Retry generation (max 5)         │
│ ├─ Wait 100ms                       │
│ └─ Try again                        │
└─────────────────────────────────────┘

ERROR 4: Email Sending Failed
┌─────────────────────────────────────┐
│ Check: Email sent successfully?     │
│ If NO:                              │
│ ├─ Log error                        │
│ ├─ Continue registration            │
│ └─ Show warning (email failed)      │
└─────────────────────────────────────┘

ERROR 5: Database Insert Failed
┌─────────────────────────────────────┐
│ Check: Insert successful?           │
│ If NO:                              │
│ ├─ Rollback transaction             │
│ ├─ Set error message                │
│ └─ Redirect to form                 │
└─────────────────────────────────────┘
```

---

**Visual Guide Complete!** 🎨
