# New Features Implementation Plan

## Overview
This document outlines the implementation plan for the following new features requested:

1. **Centre Management Module** - Manage centres and link them to courses
2. **Theme Customization System** - Allow admin to switch between multiple themes
3. **Index Page Content Management** - Allow admin to modify index page content
4. **Remove Level Labels** - Remove "LEVEL 1", "LEVEL 2", etc. from index page
5. **Centre-Based Course Filtering** - Show courses based on selected centre

---

## Feature 1: Centre Management Module

### Database Schema
```sql
CREATE TABLE centres (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    phone VARCHAR(20),
    email VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_code (code),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add centre_id to courses table
ALTER TABLE courses 
ADD COLUMN centre_id INT(11) DEFAULT NULL AFTER id,
ADD KEY idx_centre (centre_id),
ADD CONSTRAINT fk_course_centre FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE SET NULL;
```

### Admin Pages to Create
1. `admin/manage_centres.php` - List all centres with add/edit/delete options
2. `admin/add_centre.php` - Form to add new centre
3. `admin/edit_centre.php` - Form to edit existing centre

### Integration Points
- Update `admin/manage_courses.php` to include centre selection dropdown
- Update `admin/edit_course.php` to show and edit centre assignment
- Update `public/courses.php` to filter by centre

---

## Feature 2: Theme Customization System

### Database Schema
```sql
CREATE TABLE themes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    primary_color VARCHAR(7) NOT NULL,
    secondary_color VARCHAR(7) NOT NULL,
    accent_color VARCHAR(7) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default themes
INSERT INTO themes (name, slug, primary_color, secondary_color, accent_color, is_active) VALUES
('Professional Blue', 'professional-blue', '#0d47a1', '#1565c0', '#ffc107', 1),
('Modern Green', 'modern-green', '#1b5e20', '#388e3c', '#ff9800', 0),
('Corporate Purple', 'corporate-purple', '#4a148c', '#7b1fa2', '#ffc107', 0),
('Tech Orange', 'tech-orange', '#e65100', '#f57c00', '#2196f3', 0);
```

### Admin Pages to Create
1. `admin/manage_themes.php` - View and switch between themes
2. `admin/theme_preview.php` - Preview theme before activating

### Implementation
- Create `includes/theme_loader.php` to load active theme colors
- Update all pages to use theme variables from database
- Add theme switcher in admin dashboard

---

## Feature 3: Index Page Content Management

### Database Schema
```sql
CREATE TABLE homepage_content (
    id INT(11) NOT NULL AUTO_INCREMENT,
    section_key VARCHAR(100) NOT NULL UNIQUE,
    section_title VARCHAR(255),
    content TEXT,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT(11) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT(11),
    PRIMARY KEY (id),
    KEY idx_section (section_key),
    CONSTRAINT fk_homepage_admin FOREIGN KEY (updated_by) REFERENCES admin(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default content sections
INSERT INTO homepage_content (section_key, section_title, content, display_order) VALUES
('welcome_heading', 'Welcome Heading', 'Welcome to NIELIT Bhubaneswar', 1),
('welcome_text', 'Welcome Text', 'Established in 2021, we are a premier center dedicated to skilling and reskilling professionals in Information, Electronics, and Communication Technology (IECT).', 2),
('notice_ticker', 'Notice Ticker', 'Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Balasore Extension Center today.', 3);
```

### Admin Pages to Create
1. `admin/manage_homepage.php` - Edit homepage content sections
2. AJAX endpoint for live preview

### Implementation
- Create content management interface with WYSIWYG editor
- Add show/hide toggle for each section
- Allow reordering of sections

---

## Feature 4: Remove Level Labels from Index Page

### Changes Required
- Remove all "LEVEL 1", "LEVEL 2", "LEVEL 3", "LEVEL 4" badge indicators
- Keep the section structure but remove level indicators
- Update CSS to remove level-specific animations

### Files to Modify
- `index.php` - Remove level badge HTML elements
- Keep section structure intact

---

## Feature 5: Centre-Based Course Filtering

### Implementation
1. Add centre dropdown on `public/courses.php`
2. Filter courses by selected centre
3. Show "All Centres" option by default
4. Update course display to show centre name

### Files to Modify
- `public/courses.php` - Add centre filter dropdown and filtering logic
- `student/register.php` - Show centre information during registration

---

## Implementation Priority

### Phase 1 (High Priority)
1. Remove Level Labels from Index Page (Quick win)
2. Centre Management Module (Database + Admin Pages)

### Phase 2 (Medium Priority)
3. Centre-Based Course Filtering
4. Index Page Content Management

### Phase 3 (Enhancement)
5. Theme Customization System

---

## Estimated Timeline
- Phase 1: 2-3 hours
- Phase 2: 3-4 hours
- Phase 3: 4-5 hours
- Total: 9-12 hours

---

## Next Steps
1. Get user confirmation on implementation priority
2. Create database migration scripts
3. Implement features phase by phase
4. Test each feature thoroughly
5. Deploy to production

