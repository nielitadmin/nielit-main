# Design Document: Role-Based Access Control System

## Overview

This document outlines the design for implementing a hierarchical Role-Based Access Control (RBAC) system for the NIELIT Bhubaneswar admin portal. The system will manage five distinct administrative roles with varying privilege levels, ensuring secure and appropriate access to system resources based on user responsibilities.

The RBAC system will be implemented as a middleware layer that integrates with the existing PHP/MySQL architecture, providing permission checks at both the server-side (PHP) and presentation layer (UI visibility). The design emphasizes backward compatibility, security through audit logging, and seamless integration with the current session management system.

**Important Note on Role Hierarchy**: Course Coordinator and Batch Coordinator are at the SAME privilege level (Level 3). They have equal authority but different scopes of responsibility - Course Coordinators manage course-specific operations while Batch Coordinators manage batch-specific operations. Both roles can manage students, generate reports, and perform administrative tasks within their assigned scope.

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Admin Portal Layer                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Dashboard   │  │   Students   │  │   Courses    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              Access Control Middleware Layer                 │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Permission Checker (check_permission.php)           │  │
│  │  - Page-level authorization                          │  │
│  │  - Action-level authorization                        │  │
│  │  - Resource-level authorization                      │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  UI Visibility Controller (rbac_helpers.php)         │  │
│  │  - Menu item filtering                               │  │
│  │  - Button visibility control                         │  │
│  │  - Widget display control                            │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Audit Logger (audit_logger.php)                     │  │
│  │  - Action logging                                    │  │
│  │  - Timestamp tracking                                │  │
│  │  - IP address capture                                │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Session Management Layer                   │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Session Variables:                                   │  │
│  │  - $_SESSION['admin']          (username)            │  │
│  │  - $_SESSION['admin_role']     (role)                │  │
│  │  - $_SESSION['admin_id']       (user ID)             │  │
│  │  - $_SESSION['assigned_courses'] (array)             │  │
│  │  - $_SESSION['assigned_batches'] (array)             │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Database Layer                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │    admin     │  │  admin_course│  │  admin_batch │     │
│  │    table     │  │  _assignments│  │  _assignments│     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│  ┌──────────────┐                                           │
│  │  audit_log   │                                           │
│  │    table     │                                           │
│  └──────────────┘                                           │
└─────────────────────────────────────────────────────────────┘
```

### Role Hierarchy

```
Master Admin (Level 4)
    │
    ├── Full system access
    ├── User management
    └── System configuration
    
Course Coordinator (Level 3) ◄─── EQUAL PRIVILEGE ───► Batch Coordinator (Level 3)
    │                                                        │
    ├── Course-specific access                              ├── Batch-specific access
    ├── Student management (assigned courses)               ├── Student management (assigned batches)
    ├── Batch management (assigned courses)                 ├── Admission order generation
    └── Report generation (assigned courses)                └── Student enrollment approval
    
Data Entry Operator (Level 2)
    │
    ├── Student record creation
    ├── Student record editing
    └── Read-only access to lists
    
Report Viewer (Level 1)
    │
    ├── Read-only access to all data
    ├── Report viewing
    └── Data export
```

## Components and Interfaces

### 1. Permission Checker Module (`includes/check_permission.php`)

**Purpose**: Centralized permission validation for all admin portal pages and actions.

**Interface**:
```php
/**
 * Check if current admin has permission for a specific action
 * @param string $action - Action identifier (e.g., 'view_students', 'edit_course')
 * @param array $context - Additional context (e.g., course_id, batch_id)
 * @return bool - True if permitted, false otherwise
 */
function has_permission($action, $context = [])

/**
 * Require permission or redirect to access denied page
 * @param string $action - Action identifier
 * @param array $context - Additional context
 * @return void - Redirects if permission denied
 */
function require_permission($action, $context = [])

/**
 * Get current admin's role
 * @return string - Role name (master_admin, course_coordinator, etc.)
 */
