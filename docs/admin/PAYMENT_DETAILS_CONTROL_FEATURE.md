# Payment Details Control Feature

## Overview
The Payment Details Control feature allows administrators to control whether payment details are **optional** or **required** for each course during student registration.

## How It Works

### Administrator Control (Edit Course)
1. **Navigate to:** Admin Dashboard → Edit Course
2. **Find:** "Payment Details Requirement" section (after Enrollment Status)
3. **Options:**
   - **Optional (Students can skip)** - Default behavior, payment details are optional
   - **Required (Must fill payment details)** - Students must provide payment information

### Visual Feedback
- **Real-time preview** shows how the payment section will appear to students
- **Color-coded badges** indicate the requirement level:
  - 🔵 **Blue Badge**: Optional
  - 🟡 **Yellow Badge**: Required
- **Toast notifications** confirm setting changes

### Student Registration Impact

#### When Payment is Optional (Default)
- Payment section shows "Optional" badge
- Blue info alert: "This section is optional..."
- Form fields are not required
- Students can register without payment details

#### When Payment is Required
- Payment section shows "Required" badge  
- Yellow warning alert: "You must provide payment details..."
- Form fields become required with red asterisks (*)
- Students cannot submit without payment information

## Database Schema
```sql
ALTER TABLE courses 
ADD COLUMN payment_details_required ENUM('optional', 'required') DEFAULT 'optional';
```

## Implementation Details

### Files Modified
1. **`admin/edit_course.php`**
   - Added payment control form section
   - Added JavaScript for real-time preview
   - Updated database update query

2. **`student/register.php`**
   - Added conditional payment requirement logic
   - Dynamic form validation based on course setting
   - Updated UI elements and messaging

3. **Database Migration**
   - `migrations/add_payment_details_control.sql`
   - `migrations/install_payment_details_control.php`

### Key Features
- **Backward Compatible**: Existing courses default to "optional"
- **Real-time Preview**: Administrators see immediate visual feedback
- **Conditional Validation**: Form validation adapts to course settings
- **Professional UI**: Consistent with existing admin theme
- **Toast Notifications**: User-friendly feedback system

## Usage Examples

### Example 1: Free Course (Optional Payment)
- Course: "Basic Computer Skills"
- Setting: **Optional**
- Result: Students can register without payment details

### Example 2: Paid Course (Required Payment)
- Course: "Advanced Web Development Bootcamp"  
- Setting: **Required**
- Result: Students must provide UTR, receipt, and payment date

## Testing the Feature

### Test as Administrator
1. Edit any course
2. Change payment requirement setting
3. Observe real-time preview changes
4. Save and verify settings persist

### Test as Student
1. Access registration link for optional payment course
2. Verify payment section is optional
3. Access registration link for required payment course  
4. Verify payment fields are required
5. Test form submission with/without payment details

## Benefits
- **Flexibility**: Different payment policies per course
- **User Experience**: Clear indication of requirements
- **Data Quality**: Ensures payment information when needed
- **Administrative Control**: Easy management per course
- **Professional Appearance**: Consistent with system design

## Migration Instructions
Run the migration to add the new column:
```bash
php migrations/install_payment_details_control.php
```

The feature is now ready to use! All existing courses will default to "optional" payment details.