# NSQF Templates Category/Sub-Category Production Deployment Guide

## Overview
This guide covers the deployment of the updated NSQF Templates system that now matches the Category/Sub-Category pattern from `edit_course.php`. The system has been enhanced to provide consistent categorization across the platform.

## Changes Made

### 1. Database Schema Updates
- **Added `nsqf_type` column** to `nsqf_course_templates` table
- **Modified `category` column** from ENUM to VARCHAR to support new categories
- **Migrated existing data** to new structure

### 2. Interface Updates
- **Category dropdown** now matches `edit_course.php` options:
  - Degree / Diploma Courses / PG
  - Skill Based (Long Term) Courses > 500 hrs
  - Skill Based (Short Term) Courses >90 hrs to <=500 hrs
  - Short Term Courses / Digital Competency Courses <= 90 hours
  - NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)
  - Internship Program

- **Sub-Category dropdown** added:
  - NSQF Course
  - NON-NSQF Course

### 3. Files Modified
- `admin/manage_nsqf_templates.php` - Updated forms and display
- `migrations/update_nsqf_templates_schema.php` - Database migration script

## Pre-Deployment Checklist

### 1. Backup Requirements
```bash
# Backup the database
mysqldump -u [username] -p [database_name] > backup_before_nsqf_update_$(date +%Y%m%d_%H%M%S).sql

# Backup the specific table
mysqldump -u [username] -p [database_name] nsqf_course_templates > nsqf_templates_backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Environment Verification
- [ ] Verify database connection
- [ ] Check current NSQF templates data
- [ ] Ensure admin users with `nsqf_course_manager` role exist
- [ ] Test file permissions for migration script

## Production Deployment Steps

### Step 1: Upload Files
Upload the following files to production:

```bash
# Upload updated files
admin/manage_nsqf_templates.php
migrations/update_nsqf_templates_schema.php
```

### Step 2: Run Database Migration
Execute the migration script on production:

```bash
# Navigate to the migrations directory
cd /path/to/your/website/migrations

# Run the migration
php update_nsqf_templates_schema.php
```

**Expected Output:**
```
Updating NSQF Course Templates Schema...
✅ Added nsqf_type column successfully!
✅ Updated category column to VARCHAR!
✅ Updated existing data to new structure!

📊 Current template distribution:
   - Short Term / Digital Competency <=90 hrs | NSQF Course: X templates
   - Skill Based (Long Term) >500 hrs | NSQF Course: Y templates

🎉 NSQF Templates schema updated successfully!
```

### Step 3: Verify Database Changes
Check the updated table structure:

```sql
-- Verify table structure
DESCRIBE nsqf_course_templates;

-- Check data migration
SELECT category, nsqf_type, COUNT(*) as count 
FROM nsqf_course_templates 
GROUP BY category, nsqf_type;

-- Verify all templates are active
SELECT COUNT(*) as total_templates, 
       SUM(is_active) as active_templates 
FROM nsqf_course_templates;
```

### Step 4: Test Functionality
1. **Login as NSQF Course Manager**
2. **Navigate to Manage NSQF Course**
3. **Test Add New NSQF Course:**
   - Verify Category dropdown shows all 6 options
   - Verify Sub-Category dropdown shows NSQF/NON-NSQF options
   - Create a test template
4. **Test Edit Existing Template:**
   - Edit an existing template
   - Verify all fields populate correctly
   - Save changes
5. **Verify Table Display:**
   - Check that both Category and Sub-Category columns display
   - Verify badges show correct colors

## Post-Deployment Verification

### 1. Data Integrity Check
```sql
-- Verify no data loss
SELECT COUNT(*) FROM nsqf_course_templates WHERE is_active = 1;

-- Check for any NULL values in new column
SELECT COUNT(*) FROM nsqf_course_templates WHERE nsqf_type IS NULL;

