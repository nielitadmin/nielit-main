# 🚀 QR Code System - Quick Start Guide

## Get Your QR Code System Running in 5 Minutes!

---

## ⚡ Quick Setup (3 Steps)

### Step 1: Update Database (1 minute)

Open phpMyAdmin and run this SQL:

```sql
-- Add QR code columns to courses table
ALTER TABLE courses 
ADD COLUMN IF NOT EXISTS qr_code_path VARCHAR(255) DEFAULT NULL AFTER registration_link,
ADD COLUMN IF NOT EXISTS qr_generated_at DATETIME DEFAULT NULL AFTER qr_code_path;

-- Create indexes for faster lookups
CREATE INDEX IF NOT EXISTS idx_course_code ON courses(course_code);
CREATE INDEX IF NOT EXISTS idx_qr_path ON courses(qr_code_path);
```

**Or use the SQL file:**
```bash
mysql -u root -p nielit_bhubaneswar < database_qr_system_update.sql
```

✅ **Done!** Database is ready.

---

### Step 2: Verify Directory (30 seconds)

Check if `assets/qr_codes/` folder exists and is writable:

```bash
# Check directory
ls -la assets/qr_codes/

# If not exists, create it
mkdir -p assets/qr_codes
chmod 777 assets/qr_codes
```

✅ **Done!** Directory is ready.

---

### Step 3: Test the System (1 minute)

1. Open: `http://localhost/public_html/admin/login.php`
2. Login with admin credentials
3. Click "Manage Courses"
4. Find any course
5. Click yellow "Generate" button
6. Wait 2 seconds
7. See green "View" and blue "Download" buttons

✅ **Done!** System is working!

---

## 🎯 Quick Usage Guide

### Generate QR Code

1. Go to **Manage Courses**
2. Find course without QR code (yellow button)
3. Click **"Generate"**
4. Wait for success message
5. Page reloads with QR code buttons

### View QR Code

1. Click green **"View"** button
2. Modal opens with QR code
3. See large QR code image
4. Download or regenerate options

### Download QR Code

**Quick Method:**
- Click blue **"Download"** button
- PNG file downloads instantly

**Modal Method:**
- Click green **"View"** button
- In modal, click **"Download QR Code"**
- PNG file downloads

### Share QR Code

**For Printing:**
1. Download QR code PNG
2. Open in image editor
3. Resize as needed
4. Print on brochures/posters

**For Digital:**
1. Download QR code PNG
2. Share via email/WhatsApp
3. Post on social media
4. Upload to website

---

## 📱 How Students Use It

1. **See QR Code** on poster/brochure
2. **Open Camera** on phone
3. **Point at QR Code**
4. **Tap Notification** to open link
5. **Fill Registration Form**
6. **Submit** and done!

---

## 🔧 Troubleshooting

### Problem: Generate button doesn't work

**Quick Fix:**
1. Refresh page (F5)
2. Check browser console for errors
3. Verify you're logged in as admin

### Problem: QR code doesn't scan

**Quick Fix:**
1. Ensure good lighting
2. Hold phone steady
3. Try different distance
4. Regenerate QR code

### Problem: Can't download QR code

**Quick Fix:**
1. Check browser download settings
2. Allow pop-ups for the site
3. Try right-click > Save Image As

---

## 📋 Quick Reference

### Button Colors

| Color | Button | Action |
|-------|--------|--------|
| 🟡 Yellow | Generate | Create QR code |
| 🟢 Green | View | Open modal |
| 🔵 Blue | Download | Download PNG |
| 🟠 Orange | Regenerate | Replace QR |

### File Locations

- **QR Codes:** `assets/qr_codes/`
- **Admin Panel:** `admin/manage_courses.php`
- **Registration:** `student/register.php`
- **Generator:** `admin/generate_qr.php`

### URLs

- **Admin Login:** `http://localhost/public_html/admin/login.php`
- **Manage Courses:** `http://localhost/public_html/admin/manage_courses.php`
- **Test QR:** `http://localhost/public_html/test_qrcode.php`

---

## 📚 Full Documentation

For detailed information, see:

1. **`ADMIN_QR_CODE_GUIDE.md`** - User-friendly admin guide
2. **`QR_CODE_INTEGRATION_COMPLETE.md`** - Technical details
3. **`QR_SYSTEM_IMPLEMENTATION_SUMMARY.md`** - Complete overview
4. **`QR_SYSTEM_TEST_CHECKLIST.md`** - Testing guide

---

## ✅ You're Ready!

Your QR code system is now set up and ready to use!

**Next Steps:**
1. Generate QR codes for all courses
2. Download QR codes
3. Add to marketing materials
4. Share with students
5. Monitor registrations

---

**Need Help?** Check the documentation files or contact support.

**Quick Start Version:** 1.0.0
**Last Updated:** February 11, 2026
