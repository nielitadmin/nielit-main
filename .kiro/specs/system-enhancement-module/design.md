# Design Document: System Enhancement Module

## Overview

The System Enhancement Module extends the NIELIT Bhubaneswar Student Management System with three integrated administrative capabilities: Centre Management, Theme Customization, and Homepage Content Management. This design follows the existing PHP/MySQL architecture and integrates seamlessly with the current admin dashboard structure.

The module enables administrators to:
- Manage multiple training centres and associate courses with specific locations
- Customize application themes (colors, logos) through a web interface
- Edit homepage content dynamically without code modifications

## Architecture

### System Context

The enhancement module integrates with the existing system architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                    NIELIT Student Management System          │
├─────────────────────────────────────────────────────────────┤
│  Existing Components:                                        │
│  - Admin Dashboard (admin/dashboard.php)                     │
│  - Course Management (admin/manage_courses.php)              │
│  - Student Management (admin/students.php)                   │
│  - Public Website (index.php, public/courses.php)           │
│  - Database Layer (config/database.php)                      │
├─────────────────────────────────────────────────────────────┤
│  New Enhancement Module:                                     │
│  ┌─────────────────┐  ┌──────────────────┐  ┌─────────────┐│
│  │ Centre          │  │ Theme            │  │ Homepage    ││
│  │ Management      │  │ Customization    │  │ Content Mgmt││
│  └─────────────────┘  └──────────────────┘  └─────────────┘│
└─────────────────────────────────────────────────────────────┘
```

### Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Bootstrap 5.3 (existing)
- **Icons**: Font Awesome 6.4 (existing)
- **File Storage**: Local filesystem

## Components and Interfaces

### 1. Centre Management Module

#### Database Schema

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
```


#### PHP Interface: manage_centres.php

**Purpose**: CRUD interface for training centres

**Key Functions**:
```php
// Create new centre
function createCentre($conn, $data) {
    $stmt = $conn->prepare("INSERT INTO centres (name, code, address, city, state, pincode, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $data['name'], $data['code'], $data['address'], $data['city'], $data['state'], $data['pincode'], $data['phone'], $data['email']);
    return $stmt->execute();
}

// Update existing centre
function updateCentre($conn, $id, $data) {
    $stmt = $conn->prepare("UPDATE centres SET name=?, code=?, address=?, city=?, state=?, pincode=?, phone=?, email=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $data['name'], $data['code'], $data['address'], $data['city'], $data['state'], $data['pincode'], $data['phone'], $data['email'], $id);
    return $stmt->execute();
}

// Toggle centre active status
function toggleCentreStatus($conn, $id, $status) {
    $stmt = $conn->prepare("UPDATE centres SET is_active=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    return $stmt->execute();
}

// Get all centres
function getAllCentres($conn, $active_only = false) {
    $sql = "SELECT * FROM centres";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name ASC";
    return $conn->query($sql);
}
```

**UI Components**:
- Data table displaying all centres
- Add/Edit modal forms
- Status toggle buttons
- Search and filter functionality

#### Course Integration

**Database Modification**:
```sql
ALTER TABLE courses 
ADD COLUMN centre_id INT(11) DEFAULT NULL AFTER id,
ADD KEY idx_centre (centre_id),
ADD CONSTRAINT fk_course_centre FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE SET NULL;
```

**Updated manage_courses.php**:
- Add centre dropdown in course add/edit forms
- Display centre name in course listings
- Filter courses by centre

**Updated public/courses.php**:
- Add centre filter dropdown
- Filter courses by selected centre
- Display centre information with course details

### 2. Theme Customization System

#### Database Schema

```sql
CREATE TABLE themes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    theme_name VARCHAR(100) NOT NULL,
    primary_color VARCHAR(7) NOT NULL,
    secondary_color VARCHAR(7) NOT NULL,
    accent_color VARCHAR(7) NOT NULL,
    logo_path VARCHAR(255),
    favicon_path VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```


#### PHP Interface: manage_themes.php

**Purpose**: Theme creation, editing, and activation interface

