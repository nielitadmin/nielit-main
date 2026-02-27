# Centre Management Guide

## Overview

The Centre Management feature allows administrators to manage multiple training centres and associate courses with specific locations. This is useful for organizations operating multiple training facilities.

## Accessing Centre Management

1. Log in to the admin panel
2. Navigate to **System Settings** in the sidebar
3. Click **Manage Centres**

## Understanding Centres

A **centre** represents a physical training location where courses are conducted. Each centre has:

- **Name** - Full name of the training centre (e.g., "NIELIT Bhubaneswar")
- **Code** - Unique identifier (e.g., "BBSR", "BLS")
- **Address** - Physical location details
- **Contact Information** - Phone and email
- **Status** - Active or Inactive

## Adding a New Centre

### Step-by-Step Instructions

1. **Open the Add Centre Form**
   - Click the **Add New Centre** button at the top of the page
   - A modal form will appear

2. **Fill in Centre Details**
   
   **Required Fields:**
   - **Centre Name** - Enter the full name (e.g., "NIELIT Balasore Extension")
   - **Centre Code** - Enter a unique 2-10 character code using uppercase letters and numbers only (e.g., "BLS")
   
   **Optional Fields:**
   - **Address** - Street address of the centre
   - **City** - City name
   - **State** - State name
   - **Pincode** - 6-digit postal code
   - **Phone** - Contact phone number (10-20 characters)
   - **Email** - Contact email address

3. **Validate Your Input**
   
   The system will check:
   - Centre code is unique (not already used)
   - Centre code format is correct (uppercase alphanumeric, 2-10 characters)
   - Email format is valid (if provided)
   - Phone format is valid (if provided)
   - Pincode is 6 digits (if provided)

4. **Save the Centre**
   - Click **Save Centre**
   - If successful, you'll see a success message
   - The new centre will appear in the centres list
   - If there are errors, review the error messages and correct the issues

### Example: Adding NIELIT Balasore

```
Centre Name: NIELIT Balasore Extension Centre
Centre Code: BLS
Address: Plot No. 123, Industrial Estate
City: Balasore
State: Odisha
Pincode: 756001
Phone: +91-6782-123456
Email: balasore@nielit.gov.in
```

### Centre Code Guidelines

**Valid Centre Codes:**
- `BBSR` - Bhubaneswar
- `BLS` - Balasore
- `CTC` - Cuttack
- `PURI` - Puri
- `SAMB` - Sambalpur

**Invalid Centre Codes:**
- `bbsr` - Must be uppercase
- `B` - Too short (minimum 2 characters)
- `BHUBANESWAR123` - Too long (maximum 10 characters)
- `BLS-01` - No special characters allowed

## Editing an Existing Centre

### Step-by-Step Instructions

1. **Locate the Centre**
   - Find the centre in the centres list
   - Use the search box to filter if needed

2. **Open the Edit Form**
   - Click the **Edit** button (pencil icon) next to the centre
   - The edit modal will appear with current values pre-filled

3. **Modify Centre Details**
   - Update any fields you want to change
   - The centre code can be changed, but must remain unique

4. **Save Changes**
   - Click **Update Centre**
   - If successful, you'll see a success message
   - The centres list will refresh with updated information

### What You Can Edit

- Centre name
- Centre code (must remain unique)
- Address, city, state, pincode
- Phone and email
- Active status (see next section)

### What You Cannot Edit

- Centre ID (automatically assigned)
- Creation timestamp
- Last modified timestamp (automatically updated)

## Activating and Deactivating Centres

### Why Deactivate a Centre?

Deactivating a centre is useful when:
- The centre is temporarily closed
- The centre has been permanently closed but you want to keep historical records
- You want to hide the centre from public course listings

**Important:** Deactivating a centre does NOT delete it or remove course associations.

### How to Deactivate a Centre

1. Find the centre in the list
2. Click the **Deactivate** button (toggle switch)
3. Confirm the action
4. The centre status will change to "Inactive"

