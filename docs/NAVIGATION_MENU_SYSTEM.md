# Navigation Menu Management System

## Overview
The Navigation Menu Management System allows administrators to dynamically control the navigation menu items displayed on the public website (index.php) through an admin interface.

## Features

### 1. Dynamic Menu Management
- Add, edit, and delete menu items through admin interface
- Support for parent-child relationships (dropdown menus)
- Drag-and-drop reordering (display order)
- Toggle active/inactive status
- Set link target (same window or new window)
- Optional FontAwesome icons

### 2. Database-Driven
- All menu items stored in `navigation_menu` table
- Automatic fallback to hardcoded menu if table doesn't exist
- Backward compatible with existing installations

### 3. Admin Interface
- Accessible from: `admin/manage_navigation.php`
- Quick access button in `admin/manage_homepage.php`
- CRUD operations with CSRF protection
- Real-time status toggling

## Database Schema

### Table: `navigation_menu`

```sql
CREATE TABLE `navigation_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `target` enum('_self','_blank') NOT NULL DEFAULT '_self',
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `display_order` (`display_order`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_navigation_parent` FOREIGN KEY (`parent_id`) REFERENCES `navigation_menu` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Field Descriptions

- **id**: Unique identifier for each menu item
- **label**: Display text for the menu item (e.g., "Home", "Student Zone")
- **url**: Link URL (use "#" for dropdown parent items)
- **parent_id**: ID of parent menu item (NULL for top-level items)
- **display_order**: Order in which items appear (lower numbers first)
- **is_active**: Whether the menu item is visible (1 = active, 0 = inactive)
- **target**: Link target (_self = same window, _blank = new window)
- **icon**: Optional FontAwesome icon class (e.g., "fas fa-home")
- **created_at**: Timestamp when item was created
- **updated_at**: Timestamp when item was last updated

## Installation

### Step 1: Run Migration
```bash
# Import the SQL migration file
mysql -u username -p database_name < migrations/add_navigation_menu.sql
```

Or execute the SQL file through phpMyAdmin or your database management tool.

### Step 2: Verify Installation
1. Log in to admin panel
2. Go to "Manage Homepage Content"
3. Click "Edit Navigation Menu" button
4. You should see the default menu items

## Usage Guide

### Accessing the Navigation Menu Editor

1. Log in to admin panel: `admin/login.php`
2. Navigate to: **Homepage Content** → **Edit Navigation Menu**
3. Or directly access: `admin/manage_navigation.php`

### Adding a New Menu Item

1. Click **"Add Menu Item"** button
2. Fill in the form:
   - **Label**: Display text (e.g., "About Us")
   - **URL**: Link destination (e.g., "public/about.php")
   - **Parent Menu**: Select parent for dropdown, or "None" for top-level
   - **Display Order**: Number to control position (lower = earlier)
   - **Target**: Same Window or New Window
   - **Icon**: Optional FontAwesome class (e.g., "fas fa-info-circle")
3. Click **"Save Menu Item"**

### Creating Dropdown Menus

1. Create parent item:
   - Label: "Student Zone"
   - URL: "#" (use hash for dropdown parents)
   - Parent Menu: None (Top Level)
   
2. Create child items:
   - Label: "Courses Offered"
   - URL: "public/courses.php"
   - Parent Menu: Select "Student Zone"
   - Display Order: 1
   
3. Add more children as needed with incremental display orders

### Editing Menu Items

1. Click the **Edit** button (pencil icon) on any menu item
2. Modify the fields as needed
3. Click **"Save Menu Item"**

### Reordering Menu Items

1. Change the **Display Order** value when editing
2. Lower numbers appear first
3. Items are sorted by display order within their level (parent/child)

### Toggling Active Status

1. Click the **Toggle** button (toggle icon) on any menu item
2. Inactive items won't appear on the public website
3. Useful for temporarily hiding menu items without deleting

### Deleting Menu Items

1. Click the **Delete** button (trash icon)
2. Confirm the deletion
3. **Warning**: Deleting a parent item will also delete all its children

## Menu Structure Examples

### Example 1: Simple Top-Level Menu
```
Home (index.php)
About (public/about.php)
Contact (public/contact.php)
```

### Example 2: Menu with Dropdown
```
Home (index.php)
Student Zone (#)
  ├─ Courses Offered (public/courses.php)
  ├─ Student Portal (student/login.php)
  └─ Fees (student/fees.php)
Admin (#)
  ├─ Admin Login (admin/login.php)
  └─ Finance Login (/Salary_Slip/login.php)
Contact (public/contact.php)
```

## Technical Details

### Files Created/Modified

**New Files:**
- `migrations/add_navigation_menu.sql` - Database migration
- `admin/manage_navigation.php` - Admin interface for menu management
- `includes/navigation_helper.php` - Helper functions for menu rendering
- `docs/NAVIGATION_MENU_SYSTEM.md` - This documentation

**Modified Files:**
- `admin/manage_homepage.php` - Added "Edit Navigation Menu" button
- `index.php` - Updated to load menu from database

### Helper Functions

**`getNavigationMenu($conn)`**
- Fetches all active menu items from database
- Organizes items in parent-child hierarchy
- Returns array of menu items

**`renderNavigationMenu($menu_items, $current_page)`**
- Generates HTML markup for navigation menu
- Handles dropdown menus automatically
- Highlights active page

**`navigationMenuTableExists($conn)`**
- Checks if navigation_menu table exists
- Used for backward compatibility

**`getFallbackNavigationMenu()`**
- Returns hardcoded menu HTML
- Used when database table doesn't exist or is empty

### Backward Compatibility

The system includes automatic fallback:
1. If `navigation_menu` table doesn't exist → uses hardcoded menu
2. If table exists but is empty → uses hardcoded menu
3. If table has items → uses database menu

This ensures the website continues to work even if:
- Migration hasn't been run yet
- Database connection fails
- Table is accidentally deleted

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **SQL Injection Prevention**: Prepared statements used throughout
- **XSS Prevention**: All output is HTML-escaped
- **Admin Authentication**: Only logged-in admins can access
- **Cascading Deletes**: Foreign key constraints prevent orphaned records

## Troubleshooting

### Menu items not appearing on website

1. Check if items are marked as **Active** (green badge)
2. Verify the URL is correct
3. Clear browser cache
4. Check database connection

### Dropdown not working

1. Ensure parent item URL is set to "#"
2. Verify child items have correct parent_id
3. Check Bootstrap JavaScript is loaded
4. Inspect browser console for errors

### Can't delete menu item

1. Check if item has children (delete children first)
2. Verify database foreign key constraints
3. Check admin permissions

### Menu shows hardcoded items instead of database items

1. Run the migration: `migrations/add_navigation_menu.sql`
2. Verify table exists: `SHOW TABLES LIKE 'navigation_menu'`
3. Check if table has data: `SELECT * FROM navigation_menu`
4. Verify `includes/navigation_helper.php` is included in index.php

## Default Menu Items

The migration includes these default items matching the current menu:

1. **Home** (index.php)
2. **Job Fair** (DGR/index.php)
3. **PM SHRI KV JNV** (#)
   - Membership Form (Membership_Form/index.php)
4. **Student Zone** (#)
   - Courses Offered (public/courses.php)
   - Student Portal (student/login.php)
5. **Admin** (#)
   - Admin Login (admin/login.php)
   - Finance Login (/Salary_Slip/login.php)
   - Certificate (/Nielit_Project/index.php)
6. **Contact** (public/contact.php)

## Future Enhancements

Possible improvements for future versions:
- Drag-and-drop visual reordering
- Menu item visibility based on user role
- Multi-level dropdowns (3+ levels)
- Menu item descriptions/tooltips
- Custom CSS classes per item
- Menu item scheduling (show/hide by date)
- Import/export menu configuration

## Support

For issues or questions:
1. Check this documentation
2. Review the code comments in the files
3. Check database table structure
4. Verify all files are uploaded correctly
5. Test with default menu items first

---

**Last Updated**: March 5, 2026
**Version**: 1.0
**Author**: NIELIT Bhubaneswar Development Team
