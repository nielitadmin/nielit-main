# 🎓 NIELIT Bhubaneswar Student Management System

A comprehensive web-based student management system for NIELIT (National Institute of Electronics & Information Technology) Bhubaneswar, designed to handle student registrations, course management, batch operations, and administrative tasks.

## 🌟 Features

### 📚 **Student Management**
- **Online Registration** - Multi-step registration with document upload
- **Student Portal** - Dashboard with profile, certificates, fees, attendance
- **Document Management** - Categorized document upload and viewing
- **PDF Form Generation** - Professional application forms with text wrapping
- **Excel Export** - Comprehensive student data export with filtering

### 👨‍💼 **Admin Panel**
- **Role-Based Access Control (RBAC)** - Master Admin, Course Coordinator, Data Entry, Report Viewer
- **Student Approval System** - Review and approve registrations
- **Course Management** - Create, edit, and manage courses with QR codes
- **Batch Management** - Complete batch lifecycle management
- **Training Centre Management** - Multi-center operations (Bhubaneswar, Balasore, Raipur)

### 🎯 **Advanced Features**
- **Educational Qualifications Enhancement** - 63 comprehensive stream options with "Other" functionality
- **Schemes/Projects Module** - Government scheme management (SCSP, TSP, PMKVY)
- **Theme Customization** - Dynamic color schemes and branding
- **QR Code System** - Course registration links with QR codes
- **OTP Verification** - Dual OTP system for secure authentication
- **Audit Logging** - Complete activity tracking

### 🏢 **Multi-Center Support**
- **NIELIT Bhubaneswar** - Main center
- **NIELIT Balasore** - Extension center
- **NIELIT Raipur** - Regional center

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependencies)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/nielitadmin/nielit-main.git
   cd nielit-main
   ```

2. **Configure database**
   ```bash
   # Import the main database
   mysql -u root -p nielit_bhubaneswar < nielit_bhubaneswar.sql
   
   # Update database configuration
   cp config/database.php.example config/database.php
   # Edit config/database.php with your database credentials
   ```

3. **Install dependencies**
   ```bash
   # Install PHP dependencies (if using Composer)
   composer install
   
   # Set proper permissions
   chmod -R 755 uploads/
   chmod -R 755 course_pdf/
   chmod -R 755 assets/qr_codes/
   ```

4. **Run migrations**
   ```bash
   # Install RBAC system
   cd migrations
   php install_rbac.php install
   
   # Install system enhancements
   php install_system_enhancement.php install
   php migrate_system_enhancement_data.php migrate
   ```

5. **Access the system**
   - **Homepage**: `http://localhost/nielit-main/`
   - **Admin Panel**: `http://localhost/nielit-main/admin/login.php`
   - **Student Portal**: `http://localhost/nielit-main/student/login.php`

## 📁 Project Structure

```
nielit-main/
├── admin/                    # Admin panel
├── student/                  # Student portal
├── public/                   # Public pages
├── batch_module/            # Batch management
├── schemes_module/          # Schemes/projects management
├── config/                  # Configuration files
├── includes/                # Shared components
├── assets/                  # CSS, JS, images
├── uploads/                 # Student documents
├── migrations/              # Database migrations
├── docs/                    # Documentation
├── tests/                   # Test files
└── libraries/               # Third-party libraries
```

## 🔧 Configuration