function get_admin_role()

/**
 * Check if admin has access to specific course
 * @param int $course_id - Course ID
 * @return bool - True if has access
 */
function has_course_access($course_id)

/**
 * Check if admin has access to specific batch
 * @param int $batch_id - Batch ID
 * @return bool - True if has access
 */
function has_batch_access($batch_id)
```

**Permission Matrix**:
```php
$permission_matrix = [
    'master_admin' => ['*'], // All permissions
    
    // Course Coordinator and Batch Coordinator have EQUAL privileges
    // Both can manage students, view reports, and perform their specific operations
    'course_coordinator' => [
        'view_students', 'edit_students', 'add_students',
        'view_courses', 'edit_courses',
        'view_batches', 'edit_batches', 'add_batches',
        'generate_reports', 'view_reports',
        'manage_course_students', 'manage_course_batches'
    ],
    'batch_coordinator' => [
        'view_students', 'edit_students', 'add_students',
        'view_batches', 'edit_batches',
        'generate_admission_orders', 'approve_students',
        'view_reports', 'generate_reports',
        'manage_batch_students'
    ],
    
    'data_entry_operator' => [
        'view_students', 'edit_students', 'add_students'
    ],
    'report_viewer' => [
        'view_students', 'view_courses', 'view_batches',
        'view_reports', 'export_reports'
    ]
];
```

### 2. UI Visibility Controller (`includes/rbac_helpers.php`)

**Purpose**: Control visibility of UI elements based on user permissions.

**Interface**:
```php
/**
 * Check if menu item should be displayed
 * @param string $menu_item - Menu identifier
 * @return bool - True if should display
 */
function should_display_menu($menu_item)

/**
 * Check if action button should be displayed
 * @param string $action - Action identifier
 * @param array $context - Additional context
 * @return bool - True if should display
 */
function should_display_button($action, $context = [])

/**
 * Get filtered navigation menu for current user
 * @return array - Array of menu items
 */
function get_user_menu()

/**
 * Render action button if user has permission
 * @param string $action - Action identifier
 * @param string $label - Button label
 * @param string $url - Button URL
 * @param array $context - Additional context
 * @return string - HTML button or empty string
 */
function render_action_button($action, $label, $url, $context = [])
```

### 3. Audit Logger (`includes/audit_logger.php`)

**Purpose**: Log all administrative actions for security and compliance.

**Interface**:
```php
/**
 * Log an administrative action
 * @param string $action_type - Type of action (create, update, delete, view)
 * @param string $resource_type - Type of resource (student, course, batch, admin)
 * @param int $resource_id - ID of affected resource
 * @param string $details - Additional details (JSON encoded)
 * @return bool - True if logged successfully
 */
function log_admin_action($action_type, $resource_type, $resource_id, $details = '')

/**
 * Get audit log entries with filters
 * @param array $filters - Filter criteria (date_from, date_to, admin_id, action_type)
 * @param int $limit - Number of entries to return
 * @param int $offset - Offset for pagination
 * @return array - Array of log entries
 */
function get_audit_log($filters = [], $limit = 50, $offset = 0)
```

### 4. Session Manager Extension (`includes/session_manager.php`)

**Purpose**: Extend existing session management to include RBAC data.

**Interface**:
```php
/**
 * Initialize admin session with RBAC data
 * @param string $username - Admin username
 * @return bool - True if initialized successfully
 */
function init_admin_session($username)

/**
 * Load admin role and assignments into session
 * @param int $admin_id - Admin ID
 * @return void
 */
function load_admin_permissions($admin_id)

/**
 * Invalidate admin session (for role changes)
 * @param int $admin_id - Admin ID
 * @return void
 */
function invalidate_admin_session($admin_id)

/**
 * Refresh session permissions
 * @return void
 */
