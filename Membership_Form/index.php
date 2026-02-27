<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Professional Membership Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --primary-color: #0288d1;
            --secondary-color: #01579b;
            --accent-color: #29b6f6;
            --bg-gradient: linear-gradient(-45deg, #e3f2fd, #bbdefb, #90caf9, #e1f5fe);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: 1px solid rgba(255, 255, 255, 0.3);
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--bg-gradient);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
            -webkit-print-color-adjust: exact;
        }

        /* --- 3D MOVING BACKGROUND OBJECTS --- */
        .background-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            animation: float 20s infinite linear;
        }

        .shape:nth-child(1) { width: 150px; height: 150px; top: 10%; left: 10%; animation-duration: 15s; }
        .shape:nth-child(2) { width: 100px; height: 100px; top: 80%; left: 80%; animation-duration: 18s; animation-delay: -5s; border-radius: 20px; }
        .shape:nth-child(3) { width: 200px; height: 200px; top: 40%; left: 60%; animation-duration: 25s; animation-delay: -10s; }
        .shape:nth-child(4) { width: 80px; height: 80px; top: 70%; left: 20%; animation-duration: 12s; background: rgba(2, 136, 209, 0.1); }
        .shape:nth-child(5) { width: 120px; height: 120px; top: 20%; left: 90%; animation-duration: 22s; background: rgba(41, 182, 246, 0.15); border-radius: 15px; transform: rotate(45deg); }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg) translateZ(0); }
            50% { transform: translateY(-50px) rotate(180deg) translateZ(50px); }
            100% { transform: translateY(0) rotate(360deg) translateZ(0); }
        }

        /* --- A4 PAGE CONTAINER --- */
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            box-shadow: var(--shadow);
            padding: 40px;
            margin: 20px auto;
            border-radius: 15px;
            position: relative;
        }

        /* --- HEADER --- */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .logo-left img, .logo-right img {
            height: 60px;
            margin: 0 5px;
        }

        .header-text {
            text-align: center;
            flex: 1;
            padding: 0 10px;
        }

        .main-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--secondary-color);
            text-transform: uppercase;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin: 4px 0;
        }

        .subtext {
            font-size: 9px;
            font-style: italic;
            color: #555;
        }

        .address {
            font-size: 10px;
            margin-top: 2px;
            color: #444;
        }

        /* --- FORM STYLES --- */
        h1 {
            font-size: 14px;
            text-align: center;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        h2 {
            font-size: 13px;
            color: var(--primary-color);
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
            margin: 15px 0 10px 0;
            text-transform: uppercase;
        }

        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-column {
            flex: 1;
            min-width: 250px;
        }

        .form-group {
            margin-bottom: 10px;
            position: relative;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 3px;
            color: #333;
        }

        .required { color: red; }

        input[type="text"],
        input[type="date"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
            background: #fff;
        }

        textarea { resize: none; }

        .modern-toggle {
            display: flex;
            gap: 15px;
            margin-top: 3px;
        }

        .modern-toggle label {
            cursor: pointer;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }

        input[type="radio"] {
            transform: scale(1.1);
            accent-color: var(--primary-color);
        }

        /* --- WEBCAM SECTION --- */
        .photo-column {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        #webcam-container, #preview-container {
            width: 140px;
            height: 170px;
            border: 2px dashed #aaa;
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f0f0f0;
            margin-bottom: 8px;
        }

        video, img#preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* --- BUTTONS --- */
        .button-group button {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: transform 0.2s;
            margin-right: 10px;
        }

        .button-group button:hover { transform: translateY(-2px); }

        /* --- DIGITAL SIGNATURE --- */
        #signature-pad {
            border: 2px solid #ccc;
            border-radius: 4px;
            cursor: crosshair;
            background: #fff;
        }
        
        .clear-sig-btn {
            background: #ff5252 !important;
            padding: 4px 10px !important;
            font-size: 10px !important;
            margin-top: 5px;
        }

        /* --- FOOTER --- */
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        /* --- EXTREME PRINT OPTIMIZATION (FOR 1 PAGE) --- */
        @media print {
            @page {
                size: A4 portrait;
                margin: 5mm; /* Very tight page margins */
            }

            body {
                background: white;
                display: block;
                margin: 0;
                padding: 0;
                /* Crucial: shrinks content to fit 1 page */
                zoom: 85%; 
            }

            .background-shapes, 
            .button-group, 
            .clear-sig-btn {
                display: none !important;
            }

            .a4-page {
                width: 100%;
                margin: 0;
                padding: 10px 20px; /* Reduced padding */
                box-shadow: none;
                border: none;
                backdrop-filter: none;
                min-height: auto;
            }

            /* Make inputs look like lines on paper */
            input[type="text"],
            input[type="date"],
            input[type="email"],
            select,
            textarea {
                border: none;
                border-bottom: 1px solid #000;
                background: transparent;
                padding: 2px 0;
                border-radius: 0;
                font-weight: 500;
                color: #000;
                -webkit-appearance: none;
                appearance: none;
            }

            /* Header Adjustments */
            .header {
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 2px solid #000;
            }
            .main-title { font-size: 14px; color: #000; }
            .subtitle { font-size: 11px; color: #000; }

            /* Title Bar Adjustment */
            h1 {
                background: #eee;
                color: #000;
                border: 1px solid #000;
                padding: 5px;
                font-size: 12px;
                margin-bottom: 10px;
            }

            h2 {
                color: #000;
                border-bottom: 1px solid #000;
                margin: 10px 0;
                font-size: 12px;
            }

            /* Hide Webcam if Photo Taken */
            #webcam-container {
                display: none;
            }

            /* Ensure Preview Box has border */
            #preview-container {
                border: 1px solid #000;
                display: flex !important; /* Force display if hidden */
                justify-content: center;
                align-items: center;
            }
            
            /* If no photo, show empty box logic handled in JS below or CSS fallback */
            .form-group { margin-bottom: 5px; }
            label { font-size: 10px; margin-bottom: 1px; }
        }
    </style>
