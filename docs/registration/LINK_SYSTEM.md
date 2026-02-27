# Registration Link System Documentation

## Overview
Automatic registration link generation system that creates unique URLs for each course, making it easy to share direct registration links with students.

## Features

### 1. Auto-Generated Links
- **Format:** `https://yoursite.com/register.php?course=CourseName`
- Automatically created when adding a new course
- Updates automatically when course name changes
- URL-encoded for special characters

### 2. Custom Link Override
- Option to use custom registration URLs
- Useful for:
  - External registration systems
  - Shortened URLs (bit.ly, etc.)
  - Custom landing pages
  - Third-party integrations

### 3. Link Management Features
- **Copy to Clipboard:** One-click copy functionality
- **Open in New Tab:** Test links directly
- **QR Code Generation:** Create scannable QR codes
- **Share Function:** Native mobile sharing
- **Bulk Download:** Export all links as text file

## How It Works

### Database Schema
```sql
ALTER TABLE courses 
ADD COLUMN registration_link VARCHAR(500),
ADD COLUMN auto_generate_link TINYINT(1) DEFAULT 1;
```

- `registration_link`: Stores the full URL
- `auto_generate_link`: 1 = Auto-generate, 0 = Use custom

### Link Generation Logic

#### Auto-Generated (Default)
```php
$base_url = "https://nielitbbsr.org";
$registration_link = $base_url . "/register.php?course=" . urlencode($course_name);
```

**Example:**
- Course: "Python Programming Internship"
- Link: `https://nielitbbsr.org/register.php?course=Python+Programming+Internship`

#### Custom Link
```php
$registration_link = $_POST['custom_link']; // Admin provided
```

**Example:**
- Custom: `https://nielitbbsr.org/python-internship-2025`

## Admin Interface

### Adding a Course with Link

1. **Navigate:** Admin Dashboard → Manage Courses
2. **Click:** "Add New Course" button
3. **Fill Course Details:**
   - Course Name
   - Course Code
   - Type, Center, Duration, Fees
4. **Link Settings:**
   - ✅ **Auto-generate** (Default - Recommended)
     - Link preview shown in real-time
   - ⬜ **Custom Link**
     - Uncheck auto-generate
     - Enter custom URL

5. **Save:** Link is generated and stored

### Viewing All Links

**Page:** `admin/course_links.php`

Features:
- 📋 **Card View:** All courses with links
- 📋 **Copy Button:** One-click copy
- 🔗 **Open Link:** Test in new tab
- 📱 **QR Code:** Generate scannable code
- 📤 **Share:** Native sharing (mobile)
- 💾 **Download All:** Export all links

### Editing Links

1. **Navigate:** Manage Courses
2. **Click:** Edit button on course
3. **Modify Link Settings:**
   - Toggle auto-generate on/off
   - Update custom link if needed
4. **Save:** Link updates automatically

## Usage Examples

### Example 1: Standard Course
```
Course Name: Drone Boot Camp 21
Course Code: DBC21
Auto-Generate: ✅ Yes

Generated Link:
https://nielitbbsr.org/register.php?course=Drone+Boot+Camp+21

Student Experience:
1. Click link
2. Form pre-filled with course selection
3. Complete registration
4. Receive ID: NIELIT/2025/DBC21/0001
```

### Example 2: Custom Landing Page
```
Course Name: Python Programming Internship
Course Code: PPI
Auto-Generate: ⬜ No
Custom Link: https://nielitbbsr.org/python-internship

Student Experience:
1. Click custom link
2. Redirected to custom landing page
3. Click "Register Now"
4. Complete registration
5. Receive ID: NIELIT/2025/PPI/0001
```

### Example 3: QR Code Campaign
```
Course Name: AI Workshop 2025
Course Code: AIW25
Auto-Generate: ✅ Yes

Marketing Use:
1. Generate QR code from admin panel
2. Print on posters/flyers
3. Students scan QR code
4. Direct to registration page
5. Instant registration
```

## Link Sharing Methods

### 1. Direct Copy-Paste
```
Admin copies link → Shares via:
- Email
- WhatsApp
- SMS
- Social Media
```

### 2. QR Code
```
Admin generates QR → Uses in:
- Printed materials
- Digital displays
- Presentations
- Websites
```

### 3. Native Share (Mobile)
```
Admin clicks Share → Options:
- WhatsApp
- Email
- Messages
- Social Apps
```

