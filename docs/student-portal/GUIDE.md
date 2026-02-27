# 🎓 Student Portal - Complete Guide

## Overview
A modern, feature-rich student portal for NIELIT Bhubaneswar with dashboard, attendance tracking, fee management, certificates, and support system.

---

## 🚀 Features

### 1. **Modern Dashboard**
- Welcome card with student info
- Quick stats cards (Course, Progress, Attendance, Status)
- Quick action buttons
- Recent announcements
- Profile summary
- Course details
- Important links

### 2. **Profile Management**
- View complete profile information
- Personal details
- Contact information
- Course information
- Educational qualifications
- Document viewing (Photo, Signature, Receipt)
- Download application form

### 3. **Attendance Tracking**
- Overall attendance percentage
- Visual attendance circle
- Detailed attendance history
- Status indicators (Present, Absent, Late)
- Subject-wise attendance
- Attendance alerts

### 4. **Fee Management**
- Total course fee display
- Amount paid tracking
- Balance due calculation
- Payment history
- Payment status visualization
- Receipt viewing and download
- Payment reminders

### 5. **Certificates**
- View all certificates
- Download certificates
- Certificate verification
- Certificate information
- Issue date tracking

### 6. **Support Center**
- Submit support tickets
- Track ticket status
- FAQ section
- Contact information
- Priority-based tickets
- Category-wise organization

### 7. **Security**
- Change password functionality
- Password strength indicator
- Secure authentication
- Session management

---

## 📁 File Structure

```
student/
├── dashboard.php          # Main dashboard
├── profile.php           # Student profile
├── attendance.php        # Attendance records
├── fees.php             # Fee details
├── certificates.php     # Certificates
├── support.php          # Support center
├── change_password.php  # Password change
├── login.php            # Student login
├── logout.php           # Logout
├── portal.php           # Old portal (keep for reference)
└── includes/
    ├── header.php       # Common header
    └── footer.php       # Common footer

assets/
├── css/
│   └── student-portal.css    # Portal styles
└── js/
    └── student-portal.js     # Portal scripts
```

---

## 🗄️ Database Setup

### Step 1: Run SQL File
```bash
# Import the SQL file in phpMyAdmin or MySQL
mysql -u root -p nielit_bhubaneswar < database_student_portal_tables.sql
```

### Step 2: Tables Created
- `announcements` - System announcements
- `attendance` - Student attendance records
- `student_progress` - Course progress tracking
- `payments` - Payment history
- `certificates` - Student certificates
- `support_tickets` - Support tickets

---

## 🎨 Design Features

### Color Scheme
- Primary: `#356c9f` (NIELIT Blue)
- Secondary: `#2c5a7f` (Dark Blue)
- Success: `#28a745` (Green)
- Warning: `#ffc107` (Yellow)
- Danger: `#dc3545` (Red)
- Info: `#17a2b8` (Cyan)

### UI Components
- Gradient stat cards
- Modern card designs
- Responsive layout
- Font Awesome icons
- Bootstrap 4 framework
- Smooth animations
- Hover effects

---

## 🔐 Login System

### Student Login
- **URL**: `student/login.php`
- **Credentials**: Student ID + Password
- **Features**:
  - Password visibility toggle
  - Remember session
  - Secure authentication
  - Redirect to dashboard

### Default Test Login
```
Student ID: [Your Student ID]
Password: [Set during registration]
```

---

## 📊 Dashboard Widgets

### Stats Cards
1. **Course Info** - Current course name
2. **Progress** - Course completion percentage
3. **Attendance** - Overall attendance %
4. **Status** - Active/Inactive status

### Quick Actions
- View Profile
- Check Attendance
- Download Form
- View Certificates
- Fee Details
- Contact Support

---

## 📱 Responsive Design

### Mobile Optimized
- Collapsible navigation
- Touch-friendly buttons
- Responsive tables
- Mobile-first approach
- Optimized images

### Breakpoints
- Desktop: 1200px+
- Tablet: 768px - 1199px
- Mobile: < 768px

