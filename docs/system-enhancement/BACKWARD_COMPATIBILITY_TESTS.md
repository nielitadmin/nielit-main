# Backward Compatibility Tests

## Overview

This document describes the backward compatibility testing for the System Enhancement Module. These tests ensure that existing features continue to work correctly after the database schema changes introduced by the Centre Management, Theme Customization, and Homepage Content Management features.

## Test Objectives

The backward compatibility tests validate:

1. **Schema Changes Integration** - New tables and columns integrate seamlessly with existing schema
2. **Course Management** - Existing course management features work with the new `centre_id` column
3. **Theme Fallback** - Application functions correctly when no active theme exists
4. **Homepage Fallback** - Homepage displays correctly when no database content exists
5. **Existing Features** - All existing queries and features continue to work

## Requirements Validated

- **Requirement 12.3**: Existing features work with new schema
- **Requirement 12.4**: Pages work without active theme (use defaults)
- **Requirement 12.5**: Homepage works without database content (show hardcoded content)

## Test Script

**Location**: `tests/test_backward_compatibility.php`

**Execution**:
```bash
# Command line
php tests/test_backward_compatibility.php

# Or via web browser
http://your-domain/tests/test_backward_compatibility.php
```

## Test Groups

### Group 1: Schema Verification

Verifies that all schema changes have been applied correctly.

| Test | Description | Expected Result |
|------|-------------|-----------------|
| 1.1 | centre_id column exists in courses | Column found |
| 1.2 | centres table exists | Table found |
| 1.3 | themes table exists | Table found |
| 1.4 | homepage_content table exists | Table found |

**Purpose**: Ensures migration scripts have run successfully.

### Group 2: Course Management with centre_id

Tests that course management works correctly with the new `centre_id` column, including NULL values.

| Test | Description | Expected Result |
|------|-------------|-----------------|
| 2.1 | Query courses with NULL centre_id | Query succeeds, returns courses |
| 2.2 | Query courses with assigned centre_id | Query succeeds, returns courses |
| 2.3 | LEFT JOIN courses with centres | Query succeeds, handles NULL correctly |
| 2.4 | Insert course with NULL centre_id | Insert succeeds |
| 2.5 | Update course centre_id from NULL to value | Update succeeds |

**Purpose**: Validates that the `centre_id` column is properly nullable and doesn't break existing course operations.

**Key Scenarios**:
- Courses can exist without a centre assignment (NULL centre_id)
- Queries handle NULL centre_id gracefully
- LEFT JOIN operations work correctly with NULL values
- Courses can be created without specifying a centre
- Centre assignments can be added later

### Group 3: Theme Loader Fallback

Tests that the theme loader returns default values when no active theme exists in the database.

| Test | Description | Expected Result |
|------|-------------|-----------------|
| 3.1 | Theme loader with no active theme | Returns default theme |
| 3.2 | Default theme structure | Has all required fields |
| 3.3 | CSS injection with default theme | CSS variables injected correctly |

**Purpose**: Ensures the application continues to function with proper styling even when no theme is configured.

**Default Theme Values**:
```php
[
    'theme_name' => 'Default Theme',
    'primary_color' => '#0d47a1',
    'secondary_color' => '#1565c0',
    'accent_color' => '#ffc107',
    'logo_path' => 'assets/images/bhubaneswar_logo.png',
    'favicon_path' => 'assets/images/favicon.ico'
]
```

**Test Process**:
1. Temporarily deactivate all themes
2. Call `loadActiveTheme()` with force reload
3. Verify default theme is returned
4. Verify all required fields are present
5. Test CSS injection works correctly
6. Restore original theme state

### Group 4: Homepage Content Fallback

Tests that the homepage handles missing or empty content gracefully.

| Test | Description | Expected Result |
|------|-------------|-----------------|
| 4.1 | Query empty homepage_content | Query succeeds, returns 0 rows |
| 4.2 | Empty content array handling | Application handles empty array |

**Purpose**: Ensures the homepage displays fallback content when no database content exists.

**Expected Behavior**:
- When `homepage_content` table is empty or all sections are inactive
- Application should detect empty result set
- Display hardcoded fallback content
- No errors or blank pages

**Implementation Pattern**:
```php
$content_sections = getAllContentSections($conn, true);

if (empty($content_sections) || $content_sections->num_rows === 0) {
    // Display hardcoded fallback content
    include 'includes/default_homepage_content.php';
} else {
    // Display database content
    while ($section = $content_sections->fetch_assoc()) {
        renderSection($section);
    }
}
```

### Group 5: Existing Features Compatibility

Tests that existing features continue to work without modification.

