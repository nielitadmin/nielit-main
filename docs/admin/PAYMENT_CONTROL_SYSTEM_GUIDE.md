# Payment Details Control System - Complete Guide

## 🎯 System Overview

The payment details control system allows administrators to control whether payment information is **required** or **optional** for each course during student registration.

## 🔧 How It Works

### 1. Admin Control (edit_course.php)

**Location:** `admin/edit_course.php?id=[course_id]`

**Payment Control Section:**
```
┌─────────────────────────────────────────────────────────────┐
│ 💳 Payment Details Requirement *                           │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ▼ Optional (Students can skip)                         │ │
│ │   Required (Must fill payment details)                 │ │
│ └─────────────────────────────────────────────────────────┘ │
│ Students can register without payment details              │
│                                                             │
│ Payment Preview                                             │
│ ┌─────────────────────┐                                     │
│ │ ℹ️ Payment Optional │                                     │
│ └─────────────────────┘                                     │
│ How payment section appears                                 │
└─────────────────────────────────────────────────────────────┘
```

### 2. Student Experience (register.php)

#### When Admin Sets "Optional":
```
┌─────────────────────────────────────────────────────────────┐
│ 💳 Payment Details [Optional]                              │
│ Transaction information (if payment already made)           │
│                                                             │
│ ℹ️ Note: This section is optional. Fill only if you have   │
│    already made the payment.                                │
│                                                             │
│ UTR/Transaction ID          (Optional)                      │
│ Payment Receipt             (Optional)                      │
│ Payment Date                (Optional)                      │
└─────────────────────────────────────────────────────────────┘
```

#### When Admin Sets "Required":
```
┌─────────────────────────────────────────────────────────────┐
│ 💳 Payment Details [Required] ⚠️                           │
│ Transaction information (required for registration)         │
│                                                             │
│ ⚠️ Required: You must provide payment details to complete   │
│    registration for this course.                            │
│                                                             │
│ UTR/Transaction ID *        (Required)                      │
│ Payment Receipt *           (Required)                      │
│ Payment Date *              (Required)                      │
└─────────────────────────────────────────────────────────────┘
```

## 🚀 Step-by-Step Usage

### For Administrators:

1. **Access Course Editor**
   ```
   Go to: admin/edit_course.php?id=[course_id]
   ```

2. **Find Payment Control Section**
   - Scroll down to "Payment Details Requirement"
   - Located after enrollment status settings

3. **Set Payment Requirement**
   - **Optional**: Students can skip payment fields
   - **Required**: Students must fill all payment fields

4. **See Real-Time Preview**
   - Preview updates automatically when you change setting
   - Shows exactly how students will see it

5. **Save Changes**
   - Click "Update Course" button
   - Setting is saved to database

### For Students:

1. **Access Registration**
   ```
   Go to: student/register.php?course=[course_id]
   ```

2. **Payment Section Behavior**
   - **If Optional**: All payment fields are optional, can be left blank
   - **If Required**: All payment fields have red asterisks (*) and are mandatory

3. **Form Validation**
   - **Optional**: Form submits even with empty payment fields
   - **Required**: Form won't submit unless payment fields are filled

## 🔍 Technical Implementation

### Database Column
```sql
ALTER TABLE courses 
ADD COLUMN payment_details_required ENUM('optional', 'required') DEFAULT 'optional';
```

### Admin Control Code
```php
// Check column exists
$column_check = $conn->query("SHOW COLUMNS FROM courses LIKE 'payment_details_required'");
$payment_column_exists = $column_check && $column_check->num_rows > 0;

// Display control if column exists
if ($payment_column_exists) {
    // Show payment control dropdown
}
```

### Student Registration Logic
```php
// Read course payment setting
$payment_required = ($course_data['payment_details_required'] ?? 'optional') === 'required';

// Set field requirements
$required_attr = $payment_required ? 'required' : '';
$badge_class = $payment_required ? 'bg-warning' : 'bg-secondary';
$badge_text = $payment_required ? 'Required' : 'Optional';
```

## 📋 Testing Checklist

### ✅ Admin Panel Tests
- [ ] Payment control dropdown appears in edit_course.php
- [ ] Preview updates when changing setting
- [ ] Setting saves correctly to database
- [ ] JavaScript toast notifications work

### ✅ Student Registration Tests
- [ ] Optional setting: Payment fields are optional
- [ ] Required setting: Payment fields are mandatory
- [ ] Badge shows correct status (Optional/Required)
- [ ] Alert message matches setting
- [ ] Form validation works correctly

### ✅ Integration Tests
- [ ] Change setting in admin → Test in student registration
- [ ] Multiple courses can have different settings
- [ ] Settings persist after page refresh

## 🎨 Visual Examples

### Admin Panel - Optional Setting
```
Payment Details Requirement: [Optional (Students can skip)     ▼]
Preview: [ℹ️ Payment Optional]
Help: Students can register without payment details
```

### Admin Panel - Required Setting
```
Payment Details Requirement: [Required (Must fill payment details) ▼]
Preview: [⚠️ Payment Required]
Help: Students must provide payment information to register
```

### Student View - Optional
```
💳 Payment Details [Optional]
ℹ️ This section is optional. Fill only if you have already made payment.

UTR/Transaction ID          [________________] (Optional)
Payment Receipt            [Choose File]      (Optional)  
Payment Date               [YYYY-MM-DD]      (Optional)
```

### Student View - Required
```
💳 Payment Details [Required] ⚠️
⚠️ You must provide payment details to complete registration.

UTR/Transaction ID *        [________________] (Required)
Payment Receipt *          [Choose File]      (Required)
Payment Date *             [YYYY-MM-DD]      (Required)
```

## 🔧 Troubleshooting

### Issue: Payment control not showing
**Solution:** Run migration to add payment_details_required column
```bash
php migrations/install_payment_details_control.php
```

### Issue: Setting not saving
**Solution:** Check database column exists and has correct ENUM values

### Issue: Student registration not reflecting changes
**Solution:** Verify course data includes payment_details_required column

## 🎯 Summary

The payment control system provides **complete flexibility** for administrators:

- **Per-course control**: Each course can have different payment requirements
- **Real-time preview**: See exactly how students will experience it
- **Seamless integration**: Works with existing registration system
- **User-friendly**: Clear visual indicators for both admin and students

**Result:** Administrators have full control over payment requirements, and students see appropriate behavior based on those settings.