function refresh_session_permissions()
```

### 5. Access Denied Handler (`admin/access_denied.php`)

**Purpose**: Display user-friendly access denied page.

**Features**:
- Clear explanation of access denial
- Link to dashboard
- Contact information for access requests
- Logged attempt for security monitoring

## Data Models

### 1. Admin Table (Modified)

```sql
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('master_admin', 'course_coordinator', 'batch_coordinator', 'data_entry_operator', 'report_viewer') NOT NULL DEFAULT 'master_admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `username`: Unique username
- `password`: Hashed password
- `phone`: Contact phone number
- `email`: Unique email address
- `role`: User role (enum for data integrity)
- `created_at`: Account creation timestamp
- `updated_at`: Last modification timestamp
- `is_active`: Account status flag

### 2. Admin Course Assignments Table (New)

```sql
CREATE TABLE `admin_course_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`admin_id`, `course_id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_course` (`course_id`),
  CONSTRAINT `fk_course_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_course_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `admin_id`: Reference to admin table
- `course_id`: Reference to courses table
- `assigned_at`: Assignment timestamp
- `assigned_by`: Admin who made the assignment

### 3. Admin Batch Assignments Table (New)

```sql
CREATE TABLE `admin_batch_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`admin_id`, `batch_id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_batch` (`batch_id`),
  CONSTRAINT `fk_batch_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batch_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `admin_id`: Reference to admin table
- `batch_id`: Reference to batches table
- `assigned_at`: Assignment timestamp
- `assigned_by`: Admin who made the assignment

### 4. Audit Log Table (New)

