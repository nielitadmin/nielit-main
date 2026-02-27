# System Enhancement Module - User Guide

## Overview

The System Enhancement Module provides three powerful administrative capabilities for the NIELIT Bhubaneswar Student Management System:

1. **Centre Management** - Manage multiple training centres and associate courses with specific locations
2. **Theme Customization** - Customize the visual appearance of the application (colors, logos)
3. **Homepage Content Management** - Edit homepage content dynamically without code modifications

This guide provides comprehensive instructions for administrators to effectively use these features.

## Quick Start

### Accessing the Management Interfaces

All system enhancement features are accessible from the admin dashboard:

1. Log in to the admin panel at `/admin/login_new.php`
2. Navigate to the **System Settings** section in the sidebar
3. Select the feature you want to manage:
   - **Manage Centres** - Centre management interface
   - **Manage Themes** - Theme customization interface
   - **Manage Homepage** - Homepage content management interface

### Prerequisites

- Admin account with appropriate permissions
- Modern web browser (Chrome, Firefox, Edge, Safari)
- Basic understanding of HTML for homepage content editing

## Feature Guides

For detailed instructions on each feature, refer to the specific guides:

### [Centre Management Guide](CENTRE_MANAGEMENT_GUIDE.md)
Learn how to:
- Add and edit training centres
- Assign centres to courses
- Filter courses by centre
- Manage centre information

### [Theme Customization Guide](THEME_CUSTOMIZATION_GUIDE.md)
Learn how to:
- Create and edit themes
- Upload logos and favicons
- Customize colors
- Activate themes
- Preview themes before activation

### [Homepage Content Management Guide](HOMEPAGE_CONTENT_GUIDE.md)
Learn how to:
- Add and edit content sections
- Reorder sections
- Use the content editor
- Activate/deactivate sections
- Preview content changes

## Best Practices

### General Guidelines

1. **Test Before Activating** - Always preview themes and content before making them live
2. **Keep Backups** - Note current settings before making major changes
3. **Use Descriptive Names** - Give centres, themes, and content sections clear, descriptive names
4. **Regular Updates** - Keep homepage content fresh and up-to-date
5. **Consistent Branding** - Maintain consistent colors and logos across themes

### Security Recommendations

1. **Strong Passwords** - Use strong passwords for admin accounts
2. **Regular Logout** - Always log out when finished with administrative tasks
3. **Verify Changes** - Review changes on the public site after activation
4. **Limit Access** - Only grant admin access to trusted personnel

### Performance Tips

1. **Optimize Images** - Compress logos and images before uploading (recommended: under 500KB)
2. **Clean Content** - Remove unnecessary HTML formatting from content sections
3. **Deactivate Unused** - Deactivate centres, themes, or content sections that are no longer needed
4. **Regular Cleanup** - Periodically review and remove outdated content

## Common Tasks

### Updating Site Branding

1. Navigate to **Manage Themes**
2. Create a new theme or edit existing theme
3. Upload new logo and favicon
4. Update brand colors
5. Preview the theme
6. Activate when satisfied

### Adding a New Training Centre

1. Navigate to **Manage Centres**
2. Click **Add New Centre**
3. Fill in centre details (name, code, address, contact)
4. Save the centre
5. Assign courses to the new centre in **Manage Courses**

### Updating Homepage Announcements

1. Navigate to **Manage Homepage**
2. Find the announcement section or create new one
3. Edit the content using the editor
4. Preview the changes
5. Save and activate

## Troubleshooting

### Common Issues

**Issue: Changes not appearing on the website**
- Solution: Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)
- Solution: Verify the item is marked as "Active"
- Solution: Check that you saved the changes

**Issue: Logo not displaying**
- Solution: Verify the image file is a valid format (JPG, PNG, GIF, SVG)
- Solution: Check that the file size is under 2MB
- Solution: Ensure the theme is activated

**Issue: Cannot upload files**
- Solution: Check file size (must be under 2MB)
- Solution: Verify file type is allowed
- Solution: Ensure uploads directory has write permissions

**Issue: Colors not applying**
- Solution: Verify color codes are in hexadecimal format (#RRGGBB)
- Solution: Clear browser cache
- Solution: Check that the theme is activated

**Issue: Content not saving**
- Solution: Check for validation errors in the form
- Solution: Ensure all required fields are filled
- Solution: Verify you have admin permissions

### Getting Help

If you encounter issues not covered in this guide:

1. Check the specific feature guide for detailed instructions
2. Review error messages carefully - they often indicate the problem
3. Contact your system administrator
4. Check the system logs for technical details

## Appendix

### File Upload Specifications

**Logos and Favicons:**
- Allowed formats: JPG, PNG, GIF, SVG
- Maximum size: 2MB
- Recommended dimensions: 
  - Logo: 200x60 pixels
  - Favicon: 32x32 pixels

**Image Optimization Tools:**
- TinyPNG (https://tinypng.com)
- ImageOptim (https://imageoptim.com)
- Squoosh (https://squoosh.app)

### Color Code Reference

Colors must be specified in hexadecimal format: `#RRGGBB`

Examples:
- Blue: `#0d47a1`
- Red: `#d32f2f`
- Green: `#388e3c`
- Orange: `#f57c00`
- Purple: `#7b1fa2`

**Color Picker Tools:**
- HTML Color Picker (https://htmlcolorcodes.com)
- Adobe Color (https://color.adobe.com)
- Coolors (https://coolors.co)

### HTML Tags Allowed in Content

For security, only the following HTML tags are allowed in homepage content:

- Text formatting: `<p>`, `<br>`, `<strong>`, `<em>`, `<u>`
- Headings: `<h1>`, `<h2>`, `<h3>`, `<h4>`, `<h5>`, `<h6>`
- Lists: `<ul>`, `<ol>`, `<li>`
- Links: `<a>`
- Images: `<img>`
- Containers: `<div>`, `<span>`

Dangerous tags (like `<script>`) are automatically removed for security.

### Keyboard Shortcuts

**Content Editor:**
- Bold: `Ctrl+B` (Windows) / `Cmd+B` (Mac)
- Italic: `Ctrl+I` (Windows) / `Cmd+I` (Mac)
- Underline: `Ctrl+U` (Windows) / `Cmd+U` (Mac)
- Save: `Ctrl+S` (Windows) / `Cmd+S` (Mac)

**General:**
- Refresh page: `F5` or `Ctrl+R` (Windows) / `Cmd+R` (Mac)
- Hard refresh: `Ctrl+F5` (Windows) / `Cmd+Shift+R` (Mac)

## Version History

- **Version 1.0** (2024) - Initial release
  - Centre Management
  - Theme Customization
  - Homepage Content Management

---

**Document Version:** 1.0  
**Last Updated:** 2024  
**For:** NIELIT Bhubaneswar Student Management System
