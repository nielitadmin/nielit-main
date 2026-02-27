# Implementation Plan: Role-Based Access Control System

## Overview

This implementation plan breaks down the RBAC system into discrete, manageable tasks that build incrementally. Each task includes specific requirements references and focuses on coding activities that can be performed by a development agent. The plan follows a logical sequence: database setup → core permission system → UI integration → admin management → audit logging → testing.

**Important**: Course Coordinator and Batch Coordinator are at EQUAL privilege levels (Level 3). They have the same authority but different operational scopes. When implementing permission checks, treat both roles with equal privilege - neither is superior to the other.

## Tasks

- [ ] 1. Database Schema Setup
  - [x] 1.1 Create database migration script for admin table modifications
    - Add `role` enum column with values: master_admin, course_coordinator, batch_coordinator, data_entry_operator, report_viewer
    - Add `created_at`, `updated_at`, `is_active` columns
    - Add indexes on `role` and `is_active` columns
    - Set default role to 'master_admin' for backward compatibility
    - Create migration file: `migrations/add_rbac_schema.sql`
    - _Requirements: 10.1, 10.5, 12.1_
  
  - [x] 1.2 Create admin_course_assignments table
    - Define table structure with admin_id, course_id, assigned_at, assigned_by columns
    - Add foreign key constraints to admin and courses tables
    - Add unique constraint on (admin_id, course_id) combination
    - Add indexes for efficient querying
    - _Requirements: 10.2, 3.1_
  
  - [x] 1.3 Create admin_batch_assignments table
    - Define table structure with admin_id, batch_id, assigned_at, assigned_by columns
    - Add foreign key constraints to admin and batches tables
    - Add unique constraint on (admin_id, batch_id) combination
    - Add indexes for efficient querying
    - _Requirements: 10.3, 4.1_
  
  - [x] 1.4 Create audit_log table
    - Define table structure with all required fields (id, admin_id, admin_username, role, action_type, resource_type, resource_id, details, ip_address, user_agent, timestamp)
    - Use bigint for id column to handle large volumes
    - Add indexes on admin_id, timestamp, action_type, and composite index on (resource_type, resource_id)
    - Add foreign key constraint to admin table
    - _Requirements: 10.4, 9.1, 9.2, 9.6_
  
  - [x] 1.5 Create database installation script
    - Write PHP script to execute all migrations in order
    - Add rollback capability for each migration
    - Include data validation checks
    - Create file: `migrations/install_rbac.php`
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

- [ ] 2. Core Permission System Implementation
  - [x] 2.1 Create permission checker module
    - Implement `has_permission($action, $context)` function
    - Implement `require_permission($action, $context)` function
    - Implement `get_admin_role()` function
    - Implement `has_course_access($course_id)` function
    - Implement `has_batch_access($batch_id)` function
    - Define permission matrix array for all roles
    - Create file: `includes/check_permission.php`
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.6, 2.1, 3.2, 3.8, 4.2, 4.7_
  
  - [ ]* 2.2 Write property test for permission checker
    - **Property 2: Permission Inheritance**
    - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6**
  
  - [ ]* 2.3 Write property test for course access control
    - **Property 3: Course Assignment Constraint**
    - **Validates: Requirements 3.2, 3.8**
  
  - [ ]* 2.4 Write property test for batch access control
    - **Property 4: Batch Assignment Constraint**
    - **Validates: Requirements 4.2, 4.7**
  
  - [ ]* 2.5 Write unit tests for permission checker edge cases
    - Test null values, invalid roles, missing session data
    - Test permission matrix lookups
    - _Requirements: 7.1, 7.2, 7.3_

- [ ] 3. Session Management Integration
  - [x] 3.1 Create session manager extension
    - Implement `init_admin_session($username)` function
    - Implement `load_admin_permissions($admin_id)` function
    - Implement `invalidate_admin_session($admin_id)` function
    - Implement `refresh_session_permissions()` function
    - Create file: `includes/session_manager.php`
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_
  
  - [x] 3.2 Update login.php to load RBAC data
    - Modify successful login handler to call `init_admin_session()`
    - Load role, assigned courses, and assigned batches into session
    - Store admin_id in session
    - _Requirements: 11.1, 11.2, 11.3_
  
  - [ ]* 3.3 Write property test for session role persistence
    - **Property 13: Session Role Persistence**
    - **Validates: Requirements 11.1, 11.2**
  
  - [ ]* 3.4 Write unit tests for session management functions
    - Test session initialization with different roles
    - Test permission loading
    - Test session invalidation
    - _Requirements: 11.1, 11.2, 11.5_

