<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login_new.php');
    exit();
}

require_once '../config/database.php';

// Fetch all active courses with links
$courses = $conn->query("SELECT * FROM courses WHERE status='active' ORDER BY course_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registration Links - NIELIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    <style>
        .link-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .qr-code {
            max-width: 150px;
            margin: 10px auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-link"></i> Course Registration Links</h1>
                    <div>
                        <button class="btn btn-success" onclick="downloadAllLinks()">
                            <i class="fas fa-download"></i> Download All Links
                        </button>
                        <a href="manage_courses.php" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Manage Courses
                        </a>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Share these links with students to allow direct registration for specific courses.
                </div>

                <div class="row">
                    <?php while ($course = $courses->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card link-card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </h5>
                                <p class="mb-2">
                                    <span class="badge bg-primary"><?= htmlspecialchars($course['course_code']) ?></span>
                                    <span class="badge bg-info"><?= htmlspecialchars($course['course_type']) ?></span>
                                </p>
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($course['training_center']) ?>
                                </p>
                                
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control form-control-sm" 
                                           value="<?= htmlspecialchars($course['registration_link']) ?>" 
                                           id="link_<?= $course['id'] ?>" readonly>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            onclick="copyLink(<?= $course['id'] ?>)" 
                                            title="Copy Link">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>

                                <div class="d-grid gap-2">
                                    <a href="<?= htmlspecialchars($course['registration_link']) ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Open Registration Page
                                    </a>
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="generateQR(<?= $course['id'] ?>, '<?= htmlspecialchars($course['registration_link'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-qrcode"></i> Generate QR Code
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="shareLink('<?= htmlspecialchars($course['course_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($course['registration_link'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-share-alt"></i> Share
                                    </button>
                                </div>

                                <div id="qr_<?= $course['id'] ?>" class="qr-code mt-3" style="display: none;"></div>
                            </div>
                            <div class="card-footer text-muted small">
                                <i class="fas fa-clock"></i> Created: <?= date('M d, Y', strtotime($course['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        function copyLink(courseId) {
            const linkInput = document.getElementById('link_' + courseId);
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(linkInput.value).then(() => {
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy: ' + err);
            });
        }

        function generateQR(courseId, link) {
            const qrDiv = document.getElementById('qr_' + courseId);
            
            if (qrDiv.style.display === 'none') {
                qrDiv.innerHTML = '';
                new QRCode(qrDiv, {
                    text: link,
                    width: 150,
                    height: 150
                });
                qrDiv.style.display = 'block';
                event.target.innerHTML = '<i class="fas fa-times"></i> Hide QR Code';
            } else {
                qrDiv.style.display = 'none';
                event.target.innerHTML = '<i class="fas fa-qrcode"></i> Generate QR Code';
            }
        }

        function shareLink(courseName, link) {
            if (navigator.share) {
                navigator.share({
                    title: 'Register for ' + courseName,
                    text: 'Register for ' + courseName + ' at NIELIT Bhubaneswar',
                    url: link
                }).catch(err => console.log('Error sharing:', err));
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(link).then(() => {
                    alert('Link copied to clipboard! You can now share it.');
                });
            }
        }

        function downloadAllLinks() {
            const links = [];
            document.querySelectorAll('[id^="link_"]').forEach(input => {
                links.push(input.value);
            });
            
            const blob = new Blob([links.join('\n')], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'nielit_course_links_' + new Date().toISOString().split('T')[0] + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