-- Verify category values are valid
SELECT DISTINCT category FROM nsqf_course_templates;
```

### 2. Functional Testing
- [ ] NSQF Course Manager can create new templates
- [ ] Category dropdown shows all 6 options
- [ ] Sub-Category dropdown works correctly
- [ ] Edit functionality preserves all data
- [ ] Table displays both Category and Sub-Category
- [ ] Delete/deactivate functionality works

### 3. Integration Testing
- [ ] Course Coordinators can still access NSQF templates in `edit_course.php`
- [ ] Template selection in course creation works
- [ ] Eligibility auto-population still functions

## Rollback Plan

If issues occur, follow this rollback procedure:

### 1. Restore Database
```bash
# Restore from backup
mysql -u [username] -p [database_name] < backup_before_nsqf_update_[timestamp].sql
```

### 2. Restore Files
```bash
# Restore previous version of manage_nsqf_templates.php
# Remove migration script if needed
```

### 3. Verify Rollback
- [ ] NSQF templates page loads correctly
- [ ] Old category structure is restored
- [ ] All existing templates are accessible

## Troubleshooting

### Common Issues

#### 1. Migration Script Fails
**Error:** "Column 'nsqf_type' already exists"
**Solution:** The column already exists, migration can be skipped

#### 2. Category Values Not Displaying
**Error:** Dropdown shows empty options
**Solution:** Clear browser cache, check for JavaScript errors

#### 3. Edit Function Not Working
**Error:** Edit modal doesn't populate correctly
**Solution:** Check JavaScript console for errors, verify JSON encoding

#### 4. Database Connection Issues
**Error:** "Could not prepare statement"
**Solution:** Verify database credentials and connection

### SQL Queries for Debugging

```sql
-- Check table structure
SHOW CREATE TABLE nsqf_course_templates;

-- View all templates with new structure
SELECT id, course_name, category, nsqf_type, is_active 
FROM nsqf_course_templates 
ORDER BY created_at DESC;

-- Check for data inconsistencies
SELECT category, nsqf_type, COUNT(*) 
FROM nsqf_course_templates 
GROUP BY category, nsqf_type;
```

## Performance Considerations

### 1. Index Optimization
The migration maintains existing indexes and adds appropriate ones for new columns.

### 2. Query Performance
New queries include both category and nsqf_type filters, which are indexed for optimal performance.

### 3. Memory Usage
The VARCHAR change for category column may slightly increase memory usage, but impact is minimal.

## Security Considerations

### 1. Access Control
- Only `nsqf_course_manager` role can access the updated interface
- All database operations use prepared statements
- Input validation maintained for new fields

### 2. Data Validation
- Category values are validated against predefined list
- Sub-category values are restricted to NSQF/NON-NSQF options
- SQL injection protection maintained

## Monitoring

### 1. Error Logs
Monitor the following logs after deployment:
- PHP error logs
- MySQL error logs
- Application logs

### 2. Performance Metrics
- Page load times for NSQF templates page
- Database query performance
- User interaction success rates

### 3. User Feedback
- Monitor for user reports of issues
- Track template creation/edit success rates
- Verify integration with course creation workflow

## Success Criteria

Deployment is considered successful when:
- [ ] All existing NSQF templates are preserved and accessible
- [ ] New Category/Sub-Category structure is functional
- [ ] NSQF Course Managers can create/edit templates
- [ ] Integration with course creation remains intact
- [ ] No performance degradation observed
- [ ] All automated tests pass

## Contact Information

For deployment support:
- **Technical Lead:** [Your Name]
- **Database Admin:** [DBA Name]
- **System Admin:** [SysAdmin Name]

## Deployment Log

| Date | Time | Action | Result | Notes |
|------|------|--------|--------|-------|
| | | Files uploaded | | |
| | | Migration executed | | |
| | | Testing completed | | |
| | | Go-live confirmed | | |

---

**Document Version:** 1.0  
**Last Updated:** [Current Date]  
**Prepared By:** [Your Name]  
**Reviewed By:** [Reviewer Name]