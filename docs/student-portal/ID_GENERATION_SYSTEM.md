# 🎓 Student ID Generation System - Course Abbreviation Feature

## 📋 Overview

This feature adds a **Course Abbreviation** field to the course management system, which is used to generate unique student IDs in the format:

```
NIELIT/2026/PPI/0001
NIELIT/2026/WDB/0002
NIELIT/2026/AIML/0003
```

---

## 🎯 Student ID Format

### Structure:
```
NIELIT / YEAR / COURSE_ABBREVIATION / SEQUENCE_NUMBER
  │       │              │                    │
  │       │              │                    └─ 4-digit sequential number (0001, 0002, etc.)
  │       │              └─ Course abbreviation (PPI, WDB, AIML, etc.)
  │       └─ Current year (2026, 2027, etc.)
  └─ Institute name (NIELIT)
```

### Examples:

| Course Name | Abbreviation | Student ID Example |
|------------|--------------|-------------------|
| Python Programming Internship | PPI | NIELIT/2026/PPI/0001 |
| Web Development Bootcamp | WDB | NIELIT/2026/WDB/0001 |
| AI & Machine Learning | AIML | NIELIT/2026/AIML/0001 |
| Data Science Course | DSC | NIELIT/2026/DSC/0001 |
| Cyber Security | CS | NIELIT/2026/CS/0001 |

---

## 🗄️ Database Changes

### New Column Added:

```sql
ALTER TABLE courses 
ADD COLUMN course_abbreviation VARCHAR(10) DEFAULT NULL 
AFTER course_code
COMMENT 'Short code for student ID generation (e.g., PPI, WDB, AIML)';
```

**Field Details:**
- **Name:** `course_abbreviation`
- **Type:** VARCHAR(10)
- **Required:** Yes (when adding/editing courses)
- **Format:** Uppercase letters/numbers (e.g., PPI, WDB25, AIML)
- **Purpose:** Generate unique student IDs
- **Example Values:** PPI, WDB, AIML, DSC, CS, IOT, ML

---

## 🎨 User Interface Changes

### Add Course Modal:

#### Before:
```
┌─────────────────────────────────────────┐
│ Course Name: [____________________]     │
│ Course Code: [________]                 │
└─────────────────────────────────────────┘
```

#### After:
```
┌─────────────────────────────────────────┐
│ Course Name: [____________________]     │
│ Course Code: [________]                 │
│ Student ID Code: [____]                 │
│ For ID: NIELIT/2026/PPI/0001            │
└─────────────────────────────────────────┘
```

### Courses Table:

#### New Column Added:
```
┌────┬──────────────┬──────────┬────────────────┬──────────────────────┐
│ ID │ Course Name  │ Code     │ Student ID Code│ Sample Student ID    │
├────┼──────────────┼──────────┼────────────────┼──────────────────────┤
│ 1  │ Python Prog  │ PPI-2026 │ PPI ✅         │ NIELIT/2026/PPI/#### │
│ 2  │ Web Dev Boot │ WDB-2026 │ WDB ✅         │ NIELIT/2026/WDB/#### │
│ 3  │ AI & ML      │ AIML-26  │ AIML ✅        │ NIELIT/2026/AIML/####│
└────┴──────────────┴──────────┴────────────────┴──────────────────────┘
```

---

## 🔧 How It Works

### Admin Workflow:

```
1. Admin opens "Add New Course"
   ↓
2. Fills in course details:
   • Course Name: "Python Programming Internship"
   • Course Code: "PPI-2026"
   • Student ID Code: "PPI" ← NEW FIELD
   ↓
3. System shows preview:
   "For ID: NIELIT/2026/PPI/0001"
   ↓
4. Admin saves course
   ↓
5. Course abbreviation stored in database
```

### Student Registration:

```
1. Student registers for course via link/QR
   ↓
2. System retrieves course abbreviation (e.g., "PPI")
   ↓
3. System gets current year (e.g., 2026)
   ↓
4. System finds next sequence number (e.g., 0001)
   ↓
5. System generates student ID:
   NIELIT/2026/PPI/0001
   ↓
6. Student ID saved to database
```