---

## 🔧 Configuration

### Update Database Connection
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'nielit_bhubaneswar');
```

### Update File Paths
Ensure correct paths in:
- Header logo: `../assets/images/bhubaneswar_logo.png`
- Emblem: `../assets/images/National-Emblem.png`
- CSS: `../assets/css/student-portal.css`
- JS: `../assets/js/student-portal.js`

---

## 🎯 Usage Guide

### For Students

1. **Login**
   - Go to `student/login.php`
   - Enter Student ID and Password
   - Click Login

2. **View Dashboard**
   - See your stats at a glance
   - Check recent announcements
   - Use quick actions

3. **Check Attendance**
   - Click "Attendance" in menu
   - View overall percentage
   - See detailed history

4. **View Fees**
   - Click "Fees" in menu
   - Check payment status
   - Download receipts

5. **Get Support**
   - Click "Support" in menu
   - Submit a ticket
   - Track ticket status

### For Admins

1. **Add Announcements**
   - Insert into `announcements` table
   - Set target audience
   - Students see on dashboard

2. **Mark Attendance**
   - Insert into `attendance` table
   - Set status (present/absent/late)
   - Students see in portal

3. **Record Payments**
   - Insert into `payments` table
   - Add transaction details
   - Updates fee balance

4. **Issue Certificates**
   - Insert into `certificates` table
   - Add certificate details
   - Students can download

---

## 🚨 Troubleshooting

### Common Issues

**1. Login Not Working**
- Check database connection
- Verify student_id exists
- Check password hash

**2. Images Not Loading**
- Verify file paths
- Check folder permissions
- Ensure images exist

**3. Attendance Not Showing**
- Check attendance table
- Verify student_id match
- Run SQL to add test data

**4. CSS Not Applied**
- Clear browser cache
- Check CSS file path
- Verify file exists

---

## 📈 Future Enhancements

### Planned Features
- [ ] Online exam system
- [ ] Study materials download
- [ ] Class timetable
- [ ] Assignment submission
- [ ] Grade/marks viewing
- [ ] Discussion forum
- [ ] Live chat support
- [ ] Mobile app
- [ ] Email notifications
- [ ] SMS alerts

---

## 🔒 Security Features

- Password hashing (bcrypt)
- Session management
- SQL injection prevention
- XSS protection
- CSRF tokens (recommended)
- Secure file uploads
- Input validation
- Access control

---

## 📞 Support

### Contact Information
- **Phone**: 0674-2960354
- **Email**: dir-bbsr@nielit.gov.in
- **Hours**: Mon-Fri, 9:00 AM - 5:30 PM

### Technical Support
- Submit ticket via Support Center
- Email technical issues
- Check FAQ section

---

## 📝 Testing Checklist

### Before Going Live

- [ ] Test login with multiple accounts
- [ ] Verify all navigation links
- [ ] Check responsive design
- [ ] Test on different browsers
- [ ] Verify database queries
- [ ] Test file downloads
- [ ] Check security measures
- [ ] Test error handling
- [ ] Verify email functionality
- [ ] Test logout functionality

---

## 🎉 Quick Start

1. **Import SQL**
   ```bash
   mysql -u root -p nielit_bhubaneswar < database_student_portal_tables.sql
   ```

2. **Update Config**
   - Edit `config/database.php`
   - Set correct credentials

3. **Test Login**
   - Go to `student/login.php`
   - Use existing student credentials

4. **Explore Portal**
   - Navigate through all sections
   - Test all features

---

## 📄 License

© 2025 NIELIT Bhubaneswar. All rights reserved.

---

## 👨‍💻 Developer Notes

### Code Standards
- PHP 7.4+
- MySQL 5.7+
- Bootstrap 4.5
- Font Awesome 6.4
- jQuery 3.5

### Best Practices
- Use prepared statements
- Sanitize all inputs
- Validate on server-side
- Handle errors gracefully
- Log important events
- Comment complex code

---

**Built with ❤️ for NIELIT Bhubaneswar Students**
