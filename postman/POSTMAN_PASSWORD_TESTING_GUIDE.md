# 🔐 Testing API with Passwords in Postman

## 🚀 Quick Setup

### Step 1: Import Updated Collection
1. Open Postman
2. Click **Import** button
3. Import: `postman/NIELIT_API_Collection_Updated.json`
4. Import: `postman/NIELIT_Local_Environment.json` (if not already imported)

### Step 2: Configure Environment
1. Select **NIELIT Local Environment** from dropdown
2. Click **eye icon** → **Edit**
3. Set these variables:
   ```
   api_key: efb21393037415f4730b729a23b46764aeceee052f1b541eb43a1f26247812ec
   base_url: http://localhost/public_html
   test_student_id: NIELIT/API/TEST/001
   test_email: apitest1@nielit.gov.in
   test_password: test123
   search_term: rahul
   ```

## 🔍 New Endpoints to Test

### 1. 🔐 Get All Students with Passwords (Sample)
- **Request:** `GET /api/v1/students.php?action=list_with_passwords&limit=10`
- **Purpose:** Get 10 students with password hashes for testing
- **Expected:** Students with password field included

### 2. 🔐 Get ALL Students with Passwords (Full Dataset)
- **Request:** `GET /api/v1/students.php?action=list_with_passwords&limit=1000`
- **Purpose:** Get all 1,034+ students for mock test database import
- **Expected:** Complete dataset with passwords

## 📋 Step-by-Step Testing

### Test 1: Sample Students with Passwords
1. Open request: **"🔐 Get All Students with Passwords"**
2. Click **Send**
3. **Expected Response:**
   ```json
   {
     "status": 200,
     "message": "Success",
     "data": {
       "students": [
         {
           "student_id": "NIELIT/2025/DBC17/0015",
           "name": "Rahul Sethi",
           "email": "sethirahulsethi09406@gmail.com",
           "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi",
           "course_id": 39,
           "training_center": "NIELIT BHUBANESWAR CENTER"
         }
       ],
       "pagination": {
         "total": 1034,
         "limit": 10,
         "has_more": true
       },
       "warning": "This endpoint includes password hashes - use securely"
     }
   }
   ```

### Test 2: Full Dataset for Mock Test
1. Open request: **"🔐 Get ALL Students with Passwords (Full Dataset)"**
2. Click **Send**
3. **Expected:** All 1,034+ students with passwords
4. **Note:** Postman may show "Response body is too large" - this is normal!

### Test 3: Compare with Regular Endpoint
1. Open request: **"Get All Students"** (without passwords)
2. Click **Send**
3. **Notice:** No password field in response
4. **Compare:** This shows the security difference

## 🧪 Automated Tests

The collection includes automated tests that verify:

✅ **Status Code:** Returns 200 OK
✅ **Students Array:** Response contains students array
✅ **Password Field:** Each student has password field
✅ **Password Format:** Password looks like bcrypt hash
✅ **Security Warning:** Response includes warning message
✅ **Pagination:** Response includes pagination info

## 🔧 Manual Testing Steps

### Step 1: Test New Endpoint
```
GET http://localhost/public_html/api/v1/students.php?action=list_with_passwords&limit=5
Headers:
  X-API-Key: efb21393037415f4730b729a23b46764aeceee052f1b541eb43a1f26247812ec
```

### Step 2: Verify Password Field
Look for this in response:
```json
{
  "student_id": "NIELIT/2025/DBC17/0015",
  "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi"
}
```

### Step 3: Test Full Dataset
```
GET http://localhost/public_html/api/v1/students.php?action=list_with_passwords&limit=1000
```

## 🚨 Important Notes

### Security Warnings
- ⚠️ **Password hashes are sensitive data**
- ⚠️ **Use only in secure backend applications**
- ⚠️ **Never expose to client-side code**
- ⚠️ **API includes warning message in response**

### Response Size
- **Small Dataset (limit=10):** Fast response, easy to view
- **Full Dataset (limit=1000):** Large response, may show "too large" message
- **"Too large" message:** This is normal - API is working correctly!

## 🔗 Integration with Mock Test App

### JavaScript Example
```javascript
// Get all students with passwords for your mock test database
const getAllStudentsWithPasswords = async () => {
    const response = await fetch('http://localhost/public_html/api/v1/students.php?action=list_with_passwords&limit=1000', {
        headers: {
            'X-API-Key': 'efb21393037415f4730b729a23b46764aeceee052f1b541eb43a1f26247812ec'
        }
    });
    
    const data = await response.json();
    return data.data.students; // Array with passwords
};

// Verify student password in your mock test app
const verifyPassword = (userPassword, storedHash) => {
    // Use PHP password_verify() equivalent in your backend
    // This is just for demonstration
    return bcrypt.compare(userPassword, storedHash);
};
```

### PHP Integration Example
```php
// In your mock test application
$students = json_decode(file_get_contents('http://localhost/public_html/api/v1/students.php?action=list_with_passwords&limit=1000', false, stream_context_create([
    'http' => [
        'header' => 'X-API-Key: efb21393037415f4730b729a23b46764aeceee052f1b541eb43a1f26247812ec'
    ]
])), true);

foreach ($students['data']['students'] as $student) {
    // Insert into your mock test database
    $stmt = $pdo->prepare("INSERT INTO mock_test_users (student_id, name, email, password_hash) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $student['student_id'],
        $student['name'], 
        $student['email'],
        $student['password']
    ]);
}

// Later, for login verification:
if (password_verify($user_input_password, $stored_password_hash)) {
    // Login successful
}
```

## ✅ Success Indicators

### ✅ Working Correctly When You See:
- Status 200 responses
- Password field in student objects
- Password hashes starting with `$2y$10$`
- Security warning in response
- Total count > 1000 students

### ❌ Issues to Check:
- Status 401: Check API key
- Status 404: Check URL path
- No password field: Using wrong action parameter
- Empty response: Check if students are approved

## 🎯 Next Steps

1. **Test in Postman:** Use the new collection
2. **Verify Passwords:** Confirm password hashes are included
3. **Test Full Dataset:** Get all 1,034+ students
4. **Integrate:** Use in your mock test application
5. **Secure Usage:** Keep password hashes in backend only

**Your API now provides everything needed for mock test integration! 🎉**