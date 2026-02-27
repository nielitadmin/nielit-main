# 🧪 Modern Registration Page - Testing Guide

## Quick Test Checklist

---

## 1. Progress Indicator Testing

### Test 1.1: Initial State
- [ ] Open registration page
- [ ] Verify progress indicator shows at top
- [ ] Step 1 should be blue (active)
- [ ] Steps 2 and 3 should be gray (pending)
- [ ] Progress line should be at 0%

### Test 1.2: Progress Updates
- [ ] Fill in "Training Center" field
- [ ] Fill in "Course" field
- [ ] Fill in "Full Name" field
- [ ] Verify progress indicator updates in real-time
- [ ] Progress line should extend as fields are filled

### Test 1.3: Level Completion
- [ ] Complete all fields in Level 1
- [ ] Verify Step 1 turns green with checkmark
- [ ] Verify Step 2 becomes active (blue)
- [ ] Progress line should be at ~33%

### Test 1.4: All Levels Complete
- [ ] Complete all fields in all 3 levels
- [ ] All 3 steps should be green with checkmarks
- [ ] Progress line should be at 100%

---

## 2. Real-Time Validation Testing

### Test 2.1: Email Validation
- [ ] Click in email field
- [ ] Type: "invalid" → Should show red X
- [ ] Type: "user@example.com" → Should show green checkmark
- [ ] Error message should appear/disappear accordingly

### Test 2.2: Mobile Validation
- [ ] Type: "123" → Should show red X
- [ ] Type: "1234567890" → Should show green checkmark
- [ ] Verify 10-digit requirement

### Test 2.3: Aadhar Validation
- [ ] Type: "123456" → Should show red X
- [ ] Type: "123456789012" → Should show green checkmark
- [ ] Verify 12-digit requirement

### Test 2.4: Pincode Validation
- [ ] Type: "123" → Should show red X
- [ ] Type: "751024" → Should show green checkmark
- [ ] Verify 6-digit requirement

---

## 3. File Upload Testing

### Test 3.1: PDF Upload
- [ ] Click "Choose File" for documents
- [ ] Select a PDF file
- [ ] Verify file preview appears
- [ ] Check file name is displayed
- [ ] Check file size is shown
- [ ] Verify PDF icon appears

### Test 3.2: Image Upload
- [ ] Click "Choose File" for passport photo
- [ ] Select an image file (JPG/PNG)
- [ ] Verify file preview appears
- [ ] Check image icon appears
- [ ] Verify file info is correct

### Test 3.3: File Removal
- [ ] Upload a file
- [ ] Click the X button in preview
- [ ] Verify file is cleared
- [ ] Verify preview disappears
- [ ] Verify can upload again

### Test 3.4: File Size Validation
- [ ] Try uploading file > 5MB
- [ ] Verify error message appears
- [ ] Verify file is not accepted

---

## 4. Animation Testing

### Test 4.1: Page Load Animations
- [ ] Refresh page
- [ ] Verify title fades in from top
- [ ] Verify progress indicator slides in
- [ ] Verify sections fade in sequentially
- [ ] All animations should be smooth (60 FPS)

### Test 4.2: Hover Animations
- [ ] Hover over form section
- [ ] Verify section lifts up
- [ ] Verify shadow increases
- [ ] Verify icon rotates slightly
- [ ] Verify gradient border glow appears

### Test 4.3: Button Animations
- [ ] Hover over submit button
- [ ] Verify button lifts up
- [ ] Verify shadow expands
- [ ] Click button
- [ ] Verify ripple effect appears

### Test 4.4: Progress Animations
- [ ] Fill a field
- [ ] Verify progress circle animates
- [ ] Verify progress line extends smoothly
- [ ] Verify checkmark pops in when complete

---

## 5. Mobile Responsiveness Testing

### Test 5.1: Layout (Mobile)
- [ ] Open on mobile device or resize browser to < 768px
- [ ] Verify single column layout
- [ ] Verify progress circles are smaller (40px)
- [ ] Verify buttons are full-width
- [ ] Verify text is readable