```sql
CREATE TABLE `audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `admin_username` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `action_type` enum('create', 'update', 'delete', 'view', 'export', 'login', 'logout') NOT NULL,
  `resource_type` varchar(50) NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_resource` (`resource_type`, `resource_id`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key (bigint for large volume)
- `admin_id`: Reference to admin table
- `admin_username`: Username snapshot (for historical records)
- `role`: Role snapshot at time of action
- `action_type`: Type of action performed
- `resource_type`: Type of resource affected
- `resource_id`: ID of affected resource
- `details`: JSON-encoded additional details
- `ip_address`: IP address of admin
- `user_agent`: Browser/client information
- `timestamp`: Action timestamp

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Role Assignment Enforcement
*For any* admin user, when they are assigned a role, the system should store exactly one role value from the predefined set (master_admin, course_coordinator, batch_coordinator, data_entry_operator, report_viewer) and reject any invalid role values.
**Validates: Requirements 1.1, 1.2**

### Property 2: Permission Inheritance
*For any* admin user with master_admin role, all permission checks should return true regardless of the specific permission being checked.
**Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6**

### Property 3: Course Assignment Constraint
*For any* admin user with course_coordinator role, attempting to access a course not in their assigned courses list should be denied.
**Validates: Requirements 3.2, 3.8**

### Property 4: Batch Assignment Constraint
*For any* admin user with batch_coordinator role, attempting to access a batch not in their assigned batches list should be denied.
**Validates: Requirements 4.2, 4.7**

### Property 5: Data Entry Operator Restrictions
*For any* admin user with data_entry_operator role, attempting to delete a student record, manage courses, manage batches, or access reports should be denied.
**Validates: Requirements 5.4, 5.5, 5.6, 5.7**

### Property 6: Report Viewer Read-Only Enforcement
*For any* admin user with report_viewer role, attempting any create, update, or delete operation should be denied while view and export operations should be permitted.
**Validates: Requirements 6.4, 6.5, 6.6**

### Property 7: Page Access Control
*For any* admin user attempting to access a page, if their role does not have permission for that page, the system should redirect them to an access denied page before any page content is rendered.
**Validates: Requirements 7.1, 7.2, 7.3**

### Property 8: AJAX Request Authorization
*For any* AJAX request or API endpoint call, the system should validate the admin's role permissions before processing the request and return an authorization error if permission is denied.
**Validates: Requirements 7.4, 7.6**

### Property 9: Role Change Session Invalidation
*For any* admin user whose role is changed, their current session should be invalidated, forcing them to log in again with the new role permissions applied.
**Validates: Requirements 7.5, 11.5**

### Property 10: UI Element Visibility
*For any* admin user viewing a page, only menu items and action buttons for which they have permission should be rendered in the HTML output.
**Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.5**

### Property 11: Audit Log Completeness
*For any* create, update, or delete action performed by an admin user, an audit log entry should be created containing the admin's ID, username, role, action type, resource type, resource ID, timestamp, and IP address.
**Validates: Requirements 9.1, 9.2**

### Property 12: Audit Log Access Control
*For any* admin user attempting to access the audit log, access should be granted only if their role is master_admin, otherwise access should be denied.
**Validates: Requirements 9.3, 9.5**

### Property 13: Session Role Persistence
*For any* admin user who successfully logs in, their role and assigned courses/batches should be loaded into the session and remain accessible throughout the session lifetime.
**Validates: Requirements 11.1, 11.2**

### Property 14: Backward Compatibility
*For any* existing admin user in the database when the RBAC system is deployed, they should be automatically assigned the master_admin role and retain full system access.
**Validates: Requirements 12.1, 12.3**

### Property 15: Assignment Uniqueness
*For any* course coordinator, the same course should not be assigned to them more than once, and for any batch coordinator, the same batch should not be assigned to them more than once.
**Validates: Requirements 3.1, 4.1**

## Error Handling

### 1. Permission Denied Scenarios

**Scenario**: User attempts to access unauthorized page
- **Response**: Redirect to `access_denied.php` with reason code
- **Logging**: Log attempt in audit log
- **User Message**: "You do not have permission to access this page. Contact your administrator if you believe this is an error."

**Scenario**: User attempts unauthorized action via AJAX
- **Response**: JSON response with `{"success": false, "error": "Permission denied", "code": 403}`
- **Logging**: Log attempt in audit log
- **User Message**: Display toast notification with permission error

### 2. Session Expiration

**Scenario**: User's session expires during activity
- **Response**: Redirect to login page with return URL
- **Logging**: Log session expiration
- **User Message**: "Your session has expired. Please log in again."

### 3. Role Change During Active Session

**Scenario**: Admin's role is changed while they are logged in
- **Response**: Invalidate session, force re-login on next request
- **Logging**: Log role change and session invalidation
- **User Message**: "Your account permissions have been updated. Please log in again."

### 4. Database Errors

**Scenario**: Database query fails during permission check
- **Response**: Fail closed (deny access)
- **Logging**: Log error with stack trace
- **User Message**: "A system error occurred. Please try again or contact support."

### 5. Missing Assignment Data

**Scenario**: Course/Batch Coordinator has no assignments
- **Response**: Allow login but show empty state with instructions
- **Logging**: Log login with no assignments
- **User Message**: "You have no courses/batches assigned. Contact your administrator to request assignments."

### 6. Audit Log Failures

**Scenario**: Audit log write fails
- **Response**: Continue with operation but log error
- **Logging**: Log to error log file
- **User Message**: No user-facing message (silent failure for audit)
- **Fallback**: Implement retry mechanism with queue

## Testing Strategy

### Unit Testing

**Framework**: PHPUnit

**Test Coverage**:
1. Permission checker functions
   - Test each role's permission matrix
   - Test edge cases (null values, invalid roles)
   - Test context-based permissions (course/batch access)

2. UI visibility functions
   - Test menu filtering for each role
   - Test button visibility logic
   - Test widget display rules

3. Audit logger functions
   - Test log entry creation
   - Test log retrieval with filters
   - Test data sanitization

4. Session management functions
   - Test session initialization
   - Test permission loading
   - Test session invalidation

**Example Unit Tests**:
```php
// Test master admin has all permissions
public function test_master_admin_has_all_permissions() {
    $_SESSION['admin_role'] = 'master_admin';
    $this->assertTrue(has_permission('any_action'));
}