### 4. Bulk Export
```
Admin downloads all links → Uses for:
- Email campaigns
- Documentation
- Partner sharing
- Backup
```

## Link Format Examples

### Auto-Generated Links
```
https://nielitbbsr.org/register.php?course=Python+Programming+Internship
https://nielitbbsr.org/register.php?course=Drone+Boot+Camp+21
https://nielitbbsr.org/register.php?course=Web+Development+Bootcamp
https://nielitbbsr.org/register.php?course=Data+Science+Workshop
```

### Custom Links
```
https://nielitbbsr.org/ppi-2025
https://nielitbbsr.org/drone-bootcamp
https://nielitbbsr.org/courses/web-dev
https://bit.ly/nielit-python
```

## Benefits

### For Admins
- ✅ No manual link creation
- ✅ Consistent URL structure
- ✅ Easy to share and track
- ✅ Flexible custom options
- ✅ QR code generation
- ✅ Bulk management

### For Students
- ✅ Direct course access
- ✅ Pre-filled forms
- ✅ Faster registration
- ✅ Mobile-friendly
- ✅ Scannable QR codes

### For Marketing
- ✅ Shareable links
- ✅ Trackable URLs
- ✅ Print-ready QR codes
- ✅ Social media ready
- ✅ Professional appearance

## Technical Details

### URL Encoding
```php
// Handles special characters
urlencode("Python Programming Internship")
// Result: Python+Programming+Internship

urlencode("C++ Programming")
// Result: C%2B%2B+Programming
```

### Link Validation
```php
// Check if link is accessible
$headers = @get_headers($registration_link);
$is_valid = $headers && strpos($headers[0], '200');
```

### QR Code Generation
```javascript
// Using QRCode.js library
new QRCode(element, {
    text: link,
    width: 150,
    height: 150
});
```

## Security Considerations

### 1. Link Protection
- Links are public but require form completion
- Aadhar verification prevents duplicates
- Payment verification required

### 2. Custom Link Validation
```php
// Validate custom URLs
if (!filter_var($custom_link, FILTER_VALIDATE_URL)) {
    $error = "Invalid URL format";
}
```

### 3. Access Control
- Only admins can create/edit links
- Link management requires authentication
- Audit trail for link changes

## Troubleshooting

### Issue: Link not working
**Solution:**
1. Check course status is 'active'
2. Verify URL is properly encoded
3. Test link in incognito mode
4. Check server configuration

### Issue: QR code not generating
**Solution:**
1. Ensure QRCode.js library is loaded
2. Check browser console for errors
3. Verify link is valid URL
4. Try different browser

### Issue: Copy function not working
**Solution:**
1. Check browser clipboard permissions
2. Use HTTPS (required for clipboard API)
3. Try manual copy-paste
4. Update browser

## Best Practices

### 1. Link Naming
- Keep course names clear and descriptive
- Avoid special characters if possible
- Use consistent naming convention

### 2. Custom Links
- Use short, memorable URLs
- Include course identifier
- Maintain consistency

### 3. QR Codes
- Test before printing
- Use high contrast colors
- Ensure adequate size (min 2cm x 2cm)
- Include text link as backup

### 4. Link Management
- Regular link testing
- Update broken links promptly
- Archive old course links
- Document custom links

## Integration Examples

### Email Template
```html
<p>Dear Student,</p>
<p>Register for our Python Programming Internship:</p>
<a href="https://nielitbbsr.org/register.php?course=Python+Programming+Internship">
    Click here to register
</a>
```

### WhatsApp Message
```
🎓 NIELIT Bhubaneswar

Register for Python Programming Internship (PPI)

📝 Direct Registration Link:
https://nielitbbsr.org/register.php?course=Python+Programming+Internship

Limited seats available!
```

### Social Media Post
```
🚀 New Course Alert!

Python Programming Internship
📅 Starting Soon
💰 Affordable Fees
🎓 Industry Certificate

Register Now: [Link]
#NIELIT #Python #Internship
```

## Future Enhancements

1. **Link Analytics**
   - Track clicks
   - Conversion rates
   - Popular courses

2. **Short URL Service**
   - Integrated URL shortener
   - Custom branded links
   - Click tracking

3. **Email Integration**
   - Send links via email
   - Bulk email campaigns
   - Automated reminders

4. **SMS Integration**
   - Send links via SMS
   - Bulk SMS campaigns
   - Registration confirmations

---

**Last Updated:** February 10, 2026
**Version:** 2.0
**Status:** Production Ready
