# 🎨 Maintenance Mode - Visual Guide

## 🔗 Quick Access

```
Admin Panel: http://localhost/public_html/admin/manage_maintenance.php
Public View:  http://localhost/public_html/maintenance.php
```

---

## 📍 Finding It In Admin Panel

### Sidebar Navigation
```
NIELIT Admin
 │
 ├── 📊 Dashboard
 ├── 👥 Students
 ├── 📚 Courses
 │
 └── ⚙️ System Settings (Master Admin Only)
      ├── 🎨 Themes
      ├── 🛠️ Maintenance Mode  ← YOU ARE HERE!
      └── 🏠 Homepage Content
```

---

## 🖥️ Admin Management Page Layout

### Header Section
```
┌──────────────────────────────────────────────────────────┐
│  🛠️ Maintenance Mode Management                          │
│  Control site maintenance mode with countdown timer      │
└──────────────────────────────────────────────────────────┘
```

### Current Status Display
```
┌──────────────────────────────────────────────────────────┐
│  ⚠️  Maintenance Mode is ACTIVE                          │
│      Public users will see the maintenance page.         │
│      Admins can still access admin panel.                │
└──────────────────────────────────────────────────────────┘

OR

┌──────────────────────────────────────────────────────────┐
│  ✅  Site is LIVE                                         │
│      All users can access the site normally.             │
└──────────────────────────────────────────────────────────┘
```

### Settings Form
```
┌──────────────────────────────────────────────────────────┐
│  Maintenance Mode Settings                               │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  ☑️ Enable Maintenance Mode                              │
│     When enabled, public users will see maintenance page │
│                                                           │
│  📝 Maintenance Title                                     │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ Site Under Maintenance                              │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                           │
│  💬 Maintenance Message                                   │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ We are currently performing scheduled               │ │
│  │ maintenance. We will be back soon!                  │ │
│  │                                                     │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                           │
│  🕐 Estimated End Time (Optional)                         │
│  ┌──────────────────────┐                                │
│  │ 2026-06-02  18:00   │  Leave empty to hide countdown │
│  └──────────────────────┘                                │
│                                                           │
│  Display Options                                          │
│  ☑️ Show Countdown Timer                                  │
│  ☑️ Show Contact Information                              │
│                                                           │
│  [💾 Save Settings]  [👁️ Preview Maintenance Page]       │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

### Quick Actions Section
```
┌──────────────────────────────────────────────────────────┐
│  ⚡ Quick Actions                                         │
├──────────────────────────────────────────────────────────┤
│                                                           │
│   ┌───────────┐    ┌───────────┐    ┌───────────┐       │
│   │     🕐    │    │     🕐    │    │     🌙    │       │
│   │           │    │           │    │           │       │
│   │  1 Hour   │    │  4 Hours  │    │ Overnight │       │
│   │Maintenance│    │Maintenance│    │Maintenance│       │
│   │           │    │           │    │           │       │
│   │ [Set for  │    │ [Set for  │    │ [Set for  │       │
│   │  1 Hour]  │    │  4 Hours] │    │ 12 Hours] │       │
│   └───────────┘    └───────────┘    └───────────┘       │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

---

## 🌐 Public Maintenance Page Layout

### What Visitors See
```
┌──────────────────────────────────────────────────────────┐
│                                                           │
│                    🏢 NIELIT LOGO                         │
│                                                           │
│                   ⚙️ (animated spinner)                   │
│                                                           │
│             Site Under Maintenance                        │
│                                                           │
│       We are currently performing scheduled               │
│       maintenance. We will be back soon!                  │
│                                                           │
│                  We'll be back in:                        │
│                                                           │
│         ┌──────┬──────┬──────┬──────┐                    │
│         │  00  │  04  │  35  │  22  │                    │
│         │ Days │ Hrs  │ Mins │ Secs │                    │
│         └──────┴──────┴──────┴──────┘                    │
│                                                           │
│           📧 Contact: admin@nielit.gov.in                 │
│                                                           │
│        🔄 This page will refresh automatically            │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

---

## 🎬 Workflow Examples

### Example 1: Quick 1-Hour Maintenance
```
1. Go to Maintenance Mode Management
   └─> Click "Set for 1 Hour" button
       └─> End time automatically set to 1 hour from now
           └─> Countdown automatically enabled
               └─> Click "Save Settings"
                   └─> ✅ Maintenance active for 1 hour!
```

### Example 2: Scheduled Overnight Maintenance
```
1. Go to Maintenance Mode Management
   └─> Enter custom title: "Scheduled System Upgrade"
       └─> Enter message: "We're upgrading our systems..."
           └─> Set end time: Tomorrow 8:00 AM
               └─> Check "Show Countdown"
                   └─> Check "Show Contact"
                       └─> Click "Save Settings"
                           └─> ✅ Scheduled maintenance active!
```

### Example 3: Disable Maintenance
```
1. Go to Maintenance Mode Management
   └─> Uncheck "Enable Maintenance Mode"
       └─> Click "Save Settings"
           └─> ✅ Site is now LIVE!
```

---

## 🎨 Color Coding

### Status Indicators
```
🟢 Green Background = Site is LIVE
   ✅ "Site is LIVE"
   All users can access normally

