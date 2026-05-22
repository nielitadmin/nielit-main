# Course Categorization Fix - Production Deployment Guide

## Overview
This guide covers deploying the course categorization fix to your production server. The fix involves both code changes (already in GitHub) and database updates.

## 🚨 **IMPORTANT: Backup First!**
Before making any changes on production:
```bash
# Backup your production database
mysqldump -u [username] -p [database_name] > backup_before_course_fix_$(date +%Y%m%d_%H%M%S).sql
```

## Step 1: Deploy Code Changes

### Option A: Git Pull (Recommended)
If your production server has git access:
```bash
# Navigate to your production directory
cd /path/to/your/production/website

# Pull the latest changes
git pull origin main

# Verify the changes
git log --oneline -5
# You should see commit: "Fix course categorization system"
```

### Option B: Manual File Upload
If you need to upload files manually:
1. Download the updated `public/courses.php` from GitHub
2. Upload it to your production server, replacing the existing file

## Step 2: Database Updates (CRITICAL)

You need to run the database migration script on your production database. Create and run this script:

### Create Migration Script
Save this as `production_course_category_migration.php` on your server:

```php
<?php
// production_course_category_migration.php
require_once 'config/config.php';

echo "=== PRODUCTION COURSE CATEGORIZATION MIGRATION ===\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n\n";

// Step 1: Show current state
echo "BEFORE MIGRATION:\n";
$result = $conn->query('SELECT category, COUNT(*) as count FROM courses GROUP BY category ORDER BY count DESC');
while ($row = $result->fetch_assoc()) {
    echo "  " . ($row['category'] ?? 'NULL') . ": " . $row['count'] . " courses\n";
}

// Step 2: Update course categories to match the 6 required categories
echo "\nApplying category updates...\n";

$updates = [
    // Map Long Term NSQF courses to Skill Based (Long Term) >500 hrs
    "UPDATE courses SET category = 'Skill Based (Long Term) >500 hrs' WHERE category = 'Long Term NSQF'",
    
    // Map Short Term NSQF courses to Skill Based (Short Term) 90-500 hrs  
    "UPDATE courses SET category = 'Skill Based (Short Term) 90-500 hrs' WHERE category = 'Short Term NSQF'",
    
    // Map Short-Term Non-NSQF to Short Term / Digital Competency <=90 hrs
    "UPDATE courses SET category = 'Short Term / Digital Competency <=90 hrs' WHERE category = 'Short-Term Non-NSQF'",
    
    // Update existing Short Term / Digital Competency to match exact format
    "UPDATE courses SET category = 'Short Term / Digital Competency <=90 hrs' WHERE category = 'Short Term / Digital Competency <=90 hrs'",
];

foreach ($updates as $sql) {
    if ($conn->query($sql)) {
        echo "  ✓ Category update applied successfully\n";
    } else {
        echo "  ✗ Error: " . $conn->error . "\n";
        exit(1);
    }
}

// Step 3: Publish all courses (make them visible)
echo "\nPublishing all courses...\n";
$publish_sql = "UPDATE courses SET link_published = 1 WHERE link_published IS NULL OR link_published = 0";
if ($conn->query($publish_sql)) {
    $affected = $conn->affected_rows;
    echo "  ✓ Published $affected courses\n";
} else {
    echo "  ✗ Error publishing courses: " . $conn->error . "\n";
    exit(1);
}

// Step 4: Show final state
echo "\nAFTER MIGRATION:\n";
$result = $conn->query('SELECT category, COUNT(*) as count FROM courses GROUP BY category ORDER BY count DESC');
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['category'] . ": " . $row['count'] . " courses\n";
}

// Step 5: Verify the 6 required categories
echo "\nVERIFICATION - Required Categories:\n";
$required_categories = [
    'Degree / Diploma / PG',
    'Skill Based (Long Term) >500 hrs', 
    'Skill Based (Short Term) 90-500 hrs',
    'Short Term / Digital Competency <=90 hrs',
    'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)',
    'Internship Program'
];

foreach ($required_categories as $cat) {
    $result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE category = '$cat' AND link_published = 1");
    $row = $result->fetch_assoc();
    echo "  $cat: " . $row['count'] . " courses\n";
}

echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
?>
```

### Run the Migration
```bash
# Navigate to your website directory
cd /path/to/your/production/website

# Run the migration script
php production_course_category_migration.php
```

## Step 3: Verify the Fix

### Test the Website
1. Visit your courses page: `https://yourdomain.com/public/courses.php`
2. Verify you see exactly 6 course categories:
   - Degree / Diploma / PG
   - Skill Based (Long Term) >500 hrs
   - Skill Based (Short Term) 90-500 hrs  
   - Short Term / Digital Competency <=90 hrs
   - NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)
   - Internship Program

3. Check that courses appear in the correct categories (not all in "Internship Program")

### Database Verification
```sql
-- Check category distribution
SELECT category, COUNT(*) as count 
FROM courses 
GROUP BY category 
ORDER BY count DESC;

-- Check published courses
SELECT COUNT(*) as total_published 
FROM courses 
WHERE link_published = 1;
```

## Expected Results

After successful deployment, you should see:
- **Skill Based (Long Term) >500 hrs**: ~10 courses
- **Skill Based (Short Term) 90-500 hrs**: ~5 courses  
- **Short Term / Digital Competency <=90 hrs**: ~5 courses
- **Internship Program**: ~16 courses
- **Degree / Diploma / PG**: 0 courses (shows "No courses available")
- **NIELIT HQ Digital Literacy**: 0 courses (shows "No courses available")

## Rollback Plan (If Needed)

If something goes wrong, you can rollback:

```bash
# Restore database from backup
mysql -u [username] -p [database_name] < backup_before_course_fix_[timestamp].sql

# Revert code changes
git revert [commit_hash]
git push origin main
```

## Troubleshooting

### Issue: Categories not showing correctly
- Check that the migration script ran successfully
- Verify database connection in `config/config.php`
- Check PHP error logs

### Issue: No courses visible
- Ensure `link_published = 1` for courses
- Check the SQL queries in `public/courses.php`

### Issue: Database connection errors
- Verify production database credentials
- Check database server status
- Ensure PHP has MySQL extension enabled

## Post-Deployment Checklist

- [ ] Database backup created
- [ ] Code deployed from GitHub
- [ ] Migration script executed successfully
- [ ] Website displays 6 course categories correctly
- [ ] Courses appear in proper categories
- [ ] No PHP errors in logs
- [ ] Training centre filtering works
- [ ] Mobile responsiveness maintained

## Support

If you encounter issues during deployment:
1. Check the migration script output for errors
2. Review PHP error logs
3. Verify database connection and permissions
4. Test on a staging environment first if possible

---

**Remember**: Always test on a staging environment before deploying to production!