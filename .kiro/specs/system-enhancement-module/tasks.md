# Implementation Plan: System Enhancement Module

## Overview

This implementation plan covers the development of three integrated administrative features for the NIELIT Bhubaneswar Student Management System: Centre Management, Theme Customization, and Homepage Content Management. The implementation follows the existing PHP/MySQL architecture and integrates with the current admin dashboard.

## Tasks

- [x] 1. Database Schema Setup
  - Create migration file for all three modules
  - Create centres table with indexes
  - Create themes table with indexes
  - Create homepage_content table with indexes
  - Add centre_id column to courses table with foreign key
  - Insert default centre data (NIELIT Bhubaneswar, NIELIT Balasore)
  - _Requirements: 1.1, 4.1, 7.1, 2.5, 12.1_

- [ ] 2. Centre Management Module - Backend
  - [x] 2.1 Create admin/manage_centres.php page structure
    - Set up page with admin authentication check
    - Include database connection and theme loader
    - Create HTML structure with sidebar navigation
    - _Requirements: 1.3, 11.1_
  
  - [x] 2.2 Implement centre CRUD operations
    - Create function to add new centre
    - Create function to update existing centre
    - Create function to toggle centre active status
    - Create function to fetch all centres
    - Add input validation for centre data
    - _Requirements: 1.2, 1.4, 1.5, 1.6_
  
  - [ ]* 2.3 Write unit tests for centre validation
    - Test centre code format validation
    - Test email format validation
    - Test phone format validation
    - Test duplicate code handling
    - _Requirements: 1.2_

- [ ] 3. Centre Management Module - Frontend
  - [x] 3.1 Create centres listing table
    - Display all centres with complete information
    - Add search and filter functionality
    - Add status badges (active/inactive)
    - _Requirements: 1.3_
  
  - [x] 3.2 Create add/edit centre modal
    - Build form with all centre fields
    - Add client-side validation
    - Implement form submission handling
    - Display success/error messages
    - _Requirements: 1.2, 1.4_
  
  - [x] 3.3 Add centre status toggle functionality
    - Create toggle buttons for active/inactive status
    - Implement AJAX status update
    - Update UI without page reload
    - _Requirements: 1.5_

- [ ] 4. Course-Centre Integration
  - [x] 4.1 Update admin/manage_courses.php
    - Add centre dropdown to add course form
    - Add centre dropdown to edit course form
    - Fetch active centres for dropdown population
    - Save centre_id when creating/updating courses
    - _Requirements: 2.1, 2.2_
  
  - [x] 4.2 Update course listing display
    - Add centre name column to courses table
    - Display centre information with course details
    - Handle null centre_id gracefully
    - _Requirements: 2.4_
  
  - [ ]* 4.3 Write property test for course-centre referential integrity
    - **Property 5: Course-Centre Referential Integrity**
    - **Validates: Requirements 2.5**

- [ ] 5. Public Centre Filtering
  - [x] 5.1 Update public/courses.php with centre filter
    - Add centre filter dropdown above course listings
    - Populate dropdown with active centres only
    - Implement filter query logic
    - Maintain other active filters (category, status)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  
  - [x] 5.2 Display centre information with courses
    - Show centre name in course cards
    - Add centre location information
    - Style centre information appropriately
    - _Requirements: 2.4_

- [ ] 6. Checkpoint - Centre Management Complete
  - Ensure all tests pass, ask the user if questions arise.


- [ ] 7. Theme Customization Module - Backend
  - [x] 7.1 Create admin/manage_themes.php page structure
    - Set up page with admin authentication check
    - Include database connection
    - Create HTML structure with sidebar navigation
    - _Requirements: 5.1, 11.1_
  
  - [x] 7.2 Implement theme CRUD operations
    - Create function to add new theme
    - Create function to update existing theme
    - Create function to activate theme (deactivate others)
    - Create function to fetch all themes
    - Create function to get active theme
    - _Requirements: 4.2, 4.3, 5.2, 5.3_
  
  - [x] 7.3 Implement theme validation
    - Validate color format (hexadecimal)
    - Validate theme name
    - Return validation errors
    - _Requirements: 4.4_
  
  - [ ]* 7.4 Write property test for single active theme
    - **Property 2: Single Active Theme**
    - **Validates: Requirements 4.2**
  
  - [ ]* 7.5 Write property test for theme color validation
    - **Property 3: Theme Color Validation**
    - **Validates: Requirements 4.4**
  
  - [ ]* 7.6 Write property test for theme activation atomicity
    - **Property 10: Theme Activation Atomicity**
    - **Validates: Requirements 4.3**

