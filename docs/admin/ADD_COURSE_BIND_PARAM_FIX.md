# Add Course bind_param Fix

## Issue
When trying to add a new course through the dashboard "Add New Course" modal, the system showed an error: "Error adding course".

## Root Cause
The `bind_param()` call in the course INSERT statement had **19 type specifiers** but only **18 variables**:

```php
// WRONG - 19 characters in type string
$stmt->bind_param("sssssssssssssssiiss", ...18 variables...)
```

Breaking down "sssssssssssssssiiss":
- 15 's' characters
- 2 'i' characters  
- 2 's' characters
- **Total: 19 characters**

But only 18 variables were being bound.

## Solution
Fixed the type definition string to have exactly **18 characters** matching the 18 variables:

```php
// CORRECT - 18 characters in type string
$stmt->bind_param("ssssssssssssssiiss", ...18 variables...)
```

Breaking down "ssssssssssssssiiss":
- 14 's' characters (strings)
- 2 'i' characters (integers: is_nsqf, link_published)
- 2 's' characters (strings: course_description, registration_token)
- **Total: 18 characters**

## Variables Being Bound (in order)
1. `$course_name` (s)
2. `$course_code` (s)
3. `$course_abbreviation` (s)
4. `$eligibility` (s)
5. `$duration` (s)
6. `$training_fees` (s)
7. `$category` (s)
8. `$start_date` (s)
9. `$end_date` (s)
10. `$description_url` (s)
11. `$description_pdf` (s)
12. `$apply_link` (s)
13. `$course_coordinator` (s)
14. `$training_center` (s)
15. `$is_nsqf` (i)
16. `$link_published` (i)
17. `$course_description` (s)
18. `$registration_token` (s)

## Files Modified
- `c:\xampp\htdocs\public_html\admin\dashboard.php` (line ~247)

## Testing
Created and ran `migrations/test_course_insert.php` which successfully inserts a test course and verifies the fix works.

## Status
✅ **FIXED** - Courses can now be added successfully through the dashboard.
