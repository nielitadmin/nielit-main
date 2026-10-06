# 🚀 Quick Start Guide - Monthly Report Generator

## For Monthly Office Reporting

### Method 1: Double-Click (Easiest!)

1. **Navigate to the tools folder:**
   ```
   c:\xampp\htdocs\public_html\tools\
   ```

2. **Double-click:** `generate_report.bat`

3. **Done!** Your browser will open with the report automatically

---

### Method 2: Command Line (More Control)

#### For Current Month:
```powershell
cd c:\xampp\htdocs\public_html\tools
C:\xampp\php\php.exe generate_monthly_report.php
```

#### For Specific Month:
```powershell
# January 2025
C:\xampp\php\php.exe generate_monthly_report.php 1 2025

# September 2026
C:\xampp\php\php.exe generate_monthly_report.php 9 2026

# December 2024
C:\xampp\php\php.exe generate_monthly_report.php 12 2024
```

---

## 📋 What You Get

### Beautiful HTML Report With:
- ✅ **Total Commits** - Number of code commits
- ✅ **Files Changed** - Files you modified
- ✅ **Lines Added/Deleted** - Code statistics (+green, -red)
- ✅ **Full Commit History** - Every single change
- ✅ **Code Diffs** - See what code changed (expandable)

### Perfect for:
- Monthly team meetings
- Performance reviews
- Progress tracking
- Manager reports
- Client updates

---

## 🖨️ Save as PDF for Office

1. Open the generated HTML file in browser
2. Click the **"🖨️ Print Report"** button (bottom right corner)
3. In the print dialog:
   - **Destination:** Save as PDF
   - **Layout:** Portrait
   - **Pages:** All
4. Click **Save**
5. Share the PDF with your team!

---

## 📊 Example Report Structure

```
╔════════════════════════════════════════╗
║   Git Activity Report                  ║
║   September 2026                       ║
╠════════════════════════════════════════╣
║                                        ║
║   📊 77 Commits                        ║
║   📁 245 Files Changed                 ║
║   ➕ +15,234 Lines Added               ║
║   ➖ -3,456 Lines Deleted              ║
║                                        ║
╠════════════════════════════════════════╣
║   📝 Commit Details                    ║
╠════════════════════════════════════════╣
║                                        ║
║ 1. Add MIS API import feature         ║
║    👤 Your Name                        ║
║    📅 2026-09-30 14:30:22             ║
║    📁 3 files changed                  ║
║    [Show Code Changes Button]          ║
║                                        ║
║ 2. Implement RBAC system               ║
║    ...                                 ║
║                                        ║
╚════════════════════════════════════════╝
```

---

## 💡 Pro Tips

### 1. Run at Month End
Generate your report on the last day of the month to capture all work.

### 2. Add Custom Notes
- Open the HTML file
- Add your own commentary about major features
- Highlight key achievements

### 3. Compare Months
Generate reports for multiple months to show trends:
```powershell
C:\xampp\php\php.exe generate_monthly_report.php 7 2026  # July
C:\xampp\php\php.exe generate_monthly_report.php 8 2026  # August
C:\xampp\php\php.exe generate_monthly_report.php 9 2026  # September
```

### 4. Keep PDFs Organized
Create a folder structure:
```
Reports/
  ├── 2024/
  │   ├── monthly_report_january_2024.pdf
  │   ├── monthly_report_february_2024.pdf
  │   └── ...
  ├── 2025/
  │   └── ...
  └── 2026/
      ├── monthly_report_september_2026.pdf
      └── ...
```

---

## 🎯 Common Use Cases

### 1. Manager Monthly Meeting
```powershell
# Generate current month report
C:\xampp\php\php.exe generate_monthly_report.php

# Print highlights:
# - Total commits this month
# - Major features completed
# - Files/lines changed
```

### 2. Performance Review
```powershell
# Generate last 6 months
for /L %m in (4,1,9) do C:\xampp\php\php.exe generate_monthly_report.php %m 2026
```

