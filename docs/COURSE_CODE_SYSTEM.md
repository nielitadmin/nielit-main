# Course Code System Implementation Guide

## Overview
This system allows admins to manage course codes/abbreviations dynamically, which are used to generate student IDs in the format: `NIELIT/YEAR/COURSE_CODE/SEQUENCE`

## Student ID Format Examples
- `NIELIT/2025/PPI/0001` - Python Programming Internship
- `NIELIT/2025/DBC15/0001` - Drone Boot Camp 15
- `NIELIT/2025/DBC21/0001` - Drone Boot Camp 21
- `NIELIT/2025/WDB/0001` - Web Development Bootcamp

## Implementation Steps

### 1. Database Changes
Run the SQL script: `database_course_abbreviation_update.sql`

This adds:
- `course_code` column (VARCHAR 20, UNIQUE)
- `course_type` column (ENUM: Regular, Internship, Bootcamp, Workshop)
- Indexes for performance

### 2. Admin Course Management
**File:** `admin/manage_courses.php`

Features:
- ✅ Add new courses with custom codes
- ✅ Edit existing course codes
- ✅ Deactivate courses
- ✅ View all courses with their codes
- ✅ Course type categorization

**Access:** Admin Dashboard → Manage Courses

### 3. Updated Registration System
**File:** `internship_register_updated.php`

Key Changes:
```php
// OLD: Hardcoded abbreviations
$course_abbreviation = [
    'Drone Boot Camp N0-21' => 'DBC21',
    'Drone Boot Camp N0-22' => 'DBC22'
];

// NEW: Dynamic from database
$stmt_course_code = $conn->prepare("SELECT course_code FROM courses WHERE course_name = ? LIMIT 1");
$stmt_course_code->bind_param("s", $course_name);
$stmt_course_code->execute();
$result_course_code = $stmt_course_code->get_result();

if ($result_course_code->num_rows > 0) {
    $course_data = $result_course_code->fetch_assoc();
    $course_name_abbr = $course_data['course_code'];
}
```

### 4. Course Code Naming Conventions

#### Recommended Format:
- **Internships:** `[SUBJECT][I]` 
  - PPI (Python Programming Internship)
  - WDI (Web Development Internship)
  - DSI (Data Science Internship)

- **Bootcamps:** `[SUBJECT]BC[NUMBER]`
  - DBC15 (Drone Boot Camp 15)
  - DBC21 (Drone Boot Camp 21)
  - AIBC01 (AI Boot Camp 01)

- **Workshops:** `[SUBJECT]W[NUMBER]`
  - DSW01 (Data Science Workshop 01)
  - MLW01 (Machine Learning Workshop 01)

- **Regular Courses:** `[COURSE_LEVEL][SUBJECT]`
  - ADCA (Advanced Diploma in Computer Applications)
  - DCA (Diploma in Computer Applications)

#### Rules:
- Maximum 20 characters
- Use uppercase letters
- No spaces or special characters (except hyphens/underscores)
- Must be unique across all courses

## Usage Workflow

### For Admins:
1. Login to admin panel
2. Navigate to "Manage Courses"
3. Click "Add New Course"
4. Fill in:
   - Course Name: "Python Programming Internship"
   - Course Code: "PPI"
   - Course Type: "Internship"
   - Training Center: Select center
   - Duration, Fees, Description
5. Save

### For Students:
1. Visit registration page
2. Select training center
3. Select course (shows course code in parentheses)
4. Complete registration
5. Receive Student ID: `NIELIT/2025/PPI/0001`

## Student ID Generation Logic

```php
// Format: NIELIT/YEAR/COURSE_CODE/SEQUENCE
$current_year = date('Y');
$course_code = 'PPI'; // From database

// Find last student ID for this course and year
$like_pattern = "NIELIT/{$current_year}/{$course_code}/%";

// If last ID was NIELIT/2025/PPI/0005
// New ID will be NIELIT/2025/PPI/0006

// If no previous students
// New ID will be NIELIT/2025/PPI/0001
```

## Benefits

### 1. Flexibility
- Add new courses without code changes
- Update course codes easily
- Support unlimited course types

### 2. Scalability
- Automatic sequence numbering
- Year-based reset
- Course-specific sequences

### 3. Organization
- Clear course identification
- Easy filtering and reporting
- Professional ID format

### 4. Maintenance
- No hardcoded values
- Centralized management
- Audit trail

## Database Schema

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(255) NOT NULL,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_type ENUM('Regular', 'Internship', 'Bootcamp', 'Workshop') DEFAULT 'Regular',
    training_center VARCHAR(255),
    duration VARCHAR(100),
    fees DECIMAL(10,2),
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_course_code ON courses(course_code);
CREATE INDEX idx_student_id_pattern ON students(student_id);
```

## Testing Checklist

- [ ] Run database migration script
- [ ] Access admin course management page
- [ ] Add a test course with code "TEST01"
- [ ] Register a student for that course
- [ ] Verify student ID format: `NIELIT/2025/TEST01/0001`
- [ ] Register another student
- [ ] Verify sequential ID: `NIELIT/2025/TEST01/0002`
- [ ] Edit course code
- [ ] Verify new registrations use updated code
- [ ] Test with different course types
- [ ] Verify email contains correct student ID

## Troubleshooting

### Issue: Course code not found
**Solution:** Ensure course has a valid `course_code` in database

### Issue: Duplicate student IDs
**Solution:** Check database indexes and LIKE pattern matching

### Issue: Course not showing in dropdown
**Solution:** Verify course status is 'active' and has training_center set

### Issue: Student ID format incorrect
**Solution:** Check course_code doesn't contain special characters

## Migration from Old System

If you have existing students with old ID format:

```sql
-- Backup first!
CREATE TABLE students_backup AS SELECT * FROM students;

-- Update existing course codes
UPDATE courses SET course_code = 'DBC21' WHERE course_name = 'Drone Boot Camp N0-21';
UPDATE courses SET course_code = 'DBC22' WHERE course_name = 'Drone Boot Camp N0-22';

-- Existing student IDs remain unchanged
-- New registrations will use new format
```

## Future Enhancements

1. **Batch Course Import**
   - CSV upload for multiple courses
   - Bulk code assignment

2. **Course Code Validation**
   - Check for conflicts
   - Suggest available codes

3. **Analytics Dashboard**
   - Students per course code
   - Popular courses
   - Registration trends

4. **Course Code History**
   - Track code changes
   - Audit log

## Support

For issues or questions:
- Check database connection
- Verify course_code column exists
- Review error logs
- Contact system administrator

---

**Last Updated:** February 10, 2026
**Version:** 1.0
**Status:** Production Ready
