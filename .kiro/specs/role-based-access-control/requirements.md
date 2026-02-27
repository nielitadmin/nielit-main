# Requirements Document

## Introduction

This document specifies the requirements for implementing a Role-Based Access Control (RBAC) system for the NIELIT Bhubaneswar admin portal. The system will enable hierarchical access control with different privilege levels for various administrative roles, ensuring secure and appropriate access to system resources based on user responsibilities.

**Role Hierarchy Overview**:
- Level 4: Master Admin (full system access)
- Level 3: Course Coordinator & Batch Coordinator (EQUAL privilege level, different scopes)
- Level 2: Data Entry Operator (student records only)
- Level 1: Report Viewer (read-only access)

Course Coordinator and Batch Coordinator have the same privilege level but manage different aspects of the system - courses vs batches.

## Glossary

- **RBAC_System**: The Role-Based Access Control system that manages user permissions and access rights
- **Admin_User**: Any authenticated user with administrative access to the portal
- **Master_Admin**: The highest privilege role with full system access
- **Course_Coordinator**: A role with limited access to manage specific courses
- **Batch_Coordinator**: A role with access to manage specific batches
- **Data_Entry_Operator**: A role with permission to add and edit student records only
- **Report_Viewer**: A read-only role with access to view reports and data
- **Role**: A named collection of permissions assigned to an Admin_User
- **Permission**: A specific action or access right within the system
- **Session**: An authenticated user's active connection to the system
- **Audit_Log**: A record of administrative actions performed by Admin_Users
- **Access_Control_Middleware**: Code that checks permissions before allowing access to resources

## Requirements

### Requirement 1: Role Management

**User Story:** As a Master Admin, I want to define and manage different administrative roles, so that I can control access levels across the organization.

#### Acceptance Criteria

1. THE RBAC_System SHALL support the following predefined roles: Master_Admin, Course_Coordinator, Batch_Coordinator, Data_Entry_Operator, and Report_Viewer
2. WHEN a Master_Admin creates a new Admin_User, THE RBAC_System SHALL require assignment of exactly one role
3. WHEN a Master_Admin views the admin list, THE RBAC_System SHALL display each Admin_User's assigned role
4. THE RBAC_System SHALL store role information in the database admin table
5. WHEN a Master_Admin updates an Admin_User's role, THE RBAC_System SHALL immediately apply the new permissions

### Requirement 2: Master Admin Privileges

**User Story:** As a Master Admin, I want full system access, so that I can manage all aspects of the portal without restrictions.

#### Acceptance Criteria

1. THE RBAC_System SHALL grant Master_Admin role access to all system features and pages
2. THE RBAC_System SHALL allow Master_Admin to create, edit, and delete other Admin_Users
3. THE RBAC_System SHALL allow Master_Admin to assign or change roles for any Admin_User
4. THE RBAC_System SHALL allow Master_Admin to manage all courses, batches, students, and schemes
5. THE RBAC_System SHALL allow Master_Admin to access all reports and data exports
6. THE RBAC_System SHALL allow Master_Admin to modify system settings and configurations

### Requirement 3: Course Coordinator Privileges

**User Story:** As a Course Coordinator, I want access limited to my assigned courses, so that I can manage my courses without affecting others.

#### Acceptance Criteria

1. WHEN a Course_Coordinator is created, THE RBAC_System SHALL require assignment of one or more courses
2. THE RBAC_System SHALL allow Course_Coordinator to view and edit only their assigned courses
3. THE RBAC_System SHALL allow Course_Coordinator to manage students enrolled in their assigned courses
4. THE RBAC_System SHALL allow Course_Coordinator to manage batches for their assigned courses
5. THE RBAC_System SHALL allow Course_Coordinator to generate reports for their assigned courses only
6. THE RBAC_System SHALL prevent Course_Coordinator from adding or deleting Admin_Users
7. THE RBAC_System SHALL prevent Course_Coordinator from modifying system settings
8. THE RBAC_System SHALL prevent Course_Coordinator from accessing courses not assigned to them

### Requirement 4: Batch Coordinator Privileges

**User Story:** As a Batch Coordinator, I want to manage specific batches, so that I can handle batch operations without broader course access.

#### Acceptance Criteria

1. WHEN a Batch_Coordinator is created, THE RBAC_System SHALL require assignment of one or more batches
2. THE RBAC_System SHALL allow Batch_Coordinator to view and edit only their assigned batches
3. THE RBAC_System SHALL allow Batch_Coordinator to manage students within their assigned batches
4. THE RBAC_System SHALL allow Batch_Coordinator to generate admission orders for their assigned batches
5. THE RBAC_System SHALL allow Batch_Coordinator to approve student enrollments for their assigned batches
6. THE RBAC_System SHALL prevent Batch_Coordinator from creating or deleting courses
7. THE RBAC_System SHALL prevent Batch_Coordinator from accessing batches not assigned to them

### Requirement 5: Data Entry Operator Privileges

**User Story:** As a Data Entry Operator, I want to add and edit student records, so that I can maintain student data without access to sensitive operations.

#### Acceptance Criteria

