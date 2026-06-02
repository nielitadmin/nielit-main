# Session Start Warning Fix

## Issue
```
Notice: session_start(): Ignoring session_start() because a session is already active
```

Appeared on production: `nielitbhubaneswar.in/student/register.php` line 10

## Root Cause
1. `maintenance_check.php` was calling `session_start()` on line 18
2. `register.php` was including `maintenance_check.php` THEN calling `session_start()` again on line 10
3. This resulted in duplicate session_start() calls causing PHP notice warning

## Solution
Wrapped all `session_start()` calls with a check:

```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

## Files Modified
1. **includes/maintenance_check.php** - Line 18
2. **student/register.php** - Line 10

## Result
- ✅ No more session warnings
- ✅ Session starts only once per request
- ✅ Safe for all files that include maintenance_check.php

## Testing
Visit: `http://localhost/public_html/student/register.php?token=VALID_TOKEN`
- No session warnings should appear
- Page should load normally
