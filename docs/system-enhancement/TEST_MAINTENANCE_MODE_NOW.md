# 🧪 TEST YOUR MAINTENANCE MODE (2 Minutes)

**Status:** ✅ Ready to Test  
**Integration:** Complete  
**Time Required:** 2 minutes

---

## 🎯 Quick Test (30 Seconds)

### Step 1: Enable Maintenance
```
1. Open: http://localhost/public_html/admin/manage_maintenance.php
2. Check: ☑️ Enable Maintenance Mode
3. Click: [Save Settings]
```

### Step 2: Test It Works
```
1. Open NEW incognito/private browser window
2. Visit: http://localhost/public_html/index.php
3. See: Maintenance page with countdown ✅
```

### Step 3: Disable Maintenance
```
1. Back to: admin/manage_maintenance.php
2. Uncheck: ☐ Enable Maintenance Mode
3. Click: [Save Settings]
4. Refresh incognito window → Site works normally ✅
```

**Done!** Your maintenance mode is working! 🎉

---

## 📊 Full Test Checklist

### ✅ Test 1: Homepage Redirect
- [ ] Enable maintenance mode
- [ ] Open incognito window
- [ ] Visit: `http://localhost/public_html/index.php`
- [ ] **Expected:** Redirects to maintenance.php
- [ ] **See:** Maintenance message with countdown

### ✅ Test 2: Courses Page Redirect
- [ ] Maintenance still enabled
- [ ] In incognito window
- [ ] Visit: `http://localhost/public_html/public/courses.php`
- [ ] **Expected:** Redirects to maintenance.php
- [ ] **See:** Same maintenance page

### ✅ Test 3: Contact Page Redirect
- [ ] Maintenance still enabled
- [ ] In incognito window
- [ ] Visit: `http://localhost/public_html/public/contact.php`
- [ ] **Expected:** Redirects to maintenance.php
- [ ] **See:** Same maintenance page

### ✅ Test 4: Admin Bypass
- [ ] Maintenance still enabled
- [ ] In NORMAL browser (not incognito)
- [ ] Login to: `http://localhost/public_html/admin/login.php`
- [ ] After login, visit: `http://localhost/public_html/index.php`
- [ ] **Expected:** NO redirect
- [ ] **See:** Normal homepage (admin bypasses maintenance)

### ✅ Test 5: Disable & Verify
- [ ] Go to: `admin/manage_maintenance.php`
- [ ] Uncheck: Enable Maintenance Mode
- [ ] Click: Save Settings
- [ ] In incognito window, visit: `http://localhost/public_html/index.php`
- [ ] **Expected:** NO redirect
- [ ] **See:** Normal homepage

---

## 🎬 Visual Test Scenarios

### Scenario A: Regular User During Maintenance

```
┌─────────────────────────────────────────┐
│  User (Incognito Window)                │
└─────────────────┬───────────────────────┘
                  │
                  │ Types URL: index.php
                  ↓
┌─────────────────────────────────────────┐
│  Page Loads: index.php                  │
│  Line 1: Check maintenance               │
└─────────────────┬───────────────────────┘
                  │
                  │ Maintenance is ON
                  ↓
┌─────────────────────────────────────────┐
│  🚨 REDIRECT → maintenance.php          │
└─────────────────┬───────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────┐
│         MAINTENANCE PAGE                │
│                                         │
│      🛠️ Site Under Maintenance          │
│                                         │
│  We're upgrading our systems            │
│                                         │
│      We'll be back in:                  │
│     00 : 02 : 30 : 45                   │
│    Days Hrs Mins Secs                   │
│                                         │
│  📧 Contact: admin@nielit.gov.in        │
└─────────────────────────────────────────┘
```

### Scenario B: Admin During Maintenance

```
┌─────────────────────────────────────────┐
│  Admin (Normal Browser)                 │
│  ✅ Logged in as admin                  │
└─────────────────┬───────────────────────┘
                  │
                  │ Types URL: index.php
                  ↓
┌─────────────────────────────────────────┐
│  Page Loads: index.php                  │
│  Line 1: Check maintenance               │
└─────────────────┬───────────────────────┘
                  │
                  │ Admin session detected!
                  ↓
┌─────────────────────────────────────────┐
│  ✅ BYPASS → Continue normally          │
└─────────────────┬───────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────┐
│         NORMAL HOMEPAGE                 │
│                                         │
│  🏠 NIELIT Bhubaneswar                  │
│                                         │
│  [Courses] [Contact] [News]             │
│                                         │
│  (Admin can work normally)              │
│                                         │
│  ⚡ Site in maintenance for others      │
└─────────────────────────────────────────┘
```

---

## 🔍 What To Look For

### ✅ When Maintenance is ON:

**Regular Users Should See:**
- ✅ Automatic redirect from ANY public page
- ✅ Beautiful maintenance page
- ✅ Countdown timer (if set)
- ✅ Custom message
- ✅ Contact information
- ✅ NIELIT logo and branding

**Admins Should See:**
- ✅ Normal pages (NO redirect)
- ✅ Full site functionality
- ✅ Can manage content
- ✅ Can disable maintenance anytime

