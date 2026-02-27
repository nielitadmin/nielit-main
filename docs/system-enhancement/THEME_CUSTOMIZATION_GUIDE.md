# Theme Customization Guide

## Overview

The Theme Customization feature allows administrators to customize the visual appearance of the NIELIT Student Management System without modifying code. You can create multiple themes with different color schemes and logos, and switch between them instantly.

## Accessing Theme Management

1. Log in to the admin panel
2. Navigate to **System Settings** in the sidebar
3. Click **Manage Themes**

## Understanding Themes

A **theme** is a collection of visual styling configurations that control the appearance of your application. Each theme includes:

- **Theme Name** - Descriptive name for the theme
- **Primary Color** - Main brand color (used for headers, buttons)
- **Secondary Color** - Supporting color (used for accents, highlights)
- **Accent Color** - Emphasis color (used for badges, alerts)
- **Logo** - Main logo image displayed in navigation
- **Favicon** - Small icon displayed in browser tabs
- **Status** - Active or Inactive (only one theme can be active at a time)

## Creating a New Theme

### Step-by-Step Instructions

1. **Open the Add Theme Form**
   - Click the **Add New Theme** button at the top of the page
   - A modal form will appear

2. **Enter Theme Name**
   - Choose a descriptive name (e.g., "Blue Corporate Theme", "Green Nature Theme")
   - This name is for internal reference only

3. **Select Colors**
   
   **Primary Color:**
   - Click the color picker for Primary Color
   - Select your main brand color
   - This color is used for navigation bars, primary buttons, and headers
   - Example: `#0d47a1` (deep blue)
   
   **Secondary Color:**
   - Click the color picker for Secondary Color
   - Select a complementary color
   - This color is used for secondary buttons, badges, and highlights
   - Example: `#1565c0` (lighter blue)
   
   **Accent Color:**
   - Click the color picker for Accent Color
   - Select an emphasis color
   - This color is used for alerts, notifications, and call-to-action elements
   - Example: `#ffc107` (amber/gold)

4. **Upload Logo (Optional)**
   - Click **Choose File** under Logo
   - Select an image file (JPG, PNG, GIF, or SVG)
   - Recommended size: 200x60 pixels
   - Maximum file size: 2MB
   - The logo will appear in the navigation header

5. **Upload Favicon (Optional)**
   - Click **Choose File** under Favicon
   - Select an icon file (JPG, PNG, GIF, or SVG)
   - Recommended size: 32x32 pixels
   - Maximum file size: 2MB
   - The favicon appears in browser tabs

6. **Save the Theme**
   - Click **Save Theme**
   - If successful, you'll see a success message
   - The new theme will appear in the themes list
   - The theme is created as inactive by default

### Example: Creating a Professional Blue Theme

```
Theme Name: Professional Blue Theme
Primary Color: #0d47a1 (Deep Blue)
Secondary Color: #1565c0 (Medium Blue)
Accent Color: #ffc107 (Amber)
Logo: nielit_logo_blue.png (200x60px, 150KB)
Favicon: nielit_favicon.png (32x32px, 5KB)
```

### Color Selection Tips

**Choosing Primary Color:**
- Use your organization's main brand color
- Ensure good contrast with white text
- Darker colors work better for headers
- Test readability before finalizing

**Choosing Secondary Color:**
- Should complement the primary color
- Often a lighter or darker shade of primary
- Used for less prominent elements
- Maintain visual harmony

**Choosing Accent Color:**
- Should stand out from primary and secondary
- Used sparingly for emphasis
- Often a contrasting color (e.g., orange with blue)
- Ensure it's noticeable but not overwhelming

**Color Harmony Examples:**

*Blue Theme:*
- Primary: `#0d47a1` (Deep Blue)
- Secondary: `#1565c0` (Medium Blue)
- Accent: `#ffc107` (Amber)

*Green Theme:*
- Primary: `#2e7d32` (Forest Green)
- Secondary: `#43a047` (Light Green)
- Accent: `#ff6f00` (Orange)

*Purple Theme:*
- Primary: `#6a1b9a` (Deep Purple)
- Secondary: `#8e24aa` (Medium Purple)
- Accent: `#fdd835` (Yellow)

*Red Theme:*
- Primary: `#c62828` (Deep Red)
- Secondary: `#e53935` (Medium Red)
- Accent: `#fbc02d` (Gold)

## Editing an Existing Theme

### Step-by-Step Instructions