### Test 5.2: Touch Interactions
- [ ] Tap on input fields
- [ ] Verify keyboard appears
- [ ] Verify fields are easy to tap
- [ ] Verify buttons are touch-friendly
- [ ] Verify no accidental taps

### Test 5.3: Scroll Behavior
- [ ] Scroll through form
- [ ] Verify smooth scrolling
- [ ] Verify progress indicator stays visible
- [ ] Verify no horizontal scroll

---

## 6. Form Functionality Testing

### Test 6.1: Course Locking
- [ ] Access via registration link with course_id
- [ ] Verify course field is locked
- [ ] Verify training center is locked
- [ ] Verify lock icon appears
- [ ] Verify "Locked by registration link" message

### Test 6.2: Age Calculation
- [ ] Enter date of birth
- [ ] Verify age is calculated automatically
- [ ] Verify age updates when DOB changes

### Test 6.3: State/City API
- [ ] Select a state
- [ ] Verify cities load for that state
- [ ] Change state
- [ ] Verify cities update

### Test 6.4: Education Table
- [ ] Click "Add More" button
- [ ] Verify new row is added
- [ ] Fill in row data
- [ ] Click remove button
- [ ] Verify row is removed
- [ ] Verify rows are renumbered

---

## 7. Form Submission Testing

### Test 7.1: Validation on Submit
- [ ] Leave required fields empty
- [ ] Click submit
- [ ] Verify toast error appears
- [ ] Verify specific field error is shown
- [ ] Verify form doesn't submit

### Test 7.2: Successful Submission
- [ ] Fill all required fields correctly
- [ ] Upload all required files
- [ ] Click submit
- [ ] Verify loading spinner appears
- [ ] Verify button is disabled
- [ ] Verify "Submitting..." text shows
- [ ] Verify toast notification appears

### Test 7.3: File Validation on Submit
- [ ] Try submitting without documents
- [ ] Verify error: "Please upload educational documents"
- [ ] Try submitting without photo
- [ ] Verify error: "Please upload passport photo"
- [ ] Try submitting without signature
- [ ] Verify error: "Please upload signature"

---

## 8. Browser Compatibility Testing

### Test 8.1: Chrome
- [ ] Open in Chrome
- [ ] Test all features
- [ ] Verify animations work
- [ ] Verify no console errors

### Test 8.2: Firefox
- [ ] Open in Firefox
- [ ] Test all features
- [ ] Verify animations work
- [ ] Verify no console errors

### Test 8.3: Safari
- [ ] Open in Safari
- [ ] Test all features
- [ ] Verify animations work
- [ ] Verify no console errors

### Test 8.4: Edge
- [ ] Open in Edge
- [ ] Test all features
- [ ] Verify animations work
- [ ] Verify no console errors

---

## 9. Accessibility Testing

### Test 9.1: Keyboard Navigation
- [ ] Use Tab key to navigate
- [ ] Verify focus order is logical
- [ ] Verify focus indicators are visible
- [ ] Verify can submit with Enter key

### Test 9.2: Focus States
- [ ] Tab through all inputs
- [ ] Verify blue outline appears on focus
- [ ] Verify outline is 2px thick
- [ ] Verify outline has 2px offset

### Test 9.3: Screen Reader
- [ ] Use screen reader (NVDA/JAWS)
- [ ] Verify labels are read correctly
- [ ] Verify required fields are announced
- [ ] Verify error messages are read

---

## 10. Performance Testing

### Test 10.1: Load Time
- [ ] Open page with DevTools Network tab
- [ ] Measure total load time
- [ ] Should be < 2 seconds
- [ ] Verify no blocking resources

### Test 10.2: Animation Performance
- [ ] Open DevTools Performance tab
- [ ] Record while interacting with page
- [ ] Verify 60 FPS maintained
- [ ] Verify no jank or stuttering

