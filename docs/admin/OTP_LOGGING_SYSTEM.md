# OTP Logging System - Implementation Complete

## Overview

The OTP Logging System allows you to view all OTP codes that are sent via email for debugging and testing purposes. This is especially useful during development to verify that OTPs are being generated and sent correctly.

## Features

✅ **Real-time OTP Logging**: All OTP codes are automatically logged to the database
✅ **Visual Dashboard**: Modern card-based interface to view OTP logs
✅ **Status Tracking**: Shows whether OTP was sent successfully or failed
✅ **Auto Cleanup**: Old logs (>24 hours) are automatically deleted
✅ **Security**: Only Master Admins can access OTP logs
✅ **Multiple Purposes**: Tracks OTPs for login, admin creation, etc.

## How to Access

1. Login as a **Master Admin**
2. Navigate to **OTP Logs** in the sidebar
3. View all OTP codes from the last 24 hours

## What Information is Logged

For each OTP, the system logs:
- **OTP Code**: The actual 6-digit code that was sent
- **Email Address**: Where the OTP was sent
- **Purpose**: Login, Admin Creation, etc.
- **Username**: Associated username (if available)
- **Status**: Sent or Failed
- **Timestamp**: When the OTP was generated
- **Time Ago**: Human-readable time difference

## Files Created/Modified

### New Files:
- `admin/view_otp_logs.php` - OTP logs viewer page
- `migrations/create_otp_logs_table.php` - Database table creation
- `includes/otp_logger.php` - OTP logging helper functions
- `admin/test_otp_logging.php` - Test script for OTP logging

### Modified Files:
- `admin/login.php` - Added OTP logging to login process
- `admin/add_admin.php` - Added OTP logging to admin creation
- `admin/includes/sidebar.php` - Added OTP Logs navigation link

## Database Schema

```sql
CREATE TABLE otp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    purpose VARCHAR(100) NOT NULL DEFAULT 'Login',
    username VARCHAR(100) NULL,
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);
```

## Usage Examples

### During Development:
1. Attempt to login as an admin
2. Check OTP Logs to see the actual OTP code
3. Use that code to complete the login process

### For Debugging Email Issues:
1. Try to send an OTP
2. Check OTP Logs to see if it shows "Failed" status
3. Investigate email configuration if needed

### For Testing:
1. Run `admin/test_otp_logging.php` to create sample logs
2. View the logs in `admin/view_otp_logs.php`

## Security Notes

- ⚠️ **Production Warning**: This feature shows actual OTP codes and should be used carefully in production
- 🔒 **Access Control**: Only Master Admins can view OTP logs
- 🕐 **Auto Cleanup**: Logs are automatically deleted after 24 hours
- 📧 **Email Privacy**: Email addresses are shown in logs for debugging

## Automatic Cleanup

The system includes automatic cleanup of old OTP logs:
- **MySQL Event**: Runs every hour to delete logs older than 24 hours
- **Manual Cleanup**: Can be triggered via `cleanupOTPLogs()` function
- **Storage Efficient**: Prevents database from growing too large

## Testing the System

1. **Test OTP Generation**:
   ```
   Visit: admin/test_otp_logging.php
   ```

2. **Test Real OTP Flow**:
   - Logout from admin panel
   - Try to login again
   - Check OTP Logs to see the actual code
   - Use the code to complete login

3. **Test Admin Creation**:
   - Go to Add Admin page
   - Enter details and send OTP
   - Check OTP Logs for the verification code

## Troubleshooting

### OTP Logs Not Showing:
1. Ensure you're logged in as Master Admin
2. Check if `otp_logs` table exists in database
3. Run the migration: `migrations/create_otp_logs_table.php`

### OTPs Not Being Logged:
1. Check if `includes/otp_logger.php` is included in login files
2. Verify database connection is working
3. Check for PHP errors in logs

### Email Sending Issues:
1. Check OTP Logs for "Failed" status entries
2. Verify SMTP configuration in `config/config.php`
3. Test email sending separately

## Benefits

✅ **Faster Development**: No need to check email during testing
✅ **Better Debugging**: See exactly what OTPs are being generated
✅ **Email Troubleshooting**: Identify email delivery issues quickly
✅ **Security Auditing**: Track OTP usage patterns
✅ **User Support**: Help users who didn't receive OTP emails

This system makes OTP debugging much easier and more efficient!