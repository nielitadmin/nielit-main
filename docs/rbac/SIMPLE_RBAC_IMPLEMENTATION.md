# Simple 2-Role RBAC System Implementation

## Overview
Implemented a simplified Role-Based Access Control (RBAC) system with two roles: Master Admin and Course Coordinator.

## Implementation Date
March 6, 2026

## Roles and Permissions

### Master Admin
**Full system access including:**
- Dashboard
- Students Management
- Courses Management
- Batches Management
- Approve Students
- Reset Password
- **Add Admin** (create new admin accounts)
- **Manage Admins** (view, edit roles, delete admin accounts)
- Training Centres Management
- Themes Management
- Homepage Content Management

### Course Coordinator
**Limited access to:**
- Dashboard
- Students Management
- Courses Management
- Batches Management
- Approve Students
- Reset Password

**No access to:**
- Add Admin
- Manage Admins
- Training Centres
- Themes
- Homepage Content

## Files Modified

### 1. Database Migration
**File:** `migrations/add_simple_rbac.php`
- Adds `role` ENUM column to admin table ('master_admin', 'course_coordinator')
- Adds `created_at` and `updated_at` timestamp columns
- Sets all existing admins to 'master_admin' role by default
- Displays current admin users with their roles

**To run:** Navigate to `http://your-site.com/migrations/add_simple_rbac.php`

### 2. Session Management
**File:** `includes/session_manager.php` (already in place)
- `init_admin_session()` - Loads admin role into session after login
- `load_admin_permissions()` - Loads role-specific permissions
- Session variables set:
  - `$_SESSION['admin']` - Username
  - `$_SESSION['admin_id']` - Admin ID
  - `$_SESSION['admin_role']` - Role (master_admin or course_coordinator)
  - `$_SESSION['admin_email']` - Email

### 3. Login System
**File:** `admin/login.php`
- Already calls `init_admin_session()` after OTP verification
- Automatically loads role into session on successful login

### 4. Add Admin Page
**File:** `admin/add_admin.php`
- Added permission check: Only master_admin can access
- Added role selection dropdown in form
- Role options: Master Admin, Course Coordinator
- Role is stored when creating new admin account
- Shows role descriptions to help with selection

### 5. Manage Admins Page (NEW)
**File:** `admin/manage_admins.php`
- **Access:** Master Admin only
- **Features:**
  - View all admin accounts
  - Display statistics (total admins, master admins, coordinators)
  - Update admin roles
  - Delete admin accounts
  - Visual role badges
  - Current user highlighted
  - Cannot modify own account
  - Confirmation before deletion

### 6. Sidebar Navigation (NEW)
**File:** `admin/includes/sidebar.php`
- Reusable sidebar component with role-based menu visibility
- Automatically shows/hides menu items based on `$_SESSION['admin_role']`
- Master Admin sees all menu items
- Course Coordinator sees limited menu items
- Active page highlighting

## Usage Instructions

### Step 1: Run Migration
1. Navigate to: `http://your-site.com/migrations/add_simple_rbac.php`
2. Verify all existing admins are set to 'master_admin'
3. Check that role column was added successfully

### Step 2: Test Login
1. Log in with existing admin credentials
2. Verify role is loaded in session
3. Check that appropriate menu items are visible

### Step 3: Add New Admin (Master Admin Only)
1. Go to "Add Admin" page
2. Fill in admin details
3. Select role: Master Admin or Course Coordinator
4. Verify OTP email
5. New admin created with selected role

### Step 4: Manage Admins (Master Admin Only)
1. Go to "Manage Admins" page
2. View all admin accounts
3. Change admin roles as needed
4. Delete admin accounts if necessary

### Step 5: Test Permissions
1. Log in as Course Coordinator
2. Verify "Add Admin" and "Manage Admins" are hidden
3. Verify access to allowed pages works
4. Try accessing restricted pages (should redirect to dashboard)

## Security Features

### Access Control
- Permission checks at page level
- Redirects unauthorized users to dashboard
- Session-based role verification

### Self-Protection
- Admins cannot change their own role
- Admins cannot delete their own account
- Prevents privilege escalation

### Audit Trail
- `created_at` timestamp for all admins
- `updated_at` timestamp tracks role changes
- Session manager logs all role changes

## Database Schema

### admin table
```sql
ALTER TABLE admin 
ADD COLUMN role ENUM('master_admin', 'course_coordinator') NOT NULL DEFAULT 'master_admin' AFTER password,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

## Session Variables

```php
$_SESSION['admin']          // Username
$_SESSION['admin_id']       // Admin ID
$_SESSION['admin_role']     // Role: 'master_admin' or 'course_coordinator'
$_SESSION['admin_email']    // Email address
```

## Permission Checking Pattern

```php
// At top of restricted pages
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    $_SESSION['message'] = "Access denied. Only Master Admins can access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}
```

## Menu Visibility Pattern

```php
<?php if ($_SESSION['admin_role'] === 'master_admin'): ?>
    <!-- Master Admin only menu items -->
    <div class="nav-item">
        <a href="add_admin.php" class="nav-link">
            <i class="fas fa-user-plus"></i> Add Admin
        </a>
    </div>
<?php endif; ?>
```

## Testing Checklist

- [x] Migration script runs successfully
- [x] Existing admins set to master_admin
- [x] Login loads role into session
- [x] Master admin sees all menu items
- [x] Course coordinator sees limited menu items
- [x] Add Admin page restricted to master admin
- [x] Manage Admins page restricted to master admin
- [x] Role selection works in Add Admin form
- [x] Role updates work in Manage Admins
- [x] Admin deletion works (except own account)
- [x] Cannot change own role
- [x] Cannot delete own account

## Future Enhancements

### Potential Additions
1. **Activity Logging:** Track all admin actions
2. **Email Notifications:** Notify admins when their role changes
3. **Session Timeout:** Auto-logout after inactivity
4. **Password Policies:** Enforce strong passwords
5. **Two-Factor Authentication:** Additional security layer
6. **IP Whitelisting:** Restrict admin access by IP
7. **Bulk Role Updates:** Change multiple admin roles at once
8. **Admin Groups:** Organize admins into groups

### Not Implemented (Simplified)
- Batch Coordinator role (removed)
- Data Entry Operator role (not needed)
- Report Viewer role (not needed)
- Course-specific assignments (not needed for now)
- Batch-specific assignments (not needed for now)

## Troubleshooting

### Issue: Role not showing in session
**Solution:** Ensure migration was run and `init_admin_session()` is called after login

### Issue: Menu items not hiding
**Solution:** Check that `$_SESSION['admin_role']` is set and sidebar uses correct condition

### Issue: Access denied on all pages
**Solution:** Verify role column exists in database and has valid values

### Issue: Cannot add new admin
**Solution:** Ensure logged-in user has 'master_admin' role

## Support

For issues or questions:
1. Check error logs: `error_log()` statements in session_manager.php
2. Verify database schema matches expected structure
3. Confirm session variables are set correctly
4. Test with fresh login to reload session data

## Conclusion

The simplified 2-role RBAC system is now fully implemented and ready for use. Master Admins have full control over the system including admin management, while Course Coordinators have access to core student and course management features.
