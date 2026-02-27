# Admission Order Save - Visual Guide

## 🔴 Current Problem

```
┌─────────────────────────────────────────────────┐
│  Edit Order Details                             │
│  ┌──────────────────────────────────────────┐  │
│  │ Ref: NIELIT/BBSR/...                     │  │
│  │ Dated: 2026-02-20                        │  │
│  │ Location: NIELIT Bhubaneswar             │  │
│  │ Examination Month: March 2026            │  │
│  │ Time: 9:00 AM to 1:30 PM                 │  │
│  │ Faculty Name: Kaushik Mohanty            │  │
│  │ Scheme Incharge: Name                    │  │
│  │ Copy To: (recipients list)               │  │
│  └──────────────────────────────────────────┘  │
│                                                 │
│  [Save Changes & Regenerate] ← CLICK THIS      │
└─────────────────────────────────────────────────┘
                    ↓
            ❌ NOT SAVING!
            (Database columns missing)
```

## ✅ Solution Flow

### Step 1: Run the Fix Script

```
Browser → http://localhost/nielit/batch_module/admin/check_and_fix_admission_order.php

┌─────────────────────────────────────────────────┐
│  Admission Order Database Check & Fix          │
│                                                 │
│  ✓ Column 'admission_order_ref' exists         │
│  ✗ Column 'admission_order_date' is MISSING    │
│  ✗ Column 'examination_month' is MISSING       │
│  ✗ Column 'class_time' is MISSING              │
│  ✗ Column 'scheme_incharge' is MISSING         │
│  ✗ Column 'copy_to_list' is MISSING            │
│  ✗ Column 'location' is MISSING                │
│                                                 │
│  [Fix Database Now] ← CLICK THIS               │
└─────────────────────────────────────────────────┘
```

### Step 2: Columns Get Added

```
Database: batches table

BEFORE:
┌────┬────────────┬──────────┬───────────┐
│ id │ batch_name │ course_id│ scheme_id │
├────┼────────────┼──────────┼───────────┤
│ 1  │ DBC-2026   │ 5        │ 2         │
└────┴────────────┴──────────┴───────────┘

AFTER:
┌────┬────────────┬──────────┬───────────┬──────────────────┬──────────────────┬─────────────────┬────────────┬─────────────────┬──────────────┬──────────────────┐
│ id │ batch_name │ course_id│ scheme_id │ admission_order_ │ admission_order_ │ examination_    │ class_time │ scheme_incharge │ copy_to_list │ location         │
│    │            │          │           │ ref              │ date             │ month           │            │                 │              │                  │
├────┼────────────┼──────────┼───────────┼──────────────────┼──────────────────┼─────────────────┼────────────┼─────────────────┼──────────────┼──────────────────┤
│ 1  │ DBC-2026   │ 5        │ 2         │ NULL             │ NULL             │ NULL            │ NULL       │ NULL            │ NULL         │ NIELIT Bhubanes..│
└────┴────────────┴──────────┴───────────┴──────────────────┴──────────────────┴─────────────────┴────────────┴─────────────────┴──────────────┴──────────────────┘
                                          ↑ NEW COLUMNS ADDED ↑
```

### Step 3: Save Now Works!

```
┌─────────────────────────────────────────────────┐
│  Edit Order Details                             │
│  ┌──────────────────────────────────────────┐  │
│  │ Ref: NIELIT/BBSR/2026/001                │  │ ← Edit this
│  │ Dated: 2026-03-15                        │  │ ← Edit this
│  │ Location: NIELIT Balasore                │  │ ← Change this
│  │ Examination Month: April 2026            │  │ ← Edit this
│  │ Time: 10:00 AM to 2:00 PM                │  │ ← Edit this
│  │ Faculty Name: John Doe                   │  │ ← Edit this
│  │ Scheme Incharge: Jane Smith              │  │ ← Edit this
│  │ Copy To: Director, NIELIT                │  │ ← Edit this
│  └──────────────────────────────────────────┘  │
│                                                 │
│  [Save Changes & Regenerate] ← CLICK           │
└─────────────────────────────────────────────────┘
                    ↓
        ✅ Changes saved successfully!
                    ↓
┌─────────────────────────────────────────────────┐
│  Preview updates immediately with your changes  │
│                                                 │
│  Ref: NIELIT/BBSR/2026/001                     │
│  Dated: 15.03.2026                             │
│  Location: NIELIT Balasore                     │
│  ...                                           │
└─────────────────────────────────────────────────┘
```

## 🎯 What Each Field Does

| Field | Saved As | Appears In Document |
|-------|----------|---------------------|
| **Ref** | `admission_order_ref` | Top left: "Ref: NIELIT/BBSR/..." |
| **Dated** | `admission_order_date` | Top right: "Dated: 15.03.2026" |
| **Location** | `location` | Body: "Location: NIELIT Bhubaneswar" |
| **Examination Month** | `examination_month` | Body: "Examination Month (Proposed): March 2026" |
| **Time** | `class_time` | Body: "Time: 9:00 AM to 1:30 PM" |
| **Faculty Name** | `batch_coordinator` | Body: "Faculty Name: Kaushik Mohanty" |
| **Scheme Incharge** | `scheme_incharge` | Signature: "(SCSP/TSP) Incharge" |
| **Copy To** | `copy_to_list` | Bottom: Numbered list of recipients |

## 🔄 Complete Workflow

```
1. Admin opens Generate Admission Order page
   ↓
2. Sees editable fields with current/default values
   ↓
3. Edits any field (changes appear in preview immediately)
   ↓
4. Clicks "Save Changes & Regenerate"
   ↓
5. JavaScript collects all field values
   ↓
6. Sends POST request to save_admission_order_details.php
   ↓
7. PHP updates batches table with new values
   ↓
8. Returns success message
   ↓
9. Page regenerates preview with saved data
   ↓
10. Admin can download PDF or print with saved changes
```

## 📋 Testing Checklist

After running the fix:

- [ ] Open any batch's admission order page
- [ ] Edit the Ref field and save
- [ ] Refresh page - Ref should persist
- [ ] Edit the Location dropdown and save
- [ ] Refresh page - Location should persist
- [ ] Edit Copy To list (add multiple lines) and save
- [ ] Refresh page - All recipients should persist
- [ ] Download PDF - Should show your edited values
- [ ] Print - Should show your edited values

## 🚨 Common Issues

### Issue: "Error saving changes: Unknown column"
**Solution:** Run the fix script again, some columns might not have been added

### Issue: Button does nothing
**Solution:** Check browser console (F12) for JavaScript errors

### Issue: Changes don't persist after refresh
**Solution:** Database columns exist but save script might have an error. Check PHP error logs.

### Issue: "Unauthorized" message
**Solution:** Make sure you're logged in as admin

---

**Quick Fix:** Visit `batch_module/admin/check_and_fix_admission_order.php` and click "Fix Database Now"
