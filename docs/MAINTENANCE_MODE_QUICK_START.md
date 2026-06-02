# ⚡ Maintenance Mode - Quick Start Guide

## 🚀 Setup (5 minutes)

### Step 1: Install Database Table
Visit this URL in your browser:
```
http://localhost/public_html/migrations/install_maintenance_mode.php
```
✅ This creates the maintenance_mode table

### Step 2: Add to Admin Menu
Edit `admin/includes/sidebar.php` and add:
```php
<li class="nav-item">
    <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage_maintenance.php">
        <i class="fas fa-tools"></i> Maintenance Mode
    </a>
</li>
```

### Step 3: Add Protection to Public Pages
At the top of `index.php` (after `<php` tag), add:
```php
<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/maintenance_check.php';
?>
```

Repeat for:
- `public/courses.php`
- `public/contact.php`
- `public/news.php`
- `student/login.php`
- `student/register.php`

---

## 🎯 How to Use

### Enable Maintenance Mode (Quick)

1. Login to Admin Panel
2. Click **Maintenance Mode** in menu
3. Click one of the quick buttons:
   - **Set for 1 Hour** → Quick fixes
   - **Set for 4 Hours** → Updates
   - **Set for 12 Hours** → Overnight
4. Click **Save Settings**

✅ Done! Users now see the maintenance page.

### Disable Maintenance Mode

1. Go to **Maintenance Mode** page
2. Toggle **Enable Maintenance Mode** to OFF
3. Click **Save Settings**

✅ Done! Site is live again.

---

## 👀 Preview

Visit this URL to see the maintenance page:
```
http://localhost/public_html/maintenance.php
```

---

## 🔍 Testing

1. **As Admin**: You can access everything normally
2. **As Public User** (incognito/logout): You see maintenance page
3. **When Countdown Ends**: Page auto-refreshes

---

## ⚙️ Configuration Options

| Option | What it does |
|--------|-------------|
| Enable Maintenance Mode | Turn on/off |
| Maintenance Title | Change headline |
| Maintenance Message | Custom message |
| End Time | Set countdown target |
| Show Countdown | Display timer |
| Show Contact | Display phone/email |

---

## 📝 Example Messages

### For Updates
```
Title: Exciting Updates in Progress
Message: We're adding new features to improve your experience. We'll be back online shortly!
Time: Set for 2 hours
```

### For Maintenance
```
Title: Scheduled Maintenance
Message: We're performing routine maintenance to keep everything running smoothly. Thank you for your patience!
Time: Set for 4 hours
```

### For Emergency
```
Title: Site Temporarily Unavailable
Message: We're working on resolving a technical issue. Please check back shortly.
Time: (leave blank)
```

---

## ✨ Features

✅ Beautiful animated maintenance page  
✅ Real-time countdown timer  
✅ Admin bypass (you can still work)  
✅ Mobile responsive  
✅ Contact information display  
✅ One-click enable/disable  
✅ Quick time presets  

---

## 🆘 Need Help?

**Not Working?**
1. Check if migration ran successfully
2. Verify maintenance mode is enabled
3. Clear browser cache
4. Check if logged in as admin (admins bypass)

**Questions?**
- Check full documentation: `MAINTENANCE_MODE_SYSTEM.md`
- Email: dir-bbsr@nielit.gov.in
- Phone: 0674-2960354

---

That's it! Your maintenance mode system is ready to use. 🎉