🟡 Yellow Background = Maintenance Active  
   ⚠️ "Maintenance Mode is ACTIVE"
   Public sees maintenance page
   Admins can still access admin panel
```

### Buttons
```
🔵 Blue Button = Primary action (Save, Set)
🔘 Gray Button = Secondary action (Preview, Cancel)
```

---

## 📱 Responsive Design

### Desktop View
```
┌─────────────────────────────────────────────────┐
│  Full width layout                              │
│  Side-by-side quick action cards                │
│  Large countdown numbers                        │
└─────────────────────────────────────────────────┘
```

### Tablet View
```
┌────────────────────────────┐
│  Adjusted width            │
│  Stacked quick actions     │
│  Medium countdown          │
└────────────────────────────┘
```

### Mobile View
```
┌─────────────────┐
│  Full width     │
│  Vertical stack │
│  Touch-friendly │
│  buttons        │
└─────────────────┘
```

---

## ⚙️ Behind The Scenes

### When You Click "Save Settings"
```
User Clicks Save
    ↓
Validate Input
    ↓
Update Database (maintenance_mode table)
    ↓
Show Success Message
    ↓
Page Refreshes with New Status
```

### When Visitor Accesses Site During Maintenance
```
Visitor Goes to index.php
    ↓
maintenance_check.php runs
    ↓
Check if maintenance_mode.is_enabled = 1
    ↓
    ├─> Yes → Check if user is admin
    │         ├─> Yes (Admin) → Allow access
    │         └─> No (Public) → Redirect to maintenance.php
    │
    └─> No → Allow normal access
```

### Auto-Refresh Countdown
```
Page Loads
    ↓
JavaScript reads end_time
    ↓
Calculate time remaining
    ↓
Update countdown display every 1 second
    ↓
If countdown reaches 0:
    └─> Auto-refresh page
        └─> Check if still in maintenance
            ├─> Still in maintenance? Show maintenance page
            └─> Maintenance ended? Redirect to homepage
```

---

## 🔍 What Each Setting Does

| Setting | What It Does | Example |
|---------|--------------|---------|
| **Enable Maintenance Mode** | Master toggle - turns maintenance on/off | ☑️ = Site in maintenance |
| **Maintenance Title** | Main heading on maintenance page | "System Upgrade in Progress" |
| **Maintenance Message** | Detailed explanation | "We're improving our services..." |
| **Estimated End Time** | When maintenance will end | "June 2, 2026 6:00 PM" |
| **Show Countdown** | Display timer to visitors | ☑️ = Shows "2 hours 30 mins remaining" |
| **Show Contact** | Display support email | ☑️ = Shows "admin@nielit.gov.in" |

---

## 🎯 Use Cases

### 1. Database Upgrade
```
Title: "Database Maintenance"
Message: "We're upgrading our database for better performance"
Duration: 4 hours
Countdown: ✅ Yes
Contact: ✅ Yes
```

### 2. Server Migration
```
Title: "Server Migration in Progress"
Message: "We're moving to faster servers!"
Duration: 12 hours
Countdown: ✅ Yes
Contact: ✅ Yes
```

### 3. Emergency Maintenance
```
Title: "Urgent System Maintenance"
Message: "We're fixing a critical issue"
Duration: 1 hour
Countdown: ✅ Yes
Contact: ✅ Yes
```

### 4. Indefinite Maintenance (No End Time)
```
Title: "Site Under Construction"
Message: "We're building something amazing"
Duration: (leave blank)
Countdown: ❌ No
Contact: ✅ Yes
```

---

## 💡 Pro Tips

### Tip 1: Preview Before Activating
Always click "Preview Maintenance Page" before enabling to see how it looks!

### Tip 2: Use Quick Actions
The quick action buttons automatically:
- Set the end time
- Enable countdown
- Check the "show countdown" box

### Tip 3: Admin Access During Maintenance
You can always access the admin panel at:
```
http://localhost/public_html/admin/login.php
```
Even when maintenance is active!

### Tip 4: Clear Messages
Write clear, friendly messages:
✅ "We're upgrading to serve you better!"
❌ "System down. Check back later."

### Tip 5: Realistic Time Estimates
Set realistic end times. Better to finish early than late!

---

## 📊 Visual Status Flow

```
                    LIVE STATE
                        │
                        │ Admin enables maintenance
                        ▼
              MAINTENANCE MODE ACTIVE
                        │
                ┌───────┴───────┐
                │               │
            Admins          Public Users
                │               │
                │               │
        Access Normally    See Maintenance
        (Admin Panel)         Page
                │               │
                │               │
                └───────┬───────┘
                        │
                        │ Admin disables maintenance
                        ▼
                   LIVE STATE
                        │
                   All Access
                    Normally
```

---

## ✨ Animation Details

### Maintenance Page Spinner
```
    ⚙️  Rotates continuously
        Smooth 2-second rotation
        Infinite loop
        Blue color (#2563eb)
```

### Countdown Timer
```
Numbers update every 1 second
Smooth transition
Large, readable font
Color: Dark blue (#1e3a8a)
```

### Auto-Refresh Indicator
```
🔄 Rotates when page is about to refresh
   Small icon at bottom
   Friendly reminder to users
```

---

**Visual Guide Complete!**  
Everything you need to use the maintenance mode system effectively.
