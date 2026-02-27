# Homepage Content Management Guide

## Overview

The Homepage Content Management feature allows administrators to edit homepage content dynamically without modifying code. You can create, edit, reorder, and manage content sections that appear on the public-facing homepage.

## Accessing Homepage Management

1. Log in to the admin panel
2. Navigate to **System Settings** in the sidebar
3. Click **Manage Homepage**

## Understanding Content Sections

A **content section** is a block of content displayed on the homepage. Each section has:

- **Section Key** - Unique identifier (e.g., "welcome_banner", "latest_news")
- **Section Title** - Display title shown to visitors
- **Section Content** - The actual content (text, HTML)
- **Section Type** - Category of content (banner, announcement, text block, etc.)
- **Display Order** - Position on the page (lower numbers appear first)
- **Status** - Active or Inactive

### Section Types

**Banner:**
- Large, prominent sections at the top of the page
- Used for hero images, welcome messages, or main announcements
- Typically full-width with eye-catching design

**Announcement:**
- Important notices or updates
- Displayed prominently to catch visitor attention
- Used for deadlines, new courses, or urgent information

**Featured Course:**
- Highlights specific courses or programs
- Showcases popular or new offerings
- Includes course details and enrollment information

**Text Block:**
- General content sections
- Used for about information, descriptions, or explanations
- Flexible formatting with rich text editor

**Image Block:**
- Image-focused content sections
- Used for galleries, infographics, or visual content
- Combines images with optional text

## Creating a New Content Section

### Step-by-Step Instructions

1. **Open the Add Section Form**
   - Click the **Add New Section** button at the top of the page
   - A modal form will appear

2. **Enter Section Key**
   - Create a unique identifier (e.g., "welcome_message", "admission_notice")
   - Use lowercase letters, numbers, and underscores only
   - Length: 3-50 characters
   - Must be unique across all sections
   - This is for internal reference only

3. **Enter Section Title**
   - This is the heading displayed to visitors
   - Use clear, descriptive titles
   - Examples: "Welcome to NIELIT", "Latest Announcements", "Featured Courses"

4. **Select Section Type**
   - Choose from dropdown: Banner, Announcement, Featured Course, Text Block, or Image Block
   - Type determines how the section is styled and positioned

5. **Set Display Order**
   - Enter a number to control position (0, 1, 2, 3, etc.)
   - Lower numbers appear first
   - Sections are displayed in ascending order
   - You can use gaps (0, 10, 20) to make reordering easier

6. **Enter Section Content**
   - Use the rich text editor to create your content
   - Format text, add links, insert images
   - Use the toolbar for formatting options
   - Preview as you type

7. **Save the Section**
   - Click **Save Section**
   - If successful, you'll see a success message
   - The new section appears in the sections list
   - Section is created as active by default

### Example: Creating a Welcome Banner

```
Section Key: welcome_banner
Section Title: Welcome to NIELIT Bhubaneswar
Section Type: Banner
Display Order: 0
Section Content:
---
<h2>Welcome to NIELIT Bhubaneswar</h2>
<p>National Institute of Electronics & Information Technology (NIELIT) 
Bhubaneswar is a premier institution offering quality IT education and 
training programs.</p>
<p><strong>Admissions Open for 2024!</strong></p>
```

### Section Key Guidelines

**Valid Section Keys:**
- `welcome_banner` - Welcome message
- `latest_news` - News section
- `admission_2024` - Admission information
- `featured_courses` - Course highlights
- `contact_info` - Contact details

**Invalid Section Keys:**
- `Welcome Banner` - No spaces or capitals
- `latest-news` - No hyphens (use underscores)
- `ad` - Too short (minimum 3 characters)
- `this_is_a_very_long_section_key_that_exceeds_fifty_characters` - Too long

## Editing an Existing Content Section

### Step-by-Step Instructions

1. **Locate the Section**
   - Find the section in the sections list
   - Sections are ordered by display_order

2. **Open the Edit Form**
   - Click the **Edit** button (pencil icon) next to the section
   - The edit modal will appear with current values pre-filled

3. **Modify Section Details**
   - Update any fields you want to change
   - Edit content using the rich text editor
   - Change section type if needed
   - Adjust display order

4. **Save Changes**
   - Click **Update Section**
   - If successful, you'll see a success message
   - Changes appear immediately on the homepage (if section is active)

### What You Can Edit

- Section title
- Section content
- Section type
- Display order
- Active status

### What You Cannot Edit

- Section key (unique identifier)
- Section ID (automatically assigned)
- Creation timestamp
- Last modified timestamp (automatically updated)

## Using the Content Editor

### Editor Toolbar

The rich text editor provides formatting tools:

**Text Formatting:**
- **Bold** - Make text bold (Ctrl+B)
- *Italic* - Make text italic (Ctrl+I)
- <u>Underline</u> - Underline text (Ctrl+U)
- Headings - H1, H2, H3, H4, H5, H6

