# Role-Based Module Restrictions Implementation

## Overview
This document outlines the implementation of role-based access control to hide/lock specific modules for batch coordinators (course_coordinator role).

## Modules Restricted for Batch Coordinators

### 🔒 **Schemes/Projects Module**
- **Path**: `schemes_module/admin/manage_schemes.php`
- **Path**: `schemes_module/admin/edit_scheme.php`
- **Access**: **Master Admin Only**
- **Restriction**: Hidden from sidebar and direct access blocked

### 🔒 **Manage Faculty Module**
- **Path**: `admin/manage_faculty.php`
- **Access**: **Master Admin Only**
- **Restriction**: Hidden from sidebar and direct access blocked

## Role Structure

| Role | Access Level | Schemes/Projects | Manage Faculty | Batches | Students |
|------|-------------|------------------|----------------|---------|----------|
| `master_admin` | Full Access | ✅ | ✅ | ✅ | ✅ |
| `course_coordinator` | Limited | ❌ | ❌ | ✅ | ✅ |
| `nsqf_course_manager` | NSQF Only | ❌ | ❌ | ❌ | ❌ |
| `front_office_desk` | Students Only | ❌ | ❌ | ❌ | ✅ |

## Implementation Details

### 1. Sidebar Navigation (`admin/includes/sidebar.php`)
```php
<?php if ($is_master_admin): ?>
<!-- Schemes/Projects - Master Admin Only -->
<div class="nav-item">
    <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php">
        <i class="fas fa-project-diagram"></i> Schemes/Projects
    </a>
</div>
<!-- Manage Faculty - Master Admin Only -->
<div class="nav-item">
    <a href="<?php echo APP_URL; ?>/admin/manage_faculty.php">
        <i class="fas fa-chalkboard-teacher"></i> Manage Faculty
    </a>
</div>
<?php endif; ?>
```

### 2. Page-Level Access Control

#### Faculty Management (`admin/manage_faculty.php`)
```php
$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');

if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: dashboard.php');
    exit();
}
```

#### Schemes Management (`schemes_module/admin/manage_schemes.php`)
```php
$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');

if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: ../../admin/dashboard.php');
    exit();
}
```

#### Edit Scheme (`schemes_module/admin/edit_scheme.php`)
```php
$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');

if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: ../../admin/dashboard.php');
    exit();
}
```

## Security Features

### ✅ **Sidebar Hiding**
- Modules are completely hidden from the navigation menu for batch coordinators
- Clean UI without restricted options

### ✅ **Direct URL Protection**
- Attempting to access restricted URLs directly redirects to dashboard
- Prevents unauthorized access via bookmarks or direct links

### ✅ **Role Validation**
- Checks both `$_SESSION['admin_role']` and fallback `$_SESSION['role']`
- Secure role-based authentication

## User Experience

### For Batch Coordinators (course_coordinator)
- **Can Access**: Dashboard, Students, Batches, Approve Students
- **Cannot Access**: Schemes/Projects, Manage Faculty
- **Behavior**: Restricted modules are invisible in navigation
- **Direct Access**: Redirected to dashboard with no error message

### For Master Administrators
- **Can Access**: All modules including Schemes/Projects and Manage Faculty
- **Behavior**: Full navigation menu visible
- **Direct Access**: Full access to all URLs

## Testing

### Test Scenarios
1. **Login as course_coordinator**: Verify Schemes/Projects and Manage Faculty are hidden
2. **Direct URL Access**: Test accessing restricted URLs directly
3. **Role Switching**: Verify proper access when switching between roles
4. **Master Admin**: Confirm all modules remain accessible

### Test URLs
- `your-domain/admin/manage_faculty.php` (should redirect for course_coordinator)
- `your-domain/schemes_module/admin/manage_schemes.php` (should redirect for course_coordinator)
- `your-domain/schemes_module/admin/edit_scheme.php` (should redirect for course_coordinator)

## Benefits

### 🎯 **Enhanced Security**
- Prevents unauthorized access to sensitive modules
- Role-based permissions properly enforced

### 🎯 **Improved User Experience**
- Clean interface without confusing restricted options
- Users only see what they can actually use

### 🎯 **Administrative Control**
- Master admins maintain full control over faculty and schemes
- Batch coordinators focus on their core responsibilities

## Future Enhancements

### Potential Additions
- **Granular Permissions**: More specific permissions within modules
- **Custom Role Creation**: Allow creating custom roles with specific permissions
- **Audit Logging**: Track access attempts to restricted modules
- **Permission Groups**: Group permissions for easier management

## Deployment Notes

### Files Modified
- `admin/includes/sidebar.php` - Navigation restrictions
- `admin/manage_faculty.php` - Access control
- `schemes_module/admin/manage_schemes.php` - Access control
- `schemes_module/admin/edit_scheme.php` - Access control

### No Database Changes Required
- Uses existing role system
- No migration needed

## Rollback Instructions

If you need to restore previous access:

1. **Restore sidebar access**:
```php
// In admin/includes/sidebar.php, change back to:
<?php if (!$is_nsqf_manager && !$is_front_office): ?>
```

2. **Restore page access**:
```php
// In admin/manage_faculty.php, change back to:
if (!in_array($admin_role, ['master_admin', 'course_coordinator'], true)) {
```

3. **Remove schemes restrictions**:
```php
// Remove the role check from schemes_module files
```