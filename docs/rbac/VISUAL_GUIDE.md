# Visual Guide: 2-Role RBAC System

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    NIELIT Admin System                       │
│                   2-Role Access Control                      │
└─────────────────────────────────────────────────────────────┘

┌──────────────────────┐         ┌──────────────────────┐
│   MASTER ADMIN       │         │  COURSE COORDINATOR  │
│   (Full Access)      │         │  (Limited Access)    │
└──────────────────────┘         └──────────────────────┘
         │                                  │
         ├─ Dashboard ✅                    ├─ Dashboard ✅
         ├─ Students ✅                     ├─ Students ✅
         ├─ Courses ✅                      ├─ Courses ✅
         ├─ Batches ✅                      ├─ Batches ✅
         ├─ Approve Students ✅             ├─ Approve Students ✅
         ├─ Reset Password ✅               ├─ Reset Password ✅
         │                                  │
         ├─ Add Admin ✅                    ├─ Add Admin ❌
         ├─ Manage Admins ✅                ├─ Manage Admins ❌
         ├─ Training Centres ✅             ├─ Training Centres ❌
         ├─ Themes ✅                       ├─ Themes ❌
         └─ Homepage Content ✅             └─ Homepage Content ❌
```

## Login Flow

```
┌─────────────┐
│ Admin Login │
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│ Enter Username  │
│ Enter Password  │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│  OTP Sent to    │
│     Email       │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│  Verify OTP     │
└──────┬──────────┘
       │
       ▼
┌─────────────────────────────┐
│ init_admin_session()        │
│ - Load username             │
│ - Load admin_id             │
│ - Load admin_role ⭐        │
│ - Load email                │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│  Redirect to Dashboard      │
│  Menu items shown based     │
│  on role                    │
└─────────────────────────────┘
```

## Add Admin Flow (Master Admin Only)

```
┌──────────────────────┐
│ Click "Add Admin"    │
│ (Master Admin only)  │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ Fill Admin Details:  │
│ - Username           │
│ - Email              │
│ - Password           │
│ - Phone              │
│ - SELECT ROLE ⭐     │
│   • Master Admin     │
│   • Course Coord.    │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ Click "Send OTP"     │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ OTP Sent to Email    │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ Enter OTP Code       │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ Admin Created with   │
│ Selected Role ✅     │
└──────────────────────┘
```

## Manage Admins Page (Master Admin Only)

```
┌─────────────────────────────────────────────────────────┐
│                    MANAGE ADMINS                         │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📊 Statistics:                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │ Total: 5 │  │ Master:2 │  │ Coord: 3 │             │
│  └──────────┘  └──────────┘  └──────────┘             │
│                                                          │
│  👤 Admin: john_doe                    [Master Admin]   │
│  ├─ Email: john@example.com                             │
│  ├─ Phone: 1234567890                                   │
│  ├─ Created: 01 Jan 2026                                │
│  └─ Actions: [Change Role ▼] [Update] [Delete]         │
│                                                          │
│  👤 Admin: jane_smith                  [Course Coord]   │
│  ├─ Email: jane@example.com                             │
│  ├─ Phone: 0987654321                                   │
│  ├─ Created: 15 Feb 2026                                │
│  └─ Actions: [Change Role ▼] [Update] [Delete]         │
│                                                          │
│  👤 Admin: current_user (YOU)          [Master Admin]   │
│  ├─ Email: you@example.com                              │
│  ├─ Phone: 5555555555                                   │
│  ├─ Created: 20 Mar 2026                                │
│  └─ ℹ️ You cannot modify your own account               │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

## Sidebar Navigation Comparison

### Master Admin Sidebar
```
┌─────────────────────────┐
│   NIELIT Admin          │
│   Bhubaneswar           │
├─────────────────────────┤
│ 🏠 Dashboard            │
│ 👥 Students             │
│ 📚 Courses              │
│ 📦 Batches              │
├─────────────────────────┤
│ System Settings         │
├─────────────────────────┤
│ 🏢 Training Centres     │
│ 🎨 Themes               │
│ 🏠 Homepage Content     │
├─────────────────────────┤
│ ✅ Approve Students     │
│ ➕ Add Admin            │ ⭐ Master Only
│ ⚙️  Manage Admins       │ ⭐ Master Only
│ 🔑 Reset Password       │
├─────────────────────────┤
│ 🌐 View Website         │
│ 🚪 Logout               │
└─────────────────────────┘
```

### Course Coordinator Sidebar
```
┌─────────────────────────┐
│   NIELIT Admin          │
│   Bhubaneswar           │
├─────────────────────────┤
│ 🏠 Dashboard            │
│ 👥 Students             │
│ 📚 Courses              │
│ 📦 Batches              │
├─────────────────────────┤
│ ✅ Approve Students     │
│ 🔑 Reset Password       │
├─────────────────────────┤
│ 🌐 View Website         │
│ 🚪 Logout               │
└─────────────────────────┘

❌ No "Add Admin"
❌ No "Manage Admins"
❌ No "System Settings"
```

