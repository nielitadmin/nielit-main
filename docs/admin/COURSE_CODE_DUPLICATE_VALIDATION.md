# Course Code Duplicate Validation System

## Overview

The system has **built-in duplicate validation** for course codes and student ID codes to prevent conflicts.

---

## How It Works

### Validation Function
**Location**: `admin/manage_courses.php`

```php
function findDuplicateCourseFields($conn, $course_code, $student_id_code, $exclude_id = null) {
    $duplicates = [];
    
    // Check course code
    $query = "SELECT id, course_name FROM courses WHERE LOWER(TRIM(course_code)) = LOWER(TRIM(?))";
    if ($exclude_id) {
        $query .= " AND id != ?";
    }
    
    $stmt = $conn->prepare($query);
    if ($exclude_id) {
        $stmt->bind_param("si", $course_code, $exclude_id);
    } else {
        $stmt->bind_param("s", $course_code);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $duplicates['course_code'] = [
            'exists' => true,
            'course_name' => $row['course_name'],
            'course_id' => $row['id']
        ];
    }
    
    // Similar check for student_id_code...
    
    return $duplicates;
}
```

---

## Where Validation Happens

### 1. Adding New Course
**File**: `admin/manage_courses.php`

When you try to add a new course:
1. System checks if course code already exists
2. System checks if student ID code already exists
3. If duplicate found, shows error message
4. Course is **NOT created** if duplicate exists

**Error Message Example**:
```
⚠️ Duplicate course code 'BCA2024' found in course 'Bachelor of Computer Applications'
⚠️ Duplicate student ID code 'NIELIT_2024_BCA' found in course 'Bachelor of Computer Applications'
```

### 2. Editing Existing Course
**File**: `admin/edit_course.php`

When you try to edit a course:
1. System checks if new course code conflicts with OTHER courses
2. System checks if new student ID code conflicts with OTHER courses
3. Excludes the current course from duplicate check (you can keep your own codes)
4. If duplicate found in another course, shows error message
5. Course is **NOT updated** if duplicate exists

---

## Validation Features

### ✅ Case-Insensitive
- `BCA2024` = `bca2024` = `BcA2024`
- All treated as duplicates

### ✅ Whitespace Trimming
- `BCA2024` = ` BCA2024 ` = `BCA2024  `
- Leading/trailing spaces ignored

### ✅ Excludes Self (Edit Mode)
- When editing "Course A" with code "BCA2024"
- You can save it with the same code "BCA2024"
- But you cannot use a code that belongs to "Course B"

### ✅ Clear Error Messages
- Shows which course has the duplicate code
- Shows the course name and ID
- Prevents form submission

---

## Testing Scenarios

### Scenario 1: Add Course with Duplicate Code

**Steps**:
1. Go to `admin/manage_courses.php`
2. Click "Add New Course"
3. Enter course code: `BCA2024` (assuming it already exists)
4. Fill other fields
5. Click "Save"

**Expected Result**:
```
❌ Error: Duplicate course code 'BCA2024' found in course 'Bachelor of Computer Applications'
```
Course is **NOT created**.

---

### Scenario 2: Add Course with Unique Code

**Steps**:
1. Go to `admin/manage_courses.php`
2. Click "Add New Course"
3. Enter course code: `MCA2026` (unique code)
4. Fill other fields
5. Click "Save"

**Expected Result**:
```
✅ Success: Course created successfully
```
Course is **created**.

---

### Scenario 3: Edit Course - Keep Same Code

**Steps**:
1. Go to `admin/manage_courses.php`
2. Click "Edit" on a course with code `BCA2024`
3. Change course name but keep code as `BCA2024`
4. Click "Update"

**Expected Result**:
```
✅ Success: Course updated successfully
```
Course is **updated** (no duplicate error because it's the same course).

---

### Scenario 4: Edit Course - Change to Duplicate Code

**Steps**:
1. Go to `admin/manage_courses.php`
2. Click "Edit" on "Course A" (code: `BCA2024`)
3. Change code to `MCA2025` (which belongs to "Course B")
4. Click "Update"

**Expected Result**:
```
❌ Error: Duplicate course code 'MCA2025' found in course 'Master of Computer Applications'
```
Course is **NOT updated**.

---

### Scenario 5: Edit Course - Change to Unique Code

**Steps**:
1. Go to `admin/manage_courses.php`
2. Click "Edit" on a course
3. Change code to `NEWCODE2026` (unique)
4. Click "Update"

**Expected Result**:
```
✅ Success: Course updated successfully
```
Course is **updated**.

---

## What Gets Validated

### 1. Course Code
- Field: `course_code`
- Example: `BCA2024`, `MCA2025`, `PGDCA2026`
- Must be unique across all courses

### 2. Student ID Code
- Field: `student_id_code`
- Example: `NIELIT_2024_BCA`, `NIELIT_2025_MCA`
- Used to generate student IDs
- Must be unique across all courses

---

## Database Schema

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(50) UNIQUE,      -- Enforced at DB level too
    student_id_code VARCHAR(50) UNIQUE,  -- Enforced at DB level too
    course_name VARCHAR(255),
    -- other fields...
);
```

**Note**: Database also has UNIQUE constraints as a safety net.

---

## Error Handling

### Application Level (PHP)
- Checks before INSERT/UPDATE
- Shows user-friendly error messages
- Prevents form submission

### Database Level (MySQL)
- UNIQUE constraints on columns
- Throws SQL error if duplicate slips through
- Last line of defense

---

## Code Locations

### Validation Function
```
admin/manage_courses.php
└── function findDuplicateCourseFields()
```

### Add Course Validation
```
admin/manage_courses.php
└── if (isset($_POST['add_course'])) {
    └── $duplicates = findDuplicateCourseFields(...);
```

### Edit Course Validation
```
admin/edit_course.php
└── if (isset($_POST['update_course'])) {
    └── $duplicates = findDuplicateCourseFields(...);
```

---

## Summary

| Feature | Status |
|---------|--------|
| **Duplicate Detection** | ✅ Working |
| **Case-Insensitive** | ✅ Yes |
| **Whitespace Handling** | ✅ Trimmed |
| **Self-Exclusion (Edit)** | ✅ Yes |
| **Error Messages** | ✅ Clear |
| **Database Constraints** | ✅ Yes |
| **Add Course** | ✅ Validated |
| **Edit Course** | ✅ Validated |

---

## Conclusion

**You CANNOT create courses with duplicate course codes or student ID codes.**

The system will:
1. ✅ Detect duplicates (case-insensitive, trimmed)
2. ✅ Show clear error messages
3. ✅ Prevent form submission
4. ✅ Maintain data integrity

**Status**: ✅ **FULLY IMPLEMENTED AND WORKING**

---

**Date**: May 26, 2026  
**Documented By**: Kiro AI Assistant