</head>
<body>

    <div class="background-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="a4-page">
        <div class="header">
            <div class="logo-left">
                <img src="logo1.png" alt="NIELIT Logo">
            </div>
            <div class="header-text">
                <p class="main-title">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</p>
                <p class="subtitle">National Institute of Electronics & Information Technology (NIELIT)</p>
                <p class="subtext">(Under Ministry of Electronics & Information Technology, Government of India)</p>
                <p class="address">3rd Floor, OCAC Tower, Acharya Vihar, Bhubaneswar-751013, Odisha</p>
            </div>
            <div class="logo-right">
                 <img src="https://upload.wikimedia.org/wikipedia/commons/5/55/Emblem_of_India.svg" alt="Govt Logo">
            </div>
        </div>

        <form class="form-container" id="mainForm" action="submit_form.php" method="POST">
            <h1>To be filled by the candidates (Fill in CAPITAL Letters only)</h1>

            <div class="form-row">
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
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1,MAHULDIHA,RAIRANGPUR">PM SHRI KENDRIYA VIDYALAYA, NO.1, MAHULDIHA,RAIRANGPUR</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1, BARIPADA">PM SHRI KENDRIYA VIDYALAYA, NO.1, BARIPADA</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, BHADRAK">PM SHRI KENDRIYA VIDYALAYA, BHADRAK</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, INS CHILKA">PM SHRI KENDRIYA VIDYALAYA, INS CHILKA</option>
                            <option value="PM SHRI KENDRIYA VIDYALAYA, NO.1 PURI">PM SHRI KENDRIYA VIDYALAYA, NO.1 PURI</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1">
                            <label for="class">Class:<span class="required">*</span></label>
                            <input type="text" id="class" name="class" required placeholder="Ex: X">
                        </div>
                        <div class="form-group" style="flex:1">
                            <label for="section">Section:</label>
                            <input type="text" id="section" name="section" placeholder="Ex: A">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="trainingDate">Date of Training:<span class="required">*</span></label>
                        <input type="date" id="trainingDate" name="trainingDate" required>
                    </div>
                </div>

                <div class="form-column photo-column" style="flex: 0 0 160px;">
                    <h2>Photograph</h2>
                    <div id="webcam-container">
                        <video id="webcam" autoplay playsinline></video>
                    </div>
                    <canvas id="canvas" style="display: none;"></canvas>
                    
                    <div id="preview-container" style="display: none;">
                        <img id="preview" alt="Photo">
                    </div>
                    <input type="hidden" name="capturedPhoto" id="capturedPhoto">
                    
                    <div class="button-group" style="display: flex; flex-direction: column; gap: 5px; width: 100%;">
                        <button type="button" id="capture-btn">Capture Photo</button>
                        <button type="button" id="retake-btn" style="display: none;">Retake</button>
                    </div>
                </div>
            </div>

            <h2>Personal Information</h2>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label for="fullName">Full Name:<span class="required">*</span></label>
                    <input type="text" id="fullName" name="fullName" required oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="fathersName">Father's Name:<span class="required">*</span></label>
                    <input type="text" id="fathersName" name="father_name" required oninput="this.value = this.value.toUpperCase()">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label for="dob">Date of Birth:<span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" required>
                </div>
                <div class="form-group" style="flex:1">
                    <label>Gender:<span class="required">*</span></label>
                    <div class="modern-toggle">
                        <label><input type="radio" name="gender" value="Male" required> Male</label>
                        <label><input type="radio" name="gender" value="Female"> Female</label>
                        <label><input type="radio" name="gender" value="Other"> Other</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label for="nationality">Nationality:<span class="required">*</span></label>
                    <input type="text" id="nationality" name="nationality" value="INDIAN" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-group" style="flex:1">
                    <label>Physical Handicap:<span class="required">*</span></label>
                    <div class="modern-toggle">
                        <label><input type="radio" name="handicap" value="Yes" required> Yes</label>
                        <label><input type="radio" name="handicap" value="No" checked> No</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label for="phoneNumber">Phone Number:<span class="required">*</span></label>
                    <input type="text" id="phoneNumber" name="phoneNumber" required pattern="\d{10}">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="aadhar">Aadhar Number:<span class="required">*</span></label>
                    <input type="text" id="aadhar" name="aadhar" required pattern="\d{12}">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email ID:</label>
                <input type="email" id="email" name="email">
            </div>

            <div class="form-group">
                <label for="address">Permanent Address:<span class="required">*</span></label>
                <textarea id="address" name="address" required rows="1" oninput="this.value = this.value.toUpperCase()"></textarea>
            </div>

            <div class="form-group">
                <label for="category">Category:<span class="required">*</span></label>
                <select id="category" name="category" required>
                    <option value="" disabled selected>-- Select --</option>
                    <option value="GEN">General</option>
                    <option value="OBC">OBC</option>
                    <option value="SC">SC</option>
                    <option value="ST">ST</option>
                </select>
            </div>

            <div class="form-row" style="margin-top: 15px; align-items: flex-end;">
                <div class="button-group" style="flex: 2;">
                    <button type="submit">Submit Registration</button>
                    <button type="button" onclick="window.print()">Print Form</button>
                </div>
                
                <div class="form-column" style="flex: 1; text-align: right;">
                    <label for="signature">Candidate's Signature:</label>
                    <canvas id="signature-pad" width="200" height="60"></canvas>
                    <input type="hidden" name="signatureData" id="signatureData">
                    <div style="text-align: right;">
                        <button type="button" class="button-group clear-sig-btn" id="clear-sig">Clear</button>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>NIELIT BHUBANESWAR | NIELIT BALASORE EXTENSION CENTER</p>
                <p>An Autonomous Scientific Society under Ministry of Electronics and Information Technology, Govt. of India</p>
            </div>
        </form>
    </div>

    <script>

    // --- WEBCAM LOGIC ---
    const webcam = document.getElementById('webcam');
    const webcamContainer = document.getElementById('webcam-container'); // NEW: Select the container
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('capture-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const previewContainer = document.getElementById('preview-container');
    const preview = document.getElementById('preview');
    const capturedPhotoInput = document.getElementById('capturedPhoto');
    const ctx = canvas.getContext('2d');

    // Start webcam
    if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
            .then((stream) => {
                webcam.srcObject = stream;
            })
            .catch((err) => {
                console.error("Webcam error:", err);
                // Show a user-friendly message in the box if camera fails
                webcamContainer.innerHTML = "<span style='font-size:10px;text-align:center;'>Camera Access<br>Denied</span>";
            });
    }

    // Capture Photo
    captureBtn.addEventListener('click', () => {
        if(webcam.srcObject) {
            // Draw image to canvas
            canvas.width = webcam.videoWidth;
            canvas.height = webcam.videoHeight;
            ctx.drawImage(webcam, 0, 0, canvas.width, canvas.height);
            const imageData = canvas.toDataURL('image/png');
            
            // Set preview image
            preview.src = imageData;
            capturedPhotoInput.value = imageData;
            
            // --- THE FIX IS HERE ---
            // Hide the entire webcam container (the top dashed box)
            webcamContainer.style.display = "none"; 
            
            // Show the preview container (the photo box)
            previewContainer.style.display = "flex"; 
            
            // Toggle buttons
            captureBtn.style.display = "none";
            retakeBtn.style.display = "inline-block"; // Changed to inline-block for better alignment
        } else {
            alert("Camera not active. Please allow camera permissions.");
        }
    });

    // Retake Photo
    retakeBtn.addEventListener('click', () => {
        // --- THE FIX IS HERE ---
        // Hide the preview container
        previewContainer.style.display = "none";
        
        // Show the webcam container again
        webcamContainer.style.display = "flex";
        
        // Toggle buttons
        captureBtn.style.display = "inline-block";
        retakeBtn.style.display = "none";
        
        // Clear the data
        capturedPhotoInput.value = "";
    });


        // --- SIGNATURE LOGIC ---
        const sigCanvas = document.getElementById('signature-pad');
        const sigCtx = sigCanvas.getContext('2d');
        let isDrawing = false;

        function getPos(c, e) {
            const r = c.getBoundingClientRect();
            return {
                x: (e.clientX || e.touches[0].clientX) - r.left,
                y: (e.clientY || e.touches[0].clientY) - r.top
            };
        }

        sigCanvas.addEventListener('mousedown', (e) => { isDrawing=true; sigCtx.beginPath(); sigCtx.moveTo(getPos(sigCanvas,e).x, getPos(sigCanvas,e).y); });
        sigCanvas.addEventListener('mousemove', (e) => { if(isDrawing) { sigCtx.lineTo(getPos(sigCanvas,e).x, getPos(sigCanvas,e).y); sigCtx.stroke(); } });
        sigCanvas.addEventListener('mouseup', () => { isDrawing=false; document.getElementById('signatureData').value = sigCanvas.toDataURL(); });

        document.getElementById('clear-sig').addEventListener('click', () => {
            sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
            document.getElementById('signatureData').value = '';
        });

        // --- PRINT HANDLING (Show empty box if no photo) ---
        window.onbeforeprint = function() {
            if(!capturedPhotoInput.value) {
                previewContainer.style.display = 'block';
                preview.style.display = 'none'; // hide img tag
                previewContainer.innerHTML = "<div style='display:flex;justify-content:center;align-items:center;height:100%;font-size:10px;text-align:center;'>PASTE<br>PHOTO<br>HERE</div>";
            }
        };

        window.onafterprint = function() {
             if(!capturedPhotoInput.value) {
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '<img id="preview" alt="Photo">'; // reset
             }
        };
    </script>
</body>
</html>