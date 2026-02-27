# Test Admission Order Save Feature 🧪

## Pre-Test Setup

### 1. Run Database Migration
```
URL: http://localhost/nielit_bhubaneswar/batch_module/update_admission_order_columns.php
```

**Expected Result:**
```
✓ Successfully added column: admission_order_ref
✓ Successfully added column: admission_order_date
✓ Successfully added column: location
✓ Successfully added column: examination_month
✓ Successfully added column: class_time
✓ Successfully added column: scheme_incharge
✓ Successfully added column: copy_to_list
✓ Successfully added column: scheme_id

Update Complete!
Success: 8
Errors: 0
```

---

## Test Cases

### Test 1: Basic Save Functionality ✅

**Steps:**
1. Login as admin
2. Go to: Admin → Batches → Select any batch → Generate Admission Order
3. Change Ref to: `TEST-REF-001`
4. Click "Save Changes & Regenerate" (green button)

**Expected Result:**
- Button shows "Saving..." with spinner
- Green toast appears: "✓ Changes saved successfully!"
- Preview regenerates automatically
- Ref field shows `TEST-REF-001`

**Pass Criteria:** ✅ Success notification appears and preview updates

---

### Test 2: Data Persistence ✅

**Steps:**
1. Complete Test 1 first
2. Press F5 to refresh the page
3. Wait for page to reload

**Expected Result:**
- Page reloads
- Ref field still shows `TEST-REF-001`
- All other saved values remain

**Pass Criteria:** ✅ Changes survive page refresh

---

### Test 3: Location Dropdown ✅

**Steps:**
1. Change Location dropdown to "NIELIT Balasore"
2. Click "Save Changes & Regenerate"
3. Check preview section

**Expected Result:**
- Success notification appears
- Preview shows "Location: NIELIT Balasore"
- Dropdown still shows "NIELIT Balasore"

**Pass Criteria:** ✅ Location change saves and displays correctly

---

### Test 4: Date Picker ✅

**Steps:**
1. Click on "Dated" field
2. Select a different date (e.g., tomorrow)
3. Click "Save Changes & Regenerate"
4. Check preview

**Expected Result:**
- Success notification appears
- Preview shows new date in DD.MM.YYYY format
- Date picker shows selected date

**Pass Criteria:** ✅ Date saves and formats correctly

---

### Test 5: Multi-line Copy To List ✅

**Steps:**
1. Edit "Copy To" field with:
```
Director Incharge, NIELIT Bhubaneswar
MIS Incharge, for necessary action
Examination Incharge
Accounts Officer, DDO
```
2. Click "Save Changes & Regenerate"
3. Scroll down to "Copy to:" section in preview

**Expected Result:**
- Success notification appears
- Preview shows numbered list:
  1. Director Incharge, NIELIT Bhubaneswar
  2. MIS Incharge, for necessary action
  3. Examination Incharge
  4. Accounts Officer, DDO

**Pass Criteria:** ✅ Multi-line text saves and formats as numbered list

---

### Test 6: PDF Download ✅

**Steps:**
1. Make several changes (Ref, Location, Exam Month)
2. Click "Save Changes & Regenerate"
3. Wait for success notification
4. Click "Download PDF" button
5. Open downloaded PDF

**Expected Result:**
- PDF downloads successfully
- PDF contains all saved changes
- Filename: `admission_order_[BATCH_CODE].pdf`

**Pass Criteria:** ✅ PDF includes all saved changes

---

### Test 7: Print Function ✅

**Steps:**
1. Make some changes and save
2. Click "Print" button
3. Check print preview

**Expected Result:**
- Print dialog opens
- Print preview shows all saved changes
- Formatted for A4 paper

**Pass Criteria:** ✅ Print preview includes saved changes

---

### Test 8: Refresh Without Saving ✅

**Steps:**
1. Edit Ref to `UNSAVED-TEST`
2. DO NOT click save
3. Click "Refresh Preview" (blue button)

**Expected Result:**
- Preview reloads
- Ref field shows last saved value (not `UNSAVED-TEST`)
- Unsaved changes are discarded

**Pass Criteria:** ✅ Refresh discards unsaved changes

---

### Test 9: Multiple Field Save ✅

**Steps:**
1. Edit ALL fields:
   - Ref: `MULTI-TEST-001`
   - Date: Tomorrow
   - Location: NIELIT Balasore
   - Exam Month: `April 2026`
   - Time: `10:00 AM to 2:00 PM`
   - Faculty: `Test Faculty`
   - Incharge: `Test Incharge`
   - Copy To: Add 3 recipients
2. Click "Save Changes & Regenerate"

**Expected Result:**
- Success notification appears
- ALL fields update in preview
- All changes persist after refresh

**Pass Criteria:** ✅ All fields save simultaneously

---

### Test 10: Error Handling ✅

**Steps:**
1. Open browser console (F12)
2. Stop the web server temporarily
3. Edit a field
4. Click "Save Changes & Regenerate"

**Expected Result:**
- Red toast appears with error message
- Button returns to normal state
- Console shows error details

**Pass Criteria:** ✅ Error is handled gracefully

---

### Test 11: Session Validation ✅

**Steps:**
1. Open admission order page
2. Open new tab and logout
3. Return to admission order tab
4. Edit a field and try to save