### How to Reactivate a Centre

1. Find the inactive centre in the list
2. Click the **Activate** button (toggle switch)
3. The centre status will change to "Active"

### Effects of Deactivation

**What Happens:**
- Centre is hidden from public course filter dropdowns
- Centre no longer appears in "active centres" lists
- Existing course associations are maintained

**What Doesn't Happen:**
- Centre data is NOT deleted
- Courses associated with the centre are NOT affected
- Historical records remain intact

## Assigning Centres to Courses

Once you've created centres, you can assign them to courses.

### Step-by-Step Instructions

1. **Navigate to Course Management**
   - Go to **Manage Courses** from the admin sidebar

2. **Create or Edit a Course**
   - Click **Add New Course** or **Edit** an existing course

3. **Select the Centre**
   - Find the **Training Centre** dropdown in the course form
   - Select the appropriate centre from the list
   - Only active centres appear in the dropdown

4. **Save the Course**
   - Complete the rest of the course details
   - Click **Save Course**

### Example: Assigning a Course to Balasore Centre

```
Course Name: Certificate in Web Design
Course Code: CWD
Training Centre: NIELIT Balasore Extension Centre
Duration: 6 months
Fees: ₹15,000
```

### Removing Centre Assignment

To remove a centre assignment from a course:

1. Edit the course
2. Select "-- No Centre --" from the Training Centre dropdown
3. Save the course

## Filtering Courses by Centre

### Admin View

In the **Manage Courses** page:

1. Look for the **Filter by Centre** dropdown
2. Select a centre to view only courses from that centre
3. Select "All Centres" to view all courses

### Public View

On the public courses page (`/public/courses.php`):

1. Visitors will see a **Filter by Centre** dropdown
2. Only active centres appear in the dropdown
3. Selecting a centre shows only courses from that location
4. This helps students find courses at their preferred location

## Viewing Centre Information

### Centres List View

The main centres list displays:

- **Centre Name** - Full name of the centre
- **Code** - Unique identifier
- **Location** - City and state
- **Contact** - Phone and email
- **Status** - Active (green badge) or Inactive (red badge)
- **Actions** - Edit and Activate/Deactivate buttons

### Sorting and Searching

**Search:**
- Use the search box to filter centres by name, code, city, or state
- Search is case-insensitive
- Results update as you type

**Sorting:**
- Click column headers to sort
- Default sort is by centre name (A-Z)

## Best Practices

### Naming Conventions

**Centre Names:**
- Use full, official names
- Include "NIELIT" prefix for consistency
- Example: "NIELIT Bhubaneswar Main Centre"

**Centre Codes:**
- Keep codes short and memorable
- Use city abbreviations when possible
- Be consistent (all uppercase)
- Examples: BBSR, BLS, CTC, PURI

### Data Entry Tips

1. **Complete Information** - Fill in all fields for better record-keeping
2. **Verify Contact Details** - Ensure phone and email are correct
3. **Consistent Formatting** - Use consistent address formats
4. **Regular Updates** - Keep contact information current

### Organizational Strategy

1. **Plan Centre Codes** - Decide on a coding scheme before adding centres
2. **Document Centres** - Keep a list of all centres and their codes
3. **Regular Audits** - Periodically review centre information for accuracy
4. **Deactivate Properly** - Don't delete centres; deactivate them instead

## Common Scenarios

### Scenario 1: Opening a New Training Centre

**Task:** Add a new extension centre in Cuttack

**Steps:**
1. Click **Add New Centre**
2. Enter details:
   - Name: "NIELIT Cuttack Extension Centre"
   - Code: "CTC"
   - Address: Complete address
   - Contact: Phone and email
3. Save the centre
4. Go to **Manage Courses**
5. Assign relevant courses to the new centre

### Scenario 2: Temporarily Closing a Centre

**Task:** Temporarily close Balasore centre for renovation

