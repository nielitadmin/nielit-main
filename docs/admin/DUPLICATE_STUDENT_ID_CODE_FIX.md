# Duplicate Student ID Code Fix

## Issue Reported
When trying to edit a course in `edit_course.php`, the system showed an error:
```
Student ID Code 'SASW' is already used by 'saswat'. Please make a different one.
```

## Root Cause
The database had **duplicate Student ID Codes** that were created before the validation system was implemented:

### Duplicates Found:
1. **SASW** - Used by 2 courses:
   - ID 59: "sasw" 
   - ID 65: "saswat"

2. **DBC24** - Used by 2 courses:
   - ID 47: "Drone Boot Camp No-24"
   - ID 55: "Drone Boot Camp N0-24(gov high school)"

## Why The Validation Is Important
Student ID Codes are used to generate unique student IDs in the format:
```
NIELIT/2026/SASW/0001
NIELIT/2026/SASW/0002
```

If two courses have the same Student ID Code, it would create confusion and ID conflicts for students enrolled in different courses.

## Solution Applied
Created and ran `migrations/fix_duplicate_student_id_codes.php` which:

1. Identified all duplicate Student ID Codes in the database
2. Kept the first occurrence unchanged
3. Updated subsequent occurrences with unique codes:
   - Course ID 65 ("saswat"): Changed from `SASW` → `SASWA`
   - Course ID 55 ("Drone Boot Camp N0-24(gov high school)"): Changed from `DBC24` → `DRONE`

## How The Validation Works
The `edit_course.php` file includes duplicate prevention logic:

```php
// Check for duplicates, excluding the current course being edited
$dup_stmt = $conn->prepare("SELECT course_name, course_code, course_abbreviation
                           FROM courses
                           WHERE id != ? AND (UPPER(course_code) = ? OR UPPER(course_abbreviation) = ?)");
```

Key points:
- Uses `id != ?` to **exclude the current course** (so you can save without changing the code)
- Uses `UPPER()` for case-insensitive comparison
- Checks both course_code and course_abbreviation for uniqueness

## Verification
After the fix, no duplicates remain:
```
✓ No duplicate course codes
✓ No duplicate student ID codes
```

## Impact
- **Course "sasw" (ID 59)**: Can now be edited without the error message
- **Course "saswat" (ID 65)**: Now has unique Student ID Code "SASWA"
- **Student IDs**: Will be generated correctly without conflicts
  - sasw students: `NIELIT/2026/SASW/0001`, `NIELIT/2026/SASW/0002`, etc.
  - saswat students: `NIELIT/2026/SASWA/0001`, `NIELIT/2026/SASWA/0002`, etc.

## Future Prevention
The validation in `edit_course.php` will prevent new duplicates from being created. The system checks:
1. When **adding a new course** (dashboard.php - though this check may need to be added)
2. When **editing an existing course** (edit_course.php - already implemented)

## Files Modified
- **Database**: Updated `course_abbreviation` for courses 55 and 65
- **Migration Script Created**: `migrations/fix_duplicate_student_id_codes.php`
- **Test Scripts Created**:
  - `migrations/test_duplicate_check.php`
  - `migrations/check_all_duplicates.php`

## Status
✅ **FIXED** - All duplicate Student ID Codes have been resolved. The error will no longer appear when editing courses.