| Test | Description | Expected Result |
|------|-------------|-----------------|
| 5.1 | Basic course query | Query succeeds |
| 5.2 | Course filtering by status | Query succeeds |
| 5.3 | Course count query | Query succeeds |

**Purpose**: Validates that schema changes don't break existing queries and features.

**Key Validations**:
- SELECT queries work without specifying centre_id
- WHERE clauses work on existing columns
- COUNT and aggregate functions work correctly
- No breaking changes to existing API

## Running the Tests

### Prerequisites

1. Database connection configured in `config/database.php`
2. System Enhancement Module migrations completed
3. PHP 7.4+ with mysqli extension

### Execution Steps

1. **Via Command Line** (Recommended):
   ```bash
   cd /path/to/project
   php tests/test_backward_compatibility.php
   ```

2. **Via Web Browser**:
   ```
   http://your-domain/tests/test_backward_compatibility.php
   ```

### Expected Output

```
=== Backward Compatibility Test Suite ===

Testing System Enhancement Module backward compatibility
Validates Requirements: 12.3, 12.4, 12.5

--- Test Group 1: Schema Verification ---

Test 1.1: Verify centre_id column exists in courses table...
✓ PASS: 1.1 centre_id column exists
  Column found in courses table

Test 1.2: Verify centres table exists...
✓ PASS: 1.2 centres table exists
  Table found

[... additional tests ...]

============================================================
=== Test Summary ===
============================================================

Total Tests: 15
Passed: 15
Failed: 0

✓ All backward compatibility tests passed!
The System Enhancement Module maintains full backward compatibility.

============================================================
```

## Test Results Interpretation

### All Tests Pass

If all tests pass, the System Enhancement Module is fully backward compatible:
- Existing features work correctly
- Schema changes integrate seamlessly
- Fallback mechanisms function properly
- No breaking changes introduced

### Some Tests Fail

If tests fail, investigate the following:

**Schema Verification Failures**:
- Run migration scripts: `php migrations/install_system_enhancement.php`
- Check database permissions
- Verify database connection

**Course Management Failures**:
- Check foreign key constraints
- Verify NULL handling in queries
- Review course table structure

**Theme Loader Failures**:
- Verify `includes/theme_loader.php` exists
- Check `getDefaultTheme()` function
- Review theme table structure

**Homepage Content Failures**:
- Verify `homepage_content` table exists
- Check query syntax
- Review fallback content implementation

**Existing Features Failures**:
- Review schema changes for breaking modifications
- Check for missing columns or tables
- Verify data migration completed

## Maintenance

### When to Run Tests

Run backward compatibility tests:
- After initial System Enhancement Module installation
- After any schema modifications
- Before deploying to production
- After database migrations
- When troubleshooting compatibility issues

### Updating Tests

When adding new features:
1. Add new test cases to appropriate test group
2. Update this documentation
3. Ensure tests validate backward compatibility
4. Run full test suite before committing

### Test Data Cleanup

The test script automatically cleans up:
- Test courses created during testing
- Temporary theme state changes
- Temporary content state changes

No manual cleanup required.

## Troubleshooting

### Common Issues

**Issue**: "Table doesn't exist" errors
- **Solution**: Run migration scripts first
- **Command**: `php migrations/install_system_enhancement.php`

**Issue**: "Column not found" errors
- **Solution**: Verify schema changes applied
- **Check**: `SHOW COLUMNS FROM courses LIKE 'centre_id'`

**Issue**: Theme loader returns NULL
- **Solution**: Check theme_loader.php is included
- **Verify**: `require_once 'includes/theme_loader.php'`

**Issue**: Homepage content query fails
- **Solution**: Verify homepage_content table exists
- **Check**: `SHOW TABLES LIKE 'homepage_content'`

### Debug Mode

To enable detailed error output, modify the test script:

```php
// At the top of test_backward_compatibility.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
```

## Related Documentation

- [System Enhancement Module Requirements](../../.kiro/specs/system-enhancement-module/requirements.md)
- [System Enhancement Module Design](../../.kiro/specs/system-enhancement-module/design.md)
- [Migration Guide](../../migrations/README.md)
- [Audit Logging Tests](AUDIT_LOGGING.md)

## Conclusion

The backward compatibility test suite ensures that the System Enhancement Module integrates seamlessly with existing functionality. All existing features continue to work correctly, and the application gracefully handles missing or empty configuration data through well-defined fallback mechanisms.

**Key Takeaways**:
- ✓ Schema changes are non-breaking
- ✓ NULL values handled correctly
- ✓ Default themes provide fallback
- ✓ Homepage shows fallback content
- ✓ Existing queries work unchanged

The module successfully validates Requirements 12.3, 12.4, and 12.5, ensuring a smooth upgrade path for existing installations.