- [ ] 4. Checkpoint - Ensure core permission system works
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. UI Visibility Controller Implementation
  - [ ] 5.1 Create RBAC helper functions
    - Implement `should_display_menu($menu_item)` function
    - Implement `should_display_button($action, $context)` function
    - Implement `get_user_menu()` function
    - Implement `render_action_button($action, $label, $url, $context)` function
    - Create file: `includes/rbac_helpers.php`
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [ ] 5.2 Update navigation sidebar to use role-based filtering
    - Modify sidebar in admin pages to call `get_user_menu()`
    - Hide "Add Admin" menu item for non-master admins
    - Filter menu items based on role permissions
    - Update files: admin dashboard, students.php, and other admin pages
    - _Requirements: 8.1, 8.3_
  
  - [ ] 5.3 Update action buttons to use permission checks
    - Wrap delete buttons with `should_display_button()` checks
    - Wrap edit buttons with permission checks
    - Add permission checks to all action buttons across admin pages
    - _Requirements: 8.2, 8.4_
  
  - [ ]* 5.4 Write property test for UI element visibility
    - **Property 10: UI Element Visibility**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.5**
  
  - [ ]* 5.5 Write unit tests for UI helper functions
    - Test menu filtering for each role
    - Test button visibility logic
    - _Requirements: 8.1, 8.2_

- [ ] 6. Page-Level Access Control
  - [ ] 6.1 Create access denied page
    - Design user-friendly access denied page
    - Include explanation, link to dashboard, contact information
    - Log access denial attempts
    - Create file: `admin/access_denied.php`
    - _Requirements: 7.2_
  
  - [ ] 6.2 Add permission checks to all admin pages
    - Add `require_permission()` calls at the top of each admin page
    - Protect students.php, manage_courses.php, manage_batches.php, etc.
    - Add context-based checks for course/batch-specific pages
    - _Requirements: 7.1, 7.2, 7.3_
  
  - [ ] 6.3 Add AJAX request validation
    - Create AJAX permission validator function
    - Add permission checks to all AJAX endpoints
    - Return JSON error responses for unauthorized requests
    - _Requirements: 7.4, 7.6_
  
  - [ ]* 6.4 Write property test for page access control
    - **Property 7: Page Access Control**
    - **Validates: Requirements 7.1, 7.2, 7.3**
  
  - [ ]* 6.5 Write property test for AJAX authorization
    - **Property 8: AJAX Request Authorization**
    - **Validates: Requirements 7.4, 7.6**

- [ ] 7. Checkpoint - Ensure access control is working
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Audit Logging System
  - [ ] 8.1 Create audit logger module
    - Implement `log_admin_action($action_type, $resource_type, $resource_id, $details)` function
    - Implement `get_audit_log($filters, $limit, $offset)` function
    - Capture IP address and user agent
    - Handle logging failures gracefully
    - Create file: `includes/audit_logger.php`
    - _Requirements: 9.1, 9.2, 9.6_
  
  - [ ] 8.2 Integrate audit logging into admin actions
    - Add audit logging to student create/update/delete operations
    - Add audit logging to course create/update/delete operations
    - Add audit logging to batch create/update/delete operations
    - Add audit logging to admin create/update/delete operations
    - Add audit logging to login/logout events
    - _Requirements: 9.1, 9.2_
  
  - [ ] 8.3 Create audit log viewer page
    - Design audit log viewing interface for master admins
    - Implement filtering by date range, admin, action type
    - Implement pagination
    - Add permission check (master_admin only)
    - Create file: `admin/audit_log.php`
    - _Requirements: 9.3, 9.4, 9.5_
  
  - [ ]* 8.4 Write property test for audit log completeness
    - **Property 11: Audit Log Completeness**
    - **Validates: Requirements 9.1, 9.2**
  
  - [ ]* 8.5 Write property test for audit log access control
    - **Property 12: Audit Log Access Control**
    - **Validates: Requirements 9.3, 9.5**
  
  - [ ]* 8.6 Write unit tests for audit logger
    - Test log entry creation
    - Test log retrieval with various filters
    - Test error handling
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [ ] 9. Admin Management Interface Updates
  - [ ] 9.1 Update add_admin.php to include role selection
    - Add role dropdown to admin creation form
    - Add course assignment interface for course coordinators
    - Add batch assignment interface for batch coordinators
    - Validate role selection
    - _Requirements: 1.2, 1.3, 3.1, 4.1_
  
  - [ ] 9.2 Create admin list page with role display
    - Show all admins with their roles
    - Add filter by role
    - Add edit role functionality (master admin only)
    - Create file: `admin/manage_admins.php`
    - _Requirements: 1.3, 2.3_
  
  - [ ] 9.3 Create role management interface
    - Allow master admin to change user roles
    - Implement role change with session invalidation
    - Add confirmation dialog for role changes
    - Log role changes in audit log
    - _Requirements: 1.5, 2.3, 7.5, 11.5_
  
  - [ ] 9.4 Create assignment management interface
    - Create course assignment page for course coordinators
    - Create batch assignment page for batch coordinators
    - Allow adding/removing assignments
    - Validate assignments (prevent duplicates)
    - Log assignment changes
    - _Requirements: 3.1, 4.1_
  
  - [ ]* 9.5 Write property test for role assignment enforcement
    - **Property 1: Role Assignment Enforcement**
    - **Validates: Requirements 1.1, 1.2**
  
  - [ ]* 9.6 Write property test for role change session invalidation
    - **Property 9: Role Change Session Invalidation**
    - **Validates: Requirements 7.5, 11.5**
  
  - [ ]* 9.7 Write property test for assignment uniqueness
    - **Property 15: Assignment Uniqueness**
    - **Validates: Requirements 3.1, 4.1**

