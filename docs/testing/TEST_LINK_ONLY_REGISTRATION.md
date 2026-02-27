# 🧪 Test Link-Only Registration - Quick Guide

## ⚡ Quick Test (2 Minutes)

### Test 1: Direct Access (Should FAIL)
```
URL: http://localhost/student/register.php
```

**Expected:**
- ❌ Page does NOT load
- ✅ Redirects to courses page
- ✅ Shows error: "Invalid access! Registration is only available through course registration links."

---

### Test 2: Link Access (Should WORK)
```
URL: http://localhost/student/register.php?course_id=1
```

**Expected:**
- ✅ Page loads successfully
- ✅ Header shows NIELIT logo (left)
- ✅ Header shows Government emblem (right)
- ✅ Hindi text visible: "राष्ट्रीय इलेक्ट्रॉनिकी..."
- ✅ English text visible: "National Institute of Electronics..."
- ✅ Blue navbar with NIELIT branding
- ✅ Course info card shows selected course
- ✅ Training center field is LOCKED (blue background)
- ✅ Course field is LOCKED (blue background)
- ✅ Lock icons visible (🔒)
- ✅ Message: "Locked by registration link"
- ✅ All other fields are editable
- ✅ Progress indicator works
- ✅ Form can be filled and submitted

---

## 🎨 Visual Checklist

### Header (Top Bar)
```
┌─────────────────────────────────────────────────────────┐
│ [Logo] राष्ट्रीय इलेक्ट्रॉनिकी...    Ministry of... [⚜️]│
│        National Institute of...       Government of India│
└─────────────────────────────────────────────────────────┘
```

**Check:**
- [ ] NIELIT logo visible (left side)
- [ ] Hindi text visible
- [ ] English text visible
- [ ] "Ministry of Electronics & IT" text (right)
- [ ] "Government of India" text (right)
- [ ] Government emblem visible (right side)

### Navbar
```
┌─────────────────────────────────────────────────────────┐
│ 🏛️ NIELIT    Home | Courses | Registration | Portal | Contact│
└─────────────────────────────────────────────────────────┘
```