---

## 📝 Form Fields

### Add Course Modal:

```html
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Course Name *</label>
        <input type="text" name="course_name" class="form-control" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Course Code *</label>
        <input type="text" name="course_code" class="form-control" 
               maxlength="20" required style="text-transform: uppercase;">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Student ID Code *</label>
        <input type="text" name="course_abbreviation" class="form-control" 
               maxlength="10" required style="text-transform: uppercase;" 
               placeholder="PPI">
        <small class="text-muted">
            For ID: NIELIT/2026/<strong>PPI</strong>/0001
        </small>
    </div>
</div>
```

### Edit Course Modal:

```html
<div class="col-md-3 mb-3">
    <label class="form-label">Student ID Code *</label>
    <input type="text" name="course_abbreviation" 
           id="edit_course_abbreviation" class="form-control" 
           maxlength="10" required style="text-transform: uppercase;">
    <small class="text-muted">
        For ID: NIELIT/2026/<strong id="edit_abbr_preview">XXX</strong>/0001
    </small>
</div>
```

---

## 💻 Code Implementation

### PHP - Add Course:

```php
if ($action === 'add') {
    $course_name = $_POST['course_name'];
    $course_code = strtoupper($_POST['course_code']);
    $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
    // ... other fields
    
    $stmt = $conn->prepare("INSERT INTO courses 
        (course_name, course_code, course_abbreviation, ...) 
        VALUES (?, ?, ?, ...)");
    $stmt->bind_param("sss...", $course_name, $course_code, 
                      $course_abbreviation, ...);
    $stmt->execute();
}
```

### PHP - Edit Course:

```php
if ($action === 'edit') {
    $id = $_POST['course_id'];
    $course_name = $_POST['course_name'];
    $course_code = strtoupper($_POST['course_code']);
    $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
    // ... other fields
    
    $stmt = $conn->prepare("UPDATE courses 
        SET course_name=?, course_code=?, course_abbreviation=?, ... 
        WHERE id=?");
    $stmt->bind_param("ssss...i", $course_name, $course_code, 
                      $course_abbreviation, ..., $id);
    $stmt->execute();
}
```

### JavaScript - Edit Course Function:

```javascript
function editCourse(course) {
    document.getElementById('edit_course_id').value = course.id;
    document.getElementById('edit_course_name').value = course.course_name;
    document.getElementById('edit_course_code').value = course.course_code;
    document.getElementById('edit_course_abbreviation').value = 
        course.course_abbreviation || '';
    
    // Update preview
    if (course.course_abbreviation) {
        document.getElementById('edit_abbr_preview').textContent = 
            course.course_abbreviation.toUpperCase();
    }
    
    // ... rest of the function
}
```

### JavaScript - Live Preview:

```javascript
// Update abbreviation preview as user types
document.getElementById('edit_course_abbreviation')
    .addEventListener('input', function() {
        const abbr = this.value.toUpperCase() || 'XXX';
        document.getElementById('edit_abbr_preview').textContent = abbr;
    });
```

---

## 🎯 Student ID Generation Logic

### Function to Generate Next Student ID:

```php
function generateStudentID($course_id, $conn) {
    // Get course abbreviation
    $stmt = $conn->prepare("SELECT course_abbreviation FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    
    if (!$course || empty($course['course_abbreviation'])) {
        return null; // No abbreviation set
    }
    
    $abbreviation = strtoupper($course['course_abbreviation']);
    $year = date('Y'); // Current year (e.g., 2026)
    
    // Get the last student ID for this course and year
    $prefix = "NIELIT/{$year}/{$abbreviation}/";
    $stmt = $conn->prepare("
        SELECT student_id FROM students 
        WHERE student_id LIKE ? 
        ORDER BY student_id DESC 
        LIMIT 1
    ");
    $search_pattern = $prefix . '%';
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_student = $result->fetch_assoc();
        $last_id = $last_student['student_id'];
        
        // Extract sequence number
        $parts = explode('/', $last_id);
        $last_sequence = intval($parts[3]);
        $next_sequence = $last_sequence + 1;
    } else {
        // First student for this course/year
        $next_sequence = 1;
    }
    
    // Format: NIELIT/2026/PPI/0001
    $student_id = sprintf("%s%04d", $prefix, $next_sequence);
    
    return $student_id;
}

// Usage:
$student_id = generateStudentID($course_id, $conn);
// Result: NIELIT/2026/PPI/0001
```