### Test 10.3: Memory Usage
- [ ] Open DevTools Memory tab
- [ ] Take heap snapshot
- [ ] Interact with page
- [ ] Take another snapshot
- [ ] Verify no memory leaks

---

## 11. Edge Cases Testing

### Test 11.1: Very Long Names
- [ ] Enter very long name (100+ characters)
- [ ] Verify field handles it gracefully
- [ ] Verify no layout breaking

### Test 11.2: Special Characters
- [ ] Enter special characters in text fields
- [ ] Verify they are accepted/rejected appropriately
- [ ] Verify no XSS vulnerabilities

### Test 11.3: Multiple File Uploads
- [ ] Upload file
- [ ] Upload different file (replace)
- [ ] Verify preview updates
- [ ] Verify old file is cleared

### Test 11.4: Rapid Input Changes
- [ ] Type quickly in validated fields
- [ ] Verify validation doesn't lag
- [ ] Verify no flickering

---

## 12. Integration Testing

### Test 12.1: End-to-End Flow
- [ ] Start from course page
- [ ] Click "Apply Now" button
- [ ] Verify registration form opens with locked course
- [ ] Fill entire form
- [ ] Upload all documents
- [ ] Submit form
- [ ] Verify success page appears
- [ ] Verify email is sent
- [ ] Verify database entry created

### Test 12.2: QR Code Flow
- [ ] Generate QR code for course
- [ ] Scan QR code with mobile
- [ ] Verify registration form opens
- [ ] Verify course is locked
- [ ] Complete registration on mobile
- [ ] Verify submission works

---

## 🎯 Quick Test Script

### 5-Minute Smoke Test
```
1. Open registration page
2. Verify progress indicator appears
3. Fill Level 1 → Check progress updates
4. Fill Level 2 → Check validation works
5. Upload files → Check preview appears
6. Submit form → Check loading state
7. Verify success page
```

### 15-Minute Full Test
```
1. Test progress indicator (all states)
2. Test real-time validation (all fields)
3. Test file uploads (all types)
4. Test animations (hover, focus, load)
5. Test mobile responsiveness
6. Test form submission
7. Test error handling
8. Test accessibility
```

---

## 📊 Test Results Template

```
Date: __________
Tester: __________
Browser: __________
Device: __________

Progress Indicator:     [ ] Pass  [ ] Fail
Real-Time Validation:   [ ] Pass  [ ] Fail
File Upload Preview:    [ ] Pass  [ ] Fail
Animations:             [ ] Pass  [ ] Fail
Mobile Responsive:      [ ] Pass  [ ] Fail
Form Submission:        [ ] Pass  [ ] Fail
Accessibility:          [ ] Pass  [ ] Fail
Performance:            [ ] Pass  [ ] Fail

Issues Found:
1. ___________________________
2. ___________________________
3. ___________________________

Overall Status: [ ] Pass  [ ] Fail

Notes:
_________________________________
_________________________________
```

---

## 🐛 Common Issues & Solutions

### Issue: Progress not updating
**Solution**: Check browser console for JavaScript errors

### Issue: Animations not smooth
**Solution**: Verify GPU acceleration is enabled in browser

### Issue: File preview not showing
**Solution**: Check file input has correct event listener

### Issue: Validation not working
**Solution**: Verify field names match validation logic

### Issue: Mobile layout broken
**Solution**: Check viewport meta tag is present

---

## ✅ Final Checklist

Before marking as complete, verify:

- [ ] All animations are smooth (60 FPS)
- [ ] Progress indicator works correctly
- [ ] Real-time validation provides feedback
- [ ] File uploads show preview
- [ ] Mobile layout is responsive
- [ ] Form submission works
- [ ] Error handling is robust
- [ ] Accessibility is maintained
- [ ] Performance is excellent
- [ ] No console errors
- [ ] Works in all major browsers
- [ ] Works on mobile devices

---

**Status**: Ready for Testing  
**Version**: 2.0  
**Last Updated**: February 11, 2026  
**Estimated Test Time**: 30-45 minutes (full test)