- [ ] 10. Role-Specific Functionality Implementation
  - [ ] 10.1 Implement data entry operator restrictions
    - Remove delete buttons from student pages for data entry operators
    - Block access to course and batch management pages
    - Block access to reports and analytics
    - _Requirements: 5.4, 5.5, 5.6, 5.7_
  
  - [ ] 10.2 Implement report viewer read-only mode
    - Block all create/update/delete operations
    - Allow view and export operations
    - Update UI to show read-only indicators
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_
  
  - [ ] 10.3 Implement course coordinator course filtering
    - Filter course lists to show only assigned courses
    - Filter student lists to show only students in assigned courses
    - Filter batch lists to show only batches for assigned courses
    - _Requirements: 3.2, 3.3, 3.4, 3.5_
  
  - [ ] 10.4 Implement batch coordinator batch filtering
    - Filter batch lists to show only assigned batches
    - Filter student lists to show only students in assigned batches
    - Enable admission order generation for assigned batches
    - _Requirements: 4.2, 4.3, 4.4, 4.5_
  
  - [ ]* 10.5 Write property test for data entry operator restrictions
    - **Property 5: Data Entry Operator Restrictions**
    - **Validates: Requirements 5.4, 5.5, 5.6, 5.7**
  
  - [ ]* 10.6 Write property test for report viewer read-only enforcement
    - **Property 6: Report Viewer Read-Only Enforcement**
    - **Validates: Requirements 6.4, 6.5, 6.6**

- [ ] 11. Checkpoint - Ensure role-specific features work correctly
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 12. Backward Compatibility and Migration
  - [ ] 12.1 Create backward compatibility verification script
    - Check all existing admins have master_admin role
    - Verify existing login flow still works
    - Verify existing admin functionality is not broken
    - Create file: `migrations/verify_compatibility.php`
    - _Requirements: 12.1, 12.2, 12.3, 12.4_
  
  - [ ] 12.2 Update existing admin pages to maintain compatibility
    - Ensure master_admin role has access to all existing features
    - Test all existing admin workflows
    - Fix any compatibility issues
    - _Requirements: 12.2, 12.3_
  
  - [ ]* 12.3 Write property test for backward compatibility
    - **Property 14: Backward Compatibility**
    - **Validates: Requirements 12.1, 12.3**

- [ ] 13. Integration Testing and Bug Fixes
  - [ ] 13.1 Test complete role assignment workflow
    - Test creating admin with each role type
    - Test logging in with each role
    - Test accessing pages with each role
    - Verify UI elements match role permissions
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 7.1, 8.1_
  
  - [ ] 13.2 Test course coordinator workflow
    - Assign courses to coordinator
    - Login as coordinator
    - Verify can access assigned courses
    - Verify cannot access unassigned courses
    - Test student management in assigned courses
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.8_
  
  - [ ] 13.3 Test batch coordinator workflow
    - Assign batches to coordinator
    - Login as coordinator
    - Verify can access assigned batches
    - Verify cannot access unassigned batches
    - Test admission order generation
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.7_
  
  - [ ] 13.4 Test audit log functionality
    - Perform various admin actions
    - Verify all actions are logged correctly
    - Test audit log viewer as master admin
    - Test audit log access denial for other roles
    - Test audit log filtering
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_
  
  - [ ] 13.5 Test session invalidation on role change
    - Login as admin with role A
    - Change role to B (as master admin)
    - Verify session is invalidated
    - Login again and verify new role is active
    - _Requirements: 7.5, 11.5_
  
  - [ ] 13.6 Fix any bugs discovered during integration testing
    - Document bugs found
    - Implement fixes
    - Re-test affected functionality
    - _Requirements: All_

- [ ] 14. Documentation and Deployment Preparation
  - [ ] 14.1 Create deployment guide
    - Document database migration steps
    - Document configuration requirements
    - Document rollback procedures
    - Create file: `docs/rbac/DEPLOYMENT_GUIDE.md`
    - _Requirements: All_
  
  - [ ] 14.2 Create user guide for each role
    - Document master admin capabilities
    - Document course coordinator capabilities
    - Document batch coordinator capabilities
    - Document data entry operator capabilities
    - Document report viewer capabilities
    - Create file: `docs/rbac/USER_GUIDE.md`
    - _Requirements: 2.1-2.6, 3.1-3.8, 4.1-4.7, 5.1-5.7, 6.1-6.6_
  
  - [ ] 14.3 Create troubleshooting guide
    - Document common issues and solutions
    - Document error messages and their meanings
    - Document how to check permissions
    - Create file: `docs/rbac/TROUBLESHOOTING.md`
    - _Requirements: All_

- [ ] 15. Final Checkpoint - Complete system verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (minimum 100 iterations each)
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end workflows
- The implementation follows a logical sequence: database → core system → UI → admin management → audit → testing
- Backward compatibility is maintained throughout to ensure existing admins can continue working
- All permission checks fail closed (deny by default) for security
- Audit logging is implemented for all administrative actions
- Session management is extended to include RBAC data
- UI elements are filtered based on role permissions for better user experience