---

## 📊 Database Queries

### Get Course Abbreviation:

```sql
SELECT course_abbreviation 
FROM courses 
WHERE id = 5;
```

### Find Next Sequence Number:

```sql
SELECT student_id 
FROM students 
WHERE student_id LIKE 'NIELIT/2026/PPI/%' 
ORDER BY student_id DESC 
LIMIT 1;
```

### Insert Student with Generated ID:

```sql
INSERT INTO students (student_id, course_id, name, ...) 
VALUES ('NIELIT/2026/PPI/0001', 5, 'John Doe', ...);
```

### Count Students per Course:

```sql
SELECT 
    c.course_name,
    c.course_abbreviation,
    COUNT(s.id) as student_count
FROM courses c
LEFT JOIN students s ON s.course_id = c.id
GROUP BY c.id
ORDER BY student_count DESC;
```

---

## ✅ Validation Rules

### Course Abbreviation:

1. **Required:** Must be provided when adding/editing course
2. **Format:** Uppercase letters and numbers only
3. **Length:** Maximum 10 characters
4. **Unique:** Should be unique per course (recommended)
5. **Examples:** PPI, WDB, AIML, DSC25, IOT2026

### Student ID:

1. **Format:** NIELIT/YYYY/ABBR/####
2. **Year:** Current year (4 digits)
3. **Abbreviation:** From course table
4. **Sequence:** 4-digit number (0001-9999)
5. **Unique:** Must be unique across all students

---

## 🧪 Testing Checklist

### Database Testing:

- [ ] `course_abbreviation` column added successfully
- [ ] Index created on `course_abbreviation`
- [ ] Existing courses updated with abbreviations
- [ ] Can insert new course with abbreviation
- [ ] Can update course abbreviation

### Admin Panel Testing:

- [ ] "Student ID Code" field appears in Add modal
- [ ] "Student ID Code" field appears in Edit modal
- [ ] Field converts input to uppercase
- [ ] Preview shows correct format (NIELIT/2026/XXX/0001)
- [ ] Preview updates as user types (Edit modal)
- [ ] Can save course with abbreviation
- [ ] Abbreviation displays in courses table
- [ ] Sample student ID shows in table

### Student Registration Testing:

- [ ] Student ID generates correctly on registration
- [ ] Format matches: NIELIT/YYYY/ABBR/####
- [ ] Sequence numbers increment correctly
- [ ] Different courses have different abbreviations
- [ ] Same course students have same abbreviation
- [ ] Year updates automatically

---

## 📋 Examples

### Example 1: Python Programming Internship

```
Course Details:
- Name: Python Programming Internship
- Code: PPI-2026
- Abbreviation: PPI

Generated Student IDs:
- NIELIT/2026/PPI/0001 (First student)
- NIELIT/2026/PPI/0002 (Second student)
- NIELIT/2026/PPI/0003 (Third student)
```

### Example 2: Web Development Bootcamp

```
Course Details:
- Name: Web Development Bootcamp
- Code: WDB-2026
- Abbreviation: WDB

Generated Student IDs:
- NIELIT/2026/WDB/0001
- NIELIT/2026/WDB/0002
- NIELIT/2026/WDB/0003
```

### Example 3: Multiple Years

```
2026 Batch:
- NIELIT/2026/PPI/0001
- NIELIT/2026/PPI/0002

2027 Batch:
- NIELIT/2027/PPI/0001 (Sequence resets for new year)
- NIELIT/2027/PPI/0002
```

---

## 🚀 Deployment Steps