### ✅ When Maintenance is OFF:

**Everyone Should See:**
- ✅ Normal website
- ✅ All pages working
- ✅ No redirects
- ✅ Full functionality

---

## 🎯 Pages That Are Protected

All these pages now check maintenance mode:

```
✅ index.php                    → Redirects if maintenance ON
✅ public/courses.php           → Redirects if maintenance ON
✅ public/contact.php           → Redirects if maintenance ON
✅ public/news.php              → Redirects if maintenance ON
✅ student/register.php         → Redirects if maintenance ON
```

Admin pages are excluded:
```
✅ admin/login.php              → Always accessible
✅ admin/dashboard.php          → Always accessible
✅ admin/manage_maintenance.php → Always accessible
✅ All admin/* pages            → Always accessible
```

---

## 🧪 Advanced Tests

### Test A: Quick Action Buttons
```
1. Go to: admin/manage_maintenance.php
2. Click: "Set for 1 Hour" button
3. Notice: End time auto-filled, countdown enabled
4. Click: Save Settings
5. Visit: maintenance.php (in incognito)
6. Verify: Countdown shows approximately 59 minutes
```

### Test B: Custom Message
```
1. Go to: admin/manage_maintenance.php
2. Enable maintenance mode
3. Change title: "Upgrading for You!"
4. Change message: "We're making NIELIT even better!"
5. Set end time: 1 hour from now
6. Save Settings
7. Visit: maintenance.php (in incognito)
8. Verify: See your custom title and message
```

### Test C: Preview Without Enabling
```
1. Go to: admin/manage_maintenance.php
2. DON'T enable maintenance mode
3. Click: "Preview Maintenance Page" button
4. Opens in new tab
5. See: How maintenance page will look
6. Close tab
7. Site still works normally (not enabled)
```

---

## 🚨 Common Issues & Solutions

### Issue: "I see normal page in incognito"

**Solution:**
1. Check maintenance is enabled in admin panel
2. Look at the checkbox - is it checked? ☑️
3. Did you click "Save Settings"?
4. Check database: 
   ```sql
   SELECT is_enabled FROM maintenance_mode WHERE id = 1;
   -- Should return: 1
   ```

### Issue: "Admin can't access site"

**Solution:**
1. Clear browser cookies
2. Re-login to admin panel
3. Check session:
   ```php
   var_dump($_SESSION['admin_logged_in']); // Should be true
   ```

### Issue: "Redirect loop"

**Solution:**
✅ This is prevented by design!
- maintenance.php excludes itself from check
- Admin pages excluded automatically
- If you see this, check for custom code

---

## 📊 Expected Results Summary

| Test | Maintenance ON | Maintenance OFF |
|------|---------------|-----------------|
| Regular user visits index.php | → Redirect to maintenance.php | → Normal homepage |
| Regular user visits courses.php | → Redirect to maintenance.php | → Normal courses page |
| Admin visits index.php | → Normal homepage | → Normal homepage |
| Admin visits admin panel | → Normal admin panel | → Normal admin panel |
| Visit maintenance.php directly | → Shows maintenance page | → Shows maintenance page |

---

## ✅ Test Completion Checklist

After testing, you should have verified:

- [ ] ✅ Maintenance mode can be enabled
- [ ] ✅ Public pages redirect when enabled
- [ ] ✅ Maintenance page displays correctly
- [ ] ✅ Countdown timer works (if set)
- [ ] ✅ Custom message displays
- [ ] ✅ Admins can bypass and work normally
- [ ] ✅ Maintenance mode can be disabled
- [ ] ✅ Site returns to normal when disabled
- [ ] ✅ Quick action buttons work
- [ ] ✅ Preview feature works

---

## 🎊 Success Indicators

You'll know it's working when:

1. **✅ Enable Maintenance:**
   - Incognito users → See maintenance page
   - Admins → See normal site

2. **✅ Countdown Works:**
   - Timer counts down in real-time
   - Shows days, hours, minutes, seconds

3. **✅ Disable Maintenance:**
   - Everyone → See normal site
   - No redirects happen

4. **✅ Admin Panel:**
   - Easy on/off toggle
   - Quick action buttons work
   - Preview shows correct look

---

## 🚀 You're Done!

If all tests pass:
- ✅ Your maintenance mode is working perfectly
- ✅ Public pages are protected
- ✅ Admins can work during maintenance
- ✅ System is production-ready

**Time to test:** 2 minutes  
**URLs to test:** 5 pages  
**Expected result:** All tests pass ✅

---

## 📞 Need Help?

Check these docs:
- `docs/system-enhancement/MAINTENANCE_MODE_HOW_IT_WORKS.md` - Full explanation
- `docs/START_HERE_MAINTENANCE_MODE.md` - Quick start guide
- `docs/system-enhancement/MAINTENANCE_MODE_COMPLETE.md` - Complete system docs

---

**Testing Date:** June 2, 2026  
**Status:** ✅ Ready to Test  
**Estimated Time:** 2 minutes