**Check:**
- [ ] Blue background (#0d47a1)
- [ ] NIELIT branding with icon
- [ ] Navigation links visible
- [ ] "Registration" is active (highlighted)

### Course Info Card
```
┌─────────────────────────────────────────────────────────┐
│ 🎓 Selected Course (Locked)                             │
│                                                          │
│ Course Name: Web Development                             │
│ Code: WD101          Fees: ₹5,000                       │
│ Training Center: NIELIT Bhubaneswar Center              │
│                                                          │
│ ℹ️ Note: Course and training center are locked...       │
└─────────────────────────────────────────────────────────┘
```

**Check:**
- [ ] Card shows selected course details
- [ ] Course name displayed
- [ ] Course code displayed
- [ ] Fees displayed
- [ ] Training center displayed
- [ ] Info alert visible
- [ ] Blue/info styling

### Locked Fields
```
Training Center: [NIELIT Bhubaneswar Center] 🔒
                 🔒 Locked by registration link

Course:          [Web Development (WD101)] 🔒
                 🔒 Locked by registration link
```

**Check:**
- [ ] Training center field has blue background
- [ ] Training center field is read-only (cursor: not-allowed)
- [ ] Course field has blue background
- [ ] Course field is read-only (cursor: not-allowed)
- [ ] Lock icons visible
- [ ] "Locked by registration link" message shown
- [ ] Cannot edit these fields

### Footer
```
┌─────────────────────────────────────────────────────────┐
│ Important Links | Quick Explore | Contact Info           │
│ • National Portal | • Home      | 📞 0674-2960354       │
│ • MyGov          | • Courses    | ✉️ dir-bbsr@nielit... │
│ • NIELIT HQ      | • Portal     | 🕐 Mon-Fri: 9-5:30   │
├─────────────────────────────────────────────────────────┤
│ © 2025 NIELIT Bhubaneswar | Designed by NIELIT Team    │
└─────────────────────────────────────────────────────────┘
```

**Check:**
- [ ] Dark background (#1a202c)
- [ ] Three columns of links
- [ ] Contact information visible
- [ ] Copyright bar at bottom
- [ ] Matches index.php footer

---

## 🔍 Detailed Test Scenarios

### Scenario 1: Invalid Course ID
```
URL: http://localhost/student/register.php?course_id=99999
```

**Expected:**
- ❌ Page does NOT load
- ✅ Redirects to courses page
- ✅ Shows error: "Invalid or inactive course..."

---

### Scenario 2: Real User Flow
```
1. Go to: http://localhost/public/courses.php
2. Find any course
3. Click "Apply Now" button
```

**Expected:**
- ✅ Redirects to registration page with course_id
- ✅ URL looks like: .../register.php?course_id=X
- ✅ Registration form loads
- ✅ Course is pre-filled and locked
- ✅ Training center is pre-filled and locked
- ✅ Can fill other fields
- ✅ Can submit form

---

### Scenario 3: Compare with Index.php
```
1. Open: http://localhost/index.php
2. Note header style
3. Open: http://localhost/student/register.php?course_id=1
4. Compare headers
```

**Expected:**
- ✅ Headers look identical
- ✅ Same logos
- ✅ Same colors
- ✅ Same layout
- ✅ Same fonts
- ✅ Same navbar style
- ✅ Same footer style

---

### Scenario 4: Mobile Test
```
1. Open registration page with course_id
2. Press F12
3. Toggle device toolbar
4. Select iPhone 12 Pro
```

**Expected:**
- ✅ Header stacks vertically
- ✅ Logos visible and sized correctly
- ✅ Text readable
- ✅ Navbar collapses to hamburger menu
- ✅ Course info card responsive
- ✅ Locked fields visible
- ✅ Progress indicator works
- ✅ All features functional

---

### Scenario 5: Modern Features Test
```
1. Open registration page with course_id
2. Fill some fields
3. Test validation
4. Upload files
```

**Expected:**
- ✅ Progress indicator updates as you fill fields
- ✅ Step 1 turns green when Level 1 complete
- ✅ Email validation shows green ✓ or red ✗
- ✅ Mobile validation works (10 digits)
- ✅ File upload shows preview
- ✅ Can remove uploaded files
- ✅ Animations are smooth
- ✅ Form submits successfully

---

## ✅ Pass/Fail Criteria

### PASS if:
1. ✅ Direct access redirects with error
2. ✅ Link access works perfectly
3. ✅ Header matches index.php
4. ✅ Footer matches index.php
5. ✅ Course fields are locked
6. ✅ Lock messages visible
7. ✅ All modern features work
8. ✅ Mobile responsive
9. ✅ No console errors
10. ✅ Form submits successfully

### FAIL if:
1. ❌ Direct access works (should not!)
2. ❌ Link access doesn't work
3. ❌ Header doesn't match index.php
4. ❌ Course fields are editable
5. ❌ Modern features broken
6. ❌ Console errors present
7. ❌ Form doesn't submit

---

## 🐛 Common Issues

### Issue: "Page not found"
**Solution:** Check XAMPP is running, verify file path

### Issue: "Database connection error"
**Solution:** Check database credentials in config.php

### Issue: "Logos not showing"
**Solution:** Verify image files exist in assets/images/

### Issue: "Course not locking"
**Solution:** Verify course_id parameter in URL

### Issue: "Redirect not working"
**Solution:** Check session_start() is at top of file

---

## 📊 Test Results Template

```
Date: __________
Tester: __________
Browser: __________

Test 1 - Direct Access:        [ ] PASS  [ ] FAIL
Test 2 - Link Access:           [ ] PASS  [ ] FAIL
Test 3 - Invalid Course ID:     [ ] PASS  [ ] FAIL
Test 4 - Real User Flow:        [ ] PASS  [ ] FAIL
Test 5 - Visual Consistency:    [ ] PASS  [ ] FAIL
Test 6 - Mobile Responsive:     [ ] PASS  [ ] FAIL
Test 7 - Modern Features:       [ ] PASS  [ ] FAIL

Overall Status: [ ] PASS  [ ] FAIL

Issues Found:
1. ___________________________
2. ___________________________
3. ___________________________

Notes:
_________________________________
_________________________________
```

---

## 🎯 Quick Commands

### Test Direct Access (Should Fail)
```
http://localhost/student/register.php
```

### Test Link Access (Should Work)
```
http://localhost/student/register.php?course_id=1
```

### Test Invalid ID (Should Fail)
```
http://localhost/student/register.php?course_id=99999
```

### Test Real Flow
```
http://localhost/public/courses.php
→ Click "Apply Now"
```

---

## 🎉 Success!

If all tests pass:
- ✅ Link-only system working
- ✅ Visual consistency achieved
- ✅ Security implemented
- ✅ Modern features retained
- ✅ Ready for production!

---

**Status:** Ready for Testing  
**Version:** 3.0  
**Date:** February 11, 2026  
**Estimated Time:** 5-10 minutes

