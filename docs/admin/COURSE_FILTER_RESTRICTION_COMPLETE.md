# Course Filter Restriction for Course Coordinators

## Problem
Course coordinators were able to see ALL courses in the "Filter by Course" dropdown in the Students page, which defeats the purpose of role-based access control. They should only see courses that have been assigned to them.

## Solution Implemented

### 1. **Enhanced Course Assignment Loading**
Updated the course assignment loading logic to collect both course names and course IDs:

```php
// Get assigned courses for this coordinator
if ($admin_id) {
    $course_query = "SELECT c.id, c.course_name 
                    FROM admin_course_assignments aca
                    JOIN courses c ON aca.course_id = c.id
                    WHERE aca.admin_id = ? AND aca.is_active = 1";
    // ... populate both $admin_courses and $admin_course_ids arrays
}
```

### 2. **Role-Based Course Filter Query**
Modified the course dropdown query to respect role-based access:

```php
// Fetch courses for dropdown list (filtered for course coordinators)
if ($is_course_coordinator && !empty($admin_course_ids)) {
    // Course coordinators only see their assigned courses
    $course_ids = implode(',', array_map('intval', $admin_course_ids));
    $sql_courses = "SELECT course_name FROM courses WHERE id IN ($course_ids) ORDER BY course_name";
} else {
    // Master admins see all courses
    $sql_courses = "SELECT course_name FROM courses ORDER BY course_name";
}
```

### 3. **Enhanced Dropdown Display**
Updated the dropdown to provide better user experience:

- **Master Admins**: See "All Courses" option and all available courses
- **Course Coordinators with Assignments**: See "All My Courses" option and only their assigned courses
- **Course Coordinators without Assignments**: See "No courses assigned" message

### 4. **User Experience Improvements**
- Clear labeling to distinguish between "All Courses" (master admin) and "All My Courses" (coordinator)
- Graceful handling of coordinators with no course assignments
- Maintains existing filtering functionality while restricting access

## How It Works

### For Master Admins:
1. Can see all courses in the system
2. Filter dropdown shows "All Courses" and complete course list
3. No restrictions applied

### For Course Coordinators:
1. System checks their assigned courses from `admin_course_assignments` table
2. Filter dropdown only shows their assigned courses
3. "All My Courses" option shows students from all their assigned courses
4. Cannot see or filter by courses they're not assigned to

### For Coordinators with No Assignments:
1. Dropdown shows "No courses assigned"
2. Cannot filter by any specific course
3. Will only see students from courses they have access to (if any)

## Benefits

1. **Security**: Course coordinators cannot access data from courses they're not responsible for
2. **User Experience**: Clear indication of available options based on role
3. **Consistency**: Aligns with the overall RBAC system implementation
4. **Flexibility**: Master admins retain full access while coordinators are appropriately restricted

## Testing

### Test as Master Admin:
1. Login as master admin
2. Go to Students page
3. Check "Filter by Course" dropdown
4. Should see "All Courses" and complete list of all courses

### Test as Course Coordinator with Assignments:
1. Assign courses to a coordinator using Course Assignments page
2. Login as that coordinator
3. Go to Students page
4. Check "Filter by Course" dropdown
5. Should see "All My Courses" and only assigned courses

### Test as Course Coordinator without Assignments:
1. Login as coordinator with no course assignments
2. Go to Students page
3. Check "Filter by Course" dropdown
4. Should see "No courses assigned"

## Files Modified
- `admin/students.php` - Enhanced course loading and filter dropdown logic

## Related Features
- Course Assignment Management (`admin/manage_course_assignments.php`)
- Role-Based Access Control system
- Session management with course assignments

This implementation ensures that the course filter respects the role-based access control system and provides appropriate restrictions for course coordinators while maintaining full functionality for master administrators.