### Database Configuration
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'nielit_bhubaneswar');
```

### Application Configuration
Edit `config/app.php`:
```php
define('APP_URL', 'http://localhost/nielit-main');
define('APP_NAME', 'NIELIT Bhubaneswar');
define('TIMEZONE', 'Asia/Kolkata');
```

### Email Configuration
Edit `config/email.php` for SMTP settings:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

## 🎯 Key Modules

### 1. **Student Registration System**
- **Location**: `student/register.php`
- **Features**: Multi-step form, document upload, OTP verification
- **Documentation**: `docs/registration/`

### 2. **Admin Panel**
- **Location**: `admin/`
- **Features**: RBAC, student management, course management
- **Documentation**: `docs/admin/`

### 3. **Batch Management**
- **Location**: `batch_module/`
- **Features**: Batch creation, student approval, enrollment tracking
- **Documentation**: `batch_module/README.md`

### 4. **Schemes Management**
- **Location**: `schemes_module/`
- **Features**: Government scheme tracking, course linking
- **Documentation**: `schemes_module/README.md`

## 🔐 User Roles & Permissions

### **Master Admin** (Level 4)
- Full system access
- User management
- System configuration
- All CRUD operations

### **Course Coordinator** (Level 3)
- Course and batch management
- Student approval
- Report generation
- Limited admin functions

### **Data Entry Operator** (Level 2)
- Student record management
- Document verification
- Basic reporting

### **Report Viewer** (Level 1)
- Read-only access
- Report viewing
- Data export

## 📊 Database Schema

### Core Tables
- `students` - Student information and documents
- `courses` - Course catalog and details
- `batches` - Batch management
- `admin` - Admin users with RBAC
- `education_details` - Student academic qualifications

### Supporting Tables
- `centres` - Training center information
- `schemes` - Government schemes/projects
- `themes` - UI customization
- `audit_log` - Activity tracking

## 🛠️ Development

### Adding New Features
1. Create feature branch: `git checkout -b feature/new-feature`
2. Follow existing code structure
3. Add documentation in `docs/`
4. Create migration if needed in `migrations/`
5. Test thoroughly
6. Submit pull request

### Code Standards
- Use prepared statements for database queries
- Follow PSR-4 autoloading standards
- Add proper error handling
- Include comprehensive comments
- Maintain backward compatibility

### Testing
```bash
# Run database tests
php tests/test_db_connection.php

# Run form validation tests
php tests/test_form_submission.php

# Run document upload tests
php tests/test_document_validation.php
```

## 📚 Documentation

### User Guides
- **Admin Guide**: `docs/admin/`
- **Student Guide**: `docs/student-portal/`
- **Installation Guide**: `docs/deployment/`

### Technical Documentation
- **API Documentation**: `docs/api/`
- **Database Schema**: `docs/database/`
- **Migration Guide**: `migrations/README.md`

### Feature Documentation
- **RBAC System**: `docs/rbac/`
- **Registration System**: `docs/registration/`
- **PDF Generation**: `docs/pdf/`
- **QR Code System**: `docs/qr-system/`

## 🔄 Recent Updates

### Latest Features (March 2026)
- ✅ **Educational Qualifications Enhancement** - 63 comprehensive stream options
- ✅ **Training Center Multi-Location Support** - Bhubaneswar|Balasore|Raipur
- ✅ **PDF Text Wrapping Fix** - Professional form generation
- ✅ **Excel Export Enhancement** - Complete student data export
- ✅ **"Other" Option Functionality** - Custom input for non-standard qualifications

### Recent Improvements
- Enhanced dropdown systems with organized optgroups
- Dynamic field conversion for custom entries
- Professional CSS styling for custom inputs
- Comprehensive JavaScript functionality
- Complete feature parity between registration and edit forms

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests and documentation
5. Submit a pull request

### Contribution Guidelines
- Follow existing code style
- Add comprehensive documentation
- Include migration scripts for database changes
- Test on multiple PHP versions
- Ensure backward compatibility

## 📞 Support

### Getting Help
- **Documentation**: Check `docs/` directory
- **Issues**: Create GitHub issue
- **Email**: Contact system administrator

### Common Issues
- **Database Connection**: Check `config/database.php`
- **File Permissions**: Ensure uploads/ is writable
- **SMTP Issues**: Verify email configuration
- **Migration Errors**: Run migrations in correct order

## 📄 License

This project is proprietary software developed for NIELIT Bhubaneswar.

## 🏆 Acknowledgments

- **NIELIT Bhubaneswar** - Primary development and testing
- **NIELIT Balasore** - Extension center support
- **NIELIT Raipur** - Regional center integration
- **Development Team** - Continuous improvement and maintenance

---

## 🎯 System Status

- **Version**: 2.0
- **Status**: Production Ready
- **Last Updated**: March 2026
- **PHP Version**: 7.4+
- **Database**: MySQL 5.7+
- **Framework**: Custom PHP/MySQL

---

**🚀 Ready for production deployment with comprehensive features and professional-grade code quality!**