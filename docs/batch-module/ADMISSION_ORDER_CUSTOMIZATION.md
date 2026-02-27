# Admission Order Customization Complete

## Changes Made

### 1. Database Updates
**File:** `schemes_module/add_admission_order_fields.sql`

Added five new columns to the `batches` table:
- `admission_order_ref` - Custom reference number for the admission order
- `admission_order_date` - Custom date for the admission order
- `examination_month` - Custom examination month (e.g., "March 2026")
- `class_time` - Training session timings (default: "9:00 AM to 1:30 PM")
- `copy_to_list` - Custom list of recipients for "Copy to" section (TEXT field)

**Action Required:** Run this SQL file in phpMyAdmin to add the new columns.

### 2. Edit Batch Form Updates
**File:** `batch_module/admin/edit_batch.php`

Added new section "Admission Order Details" with five fields:
- **Reference Number** - Editable field with auto-generation fallback
- **Order Date** - Date picker, defaults to today
- **Examination Month** - Text field with auto-calculation fallback
- **Class Time** - Text field for training timings (default: "9:00 AM to 1:30 PM")
- **Copy To (Recipients)** - Textarea for custom recipient list (one per line)

### 3. Backend Function Updates
**File:** `batch_module/includes/batch_functions.php`

Updated `updateBatch()` function to save all five new admission order fields.

### 4. Admission Order Generation Updates
**File:** `schemes_module/admin/generate_admission_order_ajax.php`

#### Auto-Fill Logic:
1. **Faculty Name**: Automatically uses `course_coordinator` from courses table
   - Fallback 1: Uses `batch_coordinator` if course coordinator is empty
   - Fallback 2: Shows "To be assigned" if both are empty

2. **Reference Number**: 
   - Uses custom value from batch if set
   - Auto-generates: `NIELIT/BBSR/Admission Order/FY-26-27/[batch_id]`

3. **Order Date**:
   - Uses custom date from batch if set
   - Defaults to today's date

4. **Examination Month**:
   - Uses custom value from batch if set
   - Auto-calculates from batch end_date (e.g., "March 2026")

5. **Class Time**:
   - Uses custom value from batch if set
   - Defaults to "9:00 AM to 1:30 PM"

6. **Copy To List**:
   - Uses custom recipients from batch if set
   - Defaults to standard NIELIT recipients:
     * Director Incharge, NIELIT Bhubaneswar
     * Incharge MIS, NIELIT Bhubaneswar
     * Examination Incharge, NIELIT Bhubaneswar
     * Ms. SukanyaPalli, Assistant Accounts& DDO

7. **Course Details**: All pulled from courses table
   - Course Name
   - Course Code
   - Duration
   - Training Fees

## How to Use

### Step 1: Run Database Migration
```sql
-- In phpMyAdmin, run:
schemes_module/add_admission_order_fields.sql
```

### Step 2: Edit Batch Settings
1. Go to **Batches** → **Manage Batches**
2. Click **Edit** on any batch
3. Scroll to **Admission Order Details** section
4. Fill in custom values (optional):
   - Reference Number (leave blank for auto-generation)
   - Order Date (defaults to today)
   - Examination Month (leave blank for auto-calculation)
   - Class Time (defaults to "9:00 AM to 1:30 PM")
   - Copy To Recipients (leave blank for default list, or enter custom recipients one per line)
5. Click **Update Batch**

### Step 3: Generate Admission Order
1. Go to **Schemes** → **Edit Scheme**
2. Find the batch in the "Batches Under This Scheme" section
3. Click **Generate Admission Order**
4. The order will show:
   - Custom Ref number (or auto-generated)
   - Custom date (or today's date)
   - Custom examination month (or auto-calculated)
   - Custom class time (or default)
   - Custom copy to list (or default recipients)
   - Faculty name from course coordinator
   - All course details from courses table

## Example: Custom Copy To List

In the "Copy To (Recipients)" field, enter one recipient per line:

```
Director, NIELIT Bhubaneswar
Project Coordinator, SCSP/TSP
Finance Officer, NIELIT Bhubaneswar
State Nodal Officer, Skill Development
```

This will replace the default recipients in the admission order.

## Data Flow

```
Courses Table
├── course_coordinator → Faculty Name
├── course_name → Course Name
├── course_code → Course Code
├── duration → Duration
└── training_fees → (not used in admission order)

Batches Table
├── batch_name → Batch ID
├── start_date → Start Date
├── end_date → End Date (used for auto exam month)
├── admission_order_ref → Ref Number
├── admission_order_date → Dated
├── examination_month → Examination Month
├── class_time → Time
└── copy_to_list → Copy To section

Schemes Table
├── scheme_name → Scheme Admitted
└── scheme_code → Remark/Scheme column

Students Table
└── course (matches course_name) → Student List
```

## Features

✅ Editable reference number with auto-generation
✅ Editable order date with today's date default
✅ Editable examination month with auto-calculation
✅ Editable class time with default value
✅ Editable copy to recipients list with default list
✅ Auto-fill faculty name from course coordinator
✅ All course details pulled from database
✅ All batch details pulled from database
✅ Scheme details pulled from database
✅ Student list filtered by course name

## Testing

1. **Test Auto-Generation:**
   - Leave all admission order fields blank
   - Generate order
   - Should show auto-generated values and default recipients

2. **Test Custom Values:**
   - Set custom ref: "NIELIT/BBSR/AO/2026/SCSP/001"
   - Set custom date: "15-02-2026"
   - Set custom exam month: "April 2026"
   - Set custom time: "10:00 AM to 2:00 PM"
   - Set custom recipients (one per line)
   - Generate order
   - Should show your custom values

3. **Test Faculty Name:**
   - Add course coordinator in course settings
   - Generate admission order
   - Should show course coordinator name

## Files Modified

1. `schemes_module/add_admission_order_fields.sql` (UPDATED - added 2 new fields)
2. `batch_module/admin/edit_batch.php` (UPDATED - added 2 new form fields)
3. `batch_module/includes/batch_functions.php` (UPDATED - added 2 new fields to update)
4. `schemes_module/admin/generate_admission_order_ajax.php` (UPDATED - added time and copy to logic)

## Next Steps

1. Run the SQL migration file (updated version)
2. Test the new fields in edit batch form
3. Generate an admission order to verify all data is correct
4. Customize class time and copy to recipients as needed