**Lists:**
- Bulleted list - Unordered list
- Numbered list - Ordered list

**Links:**
- Insert link - Add hyperlinks to text
- Remove link - Remove hyperlinks

**Images:**
- Insert image - Add images (use image URL)

**Alignment:**
- Align left
- Align center
- Align right
- Justify

**Other:**
- Undo - Undo last change (Ctrl+Z)
- Redo - Redo last change (Ctrl+Y)
- Clear formatting - Remove all formatting

### Formatting Tips

**Headings:**
```html
<h2>Main Section Heading</h2>
<h3>Subsection Heading</h3>
<p>Regular paragraph text.</p>
```

**Bold and Italic:**
```html
<p>This is <strong>bold text</strong> and this is <em>italic text</em>.</p>
```

**Lists:**
```html
<ul>
  <li>First item</li>
  <li>Second item</li>
  <li>Third item</li>
</ul>
```

**Links:**
```html
<p>Visit our <a href="/courses.php">courses page</a> for more information.</p>
```

**Images:**
```html
<img src="/assets/images/banner.jpg" alt="NIELIT Campus">
```

### Allowed HTML Tags

For security, only safe HTML tags are allowed:

**Allowed:**
- Text: `<p>`, `<br>`, `<strong>`, `<em>`, `<u>`
- Headings: `<h1>`, `<h2>`, `<h3>`, `<h4>`, `<h5>`, `<h6>`
- Lists: `<ul>`, `<ol>`, `<li>`
- Links: `<a>`
- Images: `<img>`
- Containers: `<div>`, `<span>`

**Not Allowed (Automatically Removed):**
- Scripts: `<script>`
- Iframes: `<iframe>`
- Forms: `<form>`, `<input>`
- Dangerous attributes: `onclick`, `onerror`, etc.

### Content Best Practices

1. **Keep It Concise** - Visitors scan, they don't read everything
2. **Use Headings** - Break content into sections with clear headings
3. **Add Links** - Link to relevant pages for more information
4. **Use Lists** - Bullet points are easier to read than paragraphs
5. **Include CTAs** - Add clear calls-to-action (e.g., "Apply Now", "Learn More")
6. **Check Spelling** - Proofread before saving
7. **Test Links** - Verify all links work correctly

## Reordering Content Sections

### Why Reorder?

Reordering allows you to:
- Change the sequence of content on the homepage
- Prioritize important information
- Organize content logically
- Respond to changing priorities

### Method 1: Drag and Drop

1. **Enable Drag Mode**
   - Sections list supports drag-and-drop reordering
   - Look for drag handles (⋮⋮) on the left of each section

2. **Drag Section**
   - Click and hold the drag handle
   - Drag the section up or down
   - Drop it in the desired position

3. **Save Order**
   - Order is saved automatically
   - Changes apply immediately to the homepage

### Method 2: Edit Display Order

1. **Edit Section**
   - Click **Edit** on the section you want to move

2. **Change Display Order**
   - Update the Display Order number
   - Lower numbers appear first
   - Example: Change from 5 to 1 to move to top

3. **Save Changes**
   - Click **Update Section**
   - Section moves to new position

### Display Order Examples

**Initial Order:**
```
0 - Welcome Banner
1 - Latest News
2 - Featured Courses
3 - About Us
4 - Contact Information
```

**After Moving "Featured Courses" to Top:**
```
0 - Featured Courses
1 - Welcome Banner
2 - Latest News
3 - About Us
4 - Contact Information
```

**Using Gaps for Flexibility:**
```
0 - Welcome Banner
10 - Latest News
20 - Featured Courses
30 - About Us
40 - Contact Information
```
*Gaps make it easy to insert new sections without renumbering everything*

## Activating and Deactivating Sections

### Why Deactivate?

Deactivating a section is useful when:
- Content is outdated or no longer relevant
- You want to temporarily hide content
- Testing new content before making it live
- Seasonal content that's not currently applicable

**Important:** Deactivating does NOT delete the section. You can reactivate it anytime.

### How to Deactivate a Section

1. Find the section in the list
2. Click the **Deactivate** button (toggle switch)
3. The section status changes to "Inactive"
4. Section is immediately hidden from the homepage

### How to Reactivate a Section

1. Find the inactive section in the list
2. Click the **Activate** button (toggle switch)
3. The section status changes to "Active"
4. Section is immediately visible on the homepage

### Effects of Deactivation

**What Happens:**
- Section is hidden from public homepage
- Section remains in the database
- Section can be edited while inactive
- Section can be reactivated anytime

**What Doesn't Happen:**
- Section data is NOT deleted
- Display order is NOT changed
- Section settings are NOT lost

## Previewing Content

