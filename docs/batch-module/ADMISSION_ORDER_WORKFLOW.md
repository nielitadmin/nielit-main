# Admission Order Edit Workflow - Visual Diagram

## Before Fix ❌

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMISSION ORDER PAGE                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Edit Fields:                                                │
│  ┌────────────────────────────────────────────────────┐    │
│  │ Ref: [NIELIT/BBSR/2026/001]                       │    │
│  │ Date: [2026-02-19]                                 │    │
│  │ Location: [NIELIT Bhubaneswar ▼]                  │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  [🔄 Refresh Preview]                                       │
│                                                              │
│  Preview shows changes ✓                                    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User clicks Refresh
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  ❌ Changes LOST!                                           │
│  ❌ Preview shows old data from database                    │
│  ❌ PDF download has old data                               │
└─────────────────────────────────────────────────────────────┘
```

---

## After Fix ✅

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMISSION ORDER PAGE                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Edit Fields:                                                │
│  ┌────────────────────────────────────────────────────┐    │
│  │ Ref: [NIELIT/BBSR/2026/001]                       │    │
│  │ Date: [2026-02-19]                                 │    │
│  │ Location: [NIELIT Balasore ▼]  ← Changed!         │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  [💾 Save Changes & Regenerate]  [🔄 Refresh Preview]      │
│                                                              │
│  Preview shows changes ✓                                    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User clicks Save
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  JavaScript Function: saveAndRegenerate()                   │
├─────────────────────────────────────────────────────────────┤
│  1. Collect all field values                                │
│  2. Show "Saving..." on button                              │
│  3. Send POST request to save_admission_order_details.php   │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  PHP Backend: save_admission_order_details.php              │
├─────────────────────────────────────────────────────────────┤
│  1. Verify admin session                                    │
│  2. Validate batch_id                                       │
│  3. Prepare SQL UPDATE statement                            │
│  4. Execute: UPDATE batches SET ...                         │
│  5. Return JSON: {success: true}                            │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Database: batches table                                    │
├─────────────────────────────────────────────────────────────┤
│  ✅ admission_order_ref = "NIELIT/BBSR/2026/001"           │
│  ✅ admission_order_date = "2026-02-19"                     │
│  ✅ location = "NIELIT Balasore"                            │
│  ✅ examination_month = "March 2026"                        │
│  ✅ class_time = "9:00 AM to 1:30 PM"                       │
│  ✅ scheme_incharge = "Kaushik Mohanty"                     │
│  ✅ copy_to_list = "Director\nMIS\nExam"                    │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  JavaScript Callback                                        │
├─────────────────────────────────────────────────────────────┤
│  1. Show success toast: "✓ Changes saved successfully!"    │
│  2. Call generateAdmissionOrder()                           │
│  3. Reload preview from database                            │
│  4. Reset button to normal state                            │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  ✅ Changes SAVED permanently!                              │
│  ✅ Preview shows saved data                                │
│  ✅ PDF download has new data                               │
│  ✅ Survives page refresh                                   │
│  ✅ Survives browser close                                  │
└─────────────────────────────────────────────────────────────┘
```

---

## Data Flow Diagram

```
┌──────────────┐
│   Browser    │
│  (Frontend)  │
└──────┬───────┘
       │
       │ 1. User edits fields
       │
       ▼
┌──────────────────────────────────────┐
│  JavaScript (Real-time Preview)      │
│  - Updates display elements          │
│  - No database interaction yet       │
└──────────────────────────────────────┘
       │
       │ 2. User clicks "Save Changes & Regenerate"
       │
       ▼
┌──────────────────────────────────────┐
│  JavaScript: saveAndRegenerate()     │
│  - Collects all field values         │
│  - Creates JSON payload              │
│  - Shows loading state               │
└──────┬───────────────────────────────┘
       │
       │ 3. POST request (JSON)
       │
       ▼
┌──────────────────────────────────────┐
│  PHP: save_admission_order_details   │
│  - Validates session                 │
│  - Validates data                    │
│  - Prepares SQL statement            │
└──────┬───────────────────────────────┘
       │
       │ 4. SQL UPDATE
       │
       ▼
┌──────────────────────────────────────┐
│  MySQL Database                      │
│  - batches table                     │
│  - Updates row for batch_id          │
│  - Commits transaction               │
└──────┬───────────────────────────────┘
       │
       │ 5. Success response
       │
       ▼
┌──────────────────────────────────────┐
│  PHP Response                        │
│  {                                   │
│    "success": true,                  │
│    "message": "Saved successfully"   │
│  }                                   │
└──────┬───────────────────────────────┘
       │
       │ 6. JSON response
       │
       ▼
┌──────────────────────────────────────┐
│  JavaScript Callback                 │
│  - Shows success toast               │
│  - Calls generateAdmissionOrder()    │
└──────┬───────────────────────────────┘
       │
       │ 7. AJAX request for preview
       │
       ▼
┌──────────────────────────────────────┐
│  PHP: generate_admission_order_ajax  │
│  - Fetches batch from database       │
│  - Uses saved values                 │
│  - Generates HTML                    │
└──────┬───────────────────────────────┘
       │
       │ 8. HTML response
       │
       ▼
┌──────────────────────────────────────┐
│  Browser displays updated preview    │
│  ✅ All changes visible              │
│  ✅ Data from database               │
└──────────────────────────────────────┘
```

