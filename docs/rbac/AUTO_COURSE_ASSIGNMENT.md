# Auto Course Assignment for Course Coordinators

## Overview
When a course coordinator creates a new course, the system automatically assigns that course to them. This ensures course coordinators have immediate access to manage the courses they create.

## Implementation Date
March 10, 2026

## How It Works

### Automatic Assignment Flow

1. **Course Coordinator Creates Course**
   - Course coordinator fills out the "Add New Course" form
   - Submits the form to create a new course

2. **Course is Created**
   - System inserts the course into the `courses` table
   - Gets the new `course_id` from the insert operation

3. **Auto-Assignment Happens**
   - System checks if the logged-in user is a course coordinator
   - If yes, automatically creates an assignment record
   - Links the course coordinator to the newly created course

4. **Success Message**
   - User sees confirmation: "Course added successfully! Course automatically assigned to you."

### Database Operations

```sql
-- Step 1: Insert course
INSERT INTO courses (...) VALUES (...)

-- Step 2: Get course_id
$course_id = $conn->insert_id;

-- Step 3: Auto-assign to course coordinator
INSERT INTO admin_course_assignments 
(admin_id, course_id, is_active, assigned_by) 
VALUES (?, ?, 1, ?)
```

### Assignment Details

- **admin_id**: ID of the course coordinator who created the course
- **course_id**: ID of the newly created course
- **is_active**: Set to 1 (active) by default
- **assigned_by**: Set to the course coordinator's own ID (self-assigned)

## Benefits

### For Course Coordinators
- Immediate access to courses they create
- No need to wait for master admin to assign courses
- Streamlined workflow for course management
- Can start managing students right away

### For Master Admins
- Less manual assignment work
- Automatic tracking of who created which course
- Can still reassign courses if needed
- Maintains audit trail through `assigned_by` field

## User Experience

### Before (Manual Assignment)
1. Course coordinator creates course
2. Course coordinator cannot see the course in their list
3. Master admin must manually assign the course
4. Course coordinator can now access the course

### After (Auto Assignment)
1. Course coordinator creates course
2. Course is automatically assigned
3. Course coordinator can immediately manage it
4. No master admin intervention needed

## Code Implementation

### File Modified
**admin/manage_courses.php**

### Code Added
```php
// Auto-assign course to course coordinator who created it
if (isset($_SESSION['admin_role']) && 
    $_SESSION['admin_role'] === 'course_coordinator' && 
    isset($_SESSION['admin_id'])) {
    
    $admin_id = $_SESSION['admin_id'];
    $assigned_by = $_SESSION['admin_id']; // Self-assigned
    
    $assign_stmt = $conn->prepare(
        "INSERT INTO admin_course_assignments 
        (admin_id, course_id, is_active, assigned_by) 
        VALUES (?, ?, 1, ?)"
    );
    $assign_stmt->bind_param("iii", $admin_id, $course_id, $assigned_by);
    $assign_stmt->execute();
    $assign_stmt->close();
}
```

## Important Notes

### Master Admin Behavior
- Master admins are NOT auto-assigned when they create courses
- Master admins have access to ALL courses by default
- Auto-assignment only applies to course coordinators

### Assignment Status
- All auto-assignments are created with `is_active = 1`
- Master admins can deactivate assignments later if needed
- Course coordinators cannot remove their own assignments

### Audit Trail
- `assigned_by` field shows who created the assignment
- For auto-assignments, `assigned_by` = `admin_id` (self-assigned)
- Timestamp is automatically recorded in `assigned_at` field

## Testing

### Test Scenario 1: Course Coordinator Creates Course
1. Log in as course coordinator
2. Go to "Manage Courses"
3. Click "Add New Course"
4. Fill in course details
5. Submit form
6. Verify success message mentions auto-assignment
7. Check that course appears in coordinator's course list

### Test Scenario 2: Master Admin Creates Course
1. Log in as master admin
2. Create a new course
3. Verify NO auto-assignment happens
4. Master admin should still see all courses

### Test Scenario 3: Verify Database
```sql
-- Check auto-assignments
SELECT aca.*, a.username, c.course_name
FROM admin_course_assignments aca
JOIN admin a ON aca.admin_id = a.id
JOIN courses c ON aca.course_id = c.id
WHERE aca.assigned_by = aca.admin_id
ORDER BY aca.assigned_at DESC;
```

## Success Messages

### With QR Code
"Course added successfully! Registration link and QR code generated. Course automatically assigned to you."

### Without QR Code
"Course added successfully! Generate registration link to create QR code. Course automatically assigned to you."

### QR Code Failed
"Course added successfully! But QR code generation failed. Course automatically assigned to you."

## Related Features

### Course Filtering
- Course coordinators only see their assigned courses in student lists
- Implemented in `admin/students.php`
- Uses `admin_course_assignments` table for filtering

### Session Management
- Course assignments loaded into session on login
- Stored in `$_SESSION['assigned_courses']`
- Managed by `includes/session_manager.php`

### Permission Checking
- Course-specific permissions checked via `includes/check_permission.php`
- Validates course coordinator access to specific courses
- Prevents unauthorized course access

## Future Enhancements

### Potential Additions
1. **Notification System**: Email course coordinator when course is assigned
2. **Assignment History**: Track all assignment changes over time
3. **Bulk Assignment**: Allow master admin to assign multiple courses at once
4. **Assignment Transfer**: Transfer course ownership between coordinators
5. **Co-Coordinators**: Allow multiple coordinators per course

## Troubleshooting

### Issue: Course not appearing in coordinator's list
**Solution:** 
- Check if `admin_course_assignments` record was created
- Verify `is_active = 1` in the assignment
- Ensure session has been refreshed (logout/login)

### Issue: Assignment created but not working
**Solution:**
- Check `$_SESSION['assigned_courses']` array
- Verify `includes/session_manager.php` is loading assignments
- Confirm course filtering logic in relevant pages

### Issue: Master admin getting auto-assigned
**Solution:**
- Verify role check: `$_SESSION['admin_role'] === 'course_coordinator'`
- Master admins should NOT be auto-assigned
- Check if role is correctly set in session

## Database Schema Reference

### admin_course_assignments table
```sql
CREATE TABLE admin_course_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    admin_id INT(11) NOT NULL,
    course_id INT(11) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_by INT(11) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_assignment (admin_id, course_id),
    KEY idx_admin_id (admin_id),
    KEY idx_course_id (course_id),
    KEY idx_is_active (is_active),
    CONSTRAINT fk_course_admin FOREIGN KEY (admin_id) REFERENCES admin(id),
    CONSTRAINT fk_course_assigned FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_course_assigned_by FOREIGN KEY (assigned_by) REFERENCES admin(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Conclusion

The auto course assignment feature streamlines the workflow for course coordinators by automatically granting them access to courses they create. This reduces administrative overhead while maintaining proper access control and audit trails.