**Expected Result:**
- Error notification appears
- Message indicates unauthorized access
- No database changes made

**Pass Criteria:** ✅ Requires active admin session

---

### Test 12: Different Batches ✅

**Steps:**
1. Open Batch A admission order
2. Set Ref to `BATCH-A-REF`
3. Save changes
4. Go to Batch B admission order
5. Set Ref to `BATCH-B-REF`
6. Save changes
7. Return to Batch A

**Expected Result:**
- Batch A shows `BATCH-A-REF`
- Batch B shows `BATCH-B-REF`
- Each batch has independent settings

**Pass Criteria:** ✅ Settings are batch-specific

---

## Performance Tests

### Test 13: Save Speed ⚡

**Steps:**
1. Edit a field
2. Click save
3. Time how long until success notification

**Expected Result:**
- Save completes in < 2 seconds
- No lag or freezing

**Pass Criteria:** ✅ Fast response time

---

### Test 14: Large Copy To List 📝

**Steps:**
1. Add 20 recipients to Copy To field
2. Click save
3. Check preview

**Expected Result:**
- All 20 recipients save
- All appear in preview
- No truncation

**Pass Criteria:** ✅ Handles large text fields

---

## Browser Compatibility Tests

### Test 15: Chrome/Edge ✅
- Run Tests 1-9 in Chrome
- All should pass

### Test 16: Firefox ✅
- Run Tests 1-9 in Firefox
- All should pass

### Test 17: Safari ✅
- Run Tests 1-9 in Safari
- All should pass

---

## Regression Tests

### Test 18: Existing Features Still Work ✅

**Steps:**
1. Verify "Back to Batch Details" button works
2. Verify student table displays correctly
3. Verify category summary calculates correctly
4. Verify header and footer display correctly

**Expected Result:**
- All existing features work normally
- No broken functionality

**Pass Criteria:** ✅ No regressions introduced

---

## Test Results Template

```
┌─────────────────────────────────────────────────────────┐
│  ADMISSION ORDER SAVE FEATURE - TEST RESULTS            │
├─────────────────────────────────────────────────────────┤
│  Date: _________________                                │
│  Tester: _______________                                │
│  Environment: __________                                │
├─────────────────────────────────────────────────────────┤
│  Test 1:  Basic Save              [ ] Pass  [ ] Fail    │
│  Test 2:  Data Persistence        [ ] Pass  [ ] Fail    │
│  Test 3:  Location Dropdown       [ ] Pass  [ ] Fail    │
│  Test 4:  Date Picker             [ ] Pass  [ ] Fail    │
│  Test 5:  Copy To List            [ ] Pass  [ ] Fail    │
│  Test 6:  PDF Download            [ ] Pass  [ ] Fail    │
│  Test 7:  Print Function          [ ] Pass  [ ] Fail    │
│  Test 8:  Refresh Without Save    [ ] Pass  [ ] Fail    │
│  Test 9:  Multiple Fields         [ ] Pass  [ ] Fail    │
│  Test 10: Error Handling          [ ] Pass  [ ] Fail    │
│  Test 11: Session Validation      [ ] Pass  [ ] Fail    │
│  Test 12: Different Batches       [ ] Pass  [ ] Fail    │
│  Test 13: Save Speed              [ ] Pass  [ ] Fail    │
│  Test 14: Large Copy To           [ ] Pass  [ ] Fail    │
│  Test 15: Chrome/Edge             [ ] Pass  [ ] Fail    │
│  Test 16: Firefox                 [ ] Pass  [ ] Fail    │
│  Test 17: Safari                  [ ] Pass  [ ] Fail    │
│  Test 18: No Regressions          [ ] Pass  [ ] Fail    │
├─────────────────────────────────────────────────────────┤
│  Total Tests: 18                                        │
│  Passed: ____                                           │
│  Failed: ____                                           │
│  Pass Rate: ____%                                       │
├─────────────────────────────────────────────────────────┤
│  Notes:                                                 │
│  _____________________________________________________  │
│  _____________________________________________________  │
│  _____________________________________________________  │
└─────────────────────────────────────────────────────────┘
```

---

## Quick Smoke Test (5 minutes)

If you're short on time, run these essential tests:

1. ✅ Run database migration
2. ✅ Edit Ref field and save
3. ✅ Refresh page - verify Ref persists
4. ✅ Download PDF - verify Ref in PDF
5. ✅ Edit Copy To and save
6. ✅ Verify numbered list in preview

If all 6 pass → Feature is working! ✅

---

## Troubleshooting

### Issue: Button doesn't respond
**Check:**
- Browser console for JavaScript errors
- Admin session is active
- Page loaded completely

### Issue: Changes don't save
**Check:**
- Database migration ran successfully
- PHP errors in browser network tab
- File permissions on save_admission_order_details.php

### Issue: Old data in preview
**Check:**
- Clicked "Save Changes & Regenerate" (not just "Refresh")
- Success notification appeared
- Database actually updated (check with phpMyAdmin)

---

## Success Criteria

Feature is ready for production if:
- ✅ All 18 tests pass
- ✅ No console errors
- ✅ No PHP errors
- ✅ Works in all browsers
- ✅ Data persists correctly
- ✅ PDF includes changes
- ✅ No regressions

---

**Testing Complete!** 🎉
