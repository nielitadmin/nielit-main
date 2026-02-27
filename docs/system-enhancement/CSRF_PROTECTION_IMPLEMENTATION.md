# CSRF Protection Implementation

## Overview

CSRF (Cross-Site Request Forgery) protection has been implemented for all three management pages in the System Enhancement Module to prevent unauthorized form submissions.

## Implementation Details

### 1. Token Generation

Each management page generates a CSRF token at the top of the file (after `session_start()`):

```php
// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

This creates a unique 64-character hexadecimal token stored in the user's session.

### 2. Token Inclusion in Forms

All forms now include a hidden input field with the CSRF token:

```html
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
```

**Forms Protected:**
- **manage_centres.php**: Add Centre form, Edit Centre form, Toggle Status form
- **manage_themes.php**: Add Theme form, Edit Theme form, Activate Theme form
- **manage_homepage.php**: Add/Edit Content Section form

### 3. Token Validation on POST Requests

All POST request handlers validate the CSRF token before processing:

```php
// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = "Invalid request. Please try again.";
    $_SESSION['message_type'] = "danger";
    header('Location: manage_centres.php');
    exit();
}
```

### 4. AJAX Request Protection

AJAX requests in `manage_homepage.php` also include the CSRF token:

```javascript
body: 'action=reorder&order_data=' + encodeURIComponent(JSON.stringify(orderData)) + 
      '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
```

**AJAX Endpoints Protected:**
- Get section data (for editing)
- Reorder sections (drag-and-drop)
- Toggle section status

## Files Modified

1. **admin/manage_centres.php**
   - Added CSRF token generation
   - Added token to Add Centre form
   - Added token to Edit Centre form
   - Added token to Toggle Status JavaScript function
   - Added validation in POST handler

2. **admin/manage_themes.php**
   - Added CSRF token generation
   - Added token to Add Theme form
   - Added token to Edit Theme form
   - Added token to Activate Theme JavaScript function
   - Added validation in POST handler

3. **admin/manage_homepage.php**
   - Added CSRF token generation
   - Added token to Add/Edit Section form
   - Added token to all AJAX fetch calls (get_section, reorder, toggle_status)
   - Added validation in POST handler
   - Added validation in AJAX handler

## Security Benefits

1. **Prevents CSRF Attacks**: Malicious websites cannot submit forms to these pages because they don't have access to the session token
2. **Session-Based**: Each user session has a unique token
3. **Validation on Server**: All form submissions are validated server-side
4. **User-Friendly Error Messages**: Invalid requests show clear error messages

## Testing

To verify CSRF protection is working:

1. **Valid Submission Test**:
   - Log in as admin
   - Submit any form (add centre, edit theme, etc.)
   - Should work normally

2. **Invalid Token Test**:
   - Log in as admin
   - Open browser developer tools
   - Modify the `csrf_token` hidden field value
   - Submit the form
   - Should see error: "Invalid request. Please try again."

3. **Missing Token Test**:
   - Log in as admin
   - Remove the `csrf_token` hidden field using developer tools
   - Submit the form
   - Should see error: "Invalid request. Please try again."

## Compliance

This implementation validates **Requirements 11.3** from the System Enhancement Module specification:

> "WHEN processing form submissions, THE System SHALL validate CSRF tokens"

## Notes

- The CSRF token is generated once per session and reused for all requests
- The token is regenerated when the session expires or user logs out
- All three management pages use the same session token for consistency
- AJAX requests include the token in the request body
- Error messages are user-friendly and don't expose security details