1. THE RBAC_System SHALL allow Data_Entry_Operator to create new student records
2. THE RBAC_System SHALL allow Data_Entry_Operator to edit existing student records
3. THE RBAC_System SHALL allow Data_Entry_Operator to view student lists and details
4. THE RBAC_System SHALL prevent Data_Entry_Operator from deleting student records
5. THE RBAC_System SHALL prevent Data_Entry_Operator from managing courses or batches
6. THE RBAC_System SHALL prevent Data_Entry_Operator from accessing reports or analytics
7. THE RBAC_System SHALL prevent Data_Entry_Operator from managing Admin_Users

### Requirement 6: Report Viewer Privileges

**User Story:** As a Report Viewer, I want read-only access to reports and data, so that I can monitor system information without making changes.

#### Acceptance Criteria

1. THE RBAC_System SHALL allow Report_Viewer to view all reports and analytics
2. THE RBAC_System SHALL allow Report_Viewer to export reports in available formats
3. THE RBAC_System SHALL allow Report_Viewer to view student, course, and batch information
4. THE RBAC_System SHALL prevent Report_Viewer from creating, editing, or deleting any records
5. THE RBAC_System SHALL prevent Report_Viewer from accessing system settings
6. THE RBAC_System SHALL prevent Report_Viewer from managing Admin_Users

### Requirement 7: Access Control Enforcement

**User Story:** As a system administrator, I want automatic permission checking on all pages, so that unauthorized access is prevented.

#### Acceptance Criteria

1. WHEN an Admin_User attempts to access a page, THE RBAC_System SHALL verify their role permissions before granting access
2. IF an Admin_User lacks permission for a page, THEN THE RBAC_System SHALL redirect them to an access denied page
3. THE RBAC_System SHALL check permissions on every page load within the admin portal
4. THE RBAC_System SHALL validate permissions for AJAX requests and API endpoints
5. WHEN an Admin_User's role is changed, THE RBAC_System SHALL apply new permissions on their next request
6. THE RBAC_System SHALL maintain permission checks even if the Admin_User modifies client-side code

### Requirement 8: UI Element Visibility Control

**User Story:** As a user, I want to see only the menu items and buttons I have permission to use, so that the interface is clear and relevant to my role.

#### Acceptance Criteria

1. WHEN an Admin_User views the navigation menu, THE RBAC_System SHALL display only menu items they have permission to access
2. WHEN an Admin_User views a page, THE RBAC_System SHALL hide action buttons they cannot use
3. THE RBAC_System SHALL hide the "Add Admin" menu item from non-Master_Admin roles
4. THE RBAC_System SHALL hide delete buttons from roles without delete permissions
5. THE RBAC_System SHALL display role-appropriate dashboard statistics and widgets

### Requirement 9: Audit Logging

**User Story:** As a Master Admin, I want to track all administrative actions, so that I can monitor system usage and investigate issues.

#### Acceptance Criteria

1. WHEN an Admin_User performs a create, update, or delete action, THE RBAC_System SHALL record the action in the Audit_Log
2. THE RBAC_System SHALL record the Admin_User's username, role, action type, affected resource, timestamp, and IP address
3. THE RBAC_System SHALL allow Master_Admin to view the complete Audit_Log
4. THE RBAC_System SHALL allow Master_Admin to filter Audit_Log by date range, user, or action type
5. THE RBAC_System SHALL prevent non-Master_Admin roles from accessing the Audit_Log
6. THE RBAC_System SHALL retain Audit_Log entries for a minimum of 12 months

### Requirement 10: Database Schema Updates

**User Story:** As a developer, I want the database schema to support role-based access control, so that role and permission data can be stored and retrieved efficiently.

#### Acceptance Criteria

1. THE RBAC_System SHALL add a role field to the admin table with values: master_admin, course_coordinator, batch_coordinator, data_entry_operator, report_viewer
2. THE RBAC_System SHALL create an admin_course_assignments table to store Course_Coordinator course assignments
3. THE RBAC_System SHALL create an admin_batch_assignments table to store Batch_Coordinator batch assignments
4. THE RBAC_System SHALL create an audit_log table with fields: id, admin_id, admin_username, role, action_type, resource_type, resource_id, details, ip_address, timestamp
5. THE RBAC_System SHALL set default role to master_admin for existing admin records during migration
6. THE RBAC_System SHALL add appropriate indexes to support efficient permission queries

### Requirement 11: Session Management Integration

**User Story:** As a developer, I want role information available in the session, so that permission checks can be performed efficiently.

#### Acceptance Criteria

1. WHEN an Admin_User successfully logs in, THE RBAC_System SHALL store their role in the Session
2. WHEN an Admin_User successfully logs in, THE RBAC_System SHALL store their assigned courses or batches in the Session
3. THE RBAC_System SHALL provide a helper function to check if the current Admin_User has a specific permission
4. THE RBAC_System SHALL provide a helper function to retrieve the current Admin_User's role
5. WHEN an Admin_User's role is updated, THE RBAC_System SHALL invalidate their current Session

### Requirement 12: Backward Compatibility

**User Story:** As a system administrator, I want existing admin accounts to continue working, so that the system remains operational during the transition.

#### Acceptance Criteria

1. WHEN the RBAC_System is deployed, THE RBAC_System SHALL assign master_admin role to all existing Admin_Users
2. THE RBAC_System SHALL maintain compatibility with existing login and authentication flows
3. THE RBAC_System SHALL not break existing admin portal functionality for Master_Admin users
4. THE RBAC_System SHALL allow gradual role assignment without requiring immediate configuration

