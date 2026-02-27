# 🚀 Deploy Now - Quick Start Guide

## ⚡ 5-Minute Deployment

Follow these steps to deploy the registration link publishing system immediately.

---

## Step 1: Update Database (2 minutes)

### Option A: Using phpMyAdmin (Recommended)

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select database: `nielit_bhubaneswar`
3. Click "SQL" tab
4. Copy and paste this SQL:

```sql
-- Add link_published to admin courses table
ALTER TABLE courses 
ADD COLUMN IF NOT EXISTS link_published TINYINT(1) DEFAULT 0 
AFTER qr_generated_at
COMMENT '1=Published, 0=Unpublished';

-- Create index
CREATE INDEX IF NOT EXISTS idx_link_published ON courses(link_published);
```

5. Click "Go"
6. You should see: "Query executed successfully"

### Option B: Using Command Line

```bash
cd C:\xampp\htdocs\public_html
mysql -u root -p nielit_bhubaneswar < database_qr_system_update.sql
mysql -u root -p nielit_bhubaneswar < database_public_courses_update.sql
```

---

## Step 2: Verify Files (1 minute)

Check these files exist and are updated:

```
✅ admin/manage_courses.php (updated with Generate Link button)
✅ public/courses.php (updated with link_published filter)
✅ includes/qr_helper.php (existing)
✅ admin/generate_qr.php (existing)
✅ assets/qr_codes/ (directory exists)
```

---

## Step 3: Test Admin Panel (2 minutes)

1. **Login to Admin Panel:**
   - URL: `http://localhost/public_html/admin/login.php`
   - Login with your admin credentials

2. **Go to Manage Courses:**
   - Click "Manage Courses" in sidebar

3. **Test Add Course:**
   - Click "Add New Course" button
   - Fill in: Course Name, Course Code
   - Click "Generate Link" button
   - ✅ Link should appear in readonly field
   - Toggle "Publish Status" ON
   - ✅ Label should change to "Published" (green)
   - Click "Add Course"
   - ✅ Success message should appear
   - ✅ QR code should be generated automatically

4. **Test Edit Course:**
   - Click "Edit" on any course
   - ✅ Current link should show
   - ✅ Publish status should load correctly
   - Toggle publish status
   - ✅ Label should update
   - Click "Update Course"
   - ✅ Changes should save

---

## Step 4: Test Public Website (1 minute)

1. **Open Public Courses Page:**
   - URL: `http://localhost/public_html/public/courses.php`

2. **Check Course Display:**
   - ✅ Published courses should show "Apply Now" button
   - ✅ Unpublished courses should NOT show "Apply Now" button

3. **Test Registration Link:**
   - Click "Apply Now" on a published course
   - ✅ Registration page should open
   - ✅ Course should be pre-selected

---

## ✅ Quick Verification

Run this SQL to check everything is working:

```sql
-- Check if column exists
SHOW COLUMNS FROM courses LIKE 'link_published';

-- View current courses
SELECT 
    id,
    course_name,
    CASE 
        WHEN link_published = 1 THEN '✅ Published'
        WHEN link_published = 0 THEN '❌ Unpublished'
        ELSE '⚠️ NULL'
    END as status
FROM courses
LIMIT 10;
```

Expected result: You should see the `link_published` column and course statuses.

---

## 🎯 Success Checklist

After deployment, verify:

- [ ] Database column `link_published` added
- [ ] "Generate Link" button works in admin panel
- [ ] Publish toggle changes label text
- [ ] QR code generates automatically on save
- [ ] Published courses show "Apply Now" on website
- [ ] Unpublished courses hide "Apply Now" from website
- [ ] No PHP errors in browser console
- [ ] No JavaScript errors in browser console

---

## 🚨 If Something Goes Wrong

### Database Error:

```sql
-- Manually add column if ALTER TABLE failed
ALTER TABLE courses ADD COLUMN link_published TINYINT(1) DEFAULT 0;
```

### "Generate Link" Not Working:

1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh page (Ctrl+F5)
3. Check browser console for errors (F12)

### QR Code Not Generated:

1. Check directory exists: `assets/qr_codes/`
2. Check directory permissions (should be writable)
3. Manually click "Generate QR" button in course table

### Public Website Not Filtering:

1. Verify `public/courses.php` was updated
2. Clear PHP opcache: Restart Apache in XAMPP
3. Check SQL queries include `link_published = 1`

---

## 📞 Quick Support

**Check PHP Errors:**
```
C:\xampp\apache\logs\error.log
```

**Check Database:**
```sql
DESCRIBE courses;
```

**Reset a Course:**
```sql
UPDATE courses SET link_published = 1 WHERE id = 1;
```

---

## 🎉 You're Done!

The system is now live and ready to use!

**What You Can Do Now:**
1. ✅ Add new courses with registration links
2. ✅ Control which courses show on public website
3. ✅ Generate QR codes automatically
4. ✅ Manage course visibility easily

**Time Taken:** ~5 minutes
**Status:** ✅ DEPLOYED

---

**Need Help?** Check these files:
- `COMPLETE_IMPLEMENTATION_SUMMARY.md` - Full documentation
- `APPLY_LINK_QR_SYSTEM_UPDATE.md` - Feature details
- `PUBLIC_WEBSITE_INTEGRATION_COMPLETE.md` - Integration guide

