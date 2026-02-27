# 🎓 Batch Management Module
## NIELIT Bhubaneswar Student Management System

A complete, modular batch management system for educational institutions.

---

## ✨ Features

### 📚 Batch Management
- Create unlimited batches per course
- Auto-generated unique batch codes
- Set start/end dates, fees, and seat capacity
- Track enrollment status in real-time
- Edit or delete batches
- Filter by course or status

### ✅ Student Approval System
- Review pending student registrations
- Approve and assign students to batches
- Reject applications with tracking
- Auto-generate student IDs
- Batch assignment with seat validation
- Approval history tracking

### 📊 Statistics & Reporting
- Real-time enrollment tracking
- Fees collection monitoring
- Attendance percentage tracking
- Batch-wise student lists
- Visual statistics dashboard

### 🔐 Security & Data Integrity
- Session-based authentication
- SQL injection protection
- Transaction support
- Foreign key constraints
- XSS protection

---

## 🚀 Quick Start

### Installation (3 Simple Steps)

1. **Upload** the `batch_module` folder to your server
2. **Import** `database_batch_system.sql` to your database
3. **Access** via: `yoursite.com/batch_module/admin/manage_batches.php`

📖 **Detailed instructions:** See [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md)

---

## 📸 Screenshots

### Manage Batches
Create and manage all course batches in one place.

### Approve Students
Review and approve student registrations with batch assignment.

### Batch Details
View complete batch information with enrolled students and statistics.

---

## 🗂️ Module Structure

```
batch_module/
├── admin/                      # Admin pages
│   ├── manage_batches.php     # Main batch management
│   ├── approve_students.php   # Student approval interface
│   └── batch_details.php      # Batch details and students
├── includes/                   # Backend functions
│   └── batch_functions.php    # All batch operations
└── database_batch_system.sql  # Database schema
```

---

## 💡 How It Works

### Workflow:

1. **Admin creates a batch**
   - Selects course
   - Sets dates, fees, seats
   - System generates unique batch code

2. **Students register** (via existing registration system)
   - Status: "Pending"

3. **Admin approves students**
   - Reviews pending applications
   - Selects appropriate batch
   - Approves → Student gets assigned + Student ID generated

4. **Track progress**
   - View batch details
   - Monitor enrollment
   - Track fees and attendance

---

## 🎯 Key Functions

### Batch Operations
```php
createBatch($data, $conn)           // Create new batch
updateBatch($batch_id, $data, $conn) // Update batch
deleteBatch($batch_id, $conn)       // Delete batch
getBatchById($batch_id, $conn)      // Get batch details
getBatchesByCourse($course_id, $conn) // Get course batches
```

### Student Operations
```php
approveStudent($student_id, $batch_id, $admin, $conn) // Approve & assign
rejectStudent($student_id, $admin, $conn)             // Reject student
getPendingStudents($conn)                             // Get pending list
getBatchStudents($batch_id, $conn)                    // Get batch students
removeStudentFromBatch($student_id, $batch_id, $conn) // Remove from batch
```

### Statistics
```php
getBatchStats($batch_id, $conn)     // Get batch statistics
```

---

## 🗄️ Database Tables

### `batches`
Stores batch information with course linkage.

**Key Fields:**
- `batch_code` - Unique identifier (auto-generated)
- `course_id` - Links to courses table
- `seats_total` / `seats_filled` - Capacity tracking
- `status` - Active/Completed/Cancelled

### `batch_students`
Junction table for batch-student relationships.

**Key Fields:**
- `batch_id` + `student_id` - Unique combination
- `fees_paid` / `fees_status` - Payment tracking
- `attendance_percentage` - Attendance tracking

### `batch_attendance`
Daily attendance records.

**Key Fields:**
- `attendance_date` - Date of attendance
- `status` - Present/Absent/Late/Leave
- `marked_by` - Admin who marked attendance

### `students` (Modified)
Added batch-related fields.

**New Fields:**
- `batch_id` - Current batch assignment
- `status` - Pending/Approved/Rejected
- `approved_by` - Admin who approved
- `student_id` - Auto-generated ID

---

## 🎨 UI/UX Features

- **Responsive Design** - Works on all devices
- **Modern Interface** - Clean, professional look
- **Color-Coded Status** - Easy visual identification
- **Interactive Cards** - Smooth hover effects
- **Real-time Stats** - Live enrollment tracking
- **Intuitive Navigation** - Easy to use

---

## 🔧 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Existing NIELIT Bhubaneswar system
- Admin authentication system

---

## 📦 Integration

This module integrates seamlessly with:
- Existing student registration system
- Course management system
- Admin authentication
- Email notification system (optional)

---

## 🛠️ Customization

### Easy to Customize:

1. **Batch Code Format**
   - Edit `generateBatchCode()` in `batch_functions.php`

2. **Student ID Format**
   - Modify in `approveStudent()` function

3. **UI Colors**
   - Uses existing `admin-theme.css`
   - Add custom styles as needed

4. **Additional Fields**
   - Extend database tables
   - Update forms and functions

---

## 📈 Benefits

✅ **Organized** - Manage batches systematically
✅ **Efficient** - Quick student approval process
✅ **Trackable** - Monitor enrollment and progress
✅ **Scalable** - Handle unlimited batches
✅ **Modular** - Easy to install and maintain
✅ **Secure** - Built with security best practices

---

## 🧪 Testing

Included test scenarios:
- Create batch with validation
- Approve student with seat check
- Remove student with rollback
- Delete batch with constraint check
- View statistics with calculations

---

## 📝 License

This module is part of the NIELIT Bhubaneswar Student Management System.

---

## 🤝 Contributing

To extend this module:
1. Follow existing code structure
2. Use prepared statements for queries
3. Add error handling
4. Update documentation

---

## 📞 Support

For installation help, see [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md)

---

## 🎉 Ready to Use!

This module is production-ready and can be deployed immediately.

**Start managing your batches efficiently today!**

---

**Version:** 1.0  
**Last Updated:** February 2026  
**Developed for:** NIELIT Bhubaneswar