### Why Preview?

Previewing allows you to:
- See how content will look on the homepage
- Check formatting and layout
- Verify links and images
- Make adjustments before publishing

### How to Preview

1. **Preview Individual Section**
   - Click the **Preview** button (eye icon) next to a section
   - A preview modal appears showing how the section will look
   - Close preview when done

2. **Preview All Content**
   - Click **Preview Homepage** button at the top
   - Opens a preview of the entire homepage with all active sections
   - Shows sections in display order
   - Close preview when done

### What Preview Shows

The preview displays:
- Section title and content
- Applied formatting and styles
- Images and links
- Section type styling
- Approximate layout on homepage

**Note:** Preview is an approximation. Always verify on the actual homepage after saving.

## Managing Content Sections

### Viewing Sections List

The sections list displays:

- **Section Title** - Display title
- **Section Key** - Unique identifier
- **Type** - Section type badge
- **Order** - Display order number
- **Status** - Active (green) or Inactive (red)
- **Actions** - Edit, Preview, Activate/Deactivate, Delete buttons

### Searching and Filtering

**Search:**
- Use the search box to filter sections
- Search by title, key, or type
- Results update as you type

**Filter by Type:**
- Use the type filter dropdown
- Show only specific section types
- Select "All Types" to show everything

**Filter by Status:**
- Use the status filter dropdown
- Show only active or inactive sections
- Select "All" to show everything

### Deleting Sections

**Warning:** Deleting a section permanently removes it. This cannot be undone.

**When to Delete:**
- Section is no longer needed
- Content is permanently outdated
- Section was created by mistake

**When NOT to Delete:**
- Content might be needed later (deactivate instead)
- Seasonal content (deactivate until next season)
- Testing purposes (deactivate while testing)

**How to Delete:**
1. Find the section in the list
2. Click the **Delete** button (trash icon)
3. Confirm the deletion
4. Section is permanently removed

## Common Scenarios

### Scenario 1: Adding an Admission Announcement

**Task:** Add urgent announcement about admission deadline

**Steps:**
1. Click **Add New Section**
2. Fill in details:
   - Section Key: `admission_deadline_2024`
   - Section Title: `Admission Deadline Extended!`
   - Section Type: Announcement
   - Display Order: 1 (near top)
   - Content: "The admission deadline for 2024 batch has been extended to March 31, 2024. Apply now!"
3. Save section
4. Verify on homepage
5. When deadline passes, deactivate the section

### Scenario 2: Featuring a New Course

**Task:** Highlight new web development course on homepage

**Steps:**
1. Click **Add New Section**
2. Fill in details:
   - Section Key: `featured_web_dev_course`
   - Section Title: `New Course: Advanced Web Development`
   - Section Type: Featured Course
   - Display Order: 15
   - Content: Course description, duration, fees, enrollment link
3. Save section
4. Preview to verify appearance
5. Activate section

### Scenario 3: Updating Welcome Message

**Task:** Update welcome banner for new academic year

**Steps:**
1. Find "Welcome Banner" section in list
2. Click **Edit**
3. Update content:
   - Change year references
   - Update statistics or achievements
   - Refresh call-to-action
4. Preview changes
5. Save section
6. Verify on homepage

### Scenario 4: Reordering for Priority

**Task:** Move "Admission Open" announcement to top of page

**Steps:**
1. Find "Admission Open" section
2. Method A: Drag section to top of list
3. Method B: Edit section, change Display Order to 0
4. Save changes
5. Verify new order on homepage

### Scenario 5: Seasonal Content Management

**Task:** Manage Independence Day message

**Steps:**
1. **Before August 15:**
   - Create section: `independence_day_2024`
   - Add patriotic message and images
   - Set Display Order: 2
   - Save as active

2. **After August 16:**
   - Find the section
   - Click **Deactivate**
   - Section is hidden but preserved for next year

3. **Next Year:**
   - Find the section
   - Edit to update year and content
   - Click **Activate**
   - Section reappears on homepage

## Best Practices

### Content Strategy

1. **Plan Your Sections** - Decide what content is essential
2. **Prioritize Information** - Most important content at top
3. **Keep It Fresh** - Update content regularly
4. **Remove Outdated** - Deactivate or delete old content
5. **Test Changes** - Always preview before publishing

### Writing Guidelines

1. **Clear Headlines** - Use descriptive, action-oriented titles
2. **Concise Content** - Keep paragraphs short (2-3 sentences)
3. **Active Voice** - "Apply now" instead of "Applications are accepted"
4. **Scannable Format** - Use headings, lists, and bold text
5. **Call to Action** - Tell visitors what to do next

### Organization Tips

1. **Consistent Naming** - Use clear, consistent section keys
2. **Logical Order** - Group related content together
3. **Use Gaps** - Leave space in display order for insertions
4. **Document Sections** - Keep notes on what each section is for
5. **Regular Audits** - Review all sections quarterly

