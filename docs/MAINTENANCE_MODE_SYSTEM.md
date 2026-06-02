# 🛠️ Maintenance Mode System - Complete Guide

## Overview

A comprehensive maintenance mode system with countdown timer for NIELIT Bhubaneswar website. This feature allows administrators to temporarily display a maintenance page to public users while keeping admin access available.

---

## ✨ Features

### 1. **Full Control Panel**
- Enable/Disable maintenance mode with a single switch
- Customize maintenance title and message
- Set estimated end time with countdown timer
- Toggle countdown display
- Toggle contact information display

### 2. **Beautiful Maintenance Page**
- Modern, animated design with NIELIT branding
- Real-time countdown timer (Days, Hours, Minutes, Seconds)
- Floating particle animations
- Responsive design for all devices
- Displays contact information for urgent help

### 3. **Smart Redirects**
- Public users see maintenance page automatically
- Admins can bypass and access admin panel normally
- Auto-refresh when countdown ends

### 4. **Quick Actions**
- Set maintenance for 1 hour (quick fix)
- Set maintenance for 4 hours (updates)
- Set maintenance for 12 hours (overnight)

---

## 📁 Files Created

```
/admin/manage_maintenance.php       # Admin control panel
/maintenance.php                    # Public maintenance page
/includes/maintenance_check.php     # Auto-redirect script
/migrations/install_maintenance_mode.php  # Database setup
/docs/MAINTENANCE_MODE_SYSTEM.md    # This documentation
```

---

## 🚀 Installation

### Step 1: Run Database Migration

```bash
php migrations/install_maintenance_mode.php
```

Or visit in browser:
```
http://localhost/public_html/migrations/install_maintenance_mode.php
```

This creates the `maintenance_mode` table with default settings.

### Step 2: Add to Admin Navigation

Add this menu item to `admin/includes/sidebar.php`:

```php
<li class="nav-item">
    <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage_maintenance.php">
        <i class="fas fa-tools"></i> Maintenance Mode
    </a>
</li>
```

### Step 3: Add Maintenance Check to Public Pages

Add this line at the top of public pages (after config.php):

```php
require_once __DIR__ . '/includes/maintenance_check.php';
```

**Recommended pages to add check:**
- `index.php`
- `public/courses.php`
- `public/contact.php`
- `public/news.php`
- `public/management.php`
- `student/register.php`
- `student/login.php`

**Example:**
```php
<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/maintenance_check.php';  // Add this line
// Rest of your code...
?>
```

---

## 💻 Usage Guide

### Accessing the Control Panel

1. Log in to Admin Panel
2. Navigate to **Maintenance Mode** menu
3. You'll see the control panel

### Enabling Maintenance Mode

#### Method 1: Quick Actions (Recommended for scheduled maintenance)

1. Click one of the quick action buttons:
   - **1 Hour** - For quick fixes
   - **4 Hours** - For updates
   - **12 Hours** - For overnight maintenance

2. The system automatically:
   - Enables maintenance mode
   - Sets countdown timer
   - Calculates end time

3. Click **Save Settings**

#### Method 2: Custom Configuration

1. Toggle **"Enable Maintenance Mode"** switch to ON
2. Customize the title (default: "Site Under Maintenance")
3. Edit the maintenance message
4. Set estimated end time (optional)
5. Choose display options:
   - ☑️ Show Countdown Timer
   - ☑️ Show Contact Information
6. Click **Save Settings**

### Disabling Maintenance Mode

Simply toggle the **"Enable Maintenance Mode"** switch to OFF and save.

---

## 🎨 Customization

### Changing Colors

Edit `maintenance.php` CSS variables:

```css
:root {
    --navy: #0a1628;      /* Dark blue background */
    --blue: #1a56db;      /* Accent blue */
    --gold: #f59e0b;      /* Highlight gold */
    --cream: #fafaf8;     /* Light background */
}
```

### Changing Logo

Replace the logo path in `maintenance.php`:

```php
<img src="<?php echo APP_URL; ?>/assets/images/YOUR_LOGO.png" alt="Logo">
```

### Adding Custom Content

Add HTML content in `maintenance.php` inside the `.maintenance-container` div.

---

## 🔒 Security Features

### 1. Admin Bypass
- Admins logged in can access the site normally
- No interruption to administrative tasks

### 2. Smart Detection
- Checks are only on public pages
- Admin panel never shows maintenance page
- Maintenance page itself never redirects

### 3. Session Management
- Uses existing admin session
- No additional authentication required

