# Requirements Document

## Introduction

The System Enhancement Module is a comprehensive upgrade to the NIELIT Bhubaneswar Student Management System that introduces three major administrative capabilities: Centre Management, Theme Customization, and Homepage Content Management. This module empowers administrators to manage multiple training centres, customize the visual appearance of the application, and dynamically control homepage content without requiring code changes.

## Glossary

- **System**: The NIELIT Bhubaneswar Student Management System
- **Centre**: A physical training location (e.g., NIELIT Bhubaneswar, NIELIT Balasore Extension)
- **Theme**: A collection of visual styling configurations including colors, logos, and fonts
- **Homepage_Content**: Dynamic content sections displayed on the public-facing homepage
- **Admin**: An authenticated administrator user with system management privileges
- **Public_User**: A visitor accessing the public-facing website
- **CRUD**: Create, Read, Update, Delete operations
- **WYSIWYG**: What You See Is What You Get editor interface

## Requirements

### Requirement 1: Centre Management

**User Story:** As an administrator, I want to manage multiple training centres, so that I can organize courses and students by their physical location.

#### Acceptance Criteria

1. THE System SHALL store centre information including centre_id, centre_name, code, address, city, state, pincode, phone, email, and active status
2. WHEN an administrator creates a new centre, THE System SHALL validate that the centre code is unique
3. WHEN an administrator views the centres list, THE System SHALL display all centres with their complete information
4. WHEN an administrator updates a centre, THE System SHALL save the changes and update the modified timestamp
5. WHEN an administrator deactivates a centre, THE System SHALL mark it as inactive without deleting the record
6. THE System SHALL maintain creation and modification timestamps for all centre records

### Requirement 2: Course-Centre Association

**User Story:** As an administrator, I want to assign courses to specific centres, so that students can identify where training is conducted.

#### Acceptance Criteria

1. WHEN an administrator creates or edits a course, THE System SHALL provide a dropdown to select the associated centre
2. THE System SHALL allow a course to be associated with exactly one centre or no centre
3. WHEN a centre is deactivated, THE System SHALL maintain existing course associations but prevent new assignments
4. WHEN displaying course information, THE System SHALL show the associated centre name
5. THE System SHALL use foreign key constraints to maintain referential integrity between courses and centres

### Requirement 3: Public Centre Filtering

**User Story:** As a public user, I want to filter courses by training centre, so that I can find courses offered at my preferred location.

#### Acceptance Criteria

1. WHEN a public user visits the courses page, THE System SHALL display a centre filter dropdown
2. THE System SHALL populate the centre filter with all active centres
3. WHEN a public user selects a centre filter, THE System SHALL display only courses associated with that centre
4. WHEN a public user clears the centre filter, THE System SHALL display all published courses
5. THE System SHALL maintain other active filters (category, status) when applying centre filters

### Requirement 4: Theme Configuration Storage

**User Story:** As an administrator, I want to store theme configurations in the database, so that visual customizations persist across sessions.

#### Acceptance Criteria

1. THE System SHALL store theme configurations including theme_id, theme_name, primary_color, secondary_color, accent_color, logo_path, and active status
2. THE System SHALL allow only one theme to be marked as active at any time
3. WHEN an administrator activates a new theme, THE System SHALL automatically deactivate the previously active theme
4. THE System SHALL validate color values to ensure they are valid hexadecimal color codes
5. THE System SHALL store logo file paths and validate that uploaded files are valid image formats
6. THE System SHALL maintain creation and modification timestamps for all theme records

### Requirement 5: Theme Management Interface

**User Story:** As an administrator, I want to create and edit themes through a web interface, so that I can customize the application appearance without technical knowledge.

#### Acceptance Criteria

1. WHEN an administrator accesses the theme management page, THE System SHALL display all existing themes
2. WHEN an administrator creates a new theme, THE System SHALL provide input fields for theme name, colors, and logo upload
3. WHEN an administrator uploads a logo, THE System SHALL validate the file type and size
4. WHEN an administrator edits a theme, THE System SHALL pre-populate the form with existing values
5. WHEN an administrator activates a theme, THE System SHALL apply it immediately to the application
6. THE System SHALL provide a color picker interface for selecting theme colors
7. WHEN an administrator previews a theme, THE System SHALL show a live preview without activating it