### 3. Client Progress Report
```powershell
# Generate project month
C:\xampp\php\php.exe generate_monthly_report.php 9 2026

# Highlight:
# - Features delivered
# - Bug fixes completed
# - Code quality improvements
```

---

## 🔧 Troubleshooting

### Issue: "PHP not found"
**Solution:** The batch file looks for PHP at `C:\xampp\php\php.exe`
- If your XAMPP is elsewhere, edit `generate_report.bat`
- Change line 10: `set PHP_PATH=C:\your\path\to\php.exe`

### Issue: "Not a git repository"
**Solution:** You must run this from a Git repository
```powershell
cd c:\xampp\htdocs\public_html
git status  # Verify it's a Git repo
cd tools
C:\xampp\php\php.exe generate_monthly_report.php
```

### Issue: "No commits found"
**Solution:** Check if there are commits in that month
```powershell
# Check commits in September 2026
git log --since="2026-09-01" --until="2026-10-01" --oneline
```

---

## 📂 File Locations

**Script:** `c:\xampp\htdocs\public_html\tools\generate_monthly_report.php`  
**Batch File:** `c:\xampp\htdocs\public_html\tools\generate_report.bat`  
**Reports:** `c:\xampp\htdocs\public_html\tools\monthly_report_*.html`

---

## 🎨 Report Features

### Interactive Elements
- ✨ Click "Show Diff" to expand code changes
- ✨ Scroll through detailed file changes
- ✨ Print-friendly layout
- ✨ Responsive design (works on mobile too!)

### Visual Design
- 🎨 Modern gradient header
- 📊 Summary cards with key metrics
- 🎯 Color-coded additions (green) and deletions (red)
- 💼 Professional layout ready for presentations

---

## 🚀 Advanced Usage

### PowerShell Function
Add this to your PowerShell profile for quick access:

```powershell
function Get-MonthlyReport {
    param(
        [int]$Month = (Get-Date).Month,
        [int]$Year = (Get-Date).Year
    )
    
    $phpPath = "C:\xampp\php\php.exe"
    $scriptPath = "c:\xampp\htdocs\public_html\tools\generate_monthly_report.php"
    
    & $phpPath $scriptPath $Month $Year
    
    $monthName = (Get-Culture).DateTimeFormat.GetMonthName($Month).ToLower()
    $reportFile = "c:\xampp\htdocs\public_html\tools\monthly_report_$($monthName)_$Year.html"
    
    if (Test-Path $reportFile) {
        Start-Process $reportFile
    }
}

# Usage:
# Get-MonthlyReport              # Current month
# Get-MonthlyReport -Month 9 -Year 2026
```

---

## 📧 Sharing Reports

### Email
1. Generate PDF
2. Attach to email
3. Add summary in email body

### Slack/Teams
1. Generate PDF
2. Upload to channel
3. @mention relevant people

### Cloud Storage
1. Save PDFs to Dropbox/Google Drive
2. Share link
3. Set permissions

---

## ✅ Monthly Checklist

```
[ ] Generate report on last day of month
[ ] Review all commits for completeness
[ ] Highlight major features/bug fixes
[ ] Save as PDF
[ ] Share with manager/team
[ ] Archive in Reports folder
[ ] Update project tracker if needed
```

---

## 🎓 Example Monthly Summary (to include with report)

```markdown
# Monthly Development Summary - September 2026

## Overview
- 77 commits completed
- 245 files modified
- 15,234 lines of code added

## Major Achievements
1. ✅ MIS API Integration - Import student data from external system
2. ✅ RBAC System - Role-based access control implemented
3. ✅ Document Upload Enhancement - Improved validation

## Bug Fixes
- Fixed photo preview issues in registration
- Resolved OTP sending problems
- Corrected batch assignment filtering

## Next Month Goals
- Complete certificate generation module
- Optimize database queries
- Add export functionality
```

---

**Made for easy office reporting! 🎉**

For full documentation, see `README_MONTHLY_REPORT.md`