1. **Locate the Theme**
   - Find the theme in the themes list
   - Themes are displayed as preview cards showing colors and status

2. **Open the Edit Form**
   - Click the **Edit** button (pencil icon) on the theme card
   - The edit modal will appear with current values pre-filled

3. **Modify Theme Settings**
   - Update theme name if needed
   - Change colors using the color pickers
   - Upload new logo or favicon (optional)
   - Leave file fields empty to keep existing images

4. **Save Changes**
   - Click **Update Theme**
   - If successful, you'll see a success message
   - If the theme is active, changes apply immediately
   - If inactive, changes will apply when activated

### Updating Logo or Favicon

**To Replace an Image:**
1. Edit the theme
2. Click **Choose File** for the image you want to replace
3. Select the new image file
4. Save the theme
5. The old image is automatically deleted

**To Keep Existing Image:**
- Simply don't select a new file
- Leave the file input empty when saving

## Activating a Theme

### Why Activate a Theme?

Activating a theme applies it to the entire application, including:
- Admin panel
- Public website
- Student portal
- All pages and components

**Important:** Only one theme can be active at a time. Activating a new theme automatically deactivates the current one.

### Step-by-Step Instructions

1. **Preview the Theme (Recommended)**
   - Click the **Preview** button on the theme card
   - Review how the theme looks
   - Check color combinations and logo placement
   - Close preview when satisfied

2. **Activate the Theme**
   - Click the **Activate** button on the theme card
   - Confirm the activation
   - The theme is applied immediately
   - The previously active theme is automatically deactivated

3. **Verify the Changes**
   - Navigate to different pages to see the new theme
   - Check both admin and public pages
   - Verify logo and colors appear correctly
   - Clear browser cache if needed (Ctrl+F5)

### Deactivating a Theme

You cannot manually deactivate a theme. Instead:
- Activate a different theme (automatically deactivates the current one)
- Or create and activate a default theme

## Previewing Themes

### Why Preview?

Previewing allows you to:
- See how colors look together
- Check logo placement and sizing
- Test readability and contrast
- Make decisions before going live

### How to Preview

1. **Open Preview**
   - Click the **Preview** button on any theme card
   - A preview modal will appear

2. **Review the Preview**
   - The preview shows sample UI elements with the theme applied
   - Check navigation bar, buttons, badges, and text
   - Verify logo appears correctly
   - Assess overall visual appeal

3. **Close Preview**
   - Click the **Close** button or click outside the modal
   - No changes are made to the active theme

### What Preview Shows

The preview displays:
- Navigation header with logo and primary color
- Primary and secondary buttons
- Badges with secondary color
- Alert boxes with accent color
- Text on various backgrounds
- Sample content sections

## Managing Multiple Themes

### Use Cases for Multiple Themes

**Seasonal Themes:**
- Create themes for different seasons or holidays
- Switch to festive colors during special occasions
- Example: Green theme for environmental awareness month

**Branding Variations:**
- Different themes for different centres
- Themes for special programs or events
- A/B testing different color schemes

**Backup Themes:**
- Keep a default theme as backup
- Create test themes for experimentation
- Maintain previous themes for quick rollback

### Best Practices

1. **Name Themes Clearly** - Use descriptive names (e.g., "Summer 2024 Theme")
2. **Keep a Default** - Always have a reliable default theme
3. **Test Before Activating** - Always preview themes before activation
4. **Document Changes** - Note why and when you change themes
5. **Limit Active Switching** - Don't change themes too frequently

## Uploading Logos and Favicons

### Logo Guidelines

**File Requirements:**
- **Formats:** JPG, PNG, GIF, SVG
- **Maximum Size:** 2MB
- **Recommended Dimensions:** 200x60 pixels
- **Aspect Ratio:** Approximately 3:1 (width:height)

**Design Tips:**
- Use transparent background (PNG or SVG)
- Ensure logo is readable at small sizes
- Test on both light and dark backgrounds
- Keep design simple and clean
- Include organization name if possible

**Optimization:**
- Compress images before uploading
- Use PNG for logos with transparency
- Use SVG for scalable vector logos
- Remove unnecessary metadata

### Favicon Guidelines

**File Requirements:**
- **Formats:** JPG, PNG, GIF, SVG (PNG recommended)
- **Maximum Size:** 2MB
- **Recommended Dimensions:** 32x32 pixels or 16x16 pixels
- **Aspect Ratio:** 1:1 (square)