// Test course coordinator cannot access unassigned course
public function test_course_coordinator_course_access() {
    $_SESSION['admin_role'] = 'course_coordinator';
    $_SESSION['assigned_courses'] = [1, 2, 3];
    $this->assertFalse(has_course_access(99));
}

// Test audit log entry creation
public function test_audit_log_creation() {
    $result = log_admin_action('create', 'student', 123, '{"name": "Test"}');
    $this->assertTrue($result);
    // Verify entry exists in database
}
```

### Property-Based Testing

**Framework**: Rapid (PHP property-based testing library)

**Configuration**: Minimum 100 iterations per property test

**Property Tests**:

Each property test will be tagged with: **Feature: role-based-access-control, Property {number}: {property_text}**

1. **Property 1 Test**: Generate random role values, verify only valid roles are accepted
2. **Property 2 Test**: Generate random permission requests for master_admin, verify all return true
3. **Property 3 Test**: Generate random course IDs and assignments, verify access control
4. **Property 4 Test**: Generate random batch IDs and assignments, verify access control
5. **Property 5 Test**: Generate random actions for data_entry_operator, verify restrictions
6. **Property 6 Test**: Generate random operations for report_viewer, verify read-only enforcement
7. **Property 7 Test**: Generate random page access attempts, verify redirection
8. **Property 8 Test**: Generate random AJAX requests, verify authorization
9. **Property 9 Test**: Simulate role changes, verify session invalidation
10. **Property 10 Test**: Generate random UI states, verify element visibility
11. **Property 11 Test**: Generate random admin actions, verify audit log entries
12. **Property 12 Test**: Generate random audit log access attempts, verify access control
13. **Property 13 Test**: Generate random login scenarios, verify session data
14. **Property 14 Test**: Simulate deployment with existing admins, verify role assignment
15. **Property 15 Test**: Generate random assignment attempts, verify uniqueness

### Integration Testing

**Test Scenarios**:

1. **End-to-End Role Assignment Flow**
   - Master admin creates new admin with specific role
   - New admin logs in
   - Verify correct permissions are applied
   - Verify UI elements match role

2. **Course Coordinator Workflow**
   - Assign courses to coordinator
   - Coordinator logs in
   - Verify can access assigned courses
   - Verify cannot access unassigned courses
   - Verify can manage students in assigned courses

3. **Batch Coordinator Workflow**
   - Assign batches to coordinator
   - Coordinator logs in
   - Verify can access assigned batches
   - Verify can generate admission orders
   - Verify cannot access unassigned batches

4. **Audit Log Verification**
   - Perform various admin actions
   - Verify all actions are logged
   - Master admin views audit log
   - Verify log entries are complete and accurate

5. **Session Invalidation Flow**
   - Admin logs in with role A
   - Master admin changes their role to B
   - Admin attempts next action
   - Verify session is invalidated
   - Admin logs in again
   - Verify new role permissions are active

### Manual Testing Checklist

1. **UI Verification**
   - [ ] Login as each role
   - [ ] Verify navigation menu shows correct items
   - [ ] Verify action buttons are visible/hidden correctly
   - [ ] Verify dashboard widgets match role

2. **Permission Enforcement**
   - [ ] Attempt unauthorized page access (should redirect)
   - [ ] Attempt unauthorized action (should fail)
   - [ ] Verify error messages are user-friendly

3. **Assignment Management**
   - [ ] Assign courses to coordinator
   - [ ] Assign batches to coordinator
   - [ ] Verify assignments persist after logout/login
   - [ ] Remove assignments and verify access is revoked

4. **Audit Log**
   - [ ] Perform various actions
   - [ ] View audit log as master admin
   - [ ] Verify all actions are logged
   - [ ] Test audit log filtering
   - [ ] Attempt to access audit log as non-master admin (should fail)

5. **Backward Compatibility**
   - [ ] Deploy RBAC system
   - [ ] Verify existing admins can still log in
   - [ ] Verify existing admins have master_admin role
   - [ ] Verify no functionality is broken

## Security Considerations

### 1. Fail-Closed Principle
- All permission checks default to deny
- Database errors result in access denial
- Missing session data results in access denial

### 2. Defense in Depth
- Server-side permission checks (primary)
- UI element hiding (secondary, convenience)
- AJAX request validation (tertiary)
- Audit logging (monitoring)

### 3. SQL Injection Prevention
- Use prepared statements for all database queries
- Parameterize all user inputs
- Validate and sanitize all inputs

### 4. Session Security
- Use secure session configuration
- Regenerate session ID on role change
- Implement session timeout
- Store minimal data in session

### 5. Audit Log Integrity
- Use bigint for ID to prevent overflow
- Implement log rotation policy
- Prevent log tampering (append-only)
- Regular backup of audit logs

### 6. Role Enumeration Protection
- Don't expose role information in error messages
- Use generic "access denied" messages
- Log enumeration attempts

## Performance Considerations

### 1. Permission Check Optimization
- Cache permission matrix in memory
- Use session variables for frequently checked permissions
- Minimize database queries per request

### 2. Assignment Loading
- Load assignments once during login
- Store in session for quick access
- Refresh only when assignments change

### 3. Audit Log Performance
- Use asynchronous logging where possible
- Implement batch inserts for high-volume actions
- Index audit log table appropriately
- Implement log archival strategy

### 4. Database Indexing
- Index role column in admin table
- Index admin_id in assignment tables
- Index timestamp in audit log
- Composite indexes for common queries

## Deployment Strategy

### Phase 1: Database Migration
1. Add role column to admin table with default 'master_admin'
2. Create admin_course_assignments table
3. Create admin_batch_assignments table
4. Create audit_log table
5. Add indexes
6. Verify all existing admins have master_admin role

### Phase 2: Core Implementation
1. Implement permission checker module
2. Implement audit logger
3. Implement session manager extensions
4. Test core functionality

### Phase 3: UI Integration
1. Implement UI visibility controller
2. Update navigation menu
3. Update action buttons
4. Update dashboard widgets
5. Test UI changes

### Phase 4: Page Protection
1. Add permission checks to all admin pages
2. Implement access denied page
3. Add AJAX request validation
4. Test all protected pages

### Phase 5: Admin Management
1. Update add_admin.php to include role selection
2. Create role management interface
3. Create assignment management interface
4. Test admin management features

### Phase 6: Audit and Monitoring
1. Implement audit log viewer
2. Add audit log filtering
3. Test audit logging
4. Verify all actions are logged

### Phase 7: Testing and Validation
1. Run unit tests
2. Run property-based tests
3. Run integration tests
4. Perform manual testing
5. Security audit

### Phase 8: Production Deployment
1. Backup database
2. Deploy database migrations
3. Deploy code changes
4. Verify backward compatibility
5. Monitor for issues
6. Gradual rollout of role assignments

## Maintenance and Monitoring

### 1. Regular Audits
- Review audit logs weekly
- Check for suspicious access patterns
- Verify role assignments are appropriate
- Review and update permission matrix as needed

### 2. Performance Monitoring
- Monitor permission check latency
- Monitor audit log write performance
- Monitor database query performance
- Optimize as needed

### 3. Security Updates
- Regular security reviews
- Update dependencies
- Patch vulnerabilities
- Review and update security policies

### 4. Documentation
- Maintain role permission matrix
- Document all permission changes
- Update user guides
- Maintain troubleshooting guides

## Future Enhancements

### 1. Custom Roles
- Allow creation of custom roles
- Define custom permission sets
- Role templates

### 2. Time-Based Access
- Temporary role assignments
- Scheduled access grants
- Automatic expiration

### 3. Multi-Factor Authentication
- Require MFA for sensitive roles
- Role-based MFA policies

### 4. Advanced Audit Features
- Real-time audit alerts
- Anomaly detection
- Compliance reporting

### 5. API Access Control
- API key management
- API rate limiting
- API permission scopes