**Key Functions**:
```php
// Create new theme
function createTheme($conn, $data) {
    $stmt = $conn->prepare("INSERT INTO themes (theme_name, primary_color, secondary_color, accent_color, logo_path, favicon_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $data['theme_name'], $data['primary_color'], $data['secondary_color'], $data['accent_color'], $data['logo_path'], $data['favicon_path']);
    return $stmt->execute();
}

// Update theme
function updateTheme($conn, $id, $data) {
    $stmt = $conn->prepare("UPDATE themes SET theme_name=?, primary_color=?, secondary_color=?, accent_color=?, logo_path=?, favicon_path=? WHERE id=?");
    $stmt->bind_param("ssssssi", $data['theme_name'], $data['primary_color'], $data['secondary_color'], $data['accent_color'], $data['logo_path'], $data['favicon_path'], $id);
    return $stmt->execute();
}

// Activate theme (deactivate others)
function activateTheme($conn, $id) {
    $conn->query("UPDATE themes SET is_active = 0");
    $stmt = $conn->prepare("UPDATE themes SET is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Get active theme
function getActiveTheme($conn) {
    $result = $conn->query("SELECT * FROM themes WHERE is_active = 1 LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

// Validate color format
function validateColor($color) {
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

// Handle logo upload
function uploadLogo($file, $upload_dir = '../uploads/themes/') {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $filename = uniqid('logo_') . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}
```

**UI Components**:
- Theme list with preview cards
- Add/Edit theme modal with color pickers
- Logo upload interface
- Live preview functionality
- Activate/Deactivate buttons

#### Theme Loader: includes/theme_loader.php

**Purpose**: Dynamically load and apply active theme across all pages

**Implementation**:
```php
<?php
// Theme loader - include in all pages
function loadActiveTheme($conn) {
    static $theme_cache = null;
    
    if ($theme_cache === null) {
        $result = $conn->query("SELECT * FROM themes WHERE is_active = 1 LIMIT 1");
        $theme_cache = $result ? $result->fetch_assoc() : getDefaultTheme();
    }
    
    return $theme_cache;
}

function getDefaultTheme() {
    return [
        'primary_color' => '#0d47a1',
        'secondary_color' => '#1565c0',
        'accent_color' => '#ffc107',
        'logo_path' => 'assets/images/bhubaneswar_logo.png',
        'favicon_path' => 'assets/images/favicon.ico'
    ];
}

function injectThemeCSS($theme) {
    echo "<style>
        :root {
            --primary-color: {$theme['primary_color']};
            --secondary-color: {$theme['secondary_color']};
            --accent-color: {$theme['accent_color']};
        }
    </style>";
}

// Usage in pages
$active_theme = loadActiveTheme($conn);
injectThemeCSS($active_theme);
?>
```

**CSS Integration**:
Update existing stylesheets to use CSS custom properties:
```css
/* Replace hardcoded colors with variables */
.navbar {
    background-color: var(--primary-color, #0d47a1);
}

.btn-primary {
    background-color: var(--primary-color, #0d47a1);
}

.badge-primary {
    background-color: var(--secondary-color, #1565c0);
}

.accent-text {
    color: var(--accent-color, #ffc107);
}
```


### 3. Homepage Content Management

#### Database Schema

