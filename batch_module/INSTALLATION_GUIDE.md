# Batch Management Module - Installation Guide
## NIELIT Bhubaneswar Student Management System

---

## 📦 Module Overview

This modular batch management system allows you to:
- ✅ Create and manage course batches
- ✅ Approve students and assign them to batches
- ✅ Track student enrollment, fees, and attendance
- ✅ View batch statistics and details
- ✅ Easy deployment on any server

---

## 🚀 Quick Installation (3 Steps)

### Step 1: Upload Files

Upload the entire `batch_module` folder to your server root directory:

```
your-website/
├── batch_module/          ← Upload this folder
│   ├── admin/
│   ├── includes/
│   └── database_batch_system.sql
├── admin/
├── config/
└── ...
```

### Step 2: Run Database Setup

1. Open phpMyAdmin or your database management tool
2. Select your database (e.g., `nielit_bhubaneswar`)
3. Go to the "SQL" tab
4. Open the file: `batch_module/database_batch_system.sql`
5. Copy all the SQL code and paste it
6. Click "Go" to execute

**What this does:**
- Creates `batches` table
- Creates `batch_students` table (junction table)
- Creates `batch_attendance` table
- Adds necessary columns to `students` table (batch_id, status, approved_by, etc.)

### Step 3: Update Navigation Links

Add batch management links to your admin dashboard navigation.

**Option A: Update existing admin pages**

Add these navigation items to your admin sidebar in:
- `admin/dashboard.php`
- `admin/students.php`
- `admin/manage_courses.php`
- etc.

```php
<div class="nav-item">
    <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
        <i class="fas fa-layer-group"></i> Batches
    </a>
</div>
<div class="nav-item">
    <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
        <i class="fas fa-user-check"></i> Approve Students
    </a>
</div>
```

**Option B: Direct Access**

Access the module directly via URLs:
- Manage Batches: `http://yoursite.com/batch_module/admin/manage_batches.php`
- Approve Students: `http://yoursite.com/batch_module/admin/approve_students.php`

---

## 📋 Features

### 1. Batch Management (`manage_batches.php`)
- Create new batches with auto-generated batch codes
- Edit existing batches
- Delete batches (only if no students enrolled)
- View all batches with enrollment status
- Filter by course

### 2. Student Approval (`approve_students.php`)
- View all pending student registrations
- Approve students and assign to batches
- Reject student applications
- Auto-generate student IDs upon approval
- Track approval history

### 3. Batch Details (`batch_details.php`)
- View complete batch information
- See enrolled students list
- Track fees collection
- Monitor attendance statistics
- Remove students from batch

---

## 🗄️ Database Schema

### Tables Created:

1. **batches**
   - Stores batch information
   - Links to courses
   - Tracks seats and enrollment

2. **batch_students**
   - Junction table for batch-student relationship
   - Tracks fees and attendance per student

3. **batch_attendance**
   - Daily attendance records
   - Supports Present/Absent/Late/Leave status

4. **students** (modified)
   - Added: `batch_id`, `status`, `approved_by`, `approved_at`, `student_id`

---

## 🔧 Configuration

The module uses your existing configuration from `config/config.php`. No additional configuration needed!

**Required:**
- Database connection (`$conn`)
- `APP_URL` constant
- Admin session management

---

## 📱 Usage Workflow

### For Administrators:

1. **Create Batches**
   - Go to "Manage Batches"
   - Fill in batch details (name, dates, fees, seats)
   - System auto-generates unique batch code
   - Click "Create Batch"

2. **Approve Students**
   - Go to "Approve Students"
   - Review pending registrations
   - Select appropriate batch from dropdown
   - Click "Approve" to assign student to batch
   - Student receives auto-generated Student ID

3. **Manage Batch**
   - View batch details and statistics
   - See enrolled students
   - Track fees and attendance
   - Remove students if needed

---

## 🎨 UI Features

- Modern, responsive design
- Matches existing admin theme
- Color-coded status badges
- Interactive statistics cards
- Mobile-friendly tables
- Smooth animations

---

## 🔒 Security Features

- Session-based authentication
- SQL injection protection (prepared statements)
- XSS protection (htmlspecialchars)
- Transaction support for data integrity
- Foreign key constraints

---

## 📊 Batch Code Generation

Batch codes are auto-generated using this format:

```
[COURSE_CODE][YEAR]_[NUMBER]

Examples:
- DBC26_01 (First DBC batch in 2026)
- CCC26_02 (Second CCC batch in 2026)
- FCIOT26_01 (First FCIOT batch in 2026)
```

---

## 🧪 Testing Checklist

After installation, test these features:

- [ ] Create a new batch
- [ ] View batch in the list
- [ ] Approve a pending student
- [ ] Assign student to batch
- [ ] View batch details
- [ ] Check student enrollment
- [ ] Remove student from batch
- [ ] Delete empty batch
- [ ] Try to delete batch with students (should fail)

---

## 🆘 Troubleshooting

### Issue: "Table 'batches' doesn't exist"
**Solution:** Run the SQL file again from Step 2

### Issue: "Cannot add foreign key constraint"
**Solution:** Make sure your `courses` and `students` tables exist first

### Issue: "Page not found"
**Solution:** Check that you uploaded the `batch_module` folder to the correct location

### Issue: "No batches showing in dropdown"
**Solution:** Create at least one batch first in "Manage Batches"

### Issue: "Student approval not working"
**Solution:** Ensure the batch has available seats (seats_filled < seats_total)

---

## 📁 File Structure

```
batch_module/
├── admin/
│   ├── manage_batches.php      # Create/edit/delete batches
│   ├── approve_students.php    # Approve and assign students
│   ├── batch_details.php       # View batch info and students
│   └── edit_batch.php          # Edit batch (optional)
├── includes/
│   └── batch_functions.php     # All batch-related functions
├── database_batch_system.sql   # Database schema
├── INSTALLATION_GUIDE.md       # This file
└── README.md                   # Module documentation
```

---

## 🔄 Future Enhancements (Optional)

You can extend this module with:
- Attendance marking system
- Fees payment tracking
- Batch reports and exports
- Email notifications
- Certificate generation
- Student portal integration

---

## 📞 Support

For issues or questions:
- Check the troubleshooting section above
- Review the code comments in `batch_functions.php`
- Test with sample data first

---

## ✅ Installation Complete!

Once you've completed all 3 steps, you're ready to:
1. Create your first batch
2. Approve pending students
3. Assign students to batches
4. Track enrollment and progress

**Happy Managing! 🎉**

---

## 📝 Version History

- **v1.0** (Feb 2026) - Initial release
  - Batch management
  - Student approval
  - Enrollment tracking
  - Statistics dashboard
