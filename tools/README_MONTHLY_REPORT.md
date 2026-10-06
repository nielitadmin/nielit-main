# 📊 Monthly Git Activity Report Generator

## Overview
This tool generates a **beautiful HTML report** showing all your Git commits, code changes, and statistics for any given month - perfect for office monthly reporting!

## Features
✅ **Complete Commit History** - Shows every commit you made in the month  
✅ **Code Statistics** - Total commits, files changed, lines added/deleted  
✅ **Detailed Changes** - View all files modified in each commit  
✅ **Visual Diff** - See actual code changes with expand/collapse  
✅ **Professional Design** - Beautiful, modern interface ready to present  
✅ **Print Ready** - One-click print or save as PDF  
✅ **Responsive** - Looks great on desktop and mobile  

## Quick Start

### Generate Report for Current Month
```powershell
cd c:\xampp\htdocs\public_html\tools
php generate_monthly_report.php
```

### Generate Report for Specific Month
```powershell
# Format: php generate_monthly_report.php [month] [year]

# January 2025
php generate_monthly_report.php 1 2025

# December 2024
php generate_monthly_report.php 12 2024

# February 2025
php generate_monthly_report.php 2 2025
```

## Output
The script creates an HTML file: `monthly_report_[month]_[year].html`

**Example:** `monthly_report_january_2025.html`

## How to View the Report

### Option 1: Open in Browser
1. Run the script
2. Open the generated HTML file in any browser
3. The report will display with full interactivity

### Option 2: Print/Save as PDF
1. Open the HTML file in browser
2. Click the **"🖨️ Print Report"** button (bottom right)
3. In print dialog:
   - Select "Save as PDF" as destination
   - Click Save
4. Share the PDF with your manager/team!

## What the Report Shows

### Summary Cards
- **Total Commits** - How many commits you made
- **Files Changed** - Number of unique files modified
- **Lines Added** - Total code additions (green)
- **Lines Deleted** - Total code removals (red)

### For Each Commit
- ✍️ Commit message
- 👤 Author name
- 📅 Date and time
- 🔑 Commit hash (short version)
- 📁 List of all files changed with +/- stats
- 🔍 Full code diff (expandable)

## Examples

### Monthly Team Meeting Report
```powershell
# Generate January report
php generate_monthly_report.php 1 2025

# Open monthly_report_january_2025.html in browser
# Print to PDF
# Share with team!
```

### Quarterly Review
```powershell
# Generate reports for all 3 months
php generate_monthly_report.php 10 2024  # October
php generate_monthly_report.php 11 2024  # November  
php generate_monthly_report.php 12 2024  # December

# Combine PDFs or present all 3 reports
```

## Tips for Office Reporting

1. **Run at Month End** - Generate report on last day of month for complete data

2. **Add Context** - Open the HTML file and add notes about major features

3. **Highlight Key Commits** - Use the search feature (Ctrl+F) to find important work

4. **Compare Months** - Generate multiple months to show productivity trends

5. **Save PDFs** - Keep monthly PDFs in a folder for annual review

## Customization

The report uses modern colors and design. If you want to customize:
- Open `generate_monthly_report.php`
- Edit the `<style>` section (around line 150)
- Change colors, fonts, or layout as needed

## Troubleshooting

### "Not a git repository" Error
**Solution:** Make sure you're in a directory with Git initialized
```powershell
cd c:\xampp\htdocs\public_html
git status  # Verify git works
cd tools
php generate_monthly_report.php
```

### "No commits found"
**Solution:** Check if you have commits in that month
```powershell
# Check all commits in January 2025
git log --since="2025-01-01" --until="2025-02-01"
```

### Invalid Month/Year
**Solution:** Use valid numbers
- Month: 1-12
- Year: 2020-2030

## Advanced Usage

### Include All Branches
The script already includes all branches by default using `git log --all`

### Custom Date Range
Edit the script to change date ranges if needed

### Multiple Authors
If multiple people work on the repo, the report shows everyone's commits
Filter by author if needed using git commands

## Report Locations

All reports are saved in: `c:\xampp\htdocs\public_html\tools\`

Example files:
- `monthly_report_january_2025.html`
- `monthly_report_february_2025.html`
- `monthly_report_december_2024.html`

## Sample Output Preview

```
📊 Git Activity Report
January 2025

╔════════════════╗
║ 47 Commits     ║
║ 132 Files      ║
║ +12,458 Lines  ║
║ -3,244 Lines   ║
╚════════════════╝

📝 Commit Details

1. Add MIS API import to digitalcertificate admin students page
   👤 nielitadmin
   📅 2025-01-15 14:30:22
   🔑 aa47d52
   
   📁 Files Changed (3)
   - digitalcertificate/config/mis_api.php     +25 -0
   - digitalcertificate/admin/students.php    +180 -2
   
   🔍 [Show Full Diff Button]

2. Implement RBAC system for admin roles
   ...
```

## Professional Presentation Tips

1. **Executive Summary** - Start with the summary cards highlighting key metrics

2. **Major Features** - Point out commits related to important features

3. **Problem Solving** - Highlight bug fixes and improvements

4. **Code Quality** - Show refactoring and optimization commits

5. **Documentation** - Include commits for docs, specs, and guides

## Automation Ideas

### Batch File for Quick Generation
Create `generate_current_month.bat`:
```batch
@echo off
cd c:\xampp\htdocs\public_html\tools
php generate_monthly_report.php
start "" "monthly_report_%date:~-7,2%_2025.html"
```

### PowerShell Function
Add to PowerShell profile:
```powershell
function New-MonthlyReport {
    param([int]$Month = (Get-Date).Month, [int]$Year = (Get-Date).Year)
    cd c:\xampp\htdocs\public_html\tools
    php generate_monthly_report.php $Month $Year
}
```

Then just run: `New-MonthlyReport`

## Support

For questions or issues:
1. Check the troubleshooting section
2. Verify Git is working: `git --version`
3. Ensure PHP is installed: `php --version`
4. Check file permissions in tools directory

## Future Enhancements

Possible additions:
- Email reports automatically
- Generate charts/graphs
- Compare month-over-month
- Filter by file type or directory
- Integration with project management tools

---

**Made with ❤️ for easy monthly reporting**

Last Updated: January 2025
