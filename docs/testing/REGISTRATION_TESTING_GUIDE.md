# Student Registration Portal - Testing Guide 🧪

## Quick Test Checklist

### 🎨 Visual Testing

#### Desktop View (> 768px)
- [ ] Page title displays with gradient effect
- [ ] All form sections have proper spacing
- [ ] Section icons are 50x50px with gradient backgrounds
- [ ] Hover effects work on form sections (lift + shadow)
- [ ] Form controls have 2px borders
- [ ] Focus states show blue ring and background change
- [ ] Course info card displays with gradient and border
- [ ] Education table has gradient header
- [ ] All buttons have gradient backgrounds
- [ ] Submit button is prominent and centered

#### Mobile View (≤ 768px)
- [ ] Layout switches to single column
- [ ] Section headers stack vertically
- [ ] Icons center properly
- [ ] Submit button is full width
- [ ] Table text is readable (12px)
- [ ] Padding reduces appropriately
- [ ] All content fits without horizontal scroll

#### Hover States
- [ ] Form sections lift on hover
- [ ] Form controls change border color on hover
- [ ] Add Row button lifts on hover
- [ ] Remove Row button scales on hover
- [ ] Submit button lifts dramatically on hover
- [ ] File upload button scales on hover

#### Focus States
- [ ] All inputs show blue ring on focus
- [ ] Input background changes to #f8fafc on focus
- [ ] Focus is visible for keyboard navigation
- [ ] Tab order is logical

---

### ⚙️ Functional Testing

#### Course Selection
- [ ] Training center dropdown works
- [ ] Course dropdown populates
- [ ] Courses filter by selected training center
- [ ] Pre-selected course shows if URL parameter present
- [ ] Course info card displays when course selected

#### Personal Information
- [ ] All text inputs accept data
- [ ] Date picker works for DOB
- [ ] Age auto-calculates from DOB
- [ ] Gender dropdown works
- [ ] Marital status dropdown works

#### Contact Information
- [ ] Mobile accepts 10 digits only
- [ ] Email validates format
- [ ] Aadhar accepts 12 digits only
- [ ] Nationality dropdown works

#### Additional Details
- [ ] Religion dropdown works
- [ ] Category dropdown works
- [ ] Position dropdown works

#### Address Details
- [ ] Address textarea accepts text
- [ ] State dropdown loads from API
- [ ] City dropdown loads based on state
- [ ] Pincode accepts 6 digits only

#### Academic Details
- [ ] College name input works
- [ ] Education table displays
- [ ] "Add More" button adds new row
- [ ] Remove button deletes row
- [ ] Row numbers update after deletion
- [ ] All table inputs accept data

#### Payment Details
- [ ] UTR input accepts text
- [ ] Payment receipt file input works

#### Document Uploads
- [ ] Educational documents accepts PDF only
- [ ] Passport photo accepts images only
- [ ] Signature accepts images only
- [ ] File size validation works (5MB max)

---

### 🔍 Validation Testing

#### Field Validation
- [ ] Required fields show error if empty
- [ ] Mobile number validates 10 digits
- [ ] Aadhar validates 12 digits
- [ ] Email validates format
- [ ] Pincode validates 6 digits
- [ ] File type validation works
- [ ] File size validation works

#### Toast Notifications
- [ ] Success toast shows for valid actions
- [ ] Error toast shows for validation failures
- [ ] Warning toast shows for warnings
- [ ] Info toast shows for information
- [ ] Loading toast shows during submission

---

### 🌐 API Testing

#### State/City API
- [ ] States load on page load
- [ ] Cities load when state selected
- [ ] API errors handled gracefully
- [ ] Loading states visible
- [ ] Dropdown updates correctly

---

### 📱 Browser Testing

#### Chrome/Edge
- [ ] All styles render correctly
- [ ] Gradients display properly
- [ ] Animations smooth
- [ ] Form submission works

#### Firefox
- [ ] All styles render correctly
- [ ] Gradients display properly
- [ ] Animations smooth
- [ ] Form submission works

#### Safari
- [ ] All styles render correctly
- [ ] Gradients display properly
- [ ] Animations smooth
- [ ] Form submission works

#### Mobile Browsers
- [ ] Responsive layout works
- [ ] Touch interactions smooth
- [ ] Keyboard appears for inputs
- [ ] File upload works

---

### ♿ Accessibility Testing

#### Keyboard Navigation
- [ ] Tab order is logical
- [ ] All interactive elements focusable
- [ ] Focus visible on all elements
- [ ] Enter/Space activates buttons
- [ ] Escape closes modals (if any)

#### Screen Reader
- [ ] Labels associated with inputs
- [ ] Required fields announced
- [ ] Error messages announced
- [ ] Button purposes clear
- [ ] Form structure logical

