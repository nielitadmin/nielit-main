# Quick Start - Admission Order Edit Fix 🚀

## 1️⃣ Run Database Update (ONE TIME ONLY)

Open this URL in your browser:
```
http://localhost/nielit_bhubaneswar/batch_module/update_admission_order_columns.php
```

You should see:
```
✓ Successfully added column: admission_order_ref
✓ Successfully added column: admission_order_date
✓ Successfully added column: location
✓ Successfully added column: examination_month
✓ Successfully added column: class_time
✓ Successfully added column: scheme_incharge
✓ Successfully added column: copy_to_list
✓ Successfully added column: scheme_id

========================================
Update Complete!
Success: 8
Errors: 0
========================================
```

---

## 2️⃣ Test the Feature

### Go to Admission Order Page:
```
Admin Dashboard → Batches → Select a Batch → Generate Admission Order
```

### You'll see:
```
┌─────────────────────────────────────────────────────────┐
│ Edit Order Details (Click to edit, changes apply...)   │
├─────────────────────────────────────────────────────────┤
│ Ref: [NIELIT/BBSR/...]                                 │
│ Dated: [2026-02-19]                                    │
│ Location: [NIELIT Bhubaneswar ▼]                       │
│ Examination Month: [March 2026]                        │
│ Time: [9:00 AM to 1:30 PM]                            │
│ Faculty Name: [Kaushik Mohanty]                        │
│ Scheme/Project Incharge: [Name]                        │
│ Copy To: [Recipients list...]                          │
└─────────────────────────────────────────────────────────┘
```

---

## 3️⃣ Edit and Save

### Step-by-Step:
1. **Edit any field** (e.g., change Location to "NIELIT Balasore")
2. **Click the GREEN button**: "Save Changes & Regenerate"
3. **Wait for notification**: "✓ Changes saved successfully!"
4. **Download PDF** - Your changes are included!

---

## 4️⃣ Verify It Works

### Test 1: Persistence
```
1. Edit Ref to "TEST-123"
2. Click "Save Changes & Regenerate"
3. Refresh the page (F5)
4. Check if Ref still shows "TEST-123" ✓
```

### Test 2: PDF Download
```
1. Edit Location to "NIELIT Balasore"
2. Click "Save Changes & Regenerate"
3. Click "Download PDF"
4. Open PDF - Check if location is "NIELIT Balasore" ✓
```

### Test 3: Copy To List
```
1. Edit Copy To field:
   Director, NIELIT Bhubaneswar
   MIS Incharge
   Examination Incharge
2. Click "Save Changes & Regenerate"
3. Check preview - Should show numbered list ✓
```

---

## 🎯 Common Use Cases

### Use Case 1: Change Location
```
Problem: Batch is at Balasore extension centre
Solution:
1. Change Location dropdown to "NIELIT Balasore"
2. Save Changes & Regenerate
3. PDF now shows Balasore location
```

### Use Case 2: Update Exam Month
```
Problem: Exam postponed to April
Solution:
1. Change Examination Month to "April 2026"
2. Save Changes & Regenerate
3. PDF shows updated exam month
```

### Use Case 3: Custom Reference Number
```
Problem: Need specific ref format
Solution:
1. Edit Ref to "NIELIT/BBSR/2026/BATCH-01"
2. Save Changes & Regenerate
3. PDF uses your custom ref number
```

### Use Case 4: Add More Recipients
```
Problem: Need to add more people to Copy To
Solution:
1. Edit Copy To field, add new lines:
   Director Incharge
   MIS Incharge
   Accounts Officer
   Training Coordinator
2. Save Changes & Regenerate
3. PDF shows all 4 recipients numbered
```

---

## ⚠️ Troubleshooting

### Problem: Button doesn't work
**Solution**: Check browser console (F12) for errors

### Problem: Changes not saving
**Solution**: 
1. Verify database update ran successfully
2. Check if you're logged in as admin
3. Try clearing browser cache

### Problem: Old data still showing
**Solution**: Click "Refresh Preview" to reload from database

### Problem: PDF has old data
**Solution**: Make sure you clicked "Save Changes & Regenerate" before downloading

---

## 📋 Checklist

Before using the feature:
- [ ] Ran `update_admission_order_columns.php`
- [ ] Saw success message (8 columns added)
- [ ] Logged in as admin
- [ ] Opened a batch's admission order page

To save changes:
- [ ] Edited the fields you want to change
- [ ] Clicked "Save Changes & Regenerate" (GREEN button)
- [ ] Saw "✓ Changes saved successfully!" notification
- [ ] Downloaded PDF to verify

---

## 🎉 You're Done!

The admission order edit feature is now working. Your changes will:
- ✅ Save to database permanently
- ✅ Show in preview immediately
- ✅ Appear in downloaded PDFs
- ✅ Appear in printed documents
- ✅ Persist across sessions
- ✅ Be batch-specific

---

**Need Help?**
- Check `ADMISSION_ORDER_EDIT_FIX.md` for detailed documentation
- Check `ADMISSION_ORDER_BUTTONS_GUIDE.md` for button explanations
- Check browser console (F12) for JavaScript errors
