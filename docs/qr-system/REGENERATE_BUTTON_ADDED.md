# ✅ QR Code Regeneration Button Added to Edit Course Page

## 🎯 Feature Added
Added a **"Regenerate QR"** button in `admin/edit_course.php` that allows admins to force regenerate QR codes for existing courses.

---

## 🔧 What Was Added

### 1. **Regenerate QR Button** in UI
**Location:** `admin/edit_course.php` - QR Code display section

**BEFORE:**
```html
<div style="display: flex; justify-content: space-between;">
    <strong>Current QR Code:</strong>
    <a href="..." class="btn btn-primary btn-sm">
        <i class="fas fa-download"></i> Download QR Code
    </a>
</div>
```

**AFTER:**
```html
<div style="display: flex; justify-content: space-between;">
    <strong>Current QR Code:</strong>
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-warning btn-sm" onclick="regenerateQRCode()">
            <i class="fas fa-sync-alt"></i> Regenerate QR
        </button>
        <a href="..." class="btn btn-primary btn-sm">
            <i class="fas fa-download"></i> Download QR
        </a>
    </div>
</div>
```

---

### 2. **JavaScript Function** - `regenerateQRCode()`
**Location:** `admin/edit_course.php` - JavaScript section

**Features:**
- ✅ Validates course code is entered
- ✅ Shows confirmation dialog before regenerating
- ✅ Sends AJAX request with `force_regenerate` flag
- ✅ Shows loading state with spinner
- ✅ Updates QR image with cache buster
- ✅ Updates "Generated" timestamp
- ✅ Reloads page after 1.5 seconds to ensure everything is updated
- ✅ Shows success/error toast notifications

**Code:**
```javascript
function regenerateQRCode() {
    const courseCode = courseCodeInput.value.trim();
    const courseId = <?php echo $course['id']; ?>;
    
    if (!courseCode) {
        toast.warning('Please enter course code first!');
        return;
    }
    
    if (!confirm('Are you sure you want to regenerate the QR code?')) {
        return;
    }
    
    // Send AJAX with force_regenerate flag
    formData.append('force_regenerate', '1');
    
    fetch('generate_link_qr.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update QR image with cache buster
                qrImage.src = data.qr_code_url + '?t=' + new Date().getTime();
                // Reload page after 1.5 seconds
                setTimeout(() => location.reload(), 1500);
            }
        });
}
```

---

### 3. **Backend Handler** - Updated `generate_link_qr.php`
**Location:** `admin/generate_link_qr.php`

**Changes:**
- ✅ Added `force_regenerate` parameter detection
- ✅ Checks if QR code already exists (skip if not forcing)
- ✅ Deletes old QR code file before regenerating
- ✅ Generates new QR code with updated course code
- ✅ Updates database with new QR path and timestamp

**Logic Flow:**
```php
if ($force_regenerate) {
    // Force regeneration requested
    $should_generate_qr = true;
    
    // Get and delete old QR code
    $old_qr_path = getOldQRPath($course_id);
    deleteQRCode($old_qr_path);
} else {
    // Only generate if QR doesn't exist
    $should_generate_qr = !qrCodeExists($course_id);
}

if ($should_generate_qr) {
    $qr_result = generateCourseQRCode($course_id, $course_code);
    // Update database...
}
```

---

## 🎨 UI/UX Features

### Button Styling:
- **Color:** Warning (Orange) - indicates caution
- **Icon:** Sync/Refresh icon with rotation animation
- **Position:** Next to "Download QR" button
- **Size:** Small button (btn-sm)

### User Experience:
1. **Confirmation Dialog:** Prevents accidental regeneration
2. **Loading State:** Button shows spinner while processing
3. **Toast Notifications:** Clear feedback on success/error
4. **Auto-reload:** Page refreshes to show new QR code
5. **Cache Buster:** Ensures browser shows new QR image

---

## 🧪 How to Test

### Test 1: Regenerate Existing QR Code
1. Go to `admin/edit_course.php?id=56`
2. Scroll to "Registration Link & QR Code" section
3. You should see the current QR code with two buttons:
   - **"Regenerate QR"** (Orange/Warning)
   - **"Download QR"** (Blue/Primary)
