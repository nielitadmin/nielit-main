# Admin Panel Testing Guide

## Quick Testing Steps

### 1. Test Login Page
**URL**: `http://localhost/public_html/admin/login.php`

**Test**:
- [ ] Page loads with modern design
- [ ] Logo displays correctly
- [ ] Login form has username and password fields
- [ ] Password toggle (eye icon) works
- [ ] Submit triggers OTP email
- [ ] OTP verification form appears
- [ ] Successful login redirects to dashboard

**Expected**: Modern blue gradient card with NIELIT logo

---

### 2. Test Dashboard
**URL**: `http://localhost/public_html/admin/dashboard.php`

**Test**:
- [ ] Sidebar appears on left with navigation
- [ ] Top bar shows page title and user info
- [ ] Statistics cards display (Courses, Students, Batches)
- [ ] Courses table shows all courses
- [ ] "Add New Course" button opens modal
- [ ] Edit and Delete buttons work
- [ ] All navigation links work

**Expected**: Modern dashboard with sidebar, stats cards, and table

---

### 3. Test Students Page
**URL**: `http://localhost/public_html/admin/students.php`

**Test**:
- [ ] Sidebar navigation active on "Students"
- [ ] Statistics cards show Total, Male, Female counts
- [ ] Filter section works (Course, Start Date, End Date)
- [ ] Students table displays all students
- [ ] Edit button redirects to edit_student.php
- [ ] Delete button shows confirmation
- [ ] Table is responsive

**Expected**: Modern page with filter and student table

---

### 4. Test Manage Batches
**URL**: `http://localhost/public_html/admin/manage_batches.php?course_id=1`

**Test**:
- [ ] Sidebar navigation active on "Batches"
- [ ] Course name displays in top bar
- [ ] Existing batches table shows (if any)
- [ ] Add new batch form displays
- [ ] Form has all required fields
- [ ] Submit adds new batch
- [ ] Delete button removes batch
- [ ] Back to Dashboard button works

**Expected**: Modern page with batch list and add form

---

### 5. Test Edit Course
**URL**: `http://localhost/public_html/admin/edit_course.php?id=1`

**Test**:
- [ ] Sidebar navigation active on "Courses"
- [ ] Course name displays in top bar
- [ ] Form pre-fills with course data
- [ ] All fields are editable
- [ ] Category dropdown works
- [ ] Training center dropdown works
- [ ] PDF upload field shows current file
- [ ] Update button saves changes
- [ ] Cancel button returns to dashboard

**Expected**: Modern form with 2-column grid layout

---

### 6. Test Add Admin
**URL**: `http://localhost/public_html/admin/add_admin.php`

**Test**:
- [ ] Sidebar navigation active on "Add Admin"
- [ ] Form displays with 4 fields (Username, Email, Password, Phone)
- [ ] All fields are required
- [ ] Submit creates new admin
- [ ] Success message displays
- [ ] Error message displays if duplicate username
- [ ] Cancel button returns to dashboard

**Expected**: Modern form with 2-column grid layout

---

### 7. Test Reset Password
**URL**: `http://localhost/public_html/admin/reset_password.php`

**Test**:
- [ ] Sidebar navigation active on "Reset Password"
- [ ] Form displays with Student ID field
- [ ] Submit generates new password
- [ ] Success card displays with green gradient
- [ ] New password shows in large text
- [ ] Security information card displays
- [ ] Invalid Student ID shows error message
- [ ] Cancel button returns to dashboard

**Expected**: Modern form with success card showing new password

---

## Visual Checks

### Sidebar
- [ ] Logo displays at top
- [ ] Navigation items have icons
- [ ] Active page is highlighted
- [ ] Hover effects work
- [ ] Divider line shows before logout

### Top Bar
- [ ] Page title with icon displays
- [ ] User name shows on right
- [ ] User avatar shows initial letter
- [ ] "Administrator" role displays

### Cards
- [ ] White background with shadow
- [ ] Rounded corners
- [ ] Card header with title and actions
- [ ] Proper spacing

### Forms
- [ ] Labels have icons
- [ ] Input fields have focus state (blue border)
- [ ] Grid layout (2 columns) works
- [ ] Buttons have icons

### Tables
- [ ] Header has gray background
- [ ] Rows have hover effect
- [ ] Action buttons styled correctly
- [ ] Badges show for status/category

### Buttons
- [ ] Primary buttons are blue
- [ ] Success buttons are green
- [ ] Warning buttons are orange
- [ ] Danger buttons are red
- [ ] Secondary buttons are gray
- [ ] All have hover effects (lift + shadow)

---

## Responsive Testing

### Desktop (1920px)
- [ ] Sidebar visible
- [ ] Content has proper spacing
- [ ] Tables fit without scrolling

### Tablet (768px)
- [ ] Sidebar still visible
- [ ] Content adjusts
- [ ] Tables may scroll horizontally

### Mobile (375px)
- [ ] Sidebar hidden by default
- [ ] Content stacks vertically
- [ ] Tables scroll horizontally
- [ ] User details hidden in top bar

---

## Browser Testing

Test in:
- [ ] Chrome
- [ ] Firefox
- [ ] Edge
- [ ] Safari (if available)

---

## Common Issues & Solutions

### Issue: Sidebar not showing
**Solution**: Check if `assets/css/admin-theme.css` is loaded correctly

### Issue: Styles not applying
**Solution**: Clear browser cache (Ctrl+Shift+R)

### Issue: Images not loading
**Solution**: Check `APP_URL` in `config/app.php` is set to `http://localhost/public_html`

### Issue: Database errors
**Solution**: Check database connection in `config/database.php`

### Issue: Session errors
**Solution**: Make sure you're logged in as admin

---

## Quick Test Script

Run these URLs in order:

1. Login: `http://localhost/public_html/admin/login.php`
2. Dashboard: `http://localhost/public_html/admin/dashboard.php`
3. Students: `http://localhost/public_html/admin/students.php`
4. Batches: `http://localhost/public_html/admin/manage_batches.php?course_id=1`
5. Edit Course: `http://localhost/public_html/admin/edit_course.php?id=1`
6. Add Admin: `http://localhost/public_html/admin/add_admin.php`
7. Reset Password: `http://localhost/public_html/admin/reset_password.php`

---

## Success Criteria

✅ All pages load without errors
✅ All pages use modern theme
✅ Sidebar navigation works
✅ Forms submit correctly
✅ Tables display data
✅ Buttons have proper styling
✅ Responsive design works
✅ No console errors

---

**Date**: February 10, 2026
**Status**: Ready for Testing