### Step 1: Update Database

```bash
mysql -u root -p nielit_bhubaneswar < database_add_course_abbreviation.sql
```

Or in phpMyAdmin:
```sql
ALTER TABLE courses 
ADD COLUMN course_abbreviation VARCHAR(10) DEFAULT NULL 
AFTER course_code;

CREATE INDEX idx_course_abbreviation ON courses(course_abbreviation);
```

### Step 2: Verify Files

- ✅ `admin/manage_courses.php` (updated)
- ✅ `database_add_course_abbreviation.sql` (created)

### Step 3: Update Existing Courses

Manually set abbreviations for existing courses:

```sql
UPDATE courses SET course_abbreviation = 'PPI' WHERE id = 1;
UPDATE courses SET course_abbreviation = 'WDB' WHERE id = 2;
UPDATE courses SET course_abbreviation = 'AIML' WHERE id = 3;
```

### Step 4: Test

1. Login to admin panel
2. Add new course with abbreviation
3. Verify abbreviation shows in table
4. Edit course and change abbreviation
5. Register test student
6. Verify student ID format

---

## 🎓 Best Practices

### Choosing Abbreviations:

1. **Keep it short:** 2-5 characters ideal (PPI, WDB, AIML)
2. **Make it memorable:** Easy to recognize and type
3. **Be consistent:** Use similar patterns across courses
4. **Avoid confusion:** Don't use similar abbreviations (PPI vs PIP)
5. **Document it:** Keep a list of all abbreviations used

### Recommended Abbreviations:

| Course Type | Abbreviation Pattern | Examples |
|------------|---------------------|----------|
| Programming | Language initials | PY, JAVA, CPP |
| Bootcamps | Topic + BC | WDB, DSB, AIB |
| Internships | Topic + I | PPI, WDI, DSI |
| Workshops | Topic + W | IOW, MLW, CSW |
| Certifications | Topic + C | DBC, FEC, BEC |

---

## 🔍 Troubleshooting

### Issue: Abbreviation not saving

**Solution:**
```sql
-- Check if column exists
SHOW COLUMNS FROM courses LIKE 'course_abbreviation';

-- Manually add if missing
ALTER TABLE courses ADD COLUMN course_abbreviation VARCHAR(10);
```

### Issue: Student ID not generating

**Solution:**
1. Verify course has abbreviation set
2. Check student ID generation function
3. Verify database connection
4. Check for SQL errors in logs

### Issue: Duplicate student IDs

**Solution:**
```sql
-- Add unique constraint
ALTER TABLE students ADD UNIQUE KEY unique_student_id (student_id);

-- Find duplicates
SELECT student_id, COUNT(*) as count 
FROM students 
GROUP BY student_id 
HAVING count > 1;
```

---

## 📊 Statistics & Reports

### Students per Course:

```sql
SELECT 
    c.course_name,
    c.course_abbreviation,
    COUNT(s.id) as total_students,
    MIN(s.student_id) as first_student_id,
    MAX(s.student_id) as last_student_id
FROM courses c
LEFT JOIN students s ON s.course_id = c.id
GROUP BY c.id
ORDER BY total_students DESC;
```

### Year-wise Distribution:

```sql
SELECT 
    SUBSTRING_INDEX(SUBSTRING_INDEX(student_id, '/', 2), '/', -1) as year,
    COUNT(*) as student_count
FROM students
GROUP BY year
ORDER BY year DESC;
```

---

## ✅ Success Criteria

Implementation is successful if:

- [x] `course_abbreviation` column added to database
- [x] "Student ID Code" field appears in Add/Edit modals
- [x] Field converts input to uppercase automatically
- [x] Preview shows correct student ID format
- [x] Abbreviation saves to database
- [x] Abbreviation displays in courses table
- [x] Sample student ID shows in table
- [x] No PHP or JavaScript errors

---

**Status:** ✅ COMPLETE
**Version:** 3.0.0
**Date:** February 11, 2026
**Feature:** Student ID Generation with Course Abbreviation