---

## 📊 Database Schema

### Table: `maintenance_mode`

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| id | INT | 1 | Primary key (always 1) |
| is_enabled | TINYINT | 0 | 0=Off, 1=On |
| maintenance_title | VARCHAR(255) | "Site Under Maintenance" | Page title |
| maintenance_message | TEXT | Default message | Custom message |
| end_time | DATETIME | NULL | Countdown target |
| show_countdown | TINYINT | 1 | Display countdown |
| show_contact | TINYINT | 1 | Display contact info |
| created_at | TIMESTAMP | NOW() | Creation time |
| updated_at | TIMESTAMP | NOW() | Last update time |

---

## 🎯 Use Cases

### Scenario 1: Scheduled Server Maintenance
```
1. Set maintenance for 4 hours
2. Display: "We're upgrading our servers for better performance"
3. Countdown shows exact completion time
4. Users see estimated return time
```

### Scenario 2: Emergency Fix
```
1. Quickly enable maintenance (no countdown)
2. Display: "We're fixing a critical issue. Please check back shortly"
3. No specific end time shown
4. Disable when fixed
```

### Scenario 3: Overnight Database Migration
```
1. Set maintenance for 12 hours
2. Display: "Overnight database maintenance in progress"
3. Scheduled for low-traffic hours
4. Auto-completes in morning
```

### Scenario 4: New Feature Deployment
```
1. Set maintenance for 1 hour
2. Display: "We're adding exciting new features! Back soon"
3. Quick deployment window
4. Users informed of improvements
```

---

## 🔧 Troubleshooting

### Issue: Maintenance page not showing

**Solution:**
1. Check if `maintenance_check.php` is included in the page
2. Verify maintenance mode is enabled in admin panel
3. Clear browser cache
4. Check database connection

### Issue: Countdown not working

**Solution:**
1. Ensure end_time is set in the future
2. Check if show_countdown is enabled
3. Verify JavaScript is not blocked
4. Check browser console for errors

### Issue: Admin still sees maintenance page

**Solution:**
1. Ensure you're logged into admin panel
2. Check session is active
3. Clear cookies and re-login

### Issue: Table doesn't exist error

**Solution:**
Run the migration script:
```bash
php migrations/install_maintenance_mode.php
```

---

## 🌟 Best Practices

### 1. **Plan Ahead**
- Schedule maintenance during low-traffic hours
- Announce maintenance in advance via email/social media

### 2. **Be Specific**
- Provide clear reasons for maintenance
- Give estimated completion time
- Update if timeline changes

### 3. **Test First**
- Preview maintenance page before enabling
- Ensure admin bypass works
- Test on mobile devices

### 4. **Monitor Progress**
- Keep admin panel open during maintenance
- Be ready to disable if issues occur
- Have rollback plan ready

### 5. **Communicate**
- Show contact information for urgent matters
- Provide alternative channels
- Thank users for patience

---

## 📱 Responsive Design

The maintenance page is fully responsive:

- **Desktop**: Full countdown with large numbers
- **Tablet**: Optimized spacing and layout
- **Mobile**: Stacked countdown boxes, readable text

---

## 🎭 Visual Features

### Animations
- ✨ Floating particles in background
- 🔄 Bouncing wrench icon
- 📊 Animated progress bar
- ⏱️ Real-time countdown updates

### Design Elements
- 🎨 Gradient backgrounds
- 💫 Blur effects (glass morphism)
- 🌈 Smooth transitions
- 📐 Professional spacing

---

## 🚦 Status Indicators

### In Admin Panel

**🟢 Site is LIVE** (Green)
- Maintenance mode disabled
- All users can access normally

**🟡 Maintenance Mode is ACTIVE** (Yellow)
- Public sees maintenance page
- Admins can still access

---

## 📞 Support

For issues or questions:
- **Email**: dir-bbsr@nielit.gov.in
- **Phone**: 0674-2960354

---

## 🎉 Summary

The Maintenance Mode System provides:

✅ Professional maintenance page  
✅ Real-time countdown timer  
✅ Admin control panel  
✅ Automatic redirects  
✅ Mobile responsive  
✅ Beautiful animations  
✅ Quick action buttons  
✅ Contact information display  
✅ Admin bypass feature  
✅ Easy customization  

Perfect for scheduled maintenance, emergency fixes, and deployments!

---

**Created for**: NIELIT Bhubaneswar  
**Version**: 1.0  
**Last Updated**: 2026