---

## State Diagram

```
┌─────────────────┐
│  Initial Load   │
│  (Page opens)   │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│  Load Saved Data from Database  │
│  - Fetch batch details          │
│  - Populate edit fields         │
│  - Generate preview             │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────┐
│  Editing State  │◄──────────┐
│  (User typing)  │           │
└────────┬────────┘           │
         │                    │
         │ Click Save         │
         ▼                    │
┌─────────────────┐           │
│  Saving State   │           │
│  (Button shows  │           │
│   spinner)      │           │
└────────┬────────┘           │
         │                    │
         ▼                    │
┌─────────────────┐           │
│  Save Success?  │           │
└────────┬────────┘           │
         │                    │
    Yes  │  No                │
         │  │                 │
         │  └─────────────────┤
         │    Show error      │
         │    Stay in edit    │
         │                    │
         ▼                    │
┌─────────────────┐           │
│  Regenerating   │           │
│  (Loading new   │           │
│   preview)      │           │
└────────┬────────┘           │
         │                    │
         ▼                    │
┌─────────────────┐           │
│  Saved State    │           │
│  (Changes in DB)│───────────┘
│  Can edit again │
└─────────────────┘
```

---

## Button State Machine

```
┌─────────────────────────────────────┐
│  Initial State                      │
│  [💾 Save Changes & Regenerate]    │
│  - Enabled                          │
│  - Green color                      │
│  - Clickable                        │
└────────┬────────────────────────────┘
         │
         │ User clicks button
         ▼
┌─────────────────────────────────────┐
│  Loading State                      │
│  [⏳ Saving...]                     │
│  - Disabled                         │
│  - Spinner icon                     │
│  - Not clickable                    │
└────────┬────────────────────────────┘
         │
         │ Server responds
         ▼
    ┌────┴────┐
    │         │
Success     Error
    │         │
    ▼         ▼
┌───────┐ ┌───────────────────────────┐
│ Toast │ │ Error Toast               │
│ "✓"   │ │ "✗ Error: [message]"      │
└───┬───┘ └───────┬───────────────────┘
    │             │
    │             │
    └─────┬───────┘
          │
          ▼
┌─────────────────────────────────────┐
│  Back to Initial State              │
│  [💾 Save Changes & Regenerate]    │
│  - Enabled                          │
│  - Ready for next save              │
└─────────────────────────────────────┘
```

---

## Database Schema

```
┌─────────────────────────────────────────────────────────┐
│  Table: batches                                         │
├─────────────────────────────────────────────────────────┤
│  id                    INT PRIMARY KEY                  │
│  course_id             INT                              │
│  batch_name            VARCHAR(100)                     │
│  batch_code            VARCHAR(50) UNIQUE               │
│  start_date            DATE                             │
│  end_date              DATE                             │
│  batch_coordinator     VARCHAR(255)                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │ NEW COLUMNS (Added by migration)                │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ admission_order_ref     VARCHAR(255)            │   │
│  │ admission_order_date    DATE                    │   │
│  │ location                VARCHAR(100)            │   │
│  │ examination_month       VARCHAR(50)             │   │
│  │ class_time              VARCHAR(100)            │   │
│  │ scheme_incharge         VARCHAR(255)            │   │
│  │ copy_to_list            TEXT                    │   │
│  │ scheme_id               INT                     │   │
│  └─────────────────────────────────────────────────┘   │
│  status                ENUM('Active','Completed')       │
│  created_at            TIMESTAMP                        │
│  updated_at            TIMESTAMP                        │
└─────────────────────────────────────────────────────────┘
```

---

## File Structure

```
nielit_bhubaneswar/
│
├── batch_module/
│   ├── admin/
│   │   ├── generate_admission_order.php ← Modified (Added save button)
│   │   ├── generate_admission_order_ajax.php ← Modified (Uses saved values)
│   │   └── save_admission_order_details.php ← NEW (Saves to database)
│   │
│   ├── update_admission_order_columns.php ← NEW (Migration script)
│   └── add_admission_order_columns.sql ← NEW (SQL migration)
│
└── Documentation/
    ├── ADMISSION_ORDER_EDIT_FIX.md
    ├── ADMISSION_ORDER_BUTTONS_GUIDE.md
    ├── QUICK_START_ADMISSION_ORDER_FIX.md
    ├── ADMISSION_ORDER_SAVE_FEATURE_COMPLETE.md
    └── ADMISSION_ORDER_WORKFLOW_DIAGRAM.md (this file)
```

---

## Success Indicators

```
✅ Database Migration
   └─ All 8 columns added successfully

✅ Save Button
   └─ Appears on page
   └─ Shows loading state
   └─ Displays notifications

✅ Data Persistence
   └─ Survives page refresh
   └─ Survives browser close
   └─ Appears in PDF downloads

✅ User Experience
   └─ Real-time preview
   └─ Clear feedback
   └─ Error handling
```

---

**Visual Guide Complete** ✅