**Design Tips:**
- Use simple, recognizable icon
- Ensure visibility at tiny size
- Use high contrast
- Avoid fine details
- Test in browser tab

### Image Optimization Tools

**Online Tools:**
- TinyPNG (https://tinypng.com) - Compress PNG and JPG
- Squoosh (https://squoosh.app) - Advanced image compression
- ImageOptim (https://imageoptim.com) - Mac app for optimization

**Recommended Workflow:**
1. Create logo at high resolution
2. Resize to recommended dimensions
3. Compress using optimization tool
4. Test file size (should be under 500KB)
5. Upload to theme

### Troubleshooting Uploads

**Error: "Invalid file type"**
- Solution: Use only JPG, PNG, GIF, or SVG files
- Check file extension is correct
- Rename file if needed (e.g., logo.png)

**Error: "File too large"**
- Solution: Compress the image
- Reduce dimensions if too large
- Use optimization tools
- Maximum allowed: 2MB

**Logo not displaying:**
- Clear browser cache (Ctrl+F5)
- Verify file uploaded successfully
- Check file path in theme settings
- Ensure theme is activated

**Logo appears distorted:**
- Check original image dimensions
- Use recommended aspect ratio (3:1 for logo)
- Upload higher quality image
- Consider using SVG for scalability

## Color Codes and Formats

### Hexadecimal Color Format

Colors must be specified in hexadecimal format: `#RRGGBB`

- `#` - Hash symbol (required)
- `RR` - Red component (00-FF)
- `GG` - Green component (00-FF)
- `BB` - Blue component (00-FF)

**Examples:**
- `#0d47a1` - Deep Blue
- `#ff0000` - Pure Red
- `#00ff00` - Pure Green
- `#0000ff` - Pure Blue
- `#ffffff` - White
- `#000000` - Black

### Using the Color Picker

1. **Click the Color Input**
   - Click on the color input field
   - A color picker will appear

2. **Select Color**
   - Drag the selector to choose hue
   - Adjust brightness and saturation
   - Or enter hex code directly

3. **Confirm Selection**
   - Click outside the picker or press Enter
   - The hex code is automatically filled

### Finding Color Codes

**From Existing Branding:**
- Use browser developer tools (F12)
- Use color picker extensions
- Extract from brand guidelines

**Color Picker Tools:**
- HTML Color Picker (https://htmlcolorcodes.com)
- Adobe Color (https://color.adobe.com)
- Coolors (https://coolors.co)
- Material Design Colors (https://materialui.co/colors)

**Color Palette Generators:**
- Coolors.co - Generate harmonious palettes
- Adobe Color - Create color schemes
- Paletton - Color scheme designer
- Material Palette - Material design colors

## Theme Application

### Where Themes Apply

**Admin Panel:**
- Navigation header
- Sidebar menu
- Buttons and forms
- Tables and cards
- Dashboard widgets

**Public Website:**
- Main navigation
- Hero sections
- Buttons and links
- Course cards
- Footer

**Student Portal:**
- Dashboard header
- Navigation menu
- Action buttons
- Status badges
- Profile sections

### CSS Custom Properties

Themes work by injecting CSS custom properties:

```css
:root {
    --primary-color: #0d47a1;
    --secondary-color: #1565c0;
    --accent-color: #ffc107;
}
```

These variables are used throughout the application:

```css
.navbar {
    background-color: var(--primary-color);
}

.btn-primary {
    background-color: var(--primary-color);
}

.badge {
    background-color: var(--secondary-color);
}
```

### Caching and Updates

**Theme Cache:**
- Active theme is cached for performance
- Cache is automatically cleared when theme is activated or updated
- Manual cache clear: Restart browser or clear browser cache

**Browser Cache:**
- Browsers cache CSS and images
- Force refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
- Clear browser cache if changes don't appear

## Common Scenarios

### Scenario 1: Rebranding the Application

**Task:** Update application to new brand colors and logo

**Steps:**
1. Prepare new logo (200x60px, optimized)
2. Prepare new favicon (32x32px)
3. Identify new brand colors (primary, secondary, accent)
4. Create new theme with brand name
5. Upload logo and favicon
6. Set brand colors using color picker
7. Preview the theme
8. Activate when satisfied
9. Verify on all pages

### Scenario 2: Creating a Seasonal Theme

**Task:** Create a festive theme for Independence Day

**Steps:**
1. Click **Add New Theme**
2. Name: "Independence Day 2024"
3. Colors:
   - Primary: `#ff9933` (Saffron)
   - Secondary: `#138808` (Green)
   - Accent: `#000080` (Navy Blue)
4. Upload tricolor-themed logo
5. Save theme
6. Activate on August 14th
7. Revert to default theme after August 16th

### Scenario 3: Testing New Colors

**Task:** Experiment with different color schemes

**Steps:**
1. Create test theme: "Test Theme - Blue"
2. Set experimental colors
3. Preview the theme
4. If unsatisfactory, edit and adjust colors
5. Preview again
6. Repeat until satisfied
7. Activate or delete test theme

### Scenario 4: Reverting to Previous Theme

**Task:** New theme doesn't look good, revert to previous

**Steps:**
1. Find the previous theme in themes list
2. Click **Activate** on the previous theme
3. The new theme is automatically deactivated
4. Verify the previous theme is applied
5. Optionally delete or edit the unsatisfactory theme

## Best Practices

### Design Principles

1. **Consistency** - Use colors consistently across all elements
2. **Contrast** - Ensure text is readable on colored backgrounds
3. **Simplicity** - Don't use too many colors
4. **Accessibility** - Consider color-blind users
5. **Branding** - Align with organizational brand guidelines

### Color Psychology

- **Blue** - Trust, professionalism, stability
- **Green** - Growth, nature, success
- **Red** - Energy, urgency, importance
- **Purple** - Creativity, luxury, wisdom
- **Orange** - Enthusiasm, warmth, confidence
- **Yellow** - Optimism, clarity, attention

### Testing Checklist

Before activating a theme:
- [ ] Preview the theme
- [ ] Check color contrast and readability
- [ ] Verify logo displays correctly
- [ ] Test on different pages (admin, public, student)
- [ ] Check on different devices (desktop, tablet, mobile)
- [ ] Verify buttons and links are visible
- [ ] Ensure text is readable
- [ ] Get feedback from colleagues

## Troubleshooting

### Colors not applying

**Problem:** Theme is activated but colors don't change

**Solution:**
- Clear browser cache (Ctrl+F5)
- Verify theme is marked as "Active"
- Check that color codes are valid hex format
- Restart browser if needed

### Logo not displaying

**Problem:** Logo uploaded but not showing

**Solution:**
- Verify file uploaded successfully
- Check file format (JPG, PNG, GIF, SVG only)
- Ensure file size is under 2MB
- Clear browser cache
- Verify theme is activated

### Error: "Invalid color format"

**Problem:** Cannot save theme due to color error

**Solution:**
- Use hexadecimal format: #RRGGBB
- Include the # symbol
- Use exactly 6 characters after #
- Use only 0-9 and A-F characters
- Example: `#0d47a1` ✓, `blue` ✗, `#0d4` ✗

### Multiple themes showing as active

**Problem:** More than one theme appears active

**Solution:**
- This shouldn't happen (system prevents it)
- If it occurs, activate your preferred theme
- System will automatically fix the issue
- Contact administrator if problem persists

### Theme changes not visible on public site

**Problem:** Admin panel shows new theme but public site doesn't

**Solution:**
- Clear browser cache completely
- Check that theme is activated (not just saved)
- Verify theme loader is included in public pages
- Wait a few minutes for cache to clear
- Try different browser to test

## Screenshots

### [Screenshot Placeholder: Themes List View]
*Shows the themes management page with theme preview cards, each displaying colors and status*

### [Screenshot Placeholder: Add Theme Modal]
*Shows the add theme form with color pickers, file upload fields, and save button*

### [Screenshot Placeholder: Color Picker Interface]
*Shows the color picker tool for selecting theme colors*

### [Screenshot Placeholder: Theme Preview Modal]
*Shows the preview modal with sample UI elements styled with the theme*

### [Screenshot Placeholder: Active Theme Indicator]
*Shows an active theme card with green "Active" badge*

### [Screenshot Placeholder: Logo Upload]
*Shows the file upload interface for logo and favicon*

### [Screenshot Placeholder: Theme Applied to Admin Panel]
*Shows the admin dashboard with custom theme colors and logo*

### [Screenshot Placeholder: Theme Applied to Public Site]
*Shows the public homepage with custom theme colors and logo*

---

**Related Guides:**
- [Main User Guide](USER_GUIDE.md)
- [Centre Management Guide](CENTRE_MANAGEMENT_GUIDE.md)
- [Homepage Content Management Guide](HOMEPAGE_CONTENT_GUIDE.md)

**Document Version:** 1.0  
**Last Updated:** 2024