4. Click **"Regenerate QR"**
5. Confirm the dialog
6. **Expected Result:**
   - Loading spinner appears
   - Toast shows "Regenerating QR code..."
   - QR code image updates
   - Toast shows "QR code regenerated successfully!"
   - Page reloads after 1.5 seconds

### Test 2: Regenerate with Updated Course Code
1. Go to `admin/edit_course.php?id=56`
2. Change course code from `DBC` to `DBC2026`
3. Click **"Regenerate QR"**
4. **Expected Result:**
   - New QR code generated with URL `?course=DBC2026`
   - Old QR file deleted
   - New QR file created: `qr_DBC2026_56.png`

### Test 3: Cancel Regeneration
1. Click **"Regenerate QR"**
2. Click **"Cancel"** in confirmation dialog
3. **Expected Result:**
   - No action taken
   - QR code remains unchanged

---

## 📊 Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│              ADMIN EDITS COURSE                              │
│  Opens: admin/edit_course.php?id=56                          │
│  Sees: Current QR code with "Regenerate QR" button          │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│           CLICKS "REGENERATE QR" BUTTON                      │
│  JavaScript: regenerateQRCode()                              │
│  Shows: Confirmation dialog                                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              CONFIRMS REGENERATION                           │
│  Button: Shows spinner "Regenerating..."                     │
│  Toast: "Regenerating QR code..."                            │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│           AJAX REQUEST TO BACKEND                            │
│  POST: generate_link_qr.php                                  │
│  Data: course_id=56, course_code=DBC, force_regenerate=1    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              BACKEND PROCESSING                              │
│  1. Checks force_regenerate flag = true                      │
│  2. Gets old QR path from database                           │
│  3. Deletes old QR file: qr_DBC_56.png                       │
│  4. Generates new QR with course code                        │
│  5. Saves new QR: qr_DBC_56.png (new version)                │
│  6. Updates database: qr_code_path, qr_generated_at          │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              FRONTEND UPDATES                                │
│  1. Receives success response                                │
│  2. Updates QR image with cache buster                       │
│  3. Updates "Generated" timestamp                            │
│  4. Shows success toast                                      │
│  5. Reloads page after 1.5 seconds                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                 PAGE RELOADED                                │
│  Shows: New QR code with updated timestamp                   │
│  QR Contains: ?course=DBC (with updated course code)         │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Use Cases

### Use Case 1: Course Code Changed
**Scenario:** Admin changes course code from `DBC` to `DBC2026`
**Action:** Click "Regenerate QR" to update QR code with new course code
**Result:** QR code now contains `?course=DBC2026`

### Use Case 2: QR Code Corrupted
**Scenario:** QR code file is corrupted or deleted manually
**Action:** Click "Regenerate QR" to create new QR code
**Result:** Fresh QR code generated and saved

### Use Case 3: Registration Link Changed
**Scenario:** Admin updates registration link URL
**Action:** Click "Regenerate QR" to sync QR with new link
**Result:** QR code matches updated registration link

---

## 📋 Summary of Changes

| File | Change | Description |
|------|--------|-------------|
| `admin/edit_course.php` | Added Button | "Regenerate QR" button in QR display section |
| `admin/edit_course.php` | Added Function | `regenerateQRCode()` JavaScript function |
| `admin/edit_course.php` | Added IDs | `qr_code_image` and `qr_generated_time` for dynamic updates |
| `admin/generate_link_qr.php` | Added Logic | Force regeneration with `force_regenerate` flag |
| `admin/generate_link_qr.php` | Added Logic | Delete old QR before regenerating |

---

## ✅ Benefits

1. **Flexibility:** Admins can regenerate QR codes anytime
2. **Course Code Updates:** QR automatically updates when course code changes
3. **Error Recovery:** Can fix corrupted or missing QR codes
4. **User-Friendly:** Simple one-click operation with confirmation
5. **Real-time Feedback:** Loading states and toast notifications
6. **Cache Handling:** Cache buster ensures new QR displays immediately

---

## 🚀 Next Steps

1. ✅ Test regeneration with different course codes
2. ✅ Verify old QR files are deleted
3. ✅ Confirm new QR codes work when scanned
4. ✅ Test with courses that don't have QR codes yet
5. ✅ Verify timestamp updates correctly

---

**Status:** ✅ COMPLETE - QR code regeneration feature fully implemented and ready to use!
