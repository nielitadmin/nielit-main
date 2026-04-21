# NIELIT API - Postman Testing Guide

## 🚀 Quick Start

### Step 1: Get Your API Key
First, generate your API key by visiting:
```
http://localhost/public_html/admin/generate_api_key_now.php
```

### Step 2: Import Collection & Environment
1. Open Postman
2. Click **Import** button
3. Import these files:
   - `postman/NIELIT_API_Collection.json` (Collection)
   - `postman/NIELIT_Local_Environment.json` (Environment)

### Step 3: Configure Environment
1. Select **NIELIT Local Environment** from environment dropdown
2. Click the **eye icon** → **Edit**
3. Update these variables:
   - `api_key`: Paste your generated API key
   - `test_student_id`: Use a real student ID from your database
   - `test_email`: Use a real student email from your database
   - `test_password`: Use the actual password for that student

### Step 4: Run Tests
1. Select the **NIELIT API - Mock Test Integration** collection
2. Click **Run** button
3. Select all requests
4. Click **Run NIELIT API**

---

## 📋 Available Test Requests

### 🔐 Authentication Tests
- **Student Login - POST**: Authenticate using student_id
- **Student Login - Email**: Authenticate using email address

### 👥 Student Data Tests
- **Get All Students**: List all approved students
- **Get Student by ID**: Retrieve specific student by ID
- **Get Student by Email**: Retrieve specific student by email
- **Search Students**: Search students by name/email/ID

### ❌ Error Handling Tests
- **Invalid API Key**: Test with wrong API key
- **Missing API Key**: Test without API key
- **Invalid Student Login**: Test with wrong credentials

---

## 🔧 Manual Testing Steps

### Test 1: Student Authentication
```http
POST http://localhost/public_html/api/v1/auth.php
Headers:
  Content-Type: application/json
  X-API-Key: YOUR_API_KEY

Body:
{
    "username": "STUDENT_ID_OR_EMAIL",
    "password": "STUDENT_PASSWORD"
}
```

**Expected Response:**
```json
{
    "status": 200,
    "message": "Success",
    "timestamp": "2024-04-10T...",
    "data": {
        "token": "auth_token_here",
        "student": {
            "student_id": "...",
            "name": "...",
            "email": "...",
            "course_id": "...",
            "training_center": "..."
        }
    }
}
```

### Test 2: Get Student List
```http
GET http://localhost/public_html/api/v1/students.php?action=list
Headers:
  X-API-Key: YOUR_API_KEY
```

**Expected Response:**
```json
{
    "status": 200,
    "message": "Success",
    "data": {
        "students": [
            {
                "student_id": "...",
                "name": "...",
                "email": "...",
                "course_id": "...",
                "training_center": "...",
                "status": "approved"
            }
        ],
        "total": 10
    }
}
```

### Test 3: Get Specific Student
```http
GET http://localhost/public_html/api/v1/students.php?action=get&student_id=STUDENT_ID
Headers:
  X-API-Key: YOUR_API_KEY
```

---

## 🎯 Testing Scenarios for Mock Test Integration

### Scenario 1: Student Login Flow
1. **POST** `/api/v1/auth.php` with student credentials
2. Verify response contains `token` and `student` data
3. Use `student_id` as username for your mock test app

### Scenario 2: Student Data Retrieval
1. **GET** `/api/v1/students.php?action=get&student_id=ID`
2. Verify student details are returned
3. Use this data to populate student profile in mock test

### Scenario 3: Student Search
1. **GET** `/api/v1/students.php?action=search&query=TERM`
2. Test searching by name, email, or student_id
3. Useful for student lookup features

### Scenario 4: Error Handling
1. Test with invalid API key → Should return 401
2. Test with missing API key → Should return 401
3. Test with invalid student credentials → Should return 401

---

## 🔍 Automated Test Validation

Each request includes automated tests that check:

✅ **Status Codes**: Correct HTTP response codes
✅ **Response Structure**: Required fields are present
✅ **Data Types**: Fields have correct data types
✅ **Authentication**: Token generation and validation
✅ **Error Handling**: Proper error responses

---

## 🚨 Common Issues & Solutions

### Issue: "Invalid API Key"
**Solution**: 
1. Generate new API key: `admin/generate_api_key_now.php`
2. Update environment variable `api_key`

### Issue: "Student not found"
**Solution**:
1. Check your database for approved students
2. Update `test_student_id` and `test_email` variables
3. Ensure student status is 'approved'

### Issue: "Connection refused"
**Solution**:
1. Ensure XAMPP is running
2. Verify URL: `http://localhost/public_html`
3. Check if API files exist in `/api/v1/` folder

### Issue: "Authentication failed"
**Solution**:
1. Verify student credentials in database
2. Check password field in students table
3. Ensure student status is 'approved'

---

## 📊 Test Results Interpretation

### ✅ Successful Test Run
- All requests return 200 status
- Authentication returns valid token
- Student data is properly formatted
- Error tests return appropriate error codes

### ❌ Failed Test Run
- Check console for specific error messages
- Verify API key is correct
- Ensure test data (student_id, email, password) exists
- Confirm database connection is working

---

## 🔄 Integration with Mock Test App

Once tests pass, integrate with your mock test application:

### 1. Authentication Endpoint
```javascript
// In your mock test app
const authenticateStudent = async (username, password) => {
    const response = await fetch('http://localhost/public_html/api/v1/auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': 'YOUR_API_KEY'
        },
        body: JSON.stringify({ username, password })
    });
    
    const data = await response.json();
    return data;
};
```

### 2. Student Data Retrieval
```javascript
const getStudentData = async (studentId) => {
    const response = await fetch(`http://localhost/public_html/api/v1/students.php?action=get&student_id=${studentId}&api_key=YOUR_API_KEY`);
    const data = await response.json();
    return data;
};
```

---

## 📝 Next Steps

1. **Generate API Key**: Visit `admin/generate_api_key_now.php`
2. **Import Collections**: Import both JSON files into Postman
3. **Configure Environment**: Update variables with real data
4. **Run Tests**: Execute full test suite
5. **Integrate**: Use working endpoints in your mock test app

---

## 🆘 Need Help?

If tests fail or you encounter issues:
1. Check the **Console** tab in Postman for detailed errors
2. Verify your database has approved students
3. Ensure XAMPP/Apache is running
4. Confirm API files exist and are accessible
5. Test individual requests before running the full suite

**Happy Testing! 🎉**