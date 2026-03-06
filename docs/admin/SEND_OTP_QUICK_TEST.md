# 🔍 Send OTP - Quick Test Card

## ⚡ 30-Second Test

```
1. Open: http://localhost/public_html/admin/add_admin.php
2. Press F12 (open console)
3. Fill form with test data
4. Click "Send OTP"
5. Look at DEBUG INFO box at top
```

## 📊 What You Should See

### ✅ WORKING (Form Submits)

**DEBUG INFO Box Changes:**
```
Before: POST data received: NO
After:  POST data received: YES ← This means it's working!
```

**Then you'll see either:**
- ✓ "OTP sent successfully" + OTP form appears
- ✗ Error message (username exists, validation failed, etc.)

### ❌ NOT WORKING (Form Doesn't Submit)

**DEBUG INFO Box Stays:**
```
POST data received: NO ← Still NO after clicking
```

**Check Console for:**
- JavaScript errors (red text)
- "Form or button not found!" message

## 🎯 Quick Diagnosis

| What You See | What It Means | What To Do |
|--------------|---------------|------------|
| DEBUG shows "YES" | ✓ Form works | Check email config |
| DEBUG shows "NO" | ✗ Form broken | Check console errors |
| Console errors | ✗ JavaScript issue | Clear cache, restart |
| "Form not found" | ✗ HTML issue | Restart Apache |
| Button disabled, no reload | ✗ Server issue | Check PHP log |

## 🔧 Quick Fixes

### Fix 1: Clear Cache
```
Ctrl + Shift + Delete → Clear cache → Reload page
```

### Fix 2: Restart Apache
```
XAMPP Control Panel → Apache → Stop → Start
```

### Fix 3: Check Console
```
F12 → Console tab → Look for red errors
```

### Fix 4: Check PHP Log
```
XAMPP → Apache → Logs → PHP Error Log
Look for: "=== ADD ADMIN DEBUG ==="
```

## 📝 Test Data

Use this for testing:
```
Username: testadmin
Email:    test@example.com
Password: Test@123
Phone:    9876543210
Role:     Course Coordinator
```

## 🆘 Still Not Working?

### Option 1: Use Test File
```
Open: http://localhost/public_html/admin/test_add_admin.php
Fill form → Click Send OTP
Should show: "✓ Send OTP button was clicked!"
```

### Option 2: Check These Files
```
✓ config/config.php - Database connection
✓ config/email.php - SMTP settings
✓ admin/add_admin.php - Main file
```

## 📸 Screenshot Checklist

When reporting issue, capture:
1. ☐ DEBUG INFO box (before and after clicking)
2. ☐ Browser Console (F12 → Console tab)
3. ☐ Network tab (F12 → Network → POST request)
4. ☐ Any error messages on page

## 📚 Full Guides

- **Detailed Testing:** `docs/admin/ADD_ADMIN_DEBUG_GUIDE.md`
- **Summary:** `docs/admin/SEND_OTP_DEBUG_SUMMARY.md`

---

## 🎬 Expected Flow (When Working)

```
1. Fill form
2. Click "Send OTP"
3. Button → "Sending OTP..." (spinner)
4. Page reloads
5. DEBUG INFO → "POST data received: YES"
6. Success message appears
7. OTP form shows
8. Email arrives with 6-digit code
9. Enter OTP → Verify
10. Admin created! ✓
```

---

**Remember:** The DEBUG INFO box is your friend! It tells you immediately if the form is submitting or not.
