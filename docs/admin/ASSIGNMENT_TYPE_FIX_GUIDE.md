# Assignment Type Fix Guide

## Problem
The Course Assignments page shows "Manual" for all assignments, even those that should be "Auto-Assigned" when course coordinators create courses themselves.

## Root Cause
1. The `assignment_type` column was missing from the database
2. Existing auto-assignment code wasn't setting the assignment type
3. Existing records had default "Manual" type regardless of how they were created

## Solution Steps

### 1. Add Assignment Type Column
Run the migration to add the `assignment_type` column:
```
http://yourdomain.com/migrations/add_assignment_type_column.php
```

### 2. Fix Existing Assignment Types
Run the fix script to correct existing assignment types:
```
http://yourdomain.com/migrations/fix_assignment_types.php
```

### 3. Updated Auto-Assignment Logic
Fixed the auto-assignment code in both files to properly set assignment type:

**Files Updated:**
- `admin/dashboard.php` - Course creation auto-assignment
- `admin/manage_courses.php` - Course creation auto-assignment
- `admin/manage_course_assignments.php` - Manual assignment logic

**Changes Made:**
```php
// OLD (missing assignment_type)
INSERT INTO admin_course_assignments (admin_id, course_id, is_active, assigned_by) VALUES (?, ?, 1, ?)

// NEW (includes assignment_type)
INSERT INTO admin_course_assignments (admin_id, course_id, is_active, assigned_by, assignment_type) VALUES (?, ?, 1, ?, 'Auto-Assigned')
```

## Assignment Type Logic

### Auto-Assigned
- When a course coordinator creates a course themselves
- `admin_id` = `assigned_by` (self-assigned)
- Shows as "Auto-Assigned" with green badge

### Manual
- When a master admin assigns courses to coordinators
- `admin_id` ≠ `assigned_by` (assigned by someone else)
- Shows as "Manual" with blue badge

## Testing

### Test Auto-Assignment:
1. Login as course coordinator
2. Create a new course via Dashboard or Manage Courses
3. Check Course Assignments page - should show "Auto-Assigned"
4. Run the auto-assignment test to verify

### Test Manual Assignment:
1. Login as master admin
2. Use Course Assignments page to assign courses
3. Check that assignments show as "Manual"

## Expected Results

After running the fixes:

1. **Auto Assignment Test**: Should show "Auto-Assigned" for self-created courses
2. **Course Assignments Page**: Should display correct assignment types with proper badges
3. **New Course Creation**: Should automatically assign with "Auto-Assigned" type
4. **Manual Assignments**: Should create with "Manual" type

## Files Modified
- `admin/dashboard.php` - Fixed auto-assignment INSERT
- `admin/manage_courses.php` - Fixed auto-assignment INSERT  
- `admin/manage_course_assignments.php` - Added assignment_type handling
- `migrations/add_assignment_type_column.php` - Database migration
- `migrations/fix_assignment_types.php` - Data correction script

## Verification Commands
```bash
# 1. Run column migration
http://yourdomain.com/migrations/add_assignment_type_column.php

# 2. Fix existing data
http://yourdomain.com/migrations/fix_assignment_types.php

# 3. Test auto-assignment
http://yourdomain.com/tests/test_auto_course_assignment.php

# 4. Check Course Assignments page
http://yourdomain.com/admin/manage_course_assignments.php
```

The assignment types should now display correctly with proper "Auto-Assigned" vs "Manual" labels and color-coded badges.