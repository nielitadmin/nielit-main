<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A4 Membership Form</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="a4-page">
        <!-- Header Section -->
        <div class="header">
            <div class="logo-left">
                <img src="logo1.png" alt="Logo 1">
            </div>
            <div class="header-text">
                <p class="main-title">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</p>
                <p class="subtitle">National Institute of Electronics & Information Technology (NIELIT), Bhubaneswar</p>
                <p class="subtext">(Under Ministry of Electronics & Information Technology, Government of India)</p>
                <p class="address">3rd Floor, OCAC Tower, Acharya Vihar, Bhubaneswar-751013, Odisha</p>
            </div>
            <div class="logo-right">
                <img src="logo2.png" alt="Logo 2">
                <img src="logo3.png" alt="Logo 3">
            </div>
        </div>

        <!-- Form Section -->
        <form class="form-container" action="submit_form.php" method="POST" enctype="multipart/form-data">
            <h1>To be filled by the candidates (Fill in CAPITAL Letters only)</h1>

            <!-- Section 1: School Information -->
            <h2>School Information</h2>
            <div class="form-row">
                <div class="form-column">
                    <div class="form-group">
                        <label for="schoolName">School Name:<span class="required">*</span></label>
                        <input type="text" id="schoolName" name="schoolName" required aria-required="true" placeholder="Enter school name">
                    </div>
                    <div class="form-group">
                        <label for="class">Class:<span class="required">*</span></label>
                        <input type="text" id="class" name="class" required aria-required="true" placeholder="Enter class">
                    </div>
                    <div class="form-group">
                        <label for="section">Section:</label>
                        <input type="text" id="section" name="section" placeholder="Enter section">
                    </div>
                    <div class="form-group">
                        <label for="trainingDate">Date of Training:<span class="required">*</span></label>
                        <input type="date" id="trainingDate" name="trainingDate" required aria-required="true">
                    </div>
                </div>
                <div class="form-column photo-column">
                    <div class="photo-placeholder">
                        <label for="photo">Photograph:</label>
                        <img id="photoPreview" src="#" alt="Photo Preview" style="display: none;" aria-hidden="true">
                        <div id="photoPlaceholder" role="img" aria-label="Photograph Placeholder">Photograph</div>
                        <input type="file" id="photo" name="photo" accept="image/*" onchange="previewPhoto(event)">
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal Information -->
            <h2>Personal Information</h2>
            <div class="form-group">
                <label for="fullName">Full Name:<span class="required">*</span></label>
                <input type="text" id="fullName" name="fullName" required aria-required="true" placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth:<span class="required">*</span></label>
                <input type="date" id="dob" name="dob" required aria-required="true">
            </div>
            <div class="form-group gender-group">
                <label>Gender:<span class="required">*</span></label>
                <div class="gender-toggle">
                    <input type="radio" id="male" name="gender" value="Male" required aria-required="true">
                    <label for="male" class="gender-option">Male</label>

                    <input type="radio" id="female" name="gender" value="Female" required aria-required="true">
                    <label for="female" class="gender-option">Female</label>

                    <input type="radio" id="other" name="gender" value="Others" required aria-required="true">
                    <label for="other" class="gender-option">Others</label>
                </div>
            </div>
            <div class="form-group">
                <label for="nationality">Nationality:<span class="required">*</span></label>
                <input type="text" id="nationality" name="nationality" required aria-required="true" placeholder="Enter your nationality">
            </div>
            <div class="form-group">
                <label for="phoneNumber">Phone Number:<span class="required">*</span></label>
                <input type="text" id="phoneNumber" name="phoneNumber" required pattern="\d{10}" title="Phone number must be 10 digits" placeholder="Enter phone number">
            </div>
            <div class="form-group">
                <label for="aadhar">Aadhar Number:<span class="required">*</span></label>
                <input type="text" id="aadhar" name="aadhar" required pattern="\d{12}" title="Aadhar number must be 12 digits" placeholder="Enter Aadhar number">
            </div>
            <div class="form-group category-group">
                <label for="category">Category:<span class="required">*</span></label>
                <select id="category" name="category" required>
                    <option value="" disabled selected>-- Select Category --</option>
                    <option value="GEN">General</option>
                    <option value="OBC">OBC</option>
                    <option value="SC">SC</option>
                    <option value="ST">ST</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <button type="submit">Submit</button>
                <button type="button" onclick="window.print()">Print Form</button>
            </div>
        </form>
    </div>

    <!-- JavaScript for Photo Preview -->
    <script>
        function previewPhoto(event) {
            const photoPreview = document.getElementById('photoPreview');
            const photoPlaceholder = document.getElementById('photoPlaceholder');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.src = e.target.result;
                    photoPreview.style.display = 'block';
                    photoPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
