# Schemes/Projects Module

A complete module for managing government schemes and projects (like SCSP, TSP, PMKVY, etc.) and linking them to courses.

## Features

- ✅ Create and manage schemes/projects
- ✅ Link multiple schemes to courses
- ✅ View courses under each scheme
- ✅ Modern UI with toast notifications
- ✅ Confirmation dialogs for delete operations
- ✅ Active/Inactive status management

## Installation

### Step 1: Install Database Tables

Run the installation script:
```
http://localhost/public_html/schemes_module/install_database.php
```

This will create:
- `schemes` table - Stores scheme information
- `course_schemes` table - Links courses to schemes (many-to-many)
- Sample schemes (SCSP, TSP, PMKVY, NSDM)

### Step 2: Add Navigation Link

Add this to your admin sidebar navigation (in all admin pages):

```php
<div class="nav-item">
    <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
        <i class="fas fa-project-diagram"></i> Schemes/Projects
    </a>
</div>
```

### Step 3: Update Course Management

The course add/edit pages need to be updated to include scheme selection. See `COURSE_INTEGRATION_GUIDE.md` for details.

## File Structure

```
schemes_module/
├── admin/
│   ├── manage_schemes.php    # List and manage all schemes
│   └── edit_scheme.php        # Edit scheme details
├── database_schemes_system.sql # Database structure
├── install_database.php       # Installation script
└── README.md                  # This file
```

## Database Structure

### schemes table
- `id` - Primary key
- `scheme_name` - Full name of the scheme
- `scheme_code` - Short code (e.g., SCSP, TSP)
- `description` - Scheme description
- `status` - Active/Inactive
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

### course_schemes table (Junction table)
- `id` - Primary key
- `course_id` - Foreign key to courses table
- `scheme_id` - Foreign key to schemes table
- `created_at` - Link creation timestamp

## Usage

### Managing Schemes

1. Go to **Admin Dashboard** → **Schemes/Projects**
2. Click **Add New Scheme** to create a scheme
3. Fill in:
   - Scheme Name (e.g., "Special Component Plan for Scheduled Castes")
   - Scheme Code (e.g., "SCSP")
   - Description
   - Status (Active/Inactive)
4. Click **Add Scheme**

### Editing Schemes

1. Click the **Edit** button on any scheme
2. Update the details
3. View linked courses at the bottom
4. Click **Update Scheme**

### Deleting Schemes

1. Click the **Delete** button
2. Confirm the deletion
3. If the scheme is linked to courses, you'll see a warning

### Linking Schemes to Courses

This will be done from the Course Add/Edit pages (see next section).

## Next Steps

To complete the integration, you need to update the course management pages to allow selecting multiple schemes. I'll create a guide for that next.

## Sample Schemes Included

1. **SCSP** - Special Component Plan for Scheduled Castes
2. **TSP** - Tribal Sub-Plan
3. **PMKVY** - Pradhan Mantri Kaushal Vikas Yojana
4. **NSDM** - National Skill Development Mission

## Support

For issues or questions, contact the system administrator.