#### Color Contrast
- [ ] Text readable on backgrounds
- [ ] Links distinguishable
- [ ] Focus indicators visible
- [ ] Error states clear

---

### 🚀 Performance Testing

#### Load Time
- [ ] Page loads in < 2 seconds
- [ ] CSS loads quickly
- [ ] JavaScript loads quickly
- [ ] Images optimized

#### Animations
- [ ] Transitions smooth (60fps)
- [ ] No jank or stuttering
- [ ] Hover effects instant
- [ ] No layout shifts

#### Form Submission
- [ ] Submits without delay
- [ ] Loading state shows
- [ ] Success/error handled
- [ ] No console errors

---

## 🐛 Common Issues to Check

### Visual Issues
- [ ] No text overflow
- [ ] No broken layouts
- [ ] No missing images
- [ ] No color inconsistencies
- [ ] No alignment issues

### Functional Issues
- [ ] No JavaScript errors in console
- [ ] No PHP errors displayed
- [ ] No broken API calls
- [ ] No validation bypasses
- [ ] No file upload failures

### Responsive Issues
- [ ] No horizontal scroll on mobile
- [ ] No tiny text on mobile
- [ ] No overlapping elements
- [ ] No broken grids
- [ ] No hidden content

---

## 📊 Test Scenarios

### Scenario 1: Complete Registration
1. Open registration page
2. Select training center
3. Select course
4. Fill all personal information
5. Fill contact information
6. Fill additional details
7. Fill address (use API for state/city)
8. Fill college name
9. Add 2 education rows
10. Fill payment details
11. Upload all documents
12. Submit form
13. Verify success message

### Scenario 2: Validation Errors
1. Open registration page
2. Try to submit empty form
3. Verify required field errors
4. Enter invalid mobile (9 digits)
5. Verify mobile error
6. Enter invalid email
7. Verify email error
8. Enter invalid aadhar (11 digits)
9. Verify aadhar error
10. Upload large file (> 5MB)
11. Verify file size error

### Scenario 3: Dynamic Features
1. Open registration page
2. Select training center
3. Verify courses filter
4. Change training center
5. Verify courses update
6. Select state
7. Verify cities load
8. Enter DOB
9. Verify age calculates
10. Add education row
11. Verify row added
12. Remove education row
13. Verify row removed and renumbered

### Scenario 4: Mobile Experience
1. Open on mobile device
2. Verify responsive layout
3. Test all form interactions
4. Test file uploads
5. Test dropdown selections
6. Test form submission
7. Verify no horizontal scroll
8. Verify readable text

---

## ✅ Sign-Off Checklist

### Before Going Live
- [ ] All visual tests passed
- [ ] All functional tests passed
- [ ] All validation tests passed
- [ ] All API tests passed
- [ ] All browser tests passed
- [ ] All accessibility tests passed
- [ ] All performance tests passed
- [ ] No console errors
- [ ] No PHP errors
- [ ] Database connection works
- [ ] File uploads work
- [ ] Email notifications work (if any)
- [ ] Success page works
- [ ] Error handling works

### Documentation
- [ ] User guide created (if needed)
- [ ] Admin guide updated
- [ ] API documentation updated
- [ ] Database schema documented
- [ ] Deployment guide ready

### Backup
- [ ] Database backed up
- [ ] Files backed up
- [ ] Old version saved
- [ ] Rollback plan ready

---

## 🎯 Quick Test URLs

### Local Testing
```
http://localhost/public_html/student/register.php
http://localhost/public_html/student/register.php?course_id=1
http://localhost/public_html/student/register.php?course=Test%20Course
```

### Test Data
```
Mobile: 9876543210
Email: test@example.com
Aadhar: 123456789012
Pincode: 751001
```

---

## 📝 Test Report Template

```
Date: ___________
Tester: ___________
Browser: ___________
Device: ___________

Visual Tests: PASS / FAIL
Functional Tests: PASS / FAIL
Validation Tests: PASS / FAIL
API Tests: PASS / FAIL
Browser Tests: PASS / FAIL
Accessibility Tests: PASS / FAIL
Performance Tests: PASS / FAIL

Issues Found:
1. ___________
2. ___________
3. ___________

Overall Status: PASS / FAIL

Notes:
___________
___________
___________
```

---

## 🚨 Critical Tests (Must Pass)

1. ✅ Form submits successfully
2. ✅ All required fields validate
3. ✅ File uploads work
4. ✅ State/City API works
5. ✅ Age calculation works
6. ✅ Education table works
7. ✅ Mobile responsive works
8. ✅ No console errors
9. ✅ No PHP errors
10. ✅ Success message displays

---

## 📞 Support

If you encounter issues:
1. Check browser console for errors
2. Check PHP error logs
3. Verify database connection
4. Check file permissions
5. Clear browser cache
6. Test in incognito mode

---

**Happy Testing!** 🎉