**Steps:**
1. Find "NIELIT Balasore Extension Centre" in the list
2. Click **Deactivate**
3. The centre will be hidden from public listings
4. Existing course associations remain intact
5. When renovation is complete, click **Activate** to reopen

### Scenario 3: Updating Centre Contact Information

**Task:** Update phone number for Bhubaneswar centre

**Steps:**
1. Find "NIELIT Bhubaneswar" in the list
2. Click **Edit**
3. Update the phone number
4. Click **Update Centre**
5. Verify the change in the centres list

### Scenario 4: Organizing Courses by Location

**Task:** Assign all web design courses to Bhubaneswar, all hardware courses to Balasore

**Steps:**
1. Go to **Manage Courses**
2. For each web design course:
   - Click **Edit**
   - Select "NIELIT Bhubaneswar" from Training Centre dropdown
   - Save
3. For each hardware course:
   - Click **Edit**
   - Select "NIELIT Balasore Extension Centre"
   - Save
4. Verify assignments in the courses list

## Troubleshooting

### Error: "Centre code already exists"

**Problem:** You're trying to use a code that's already assigned to another centre

**Solution:**
- Choose a different, unique code
- Check the centres list to see which codes are in use
- Consider using a variation (e.g., "BLS2" if "BLS" is taken)

### Error: "Invalid centre code format"

**Problem:** The code doesn't meet format requirements

**Solution:**
- Use only uppercase letters (A-Z) and numbers (0-9)
- Ensure code is 2-10 characters long
- Remove any spaces or special characters
- Examples: "BBSR" ✓, "bbsr" ✗, "BLS-01" ✗

### Error: "Invalid email format"

**Problem:** The email address is not valid

**Solution:**
- Check for typos
- Ensure format is: name@domain.com
- Remove any spaces
- Example: "balasore@nielit.gov.in" ✓, "balasore@nielit" ✗

### Error: "Invalid phone format"

**Problem:** The phone number doesn't meet requirements

**Solution:**
- Use 10-20 characters
- Allowed characters: digits, spaces, hyphens, plus sign, parentheses
- Examples: "+91-6782-123456" ✓, "06782123456" ✓, "abc123" ✗

### Error: "Pincode must be 6 digits"

**Problem:** The pincode is not exactly 6 digits

**Solution:**
- Enter exactly 6 numeric digits
- Examples: "756001" ✓, "75600" ✗, "756001-1" ✗

### Centre not appearing in course dropdown

**Problem:** You can't select a centre when editing a course

**Solution:**
- Verify the centre is marked as "Active"
- Inactive centres don't appear in dropdowns
- Activate the centre if needed

### Changes not visible on public website

**Problem:** Centre filter not showing on courses page

**Solution:**
- Clear browser cache (Ctrl+F5)
- Verify the centre is active
- Ensure at least one course is assigned to the centre
- Check that courses are published

## Screenshots

### [Screenshot Placeholder: Centres List View]
*Shows the main centres management page with list of centres, search box, and Add New Centre button*

### [Screenshot Placeholder: Add Centre Modal]
*Shows the add centre form with all fields (name, code, address, contact)*

### [Screenshot Placeholder: Edit Centre Modal]
*Shows the edit centre form with pre-filled values*

### [Screenshot Placeholder: Centre Status Toggle]
*Shows active/inactive toggle buttons and status badges*

### [Screenshot Placeholder: Course Centre Assignment]
*Shows the Training Centre dropdown in the course edit form*

### [Screenshot Placeholder: Public Centre Filter]
*Shows the centre filter dropdown on the public courses page*

---

**Related Guides:**
- [Main User Guide](USER_GUIDE.md)
- [Theme Customization Guide](THEME_CUSTOMIZATION_GUIDE.md)
- [Homepage Content Management Guide](HOMEPAGE_CONTENT_GUIDE.md)

**Document Version:** 1.0  
**Last Updated:** 2024
