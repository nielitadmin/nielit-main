# Admission Order - Button Guide 🎯

## The Problem (Before)
```
❌ Edit fields → Changes show → Click Refresh → Changes LOST!
❌ Edit fields → Download PDF → Old data in PDF!
❌ No way to save your edits permanently
```

## The Solution (After)
```
✅ Edit fields → Click "Save Changes & Regenerate" → Changes SAVED!
✅ Edit fields → Save → Download PDF → New data in PDF!
✅ Changes persist forever in database
```

---

## Button Layout

```
┌─────────────────────────────────────────────────────────────┐
│  [← Back to Batch]  [💾 Save Changes & Regenerate]  [🔄 Refresh]  │
└─────────────────────────────────────────────────────────────┘
```

---

## Button Functions

### 1. 💾 Save Changes & Regenerate (GREEN)
**When to use**: After editing any field

**What it does**:
1. Saves ALL your edits to the database
2. Shows "Saving..." with spinner
3. Displays success notification
4. Automatically regenerates preview
5. Changes are now permanent

**Example**:
```
1. Change Ref to "NIELIT/BBSR/2026/001"
2. Change Location to "NIELIT Balasore"
3. Click "Save Changes & Regenerate"
4. ✓ Success! Changes saved
5. Download PDF → Your changes are there!
```

---

### 2. 🔄 Refresh Preview (BLUE)
**When to use**: To reload from database

**What it does**:
1. Fetches current saved data from database
2. Regenerates preview
3. Shows what's actually saved
4. Does NOT save any unsaved edits

**Example**:
```
1. You edited some fields but didn't save
2. Click "Refresh Preview"
3. Your unsaved edits disappear
4. Shows the last saved version
```

---

### 3. ⬇️ Download PDF
**What it does**:
- Downloads current preview as PDF
- Includes all saved changes
- Filename: `admission_order_[BATCH_CODE].pdf`

---

### 4. 🖨️ Print
**What it does**:
- Opens print dialog
- Includes all saved changes
- Formatted for A4 paper

---

## Workflow Examples

### Scenario 1: First Time Setup
```
1. Open admission order page
2. Edit all fields (Ref, Date, Location, etc.)
3. Click "Save Changes & Regenerate" 
4. ✓ Success message appears
5. Download PDF or Print
```

### Scenario 2: Update Existing Order
```
1. Open admission order page
2. Current saved values load automatically
3. Change only what you need (e.g., Exam Month)
4. Click "Save Changes & Regenerate"
5. ✓ Only changed fields are updated
6. Download updated PDF
```

### Scenario 3: Undo Unsaved Changes
```
1. Edit some fields
2. Realize you made a mistake
3. Click "Refresh Preview" (don't save)
4. Unsaved edits are discarded
5. Last saved version appears
```

---

## Edit Fields Reference

### Editable Fields:
1. **Ref** - Reference number (e.g., NIELIT/BBSR/Admission Order/FY-25-26/1)
2. **Dated** - Order date (date picker)
3. **Location** - Dropdown: NIELIT Bhubaneswar or NIELIT Balasore
4. **Examination Month** - Text (e.g., "March 2026")
5. **Time** - Class timing (e.g., "9:00 AM to 1:30 PM")
6. **Faculty Name** - Instructor name
7. **Scheme/Project Incharge** - Incharge name for signature
8. **Copy To** - Recipients list (one per line)

### Copy To Format:
```
Director Incharge, NIELIT Bhubaneswar, for Kind Information
Incharge MIS, NIELIT Bhubaneswar, for necessary action
Examination Incharge, NIELIT Bhubaneswar
Ms. SukanyaPalli, Assistant Accounts& DDO
```
Each line becomes a numbered item in the PDF.

---

## Visual Indicators

### While Saving:
```
[💾 Saving...] ← Button shows spinner
```

### After Success:
```
✓ Changes saved successfully! ← Green toast notification
```

### After Error:
```
✗ Error saving changes: [error message] ← Red toast notification
```

---

## Important Notes

⚠️ **Must Save**: Changes are NOT saved automatically. You MUST click "Save Changes & Regenerate"

⚠️ **Per Batch**: Each batch has its own saved settings

⚠️ **Database Required**: Run `update_admission_order_columns.php` first to add database columns

✅ **Real-time Preview**: Changes show immediately as you type (but aren't saved until you click the button)

✅ **Persistent**: Once saved, changes remain forever (until you change them again)

---

## Quick Reference Card

| Action | Button | Result |
|--------|--------|--------|
| Save edits permanently | 💾 Save Changes & Regenerate | Saves to DB + Regenerates |
| Discard unsaved edits | 🔄 Refresh Preview | Reloads from DB |
| Get PDF with changes | ⬇️ Download PDF | Downloads current preview |
| Print with changes | 🖨️ Print | Opens print dialog |

---

**Pro Tip**: Always click "Save Changes & Regenerate" before downloading or printing to ensure your edits are included!
