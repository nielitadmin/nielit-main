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
                <img src="logo2.png" alt="Logo 2">
                <img src="logo3.png" alt="Logo 3">
            </div>
            <div class="header-text">
                <p class="main-title">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</p>
                <p class="subtitle">National Institute of Electronics & Information Technology (NIELIT), Bhubaneswar</p>
                <p class="subtext">(Under Ministry of Electronics & Information Technology, Government of India)</p>
                <p class="address">3rd Floor, OCAC Tower, Acharya Vihar, Bhubaneswar-751013, Odisha</p>
            </div>
            <div class="logo-right">
                <img src="logo1.png" alt="Logo 1">
            </div>
        </div>
        <div class="footer">



        <!-- Form Section -->
        <form class="form-container" action="submit_form.php" method="POST" enctype="multipart/form-data">
            <h1>To be filled by the candidates (Fill in CAPITAL Letters only)</h1>


             <!-- Footer Section -->
    <div class="footer">
        <p>NIELIT BHUBANESWAR | NIELIT BALASORE EXTENSION CENTER</p>
    </div>

            <!-- Section 1: School Information and Photograph -->
            <div class="form-row">
                <!-- School Information -->
                <div class="form-column">
                    <h2>School Information</h2>
                    <div class="form-group">
                        <label for="schoolName">School Name:<span class="required">*</span></label>
                        <select id="schoolName" name="schoolName" required>
                            <option value="" disabled selected>-- Select School --</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.2, CRPF CAMPUS, BBSR">PM SHRI KENDRIYA VIDYALAYA, NO.2, CRPF CAMPUS, BBSR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1, SATYA NAGAR, BBSR">PM SHRI KENDRIYA VIDYALAYA, NO.1, SATYA NAGAR, BBSR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.3, SATYA NAGAR, BBSR">PM SHRI KENDRIYA VIDYALAYA, NO.3, MANCHESWAR, BBSR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1, BALASOR, BALASOR">PM SHRI KENDRIYA VIDYALAYA, NO.1, BALASOR, BALASOR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.5, KALINGA VIHAR, BBSR">PM SHRI KENDRIYA VIDYALAYA, NO.5, KALINGA VIHAR, BBSR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.2, SAMBALPUR, SAMBALPUR">PM SHRI KENDRIYA VIDYALAYA, NO.2, SAMBALPUR, SAMBALPUR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1, SAMBALPUR, SAMBALPUR">PM SHRI KENDRIYA VIDYALAYA, NO.1, SAMBALPUR, SAMBALPUR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1,MAHULDIHA,RAIRANGPUR,MAYURBHANJ">PM SHRI KENDRIYA VIDYALAYA, NO.1, MAHULDIHA,RAIRANGPUR,MAYURBHANJ</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1,KENDRIYA VIDYALAYA NO-1, BARIPADA,MAYURBHANJ">PM SHRI KENDRIYA VIDYALAYA, NO.1,KENDRIYA VIDYALAYA NO-1, BARIPADA,MAYURBHANJ</option>
                            <option value="PM SHRI JAWAHAR NAVODAYA VIDYALAYA, JAGDALPUR, BASTAR ">PM SHRI JAWAHAR NAVODAYA VIDYALAYA, JAGDALPUR, BASTAR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, HAATKACHOURA, JAGDALPUR , BASTAR">PM SHRI KENDRIYA VIDYALAYA, HAATKACHOURA, JAGDALPUR , BASTAR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, BHADRAK">PM SHRI KENDRIYA VIDYALAYA, BHADRAK</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, INS CHILKA (NAVAL BASE,BARKUL)">PM SHRI KENDRIYA VIDYALAYA, INS CHILKA (NAVAL BASE,BARKUL)</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA,  NO.1 PURI">PM SHRI KENDRIYA VIDYALAYA, NO.1 PURI</option>
                        </select>
                            
                        
                    </div>
                    <div class="form-group">
                        <label for="class">Class:<span class="required">*</span></label>
                        <input type="text" id="class" name="class" required placeholder="Enter class">
                    </div>
                    <div class="form-group">
                        <label for="section">Section:</label>
                        <input type="text" id="section" name="section" placeholder="Enter section">
                    </div>
                    <div class="form-group">
                        <label for="trainingDate">Date of Training:<span class="required">*</span></label>
                        <input type="date" id="trainingDate" name="trainingDate" required>
                    </div>
                </div>

                <!-- Photograph Section -->
                <div class="form-column photo-column">
                    <h2>Photograph</h2>
                    <div id="webcam-container">
                        <video id="webcam" autoplay playsinline></video>
                    </div>
                    <canvas id="canvas" style="display: none;"></canvas>
                    <div class="button-group">
                        <button type="button" id="capture-btn">Capture Photo</button>
                        <button type="button" id="retake-btn" style="display: none;">Retake</button>
                    </div>
                    <input type="hidden" name="capturedPhoto" id="capturedPhoto">
                    <div id="preview-container" style="display: none;">
                        <img id="preview" alt="Captured Photo Preview">
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal Information -->
            <h2>Personal Information</h2>
            <div class="form-group">
                <label for="fullName">Full Name:<span class="required">*</span></label>
                <input type="text" id="fullName" name="fullName" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
    <label for="fathersName">Father's Name:<span class="required">*</span></label>
    <input type="text" id="fathersName" name="father_name" required placeholder="Enter father's name">