## Permission Check Flow

```
┌─────────────────────────┐
│ User tries to access    │
│ restricted page         │
│ (e.g., add_admin.php)   │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│ Check if logged in      │
│ $_SESSION['admin']?     │
└──────┬──────────────────┘
       │
       ├─ NO ──→ Redirect to login.php
       │
       ▼ YES
┌─────────────────────────┐
│ Check role              │
│ $_SESSION['admin_role'] │
│ === 'master_admin'?     │
└──────┬──────────────────┘
       │
       ├─ NO ──→ Show "Access Denied"
       │         Redirect to dashboard.php
       │
       ▼ YES
┌─────────────────────────┐
│ Allow access to page    │
└─────────────────────────┘
```

## Database Structure

```
┌─────────────────────────────────────────────────────┐
│                   admin table                        │
├─────────────────────────────────────────────────────┤
│ id          INT PRIMARY KEY AUTO_INCREMENT          │
│ username    VARCHAR(50) UNIQUE                      │
│ password    VARCHAR(255)                            │
│ email       VARCHAR(100)                            │
│ phone       VARCHAR(20)                             │
│ role        ENUM('master_admin',                    │ ⭐ NEW
│                  'course_coordinator')              │
│             DEFAULT 'master_admin'                  │
│ created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP     │ ⭐ NEW
│ updated_at  TIMESTAMP ON UPDATE CURRENT_TIMESTAMP   │ ⭐ NEW
└─────────────────────────────────────────────────────┘
```

## Session Variables

```
┌─────────────────────────────────────────────────────┐
│              $_SESSION Variables                     │
├─────────────────────────────────────────────────────┤
│ 'admin'       → 'john_doe'                          │
│ 'admin_id'    → 1                                   │
│ 'admin_role'  → 'master_admin'                      │ ⭐ KEY
│ 'admin_email' → 'john@example.com'                  │
└─────────────────────────────────────────────────────┘
```

## Role Badge Colors

```
┌──────────────────────┐
│   Master Admin       │  Green gradient
│   🟢 Full Access     │  #10b981 → #059669
└──────────────────────┘

┌──────────────────────┐
│ Course Coordinator   │  Blue gradient
│   🔵 Limited Access  │  #3b82f6 → #2563eb
└──────────────────────┘
```

## File Structure

```
project/
├── admin/
│   ├── add_admin.php           ⭐ Modified (role selection)
│   ├── manage_admins.php       ⭐ NEW (admin management)
│   ├── login.php               ✅ Already loads role
│   ├── dashboard.php           (shows role-based menu)
│   └── includes/
│       └── sidebar.php         ⭐ NEW (role-based nav)
│
├── includes/
│   └── session_manager.php     ✅ Already has RBAC
│
├── migrations/
│   └── add_simple_rbac.php     ⭐ NEW (database setup)
│
└── docs/
    └── rbac/
        ├── IMPLEMENTATION_COMPLETE.md
        ├── SIMPLE_RBAC_IMPLEMENTATION.md
        ├── QUICK_START_RBAC.md
        └── VISUAL_GUIDE.md         ⭐ This file
```

## Quick Reference

### To Add New Admin:
1. Log in as Master Admin
2. Click "Add Admin"
3. Fill form + select role
4. Verify OTP
5. Done!

### To Change Admin Role:
1. Log in as Master Admin
2. Click "Manage Admins"
3. Select new role from dropdown
4. Click "Update Role"
5. Done!

### To Delete Admin:
1. Log in as Master Admin
2. Click "Manage Admins"
3. Click "Delete" button
4. Confirm deletion
5. Done!

## Color Coding

- 🟢 Green = Master Admin features
- 🔵 Blue = Course Coordinator features
- ⭐ Star = New/Modified in this implementation
- ✅ Check = Already working
- ❌ Cross = Not accessible

## Summary

```
┌─────────────────────────────────────────────────────────┐
│  SIMPLE 2-ROLE RBAC SYSTEM                              │
│  ✅ FULLY IMPLEMENTED AND READY TO USE                  │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📝 Next Step: Run Migration                            │
│  🔗 URL: /migrations/add_simple_rbac.php                │
│                                                          │
│  📚 Documentation:                                       │
│  - Quick Start: docs/rbac/QUICK_START_RBAC.md          │
│  - Full Docs: docs/rbac/SIMPLE_RBAC_IMPLEMENTATION.md  │
│  - This Guide: docs/rbac/VISUAL_GUIDE.md               │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

**System is ready! Run the migration and start using the 2-role RBAC system! 🚀**
