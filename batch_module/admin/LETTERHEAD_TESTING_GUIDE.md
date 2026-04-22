# 🧪 Letterhead Word Generator Testing Guide

## Quick Test Steps

### ✅ **Step 1: Pre-Test Verification**
1. **Check Database Connection**
   - Ensure your database is running
   - Verify batch and student data exists

2. **Check Logo File**
   - Verify `assets/images/bhubaneswar_logo.png` exists
   - File should be readable and valid PNG format

3. **Admin Authentication**
   - Ensure you can login to admin panel
   - Verify admin session is working

### ✅ **Step 2: Quick Test Access**
Run the quick test page:
```
http://your-domain/batch_module/admin/test_letterhead_quick.php
```

This will show you:
- Available batches for testing
- Student counts per batch
- Logo file status
- Direct test links

### ✅ **Step 3: UI Testing**
1. **Login to Admin Panel**
   ```
   http://your-domain/admin/login_new.php
   ```

2. **Navigate to Batch Module**
   - Click "Batch Module" → "Manage Batches"
   - Click on any batch to view details

3. **Look for Letterhead Button**
   - Should see blue "Generate Letterhead (Word)" button
   - Button should have Word icon (📄)

4. **Click and Download**
   - Click the letterhead button
   - Document should download as `.doc` file
   - Filename format: `Admission_Order_Letterhead_[BatchName]_[Date].doc`

### ✅ **Step 4: Document Quality Check**

**Open the downloaded Word document and verify:**

#### 📋 **Letterhead Header**
- [ ] NIELIT logo appears on left side
- [ ] Hindi institutional name displays correctly
- [ ] English institutional name shows properly
- [ ] Extension centre (Bhubaneswar/Balasore) is correct
- [ ] Government affiliation text is present

#### 📋 **Document Content**
- [ ] Reference number and date are present
- [ ] "ADMISSION ORDER" title is centered and underlined
- [ ] Batch and course details table is formatted properly
- [ ] Student list table shows all enrolled students
- [ ] Category summary table has correct counts
- [ ] Signature section shows proper designation
- [ ] Copy-to list is included

#### 📋 **Professional Formatting**
- [ ] Consistent fonts throughout document
- [ ] Proper spacing and margins
- [ ] Tables are well-formatted with borders
- [ ] Text alignment is appropriate
- [ ] Document looks professional and official

### ✅ **Step 5: Cross-Browser Testing**
Test the download functionality in:
- [ ] Chrome
- [ ] Firefox  
- [ ] Safari (if on Mac)
- [ ] Edge

### ✅ **Step 6: Microsoft Word Compatibility**
Open the downloaded document in:
- [ ] Microsoft Word 2016/2019/365
- [ ] LibreOffice Writer
- [ ] Google Docs (upload and check)

## 🔧 **Troubleshooting Common Issues**

### **Issue: "No students found for this batch"**
**Solution:** 
- Check if students are enrolled in the batch
- Verify batch_id exists in database
- Ensure students table has data linked to the batch

### **Issue: Logo not appearing**
**Solution:**
- Check if `assets/images/bhubaneswar_logo.png` exists
- Verify file permissions are readable
- Ensure file is valid PNG format

### **Issue: "Unauthorized" error**
**Solution:**
- Login to admin panel first
- Verify admin session is active
- Check admin authentication

### **Issue: Download not working**
**Solution:**
- Check browser download settings
- Verify PHP headers are being sent correctly
- Try different browser

### **Issue: Formatting looks wrong in Word**
**Solution:**
- Ensure you're using Microsoft Word (not WordPad)
- Try LibreOffice Writer as alternative
- Check if document downloaded completely

## 🎯 **Expected Results**

### **Successful Test Should Produce:**
1. **Professional letterhead document** with NIELIT branding
2. **Bilingual headers** in Hindi and English
3. **Complete student data** with proper formatting
4. **Government-standard layout** suitable for official use
5. **Editable Word document** that opens properly in Microsoft Word

### **File Details:**
- **Format:** Microsoft Word (.doc)
- **Size:** Varies based on student count (typically 50-200KB)
- **Compatibility:** Word 2016+, LibreOffice Writer, Google Docs
- **Layout:** Professional letterhead with institutional branding

## 📞 **Support**

If you encounter issues:
1. Check the error logs in your web server
2. Verify database connectivity
3. Ensure all required files are present
4. Test with a batch that has enrolled students

## 🚀 **Quick Test URLs**

Replace `your-domain` with your actual domain:

- **Quick Test Page:** `http://your-domain/batch_module/admin/test_letterhead_quick.php`
- **Direct Generator:** `http://your-domain/batch_module/admin/generate_admission_order_word_letterhead.php?batch_id=1`
- **Batch Management:** `http://your-domain/batch_module/admin/manage_batches.php`
- **Admin Login:** `http://your-domain/admin/login_new.php`