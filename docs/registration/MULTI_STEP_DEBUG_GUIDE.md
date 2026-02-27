# Multi-Step Form Debug Guide

## Changes Made

### 1. **Forced Level 1 Visibility**
Changed level1 div to use `!important` to ensure it's always visible:
```html
<div class="registration-level-section" id="level1" style="display: block !important;">
```

### 2. **Enhanced Debug Logging**
Added comprehensive console.log statements to track:
- DOM Content Loaded event
- All level elements (found/not found)
- Display properties of each level
- Form sections inside each level
- Button elements (found/not found)
- Button click events
- Validation results

## How to Test

### Step 1: Clear Browser Cache
**IMPORTANT:** Clear your browser cache completely:
- **Chrome/Edge:** Press `Ctrl + Shift + Delete`, select "All time", check "Cached images and files", click "Clear data"
- **Firefox:** Press `Ctrl + Shift + Delete`, select "Everything", check "Cache", click "Clear Now"

### Step 2: Hard Refresh the Page
After clearing cache, do a hard refresh:
- **Windows:** `Ctrl + F5` or `Ctrl + Shift + R`
- **Mac:** `Cmd + Shift + R`

### Step 3: Open Browser Console
Press `F12` to open Developer Tools, then click on the "Console" tab.

### Step 4: Check Console Output
You should see detailed debug output like:
```
=== MULTI-STEP FORM DEBUG ===
DOM Content Loaded - Starting initialization
Level 1 element: FOUND
Level 1 current display: block
Level 2 element: FOUND
Level 2 current display: none
Level 3 element: FOUND
Level 3 current display: none
...
=== Initialization complete ===
```

## What to Look For

### ✅ Good Signs
- All level elements show "FOUND"
- Level 1 display shows "block"
- Form sections count > 0
- All buttons show "FOUND"
- No JavaScript errors in console

### ❌ Bad Signs
- Any element shows "NOT FOUND"
- JavaScript errors in red
- Level 1 display shows "none"
- Form sections count = 0

## Troubleshooting

### Issue: Form Fields Still Not Visible

**Solution 1: Check CSS Conflicts**
Open Developer Tools (F12), click "Elements" tab, find the `.form-section` elements and check their computed styles. Look for:
- `display: none`
- `visibility: hidden`
- `opacity: 0`
- `height: 0`

**Solution 2: Disable Browser Extensions**
Some browser extensions (ad blockers, privacy tools) can interfere with page rendering. Try:
1. Open browser in Incognito/Private mode
2. Test the registration page
3. If it works, disable extensions one by one to find the culprit

**Solution 3: Try Different Browser**
Test in a different browser (Chrome, Firefox, Edge) to rule out browser-specific issues.

### Issue: JavaScript Errors in Console

**Solution:** Take a screenshot of the error and share it. Common errors:
- `Uncaught ReferenceError: toast is not defined` - Toast library not loaded
- `Cannot read property 'style' of null` - Element not found
- `Unexpected token` - Syntax error in JavaScript

## Expected Behavior

### When Page Loads
1. Course info card visible at top
2. Progress indicator showing 3 steps (Step 1 active)
3. Level 1 form sections visible:
   - Course Selection (locked fields)
   - Personal Information (name, father's name, etc.)
4. Next button visible at bottom
5. Previous and Submit buttons hidden

### When Clicking Next
1. Validates all required fields in current level
2. Shows error if any field is empty
3. Moves to next level if validation passes
4. Updates progress indicator
5. Shows/hides appropriate buttons

## Console Commands for Manual Testing

Open browser console (F12) and try these commands:

### Check if level1 is visible:
```javascript
document.getElementById('level1').style.display
// Should return: "block"
```

### Check form sections:
```javascript
document.querySelectorAll('#level1 .form-section').length
// Should return: 2 (Course Selection + Personal Information)
```

### Force show level1:
```javascript
document.getElementById('level1').style.display = 'block';
document.getElementById('level1').style.visibility = 'visible';
document.getElementById('level1').style.opacity = '1';
```

### Check if buttons exist:
```javascript
console.log('Next:', !!document.getElementById('nextBtn'));
console.log('Prev:', !!document.getElementById('prevBtn'));
console.log('Submit:', !!document.getElementById('submitBtn'));
// All should return: true
```

## Next Steps

1. **Clear cache and hard refresh** (most important!)
2. **Open console and check debug output**
3. **Take screenshot of console** if issues persist
4. **Try incognito mode** to rule out extensions
5. **Try different browser** to rule out browser issues

## Contact Information

If issues persist after trying all troubleshooting steps, provide:
1. Screenshot of browser console (F12 → Console tab)
2. Screenshot of page showing the issue
3. Browser name and version
4. Operating system
5. Any error messages in red in the console