- [ ] 8. Theme Customization Module - File Upload
  - [x] 8.1 Implement logo upload functionality
    - Create uploads/themes/ directory
    - Validate file type (JPEG, PNG, GIF, SVG)
    - Validate file size (max 2MB)
    - Generate unique filename
    - Move uploaded file to destination
    - Return file path or error
    - _Requirements: 10.1, 10.2, 10.3, 10.4_
  
  - [x] 8.2 Implement file deletion on replacement
    - Delete old logo when new one is uploaded
    - Handle missing file gracefully
    - _Requirements: 10.5_
  
  - [ ]* 8.3 Write property test for file upload size validation
    - **Property 6: File Upload Size Validation**
    - **Validates: Requirements 10.2**
  
  - [ ]* 8.4 Write property test for file type validation
    - **Property 7: File Type Validation**
    - **Validates: Requirements 10.1**

- [ ] 9. Theme Customization Module - Frontend
  - [x] 9.1 Create themes listing interface
    - Display all themes as preview cards
    - Show theme colors visually
    - Display active/inactive status
    - Add activate/deactivate buttons
    - _Requirements: 5.1_
  
  - [x] 9.2 Create add/edit theme modal
    - Build form with theme name input
    - Add color picker inputs for primary, secondary, accent colors
    - Add logo upload field
    - Add favicon upload field
    - Implement client-side validation
    - _Requirements: 5.2, 5.6_
  
  - [x] 9.3 Implement theme preview functionality
    - Create preview modal
    - Apply theme colors to preview elements
    - Show logo in preview
    - Allow preview without activation
    - _Requirements: 5.7_
  
  - [x] 9.4 Implement theme activation
    - Add activate button for each theme
    - Confirm activation with user
    - Apply theme immediately after activation
    - Show success message
    - _Requirements: 5.5_

- [ ] 10. Theme Loader Implementation
  - [x] 10.1 Create includes/theme_loader.php
    - Implement loadActiveTheme() function
    - Implement getDefaultTheme() function
    - Implement injectThemeCSS() function
    - Add theme caching mechanism
    - _Requirements: 6.1, 6.3, 6.4_
  
  - [x] 10.2 Update CSS files to use CSS custom properties
    - Replace hardcoded colors in admin-theme.css
    - Replace hardcoded colors in public-theme.css
    - Use var(--primary-color) syntax
    - Provide fallback values
    - _Requirements: 6.2_
  
  - [x] 10.3 Integrate theme loader in all pages
    - Include theme_loader.php in admin pages
    - Include theme_loader.php in public pages
    - Call injectThemeCSS() in page headers
    - Apply theme logo to navigation
    - _Requirements: 6.1, 6.6_
  
  - [x] 10.4 Implement theme cache clearing
    - Clear cache when theme is activated
    - Clear cache when theme is updated
    - _Requirements: 6.5_

- [ ] 11. Checkpoint - Theme Customization Complete
  - Ensure all tests pass, ask the user if questions arise.


- [ ] 12. Homepage Content Management - Backend
  - [x] 12.1 Create admin/manage_homepage.php page structure
    - Set up page with admin authentication check
    - Include database connection and theme loader
    - Create HTML structure with sidebar navigation
    - _Requirements: 8.1, 11.1_
  
  - [x] 12.2 Implement content section CRUD operations
    - Create function to add new content section
    - Create function to update existing content section
    - Create function to toggle section active status
    - Create function to fetch all content sections
    - Create function to get content by section key
    - _Requirements: 7.2, 7.3, 7.4, 7.5_
  
  - [x] 12.3 Implement content validation
    - Validate section key format
    - Validate section title
    - Validate section type
    - Validate display order
    - Return validation errors
    - _Requirements: 7.1_
  
  - [x] 12.4 Implement content sanitization
    - Create sanitizeContent() function
    - Allow safe HTML tags only
    - Strip dangerous tags (script, iframe, etc.)
    - Sanitize attributes
    - _Requirements: 9.4_
  
  - [ ]* 12.5 Write property test for section key uniqueness
    - **Property 4: Section Key Uniqueness**
    - **Validates: Requirements 7.3**
  
  - [ ]* 12.6 Write property test for content sanitization
    - **Property 8: Content Sanitization**
    - **Validates: Requirements 9.4**

- [ ] 13. Homepage Content Management - Section Reordering
  - [x] 13.1 Implement reorderSections() function
    - Accept array of section IDs with new order values
    - Update display_order for each section
    - Handle database transaction
    - _Requirements: 8.4_
  
  - [x] 13.2 Create drag-and-drop reordering UI
    - Add drag handles to section rows
    - Implement JavaScript drag-and-drop
    - Send AJAX request on drop
    - Update UI without page reload
    - _Requirements: 8.4_
  
  - [ ]* 13.3 Write property test for display order consistency
    - **Property 9: Display Order Consistency**
    - **Validates: Requirements 7.4**

