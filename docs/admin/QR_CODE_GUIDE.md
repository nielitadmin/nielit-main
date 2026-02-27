# 📱 Admin Guide: QR Code System

## Quick Start Guide for Admins

---

## 🎯 What is the QR Code System?

The QR Code system allows you to:
- Generate unique QR codes for each course
- Share registration links easily
- Print QR codes on brochures and posters
- Let students register by scanning with their phones

---

## 📋 Step-by-Step Instructions

### Step 1: Access Course Management

1. Login to admin panel
2. Click "Manage Courses" in the sidebar
3. You'll see all your courses in a table

### Step 2: Generate QR Code for a Course

**For courses WITHOUT QR codes:**

1. Find the course in the table
2. Look at the "QR Code" column
3. You'll see a yellow button that says "Generate"
4. Click the "Generate" button
5. Wait 2-3 seconds (you'll see a loading spinner)
6. Success message will appear
7. Page will reload automatically
8. Now you'll see green "View" and blue "Download" buttons

**Visual:**
```
Before:  [🟡 Generate]
After:   [🟢 View] [🔵 Download]
```

### Step 3: View QR Code

1. Click the green "View" button
2. A popup window (modal) will open
3. You'll see:
   - Course name at the top
   - Large QR code image
   - "Download QR Code" button
   - "Regenerate" button

### Step 4: Download QR Code

**Method 1: Quick Download**
- Click the blue "Download" button in the table
- QR code PNG file downloads immediately
- No popup, instant download

**Method 2: From Modal**
- Click green "View" button
- In the popup, click "Download QR Code"
- PNG file downloads to your computer

**File Name Format:**
```
qr_[COURSE_CODE]_[ID].png

Examples:
- qr_DBC21_1.png
- qr_PPI_5.png
- qr_WEBDEV_12.png
```

### Step 5: Share QR Code

**For Printing:**
1. Download the QR code PNG file
2. Open in image editor or Word document
3. Resize as needed (maintains quality)
4. Print on:
   - Course brochures
   - Posters
   - Banners
   - Flyers
   - Business cards

**For Digital Sharing:**
1. Download QR code
2. Share via:
   - Email attachments
   - WhatsApp/Telegram
   - Social media posts
   - Website uploads
   - Google Drive/Dropbox

### Step 6: Copy Registration Link

**To share the direct link:**

1. Find the "Registration Link" column
2. You'll see the link in a text box
3. Click the "Copy" button (📋 icon)
4. Link is copied to clipboard
5. Paste anywhere you want to share

**Or click the "Open" button (🔗 icon) to:**
- Test the link in a new browser tab
- See how the registration page looks
- Verify everything works correctly

---

## 🔄 Regenerating QR Codes

**When to regenerate:**
- QR code file is lost or corrupted
- Want a fresh QR code
- Testing purposes

**How to regenerate:**

1. Click green "View" button
2. In the popup, click yellow "Regenerate" button
3. Confirm the action (popup will ask "Are you sure?")
4. Old QR code is deleted
5. New QR code is generated
6. Same registration link (URL doesn't change)
7. Page reloads with new QR code

**Important:** The registration link stays the same, only the QR code image file is replaced.

---

## 📊 Understanding the Course Table

### Columns Explained:

1. **ID** - Course database ID number
2. **Course Name** - Full course name
3. **Course Code** - Short code (e.g., DBC21, PPI)
4. **Type** - Regular, Internship, Bootcamp, Workshop
5. **Training Center** - Bhubaneswar or Balasore
6. **Duration** - Course length
7. **Fees** - Course cost in rupees
8. **Registration Link** - Direct URL for registration
   - Copy button (📋)
   - Open button (🔗)
9. **QR Code** - QR code status and actions
   - Generate (🟡) - Create new QR code
   - View (🟢) - See QR code in popup
   - Download (🔵) - Download PNG file
10. **Status** - Active or Inactive
11. **Actions** - Edit or Delete course

---

## 🎨 QR Code Features

### What's Encoded in the QR Code?

Each QR code contains a registration link like:
```
http://localhost/public_html/student/register.php?course_id=123
```

When students scan:
1. Their phone camera opens the link
2. Registration page loads
3. Course is pre-selected
4. Student fills in their details
5. Submits registration

### QR Code Specifications:

- **Format:** PNG image
- **Size:** ~300x300 pixels
- **File Size:** 2-5 KB
- **Color:** Black on white background
- **Border:** 2-module quiet zone
- **Error Correction:** Low (7% recovery)
- **Scannable Distance:** 1-2 meters

### Quality Assurance:

✅ High-quality PNG format
✅ Scalable without quality loss
✅ Works with all QR code scanners
✅ Compatible with all smartphones
✅ Prints clearly on paper
✅ Fast scanning response

---

## 📱 How Students Use QR Codes

### Scanning Process:

1. **Open Camera App**
   - iPhone: Native camera app
   - Android: Camera or Google Lens
   - Any QR scanner app

2. **Point at QR Code**
   - Hold phone steady
   - Keep QR code in frame
   - Wait 1-2 seconds

3. **Tap Notification**
   - Phone shows link preview
   - Tap to open in browser

4. **Register**
   - Registration page opens
   - Course is pre-selected
   - Fill in details
   - Submit form

### Student Benefits:

✅ No typing long URLs
✅ No searching for course
✅ Direct access to registration
✅ Mobile-friendly process
✅ Quick and convenient

---

## 🎯 Best Practices

### For Printing:

1. **Size Recommendations:**
   - Business cards: 2x2 cm minimum
   - Flyers: 3x3 cm recommended
   - Posters: 5x5 cm or larger
   - Banners: 10x10 cm for distance viewing

2. **Placement Tips:**
   - Bottom right corner of brochures
   - Center of posters
   - Back of business cards
   - Prominent position on banners

3. **Testing:**
   - Always test scan before mass printing
   - Check from different distances
   - Try different phone models
   - Verify link opens correctly

### For Digital Sharing:

1. **Social Media:**
   - Include QR code in course announcement posts
   - Add caption: "Scan to register!"
   - Use high-quality image
   - Tag relevant accounts

2. **Email Campaigns:**
   - Embed QR code in email body
   - Add "Click or Scan to Register" text
   - Include direct link as backup
   - Test email on mobile devices

3. **Website:**
   - Display QR code on course pages
   - Add download button for students
   - Show QR code in course catalog
   - Include in course comparison tables

---

## 🔍 Troubleshooting

### Problem: "Generate" button doesn't work

**Solutions:**
1. Refresh the page (F5)
2. Check your internet connection
3. Try logging out and back in
4. Clear browser cache
5. Try a different browser

### Problem: QR code doesn't scan

**Solutions:**
1. Ensure good lighting
2. Hold phone steady
3. Try from different distance
4. Clean phone camera lens
5. Use a different QR scanner app
6. Regenerate the QR code

### Problem: Link opens but shows error

**Solutions:**
1. Check if course is still active
2. Verify registration page exists
3. Test link in browser directly
4. Contact technical support

### Problem: Can't download QR code

**Solutions:**
1. Check browser download settings
2. Allow pop-ups for the site
3. Try right-click > Save Image As
4. Use the View modal download button
5. Check disk space on computer

---

## 📈 Usage Statistics

### Track Your QR Codes:

**In the course table, you can see:**
- Which courses have QR codes (green buttons)
- Which courses need QR codes (yellow buttons)
- Total courses with QR codes

**To generate QR codes for all courses:**
1. Go through each course
2. Click "Generate" for courses without QR
3. Wait for each to complete
4. Download all QR codes
5. Organize in a folder

---

## 💡 Tips & Tricks

### Tip 1: Batch Download
- Open each course's QR code
- Download all at once
- Organize by course code
- Keep backup copies

### Tip 2: Version Control
- Save QR codes with date in filename
- Keep old versions if regenerating
- Document which QR codes are in use
- Update printed materials when needed

### Tip 3: Testing
- Always test QR codes before printing
- Scan from different phones
- Check on both iOS and Android
- Verify registration page loads

### Tip 4: Marketing
- Use QR codes in all marketing materials
- Track which materials get most scans
- Update QR codes seasonally
- A/B test different placements

### Tip 5: Backup
- Keep digital copies of all QR codes
- Save to cloud storage
- Document which QR goes with which course
- Have backup plan if QR code fails

---

## 📞 Need Help?

### Common Questions:

**Q: Can I change the registration link?**
A: Yes, edit the course and update the registration link field.

**Q: Will old QR codes still work after regenerating?**
A: No, old QR code files are deleted. But the link stays the same.

**Q: Can I have multiple QR codes for one course?**
A: Currently one QR code per course. Contact support for custom solutions.

**Q: How long do QR codes last?**
A: Forever! As long as the course exists and is active.

**Q: Can students register without QR codes?**
A: Yes! They can use the direct registration link or visit the website.

### Contact Support:

- **Technical Issues:** Check documentation files
- **Feature Requests:** Contact development team
- **Training:** Request admin training session
- **Custom Solutions:** Discuss with IT department

---

## ✅ Quick Reference

### Button Colors & Actions:

| Color | Button | Action |
|-------|--------|--------|
| 🟡 Yellow | Generate | Create new QR code |
| 🟢 Green | View | Open QR code modal |
| 🔵 Blue | Download | Download PNG file |
| 🟠 Orange | Regenerate | Replace existing QR |
| 📋 Gray | Copy | Copy link to clipboard |
| 🔗 Blue | Open | Open link in new tab |

### File Locations:

- **QR Codes:** `assets/qr_codes/`
- **Registration Page:** `student/register.php`
- **Course Management:** `admin/manage_courses.php`

### Keyboard Shortcuts:

- **Refresh Page:** F5 or Ctrl+R
- **Open Link:** Ctrl+Click
- **Copy:** Ctrl+C
- **Paste:** Ctrl+V

---

## 🎉 You're Ready!

You now know how to:
✅ Generate QR codes for courses
✅ View and download QR codes
✅ Share QR codes with students
✅ Regenerate QR codes when needed
✅ Troubleshoot common issues

**Start generating QR codes for your courses now!**

---

**Last Updated:** February 11, 2026
**Version:** 1.0.0
**For:** NIELIT Bhubaneswar Admin Panel
