# Faculty Management Workflow Guide

## 🎯 **Where Course Coordinators Add Faculty Names**

### **Step 1: Access Admission Order Generation**
1. Login as Course Coordinator
2. Go to **Batch Management** → **Generate Admission Order**
3. Select your batch from the dropdown
4. Click **"Generate Admission Order"**

### **Step 2: Faculty Selection Interface**
In the **"Edit Order Details"** section (blue box at top), you'll see:

```
Faculty Name: [Multi-select Dropdown]
┌─────────────────────────────────────────┐
│ ☑ Dr. Rajesh Kumar (Senior Faculty)    │
│ ☐ Prof. Priya Sharma (Assoc Professor) │
│ ☐ Mr. Amit Singh (Assistant Professor)  │
│ ☐ Dr. Sunita Patel (Professor)         │
│ ☐ Ms. Kavita Joshi (Lecturer)          │
└─────────────────────────────────────────┘
Hold Ctrl/Cmd to select multiple faculty members
```

### **Step 3: Select Faculty Members**
- **Single Faculty:** Click on one faculty name
- **Multiple Faculty:** Hold `Ctrl` (Windows) or `Cmd` (Mac) and click multiple names
- **Deselect:** Click again while holding Ctrl/Cmd

### **Step 4: Save Changes**
- Click **"Save Changes & Regenerate"** button
- Faculty assignments are saved to database
- Admission order updates to show selected faculty

## 🔄 **Complete Workflow**

### **For Master Admins (Add New Faculty to System):**
1. Go to `admin/manage_faculty.php`
2. Click **"Add Faculty"** button
3. Fill in faculty details:
   - Name (required)
   - Email
   - Phone
   - Designation (e.g., Professor, Assistant Professor)
   - Department (e.g., Computer Science, IT)
4. Click **"Add Faculty"**

### **For Course Coordinators (Assign Faculty to Batches):**
1. Go to `batch_module/admin/generate_admission_order.php`
2. Select batch and generate admission order
3. In the **Faculty Name** dropdown, select faculty members
4. Click **"Save Changes & Regenerate"**
5. Faculty names appear in the generated admission order

## 📋 **What Happens After Selection**

### **In the Admission Order Document:**
```
Faculty Name: Dr. Rajesh Kumar (Senior Faculty), Prof. Priya Sharma (Associate Professor)
```

### **In the Database:**
- `batch_faculty` table stores the assignments
- Each faculty-batch relationship is recorded
- Assignments persist across sessions

## 🎯 **Key Features**

✅ **Multi-Select:** Choose multiple faculty per batch
✅ **Real-time Preview:** See changes immediately
✅ **Persistent Storage:** Assignments saved to database
✅ **Professional Display:** Faculty shown with designations
✅ **Role-Based Access:** Course coordinators can only assign, not add new faculty

## 🚀 **Quick Access URLs**

- **Faculty Management (Master Admin):** `/admin/manage_faculty.php`
- **Faculty Assignment (Course Coordinator):** `/batch_module/admin/generate_admission_order.php`

## 💡 **Tips**

1. **New Faculty:** If you need a new faculty member added to the system, contact your Master Admin
2. **Multiple Selection:** Use Ctrl+Click (Windows) or Cmd+Click (Mac) for multiple selections
3. **Designations:** Faculty designations automatically appear in parentheses
4. **Persistence:** Once saved, faculty assignments remain until changed
5. **Preview:** Changes show immediately in the preview before saving