- [ ] 14. Homepage Content Management - Frontend
  - [x] 14.1 Create content sections listing
    - Display all sections ordered by display_order
    - Show section type badges
    - Display active/inactive status
    - Add edit and delete buttons
    - _Requirements: 8.1_
  
  - [x] 14.2 Create add/edit content section modal
    - Build form with section key, title, type inputs
    - Add display order input
    - Integrate WYSIWYG editor (TinyMCE or CKEditor)
    - Implement client-side validation
    - _Requirements: 8.2, 8.3_
  
  - [x] 14.3 Implement content preview
    - Create preview modal
    - Render content as it will appear on homepage
    - Allow preview without saving
    - _Requirements: 8.6_
  
  - [x] 14.4 Add section status toggle
    - Create toggle buttons for active/inactive
    - Implement AJAX status update
    - Update UI without page reload
    - _Requirements: 8.5_

- [ ] 15. Homepage Dynamic Rendering
  - [x] 15.1 Update index.php to load content from database
    - Query homepage_content table for active sections
    - Group sections by type (banner, announcement, etc.)
    - Order sections by display_order
    - _Requirements: 9.1, 9.2_
  
  - [x] 15.2 Render dynamic content sections
    - Create rendering logic for each section type
    - Apply appropriate HTML structure and styling
    - Handle empty sections gracefully
    - _Requirements: 9.2_
  
  - [x] 15.3 Implement content caching
    - Cache homepage content in session or file
    - Set cache expiration time
    - Serve cached content when available
    - _Requirements: 9.5_
  
  - [x] 15.4 Implement cache clearing
    - Clear cache when content is created
    - Clear cache when content is updated
    - Clear cache when content is deleted
    - Clear cache when section is reordered
    - _Requirements: 9.6_
  
  - [x] 15.5 Add fallback to hardcoded content
    - Check if database content exists
    - Display hardcoded content if no database content
    - Ensure backward compatibility
    - _Requirements: 12.4_

- [ ] 16. Checkpoint - Homepage Content Management Complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 17. Security Implementation
  - [x] 17.1 Add authentication checks to all management pages
    - Verify admin session on page load
    - Redirect to login if not authenticated
    - _Requirements: 11.1_
  
  - [x] 17.2 Implement CSRF protection
    - Generate CSRF token in session
    - Add token to all forms
    - Validate token on form submission
    - _Requirements: 11.3_
  
  - [x] 17.3 Implement input sanitization
    - Sanitize all POST data
    - Use prepared statements for all queries
    - Escape output with htmlspecialchars()
    - _Requirements: 11.4_
  
  - [x] 17.4 Add audit logging
    - Log all administrative actions
    - Include timestamp, admin user, action type
    - Store logs in database or file
    - _Requirements: 11.5_

- [ ] 18. Data Migration
  - [x] 18.1 Create migration script
    - Populate centres table with existing training centres
    - Update existing courses with default centre_id
    - Create default theme from existing CSS values
    - _Requirements: 12.1, 12.2_
  
  - [x] 18.2 Test backward compatibility
    - Verify existing features work with new schema
    - Test course management with centre_id column
    - Test pages without active theme
    - Test homepage without database content
    - _Requirements: 12.3, 12.4, 12.5_

- [ ] 19. Navigation Integration
  - [x] 19.1 Add menu items to admin sidebar
    - Add "Manage Centres" link
    - Add "Manage Themes" link
    - Add "Manage Homepage" link
    - Group under "System Settings" section
    - _Requirements: 1.3, 5.1, 8.1_
  
  - [x] 19.2 Update admin dashboard statistics
    - Add centre count card
    - Add active theme indicator
    - Add homepage sections count
    - _Requirements: 1.3, 5.1, 8.1_

- [ ] 20. Documentation and Testing
  - [x] 20.1 Create user documentation
    - Write guide for centre management
    - Write guide for theme customization
    - Write guide for homepage content management
    - Include screenshots and examples
  
  - [ ]* 20.2 Run integration tests
    - Test complete centre management workflow
    - Test complete theme customization workflow
    - Test complete homepage content workflow
    - Test cross-module interactions
  
  - [ ]* 20.3 Perform security testing
    - Test authentication on all management pages
    - Test CSRF protection
    - Test SQL injection prevention
    - Test XSS prevention
    - Test file upload security

- [ ] 21. Final Checkpoint - System Enhancement Module Complete
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- The module integrates seamlessly with existing admin dashboard structure
- All new pages follow existing UI patterns and styling
- Database migrations maintain backward compatibility