### Requirement 6: Dynamic Theme Loading

**User Story:** As a system component, I want to load the active theme dynamically, so that all pages reflect the current theme configuration.

#### Acceptance Criteria

1. WHEN any page loads, THE System SHALL query the database for the active theme
2. THE System SHALL inject theme colors into CSS custom properties
3. WHEN no active theme exists, THE System SHALL use default hardcoded theme values
4. THE System SHALL cache theme configuration to minimize database queries
5. WHEN a theme is activated, THE System SHALL clear the theme cache
6. THE System SHALL apply theme logo to the navigation header and footer

### Requirement 7: Homepage Content Storage

**User Story:** As an administrator, I want to store homepage content sections in the database, so that I can update the homepage without modifying code.

#### Acceptance Criteria

1. THE System SHALL store homepage content sections including section_id, section_key, section_title, section_content, section_type, display_order, and active status
2. THE System SHALL support multiple section types including banner, announcement, featured_course, and text_block
3. WHEN an administrator creates a content section, THE System SHALL assign a unique section_key
4. THE System SHALL allow administrators to specify display order for content sections
5. THE System SHALL store rich HTML content for text-based sections
6. THE System SHALL maintain creation and modification timestamps for all content sections

### Requirement 8: Homepage Content Management Interface

**User Story:** As an administrator, I want to edit homepage content through a web interface, so that I can update announcements and featured content easily.

#### Acceptance Criteria

1. WHEN an administrator accesses the homepage management page, THE System SHALL display all content sections ordered by display_order
2. WHEN an administrator creates a new content section, THE System SHALL provide a form with fields for title, content, type, and order
3. WHEN an administrator edits text content, THE System SHALL provide a WYSIWYG editor or structured input fields
4. WHEN an administrator reorders sections, THE System SHALL update the display_order values
5. WHEN an administrator deactivates a section, THE System SHALL hide it from the public homepage
6. THE System SHALL provide a preview function to show how content will appear on the homepage

### Requirement 9: Dynamic Homepage Rendering

**User Story:** As a public user, I want to see current homepage content, so that I receive up-to-date information about courses and announcements.

#### Acceptance Criteria

1. WHEN the homepage loads, THE System SHALL query the database for all active content sections
2. THE System SHALL render content sections in order specified by display_order
3. WHEN no content sections exist for a section type, THE System SHALL display a default message or hide the section
4. THE System SHALL sanitize HTML content to prevent XSS attacks
5. THE System SHALL cache homepage content to improve performance
6. WHEN content is updated, THE System SHALL clear the homepage cache

### Requirement 10: File Upload Management

**User Story:** As an administrator, I want to upload images for themes and homepage content, so that I can customize visual elements.

#### Acceptance Criteria

1. WHEN an administrator uploads a file, THE System SHALL validate the file type against allowed extensions
2. THE System SHALL validate file size to prevent excessively large uploads
3. WHEN a file upload succeeds, THE System SHALL store the file in a designated uploads directory
4. THE System SHALL generate unique filenames to prevent conflicts
5. WHEN a file is replaced, THE System SHALL delete the old file from the filesystem
6. THE System SHALL store relative file paths in the database

### Requirement 11: Security and Access Control

**User Story:** As a system administrator, I want management interfaces to be protected, so that only authorized users can modify system configuration.

#### Acceptance Criteria

1. WHEN an unauthenticated user attempts to access management pages, THE System SHALL redirect to the login page
2. THE System SHALL verify admin session validity before processing any management requests
3. WHEN processing form submissions, THE System SHALL validate CSRF tokens
4. THE System SHALL sanitize all user inputs to prevent SQL injection
5. THE System SHALL log all administrative actions for audit purposes

### Requirement 12: Data Migration and Compatibility

**User Story:** As a system administrator, I want existing data to remain functional, so that the enhancement module integrates seamlessly with current functionality.

#### Acceptance Criteria

1. WHEN the centres table is created, THE System SHALL populate it with existing training centre data
2. WHEN the centre_id column is added to courses, THE System SHALL set default values for existing courses
3. WHEN no theme is configured, THE System SHALL use existing CSS stylesheets
4. WHEN no homepage content exists, THE System SHALL display existing hardcoded content
5. THE System SHALL maintain backward compatibility with existing course and student management features