```sql
CREATE TABLE homepage_content (
    id INT(11) NOT NULL AUTO_INCREMENT,
    section_key VARCHAR(50) NOT NULL UNIQUE,
    section_title VARCHAR(255) NOT NULL,
    section_content TEXT,
    section_type ENUM('banner', 'announcement', 'featured_course', 'text_block', 'image_block') NOT NULL,
    display_order INT(11) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_section_key (section_key),
    KEY idx_active (is_active),
    KEY idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### PHP Interface: manage_homepage.php

**Purpose**: Content management interface for homepage sections

**Key Functions**:
```php
// Create content section
function createContentSection($conn, $data) {
    $stmt = $conn->prepare("INSERT INTO homepage_content (section_key, section_title, section_content, section_type, display_order) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $data['section_key'], $data['section_title'], $data['section_content'], $data['section_type'], $data['display_order']);
    return $stmt->execute();
}

// Update content section
function updateContentSection($conn, $id, $data) {
    $stmt = $conn->prepare("UPDATE homepage_content SET section_title=?, section_content=?, section_type=?, display_order=? WHERE id=?");
    $stmt->bind_param("sssii", $data['section_title'], $data['section_content'], $data['section_type'], $data['display_order'], $id);
    return $stmt->execute();
}

// Toggle section active status
function toggleSectionStatus($conn, $id, $status) {
    $stmt = $conn->prepare("UPDATE homepage_content SET is_active=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    return $stmt->execute();
}

// Get all content sections
function getAllContentSections($conn, $active_only = false) {
    $sql = "SELECT * FROM homepage_content";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY display_order ASC";
    return $conn->query($sql);
}

// Get content by section key
function getContentByKey($conn, $section_key) {
    $stmt = $conn->prepare("SELECT * FROM homepage_content WHERE section_key = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $section_key);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

// Reorder sections
function reorderSections($conn, $order_data) {
    foreach ($order_data as $id => $order) {
        $stmt = $conn->prepare("UPDATE homepage_content SET display_order = ? WHERE id = ?");
        $stmt->bind_param("ii", $order, $id);
        $stmt->execute();
    }
    return true;
}

// Sanitize HTML content
function sanitizeContent($content) {
    // Allow safe HTML tags
    $allowed_tags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span>';
    return strip_tags($content, $allowed_tags);
}
```

**UI Components**:
- Content sections list with drag-and-drop reordering
- Add/Edit modal with WYSIWYG editor (TinyMCE or CKEditor)
- Section type selector
- Preview functionality
- Status toggle buttons

#### Updated index.php

**Purpose**: Dynamically render homepage content from database

**Implementation**:
```php
<?php
require_once 'config/database.php';
require_once 'includes/theme_loader.php';

// Load active theme
$active_theme = loadActiveTheme($conn);

// Load homepage content sections
$content_sections = getAllContentSections($conn, true);

// Group sections by type
$banners = [];
$announcements = [];
$featured_courses = [];
$text_blocks = [];

while ($section = $content_sections->fetch_assoc()) {
    switch ($section['section_type']) {
        case 'banner':
            $banners[] = $section;
            break;
        case 'announcement':
            $announcements[] = $section;
            break;
        case 'featured_course':
            $featured_courses[] = $section;
            break;
        case 'text_block':
            $text_blocks[] = $section;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Theme injection -->
    <?php injectThemeCSS($active_theme); ?>
</head>
<body>
    <!-- Dynamic banner section -->
    <?php if (!empty($banners)): ?>
        <div class="banner-section">
            <?php foreach ($banners as $banner): ?>
                <div class="banner-item">
                    <h2><?php echo htmlspecialchars($banner['section_title']); ?></h2>
                    <div><?php echo $banner['section_content']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Dynamic announcements -->
    <?php if (!empty($announcements)): ?>
        <div class="announcements-section">
            <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-item">
                    <h3><?php echo htmlspecialchars($announcement['section_title']); ?></h3>
                    <div><?php echo $announcement['section_content']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Fallback to hardcoded content if no database content -->
    <?php if (empty($banners) && empty($announcements)): ?>
        <!-- Original hardcoded content -->
    <?php endif; ?>
</body>
</html>
```


## Data Models

### Centre Model

```php
class Centre {
    public $id;
    public $name;
    public $code;
    public $address;
    public $city;
    public $state;
    public $pincode;
    public $phone;
    public $email;
    public $is_active;
    public $created_at;
    public $updated_at;
    
    public function validate() {
        $errors = [];
        
        if (empty($this->name)) {
            $errors[] = "Centre name is required";
        }
        
        if (empty($this->code)) {
            $errors[] = "Centre code is required";
        } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $this->code)) {
            $errors[] = "Centre code must be 2-10 uppercase alphanumeric characters";
        }
        
        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (!empty($this->phone) && !preg_match('/^[0-9\-\+\(\) ]{10,20}$/', $this->phone)) {
            $errors[] = "Invalid phone format";
        }
        
        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }
}
```

### Theme Model

```php
class Theme {
    public $id;
    public $theme_name;
    public $primary_color;
    public $secondary_color;
    public $accent_color;
    public $logo_path;
    public $favicon_path;
    public $is_active;
    public $created_at;
    public $updated_at;
    
    public function validate() {
        $errors = [];
        
        if (empty($this->theme_name)) {
            $errors[] = "Theme name is required";
        }
        
        if (!$this->validateColor($this->primary_color)) {
            $errors[] = "Invalid primary color format";
        }
        
        if (!$this->validateColor($this->secondary_color)) {
            $errors[] = "Invalid secondary color format";
        }
        
        if (!$this->validateColor($this->accent_color)) {
            $errors[] = "Invalid accent color format";
        }
        
        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }
    
    private function validateColor($color) {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }
}
```

### HomepageContent Model

```php
class HomepageContent {
    public $id;
    public $section_key;
    public $section_title;
    public $section_content;
    public $section_type;
    public $display_order;
    public $is_active;
    public $created_at;
    public $updated_at;
    
    const ALLOWED_TYPES = ['banner', 'announcement', 'featured_course', 'text_block', 'image_block'];
    
    public function validate() {
        $errors = [];
        
        if (empty($this->section_key)) {
            $errors[] = "Section key is required";
        } elseif (!preg_match('/^[a-z0-9_]{3,50}$/', $this->section_key)) {
            $errors[] = "Section key must be 3-50 lowercase alphanumeric characters with underscores";
        }
        
        if (empty($this->section_title)) {
            $errors[] = "Section title is required";
        }
        
        if (!in_array($this->section_type, self::ALLOWED_TYPES)) {
            $errors[] = "Invalid section type";
        }
        
        if (!is_numeric($this->display_order) || $this->display_order < 0) {
            $errors[] = "Display order must be a non-negative number";
        }
        
        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }
    
    public function sanitizeContent() {
        $allowed_tags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span>';
        $this->section_content = strip_tags($this->section_content, $allowed_tags);
    }
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Centre Code Uniqueness
*For any* two centres in the database, their centre codes must be different
**Validates: Requirements 1.2**

### Property 2: Single Active Theme
*For any* point in time, at most one theme can be marked as active in the database
**Validates: Requirements 4.2**

### Property 3: Theme Color Validation
*For any* theme record, all color fields (primary_color, secondary_color, accent_color) must be valid hexadecimal color codes
**Validates: Requirements 4.4**

### Property 4: Section Key Uniqueness
*For any* two homepage content sections, their section keys must be different
**Validates: Requirements 7.3**

### Property 5: Course-Centre Referential Integrity
*For any* course with a non-null centre_id, there must exist a corresponding centre record with that id
**Validates: Requirements 2.5**

### Property 6: File Upload Size Validation
*For any* file upload operation, files exceeding the maximum size limit must be rejected
**Validates: Requirements 10.2**

### Property 7: File Type Validation
*For any* file upload operation, only files with allowed extensions must be accepted
**Validates: Requirements 10.1**

### Property 8: Content Sanitization
*For any* homepage content section, the section_content field must not contain dangerous HTML tags or scripts
**Validates: Requirements 9.4**

### Property 9: Display Order Consistency
*For any* set of homepage content sections, no two sections should have the same display_order value
**Validates: Requirements 7.4**

### Property 10: Theme Activation Atomicity
*For any* theme activation operation, exactly one theme must be active after the operation completes
**Validates: Requirements 4.3**


## Error Handling

### Database Errors

**Connection Failures**:
```php
try {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("System temporarily unavailable. Please try again later.");
    }
} catch (Exception $e) {
    error_log("Database exception: " . $e->getMessage());
    die("System error. Please contact administrator.");
}
```

**Query Failures**:
```php
if (!$stmt->execute()) {
    error_log("Query failed: " . $stmt->error);
    $_SESSION['message'] = "Operation failed. Please try again.";
    $_SESSION['message_type'] = "danger";
    return false;
}
```

**Constraint Violations**:
```php
// Handle duplicate centre code
if ($conn->errno === 1062) {
    $_SESSION['message'] = "Centre code already exists. Please use a different code.";
    $_SESSION['message_type'] = "danger";
    return false;
}

// Handle foreign key violations
if ($conn->errno === 1451) {
    $_SESSION['message'] = "Cannot delete centre. Courses are associated with it.";
    $_SESSION['message_type'] = "danger";
    return false;
}
```

### File Upload Errors

**Invalid File Type**:
```php
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
if (!in_array($file['type'], $allowed_types)) {
    return [
        'success' => false,
        'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, SVG'
    ];
}
```

**File Size Exceeded**:
```php
$max_size = 2 * 1024 * 1024; // 2MB
if ($file['size'] > $max_size) {
    return [
        'success' => false,
        'message' => 'File size exceeds 2MB limit'
    ];
}
```

**Upload Failure**:
```php
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    error_log("File upload failed: " . $destination);
    return [
        'success' => false,
        'message' => 'Failed to save file. Please try again.'
    ];
}
```

### Validation Errors

**Input Validation**:
```php
function validateCentreInput($data) {
    $errors = [];
    
    if (empty($data['name'])) {
        $errors['name'] = "Centre name is required";
    }
    
    if (empty($data['code'])) {
        $errors['code'] = "Centre code is required";
    } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $data['code'])) {
        $errors['code'] = "Centre code must be 2-10 uppercase alphanumeric characters";
    }
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    
    return $errors;
}
```

**Color Validation**:
```php
function validateThemeColors($data) {
    $errors = [];
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['primary_color'])) {
        $errors['primary_color'] = "Invalid color format. Use #RRGGBB";
    }
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['secondary_color'])) {
        $errors['secondary_color'] = "Invalid color format. Use #RRGGBB";
    }
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['accent_color'])) {
        $errors['accent_color'] = "Invalid color format. Use #RRGGBB";
    }
    
    return $errors;
}
```

### Security Errors

**Authentication Failures**:
```php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit();
}
```

**CSRF Protection**:
```php
// Generate token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request. Please try again.");
    }
}
```

**SQL Injection Prevention**:
```php
// Always use prepared statements
$stmt = $conn->prepare("SELECT * FROM centres WHERE code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
```

**XSS Prevention**:
```php
// Sanitize output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// Sanitize HTML content
$allowed_tags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span>';
$clean_content = strip_tags($content, $allowed_tags);
```

## Testing Strategy

### Unit Testing

**Test Framework**: PHPUnit 9.x

**Test Coverage**:
- Model validation methods
- Data sanitization functions
- File upload handlers
- Color validation functions
- Database query builders

**Example Unit Tests**:
```php
class CentreTest extends TestCase {
    public function testValidCentreCode() {
        $centre = new Centre();
        $centre->code = "BBSR";
        $this->assertTrue($centre->validateCode());
    }
    
    public function testInvalidCentreCode() {
        $centre = new Centre();
        $centre->code = "invalid-code";
        $this->assertFalse($centre->validateCode());
    }
    
    public function testEmailValidation() {
        $centre = new Centre();
        $centre->email = "invalid-email";
        $result = $centre->validate();
        $this->assertFalse($result['valid']);
        $this->assertContains("Invalid email format", $result['errors']);
    }
}

class ThemeTest extends TestCase {
    public function testValidColorFormat() {
        $theme = new Theme();
        $theme->primary_color = "#0d47a1";
        $this->assertTrue($theme->validateColor($theme->primary_color));
    }
    
    public function testInvalidColorFormat() {
        $theme = new Theme();
        $theme->primary_color = "blue";
        $this->assertFalse($theme->validateColor($theme->primary_color));
    }
    
    public function testSingleActiveTheme() {
        // Test that activating a theme deactivates others
        $theme1 = new Theme();
        $theme1->activate($conn);
        
        $theme2 = new Theme();
        $theme2->activate($conn);
        
        $active_count = $conn->query("SELECT COUNT(*) as count FROM themes WHERE is_active = 1")->fetch_assoc()['count'];
        $this->assertEquals(1, $active_count);
    }
}
```

### Property-Based Testing

**Test Framework**: Hypothesis (via PHP port or custom implementation)

**Configuration**: Minimum 100 iterations per property test

**Property Tests**:

```php
/**
 * Feature: system-enhancement-module, Property 1: Centre Code Uniqueness
 * For any two centres in the database, their centre codes must be different
 */
public function testCentreCodeUniqueness() {
    for ($i = 0; $i < 100; $i++) {
        $code1 = $this->generateRandomCentreCode();
        $code2 = $this->generateRandomCentreCode();
        
        $centre1 = $this->createCentre(['code' => $code1]);
        
        // Attempting to create another centre with same code should fail
        if ($code1 === $code2) {
            $this->expectException(DuplicateKeyException::class);
        }
        
        $centre2 = $this->createCentre(['code' => $code2]);
    }
}

/**
 * Feature: system-enhancement-module, Property 2: Single Active Theme
 * For any point in time, at most one theme can be marked as active
 */
public function testSingleActiveTheme() {
    for ($i = 0; $i < 100; $i++) {
        // Create random number of themes
        $theme_count = rand(1, 10);
        $themes = [];
        
        for ($j = 0; $j < $theme_count; $j++) {
            $themes[] = $this->createRandomTheme();
        }
        
        // Activate random theme
        $random_theme = $themes[array_rand($themes)];
        $random_theme->activate($this->conn);
        
        // Verify only one theme is active
        $active_count = $this->conn->query("SELECT COUNT(*) as count FROM themes WHERE is_active = 1")->fetch_assoc()['count'];
        $this->assertEquals(1, $active_count);
        
        // Cleanup
        $this->cleanupThemes();
    }
}

/**
 * Feature: system-enhancement-module, Property 3: Theme Color Validation
 * For any theme record, all color fields must be valid hexadecimal color codes
 */
public function testThemeColorValidation() {
    for ($i = 0; $i < 100; $i++) {
        $theme = new Theme();
        $theme->primary_color = $this->generateRandomColor();
        $theme->secondary_color = $this->generateRandomColor();
        $theme->accent_color = $this->generateRandomColor();
        
        $result = $theme->validate();
        
        // All colors should be valid hex format
        $this->assertTrue($result['valid']);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $theme->primary_color);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $theme->secondary_color);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $theme->accent_color);
    }
}

/**
 * Feature: system-enhancement-module, Property 8: Content Sanitization
 * For any homepage content section, the section_content must not contain dangerous HTML
 */
public function testContentSanitization() {
    for ($i = 0; $i < 100; $i++) {
        $dangerous_content = $this->generateContentWithScripts();
        
        $content = new HomepageContent();
        $content->section_content = $dangerous_content;
        $content->sanitizeContent();
        
        // Verify no script tags remain
        $this->assertStringNotContainsString('<script', $content->section_content);
        $this->assertStringNotContainsString('javascript:', $content->section_content);
        $this->assertStringNotContainsString('onerror=', $content->section_content);
    }
}
```

### Integration Testing

**Test Scenarios**:
1. Create centre → Assign to course → Filter courses by centre
2. Create theme → Activate theme → Verify theme loads on public pages
3. Create homepage content → Verify content displays on index.php
4. Upload logo → Activate theme → Verify logo appears in navigation
5. Deactivate centre → Verify courses still display but with null centre

**Test Database**: Use separate test database with sample data

### Manual Testing Checklist

**Centre Management**:
- [ ] Create new centre with all fields
- [ ] Create centre with duplicate code (should fail)
- [ ] Edit existing centre
- [ ] Deactivate centre
- [ ] Assign centre to course
- [ ] Filter courses by centre on public page

**Theme Customization**:
- [ ] Create new theme with color picker
- [ ] Upload logo image
- [ ] Activate theme
- [ ] Verify colors apply across all pages
- [ ] Verify logo displays in header
- [ ] Preview theme before activation

**Homepage Content**:
- [ ] Create banner section
- [ ] Create announcement section
- [ ] Reorder sections via drag-and-drop
- [ ] Edit content with WYSIWYG editor
- [ ] Deactivate section
- [ ] Verify content displays on homepage

**Security**:
- [ ] Access management pages without login (should redirect)
- [ ] Submit form without CSRF token (should fail)
- [ ] Upload invalid file type (should fail)
- [ ] Upload oversized file (should fail)
- [ ] Inject SQL in form fields (should be sanitized)
- [ ] Inject XSS in content fields (should be sanitized)