### Maintenance Schedule

**Daily:**
- Check for urgent announcements to add
- Verify all active sections are current

**Weekly:**
- Review and update time-sensitive content
- Check for broken links or images

**Monthly:**
- Audit all active sections
- Deactivate outdated content
- Update statistics or information

**Quarterly:**
- Review all sections (active and inactive)
- Delete permanently outdated sections
- Plan content for next quarter

## Troubleshooting

### Error: "Section key already exists"

**Problem:** You're trying to use a key that's already assigned

**Solution:**
- Choose a different, unique key
- Check the sections list to see which keys are in use
- Add a number or year to make it unique (e.g., `news_2024`)

### Error: "Invalid section key format"

**Problem:** The key doesn't meet format requirements

**Solution:**
- Use only lowercase letters (a-z), numbers (0-9), and underscores (_)
- Ensure key is 3-50 characters long
- Remove any spaces or special characters
- Examples: `welcome_banner` ✓, `Welcome Banner` ✗, `news-2024` ✗

### Content not appearing on homepage

**Problem:** Section is saved but not visible on the website

**Solution:**
- Verify section is marked as "Active"
- Clear browser cache (Ctrl+F5)
- Check display order (very high numbers appear at bottom)
- Verify section type is appropriate
- Check that homepage is loading content from database

### Formatting lost when saving

**Problem:** Content formatting disappears after saving

**Solution:**
- Use only allowed HTML tags
- Avoid copying from Word (use plain text)
- Use the editor toolbar instead of pasting HTML
- Check that tags are properly closed

### Images not displaying

**Problem:** Images in content don't show up

**Solution:**
- Verify image URL is correct and accessible
- Use absolute URLs (https://example.com/image.jpg)
- Check image file exists on server
- Ensure image format is supported (JPG, PNG, GIF)

### Drag and drop not working

**Problem:** Cannot reorder sections by dragging

**Solution:**
- Ensure JavaScript is enabled in browser
- Try using Edit method to change display order
- Refresh the page and try again
- Use a different browser if issue persists

### Changes not visible immediately

**Problem:** Updated content doesn't appear on homepage

**Solution:**
- Clear browser cache (Ctrl+F5)
- Wait a few minutes for cache to clear
- Verify section is active
- Check that you saved the changes
- Try viewing in incognito/private browsing mode

## Advanced Tips

### Using HTML Directly

If you're comfortable with HTML, you can:

1. Click the **Source** or **HTML** button in the editor
2. Edit HTML directly
3. Use allowed tags for advanced formatting
4. Switch back to visual mode to see results

**Example HTML:**
```html
<div class="alert alert-info">
  <h3>Important Notice</h3>
  <p>Admissions for the <strong>2024 batch</strong> are now open!</p>
  <p><a href="/courses.php" class="btn btn-primary">View Courses</a></p>
</div>
```

### Embedding Content

**YouTube Videos:**
```html
<div class="video-container">
  <iframe width="560" height="315" 
    src="https://www.youtube.com/embed/VIDEO_ID" 
    frameborder="0" allowfullscreen>
  </iframe>
</div>
```

**Note:** Iframes may be stripped for security. Check with administrator.

### Responsive Design

Content automatically adapts to different screen sizes, but you can help:

- Use relative sizes (percentages) instead of fixed pixels
- Test content on mobile devices
- Keep images reasonably sized
- Avoid very wide tables

## Screenshots

### [Screenshot Placeholder: Sections List View]
*Shows the homepage management page with list of content sections, search box, and Add New Section button*

### [Screenshot Placeholder: Add Section Modal]
*Shows the add section form with all fields (key, title, type, order, content editor)*

### [Screenshot Placeholder: Rich Text Editor]
*Shows the WYSIWYG editor with formatting toolbar and content area*

### [Screenshot Placeholder: Drag and Drop Reordering]
*Shows sections being reordered via drag and drop with drag handles visible*

### [Screenshot Placeholder: Section Preview]
*Shows the preview modal displaying how a section will appear on the homepage*

### [Screenshot Placeholder: Section Status Toggle]
*Shows active/inactive toggle buttons and status badges*

### [Screenshot Placeholder: Homepage with Dynamic Content]
*Shows the public homepage displaying multiple content sections from the database*

### [Screenshot Placeholder: Section Types Examples]
*Shows examples of different section types (banner, announcement, text block) on the homepage*

---

**Related Guides:**
- [Main User Guide](USER_GUIDE.md)
- [Centre Management Guide](CENTRE_MANAGEMENT_GUIDE.md)
- [Theme Customization Guide](THEME_CUSTOMIZATION_GUIDE.md)

**Document Version:** 1.0  
**Last Updated:** 2024
