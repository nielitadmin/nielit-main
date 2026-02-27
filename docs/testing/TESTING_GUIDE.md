# Testing Guide - Public Pages Update

## Quick Testing Checklist

### 1. Visual Testing

#### Test All Pages
Visit each page and verify the design:

1. **http://localhost/public_html/public/courses.php**
   - [ ] Top bar displays correctly
   - [ ] Navigation menu works
   - [ ] Notice ticker animates
   - [ ] Course cards display properly
   - [ ] Footer matches design
   - [ ] Responsive on mobile

2. **http://localhost/public_html/public/management.php**
   - [ ] Top bar displays correctly
   - [ ] Navigation menu works
   - [ ] Notice ticker animates
   - [ ] Organizational chart shows
   - [ ] Info boxes display properly
   - [ ] Footer matches design
   - [ ] Responsive on mobile

3. **http://localhost/public_html/public/news.php**
   - [ ] Top bar displays correctly
   - [ ] Navigation menu works
   - [ ] Notice ticker animates
   - [ ] News cards display properly
   - [ ] Icons and badges show
   - [ ] Footer matches design
   - [ ] Responsive on mobile

4. **http://localhost/public_html/public/contact.php**
   - [ ] Top bar displays correctly
   - [ ] Navigation menu works
   - [ ] Notice ticker animates
   - [ ] Contact information displays
   - [ ] Map loads correctly
   - [ ] Quick access cards work
   - [ ] Footer matches design
   - [ ] Responsive on mobile

### 2. Navigation Testing

#### Test All Navigation Links
- [ ] Home link goes to index.php
- [ ] Job Fair link works
- [ ] PM SHRI KV JNV dropdown works
- [ ] Student Zone dropdown works
- [ ] About dropdown shows Management and News
- [ ] Admin dropdown works
- [ ] Contact link works
- [ ] All dropdown items are clickable

### 3. Responsive Testing

#### Desktop (1200px+)
- [ ] All pages display in 3-column layout where applicable
- [ ] Navigation is horizontal
- [ ] Footer has 3 columns
- [ ] Images are full size

#### Tablet (768px - 1199px)
- [ ] Pages display in 2-column layout
- [ ] Navigation is horizontal
- [ ] Footer has 2-3 columns
- [ ] Images are medium size

#### Mobile (<768px)
- [ ] Pages display in 1-column layout
- [ ] Hamburger menu appears
- [ ] Footer stacks vertically
- [ ] Images are small size
- [ ] Touch targets are large enough

### 4. Functional Testing

#### Links
- [ ] All internal links work
- [ ] All external links open in new tab
- [ ] PDF downloads work (news page)
- [ ] Email links open mail client
- [ ] Phone links work on mobile

#### Interactive Elements
- [ ] Dropdown menus open/close
- [ ] Hover effects work on cards
- [ ] Buttons are clickable
- [ ] Map is interactive (zoom, pan)

### 5. Cross-Browser Testing

Test in multiple browsers:
- [ ] Google Chrome
- [ ] Mozilla Firefox
- [ ] Microsoft Edge
- [ ] Safari (if available)
- [ ] Mobile Chrome
- [ ] Mobile Safari

### 6. Admin Panel Testing

Verify admin panel still works:
- [ ] Admin login page loads
- [ ] Can log in successfully
- [ ] Dashboard displays correctly
- [ ] Student management works
- [ ] Course management works
- [ ] No JavaScript errors in console

### 7. Performance Testing

#### Load Times
- [ ] Pages load in under 3 seconds
- [ ] Images load properly
- [ ] No broken resources in console
- [ ] CDN resources load correctly

#### Console Errors
Open browser console (F12) and check:
- [ ] No JavaScript errors
- [ ] No CSS errors
- [ ] No 404 errors for resources
- [ ] No PHP errors displayed

### 8. Accessibility Testing

#### Basic Checks
- [ ] All images have alt text
- [ ] Links have descriptive text
- [ ] Color contrast is sufficient
- [ ] Keyboard navigation works
- [ ] Focus indicators are visible

### 9. Content Verification

#### Text Content
- [ ] No spelling errors
- [ ] Hindi text displays correctly
- [ ] Contact information is accurate
- [ ] Links point to correct URLs

#### Images
- [ ] NIELIT logo displays
- [ ] National emblem displays
- [ ] Organizational chart displays
- [ ] All icons display correctly

### 10. Mobile-Specific Testing

#### Touch Interactions
- [ ] Tap targets are large enough
- [ ] Dropdowns work on touch
- [ ] Map is touch-friendly
- [ ] No horizontal scrolling

#### Mobile Layout
- [ ] Text is readable without zooming
- [ ] Buttons are easy to tap
- [ ] Forms are mobile-friendly
- [ ] Navigation is easy to use

## Common Issues and Solutions

### Issue: Top bar images not showing
**Solution**: Check that images exist in `assets/images/` folder:
- `bhubaneswar_logo.png`
- `National-Emblem.png`

### Issue: CSS not loading
**Solution**: Verify `assets/css/public-theme.css` exists and check `APP_URL` in `config/config.php`

### Issue: Navigation dropdowns not working
**Solution**: Ensure Bootstrap 5 JavaScript is loaded at the bottom of the page

### Issue: Map not loading
**Solution**: Check internet connection (Google Maps requires internet)

### Issue: PHP errors displayed
**Solution**: Check `config/config.php` for correct database credentials

### Issue: Responsive design not working
**Solution**: Verify viewport meta tag is present in `<head>` section

## Testing Tools

### Browser DevTools
- **Chrome DevTools**: F12 or Right-click → Inspect
- **Responsive Mode**: Ctrl+Shift+M (Chrome) or Cmd+Opt+M (Mac)
- **Console**: Check for errors
- **Network**: Check resource loading

### Online Tools
- **PageSpeed Insights**: https://pagespeed.web.dev/
- **Mobile-Friendly Test**: https://search.google.com/test/mobile-friendly
- **WAVE Accessibility**: https://wave.webaim.org/

## Reporting Issues

If you find any issues:

1. **Note the page URL**
2. **Describe the issue**
3. **Include browser and version**
4. **Take a screenshot if possible**
5. **Check browser console for errors**

## Success Criteria

All tests should pass with:
- ✅ No visual inconsistencies
- ✅ All links working
- ✅ Responsive design functioning
- ✅ No console errors
- ✅ Fast load times
- ✅ Admin panel intact

## Final Verification

Before considering the update complete:

1. [ ] All 4 public pages tested
2. [ ] Navigation works across all pages
3. [ ] Responsive design verified
4. [ ] Cross-browser testing done
5. [ ] Admin panel verified
6. [ ] No console errors
7. [ ] Performance is acceptable
8. [ ] Content is accurate

---

**Testing Date**: _____________
**Tested By**: _____________
**Status**: [ ] Pass [ ] Fail
**Notes**: _____________________________________________

---

## Quick Test Commands

If you have access to the server, you can run these quick checks:

```bash
# Check if files exist
ls -la public/management.php
ls -la public/news.php
ls -la public/contact.php
ls -la assets/css/public-theme.css

# Check file permissions
chmod 644 public/*.php
chmod 644 assets/css/*.css

# Check for PHP syntax errors (if PHP CLI is available)
php -l public/management.php
php -l public/news.php
php -l public/contact.php
```

---

**Last Updated**: February 10, 2026
**Version**: 1.0
