# Database Migrations

This directory contains database migration scripts for the NIELIT Bhubaneswar Student Management System.

## Files

### RBAC System
- `add_rbac_schema.sql` - SQL migration file with all schema changes
- `install_rbac.php` - PHP installation script with rollback capability

### System Enhancement Module
- `add_centres_module.sql` - SQL migration file for centres, themes, and homepage content
- `install_system_enhancement.php` - PHP installation script with rollback capability

## Installation Methods

### Method 1: Using PHP Script (Recommended)

The PHP script provides automated installation with validation and rollback capability.

#### Install RBAC Schema

```bash
cd migrations
php install_rbac.php install
```

This will:
- Add role column to admin table
- Add created_at, updated_at, is_active columns
- Create indexes on role and is_active
- Set all existing admins to master_admin role
- Create admin_course_assignments table
- Create audit_log table
- Verify the installation

#### Verify Installation

```bash
php install_rbac.php verify
```

This checks:
- All required columns exist
- All required indexes exist
- All required tables exist
- All admins have valid roles
- Foreign key constraints are in place

#### Rollback (Remove RBAC)

```bash
php install_rbac.php rollback
```

**WARNING**: This will remove all RBAC data including:
- Role assignments
- Course assignments
- Audit logs
- All RBAC columns from admin table

### Method 2: Using SQL File

If you prefer to run the SQL directly:

```bash
mysql -u root -p nielit_bhubaneswar < add_rbac_schema.sql
```

Or import via phpMyAdmin:
1. Open phpMyAdmin
2. Select the `nielit_bhubaneswar` database
3. Go to Import tab
4. Choose `add_rbac_schema.sql`
5. Click Go

## Features

### Safe to Run Multiple Times

The PHP script checks if each migration step has already been applied and skips it if necessary. This means you can safely run `install` multiple times without errors.

### Rollback Capability

The script provides complete rollback functionality to remove all RBAC changes. This is useful for:
- Testing
- Development
- Emergency rollback in production

### Data Validation

The script validates:
- Column existence before adding
- Table existence before creating
- Index existence before creating
- Data integrity after installation

### Colored Output

When run from CLI, the script provides colored output:
- 🟢 Green: Success messages
- 🔴 Red: Error messages
- 🟡 Yellow: Warning/info messages
- 🔵 Blue: Progress messages

## Role Hierarchy

After installation, the following roles are available:

- **master_admin** (Level 4): Full system access
- **course_coordinator** (Level 3): Manages courses and batches
- **data_entry_operator** (Level 2): Student records only
- **report_viewer** (Level 1): Read-only access

**Note**: The `batch_coordinator` role has been removed. Course coordinators now manage both courses AND batches.

## Backward Compatibility

All existing admin users are automatically assigned the `master_admin` role during installation, ensuring they retain full system access.

## Troubleshooting

### "Table 'admin' doesn't exist"

Make sure you're running the script in the correct database. Check your `config/database.php` file.

### "Foreign key constraint fails"

This usually means the referenced tables (courses, batches) don't exist yet. Make sure your main database schema is set up first.

### "Access denied"

Check your database credentials in `config/database.php`.

### Script hangs during rollback

The rollback command requires confirmation. Type `yes` and press Enter when prompted.

## Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- mysqli extension enabled
- Existing `admin` table in database

## Support

For issues or questions, refer to:
- `.kiro/specs/role-based-access-control/requirements.md`
- `.kiro/specs/role-based-access-control/design.md`
- `.kiro/specs/role-based-access-control/tasks.md`


---

## System Enhancement Module Installation

The System Enhancement Module adds three major features:
- **Centre Management**: Manage multiple training centres
- **Theme Customization**: Customize application colors and logos
- **Homepage Content Management**: Edit homepage content dynamically

### Install System Enhancement Module

```bash
cd migrations
php install_system_enhancement.php install
```

This will:
- Create centres table with indexes
- Insert default centres (NIELIT Bhubaneswar, NIELIT Balasore)
- Add centre_id column to courses table with foreign key
- Create themes table with indexes
- Create homepage_content table with indexes
- Verify the installation

### Verify Installation

```bash
php install_system_enhancement.php verify
```

This checks:
- All required tables exist (centres, themes, homepage_content)
- centre_id column exists in courses table
- Default centres are inserted
- All indexes are created

### Rollback (Remove System Enhancement Module)

```bash
php install_system_enhancement.php rollback
```

**WARNING**: This will remove all data including:
- All centres
- All themes
- All homepage content
- centre_id column from courses table

### Features

- **Safe to Run Multiple Times**: The script checks if each step has already been applied
- **Rollback Capability**: Complete rollback functionality to remove all changes
- **Data Validation**: Validates table and column existence
- **Colored Output**: CLI output with color-coded messages

### Data Migration

After installing the schema, run the data migration to populate initial data:

```bash
cd migrations
php migrate_system_enhancement_data.php migrate
```

This will:
- Populate centres table with NIELIT Bhubaneswar and NIELIT Balasore
- Update existing courses with default centre (NIELIT Bhubaneswar)
- Create default theme from existing CSS values (#0d47a1, #1565c0, #ffc107)
- Verify the migration

#### Verify Data Migration

```bash
php migrate_system_enhancement_data.php verify
```

This checks:
- At least 2 centres exist
- Default centre (BBSR) exists
- All courses have centre assignments
- At least 1 theme exists
- An active theme is set

#### Rollback Data Migration

```bash
php migrate_system_enhancement_data.php rollback
```

**WARNING**: This will:
- Delete all centres
- Delete all themes
- Set all course centre_id values to NULL

### Database Schema

#### Centres Table
Stores information about NIELIT training centres:
- id, name, code (unique), address, city, state, pincode, phone, email
- is_active, created_at, updated_at
- Indexes on code and is_active

#### Themes Table
Stores theme customization configurations:
- id, theme_name, primary_color, secondary_color, accent_color
- logo_path, favicon_path, is_active
- created_at, updated_at
- Index on is_active

#### Homepage Content Table
Stores dynamic homepage content sections:
- id, section_key (unique), section_title, section_content
- section_type (enum: banner, announcement, featured_course, text_block, image_block)
- display_order, is_active, created_at, updated_at
- Indexes on section_key, is_active, and display_order

#### Courses Table Modification
Adds centre_id column with foreign key reference to centres table.

### Installation Order

1. **Schema Installation**: Run `install_system_enhancement.php` first to create tables
2. **Data Migration**: Run `migrate_system_enhancement_data.php` to populate initial data