</div>

            <div class="form-group">
                <label for="dob">Date of Birth:<span class="required">*</span></label>
                <input type="date" id="dob" name="dob" required>
            </div>

            <!-- Gender Section -->
            <div class="form-group">
                <label>Gender:<span class="required">*</span></label>
                <div class="modern-toggle">
                    <input type="radio" id="male" name="gender" value="Male" required>
                    <label for="male">Male</label>

                    <input type="radio" id="female" name="gender" value="Female" required>
                    <label for="female">Female</label>

                    <input type="radio" id="other" name="gender" value="Other" required>
                    <label for="other">Other</label>
                </div>
            </div>

            <div class="form-group">
    <label for="nationality">Nationality:<span class="required">*</span></label>
    <input type="text" id="nationality" name="nationality" required placeholder="Enter nationality">
</div>



            <!-- Physical Handicap Section -->
            <div class="form-group">
                <label>Physical Handicap:<span class="required">*</span></label>
                <div class="modern-toggle">
                    <input type="radio" id="handicapYes" name="handicap" value="Yes" required>
                    <label for="handicapYes">Yes</label>

                    <input type="radio" id="handicapNo" name="handicap" value="No" required>
                    <label for="handicapNo">No</label>
                </div>
            </div>

            <!-- Additional Fields -->
            <div class="form-group">
                <label for="phoneNumber">Phone Number:<span class="required">*</span></label>
                <input type="text" id="phoneNumber" name="phoneNumber" required pattern="\d{10}" title="Phone number must be 10 digits" placeholder="Enter phone number">
            </div>
            <div class="form-group">
                <label for="aadhar">Aadhar Number:<span class="required">*</span></label>
                <input type="text" id="aadhar" name="aadhar" required pattern="\d{12}" title="Aadhar number must be 12 digits" placeholder="Enter Aadhar number">
            </div>
            <div class="form-group">
                <label for="email">Email ID:</label>
                <input type="email" id="email" name="email" placeholder="Enter your email ID">
            </div>

            <div class="form-group">
                <label for="address">Address:<span class="required">*</span></label>
                <textarea id="address" name="address" required rows="1" placeholder="Enter your address"></textarea>
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

            <!-- Buttons and Signature -->
            <div class="form-row">
                <div class="button-group" style="flex: 1;">
                    <button type="submit">Submit</button>
                    <button type="button" onclick="window.print()">Print Form</button>
                </div>
                <div class="form-column" style="text-align: right; flex: 0.5;">
                    <label for="signature">Signature:</label>
                    <div id="signature-box" style="border: 1px solid #ccc; width: 200px; height: 50px; margin-top: 10px;"></div>
                </div>
            </div>
        </form>
    </div>

    <!-- JavaScript for Webcam Capture -->
    <script>
        const webcam = document.getElementById('webcam');
        const canvas = document.getElementById('canvas');
        const captureBtn = document.getElementById('capture-btn');
        const retakeBtn = document.getElementById('retake-btn');
        const previewContainer = document.getElementById('preview-container');
        const preview = document.getElementById('preview');
        const capturedPhotoInput = document.getElementById('capturedPhoto');
        const ctx = canvas.getContext('2d');

        // Start the webcam
        navigator.mediaDevices.getUserMedia({ video: true })
            .then((stream) => {
                webcam.srcObject = stream;
            })
            .catch((err) => {
                alert("Webcam access denied or not supported");
                console.error(err);
            });

        // Capture the photo
        captureBtn.addEventListener('click', () => {
            canvas.width = webcam.videoWidth;
            canvas.height = webcam.videoHeight;
            ctx.drawImage(webcam, 0, 0, canvas.width, canvas.height);
            const imageData = canvas.toDataURL('image/png');
            preview.src = imageData;
            capturedPhotoInput.value = imageData;
            webcam.style.display = "none";
            previewContainer.style.display = "block";
            retakeBtn.style.display = "inline-block";
        });

        // Retake the photo
        retakeBtn.addEventListener('click', () => {
            previewContainer.style.display = "none";
            webcam.style.display = "block";
            retakeBtn.style.display = "none";
        });
    </script>
    <script>
    const form = document.querySelector('.form-container');

    form.addEventListener('submit', function (event) {
        event.preventDefault(); // Stop form from submitting immediately

        // Show popup message
        if (confirm("Are you sure you want to submit the form?")) {
            // If user confirms, submit the form
            form.submit();
            alert("Form submitted successfully!");
        }
    });
</script>
</body>
</html>
