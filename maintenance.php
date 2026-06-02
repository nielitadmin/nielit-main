<?php
require_once __DIR__ . '/config/config.php';

// Check if maintenance mode is enabled
$maintenance_query = $conn->query("SELECT * FROM maintenance_mode WHERE id = 1");
$maintenance = $maintenance_query ? $maintenance_query->fetch_assoc() : null;

// If maintenance mode is not enabled, redirect to home
if (!$maintenance || !$maintenance['is_enabled']) {
    header("Location: " . APP_URL . "/index.php");
    exit();
}

$title = $maintenance['maintenance_title'] ?? 'Site Under Maintenance';
$message = $maintenance['maintenance_message'] ?? 'We are currently performing scheduled maintenance. We will be back soon!';
$end_time = $maintenance['end_time'] ?? null;
$show_countdown = $maintenance['show_countdown'] ?? 1;
$show_contact = $maintenance['show_contact'] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - NIELIT Bhubaneswar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    
    <style>
        :root {
            --navy: #0a1628;
            --blue: #1a56db;
            --gold: #f59e0b;
            --cream: #fafaf8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #112240 50%, #1a3a5a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background pattern */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(245, 158, 11, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(26, 86, 219, 0.1) 0%, transparent 50%);
            animation: pulse 10s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        /* Floating particles */
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(245, 158, 11, 0.3);
            border-radius: 50%;
            animation: float 15s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); }
            33% { transform: translateY(-100px) translateX(50px); }
            66% { transform: translateY(-50px) translateX(-50px); }
        }

        .maintenance-container {
            position: relative;
            z-index: 1;
            max-width: 700px;
            width: 90%;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .maintenance-icon {
            font-size: 5rem;
            color: var(--gold);
            margin-bottom: 1.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .maintenance-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 1rem;
            letter-spacing: -0.5px;
        }

        .maintenance-message {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .countdown-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .countdown-box {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a5a 100%);
            color: white;
            padding: 1.5rem 1rem;
            border-radius: 16px;
            min-width: 100px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .countdown-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gold);
        }

        .countdown-number {
            font-size: 2.5rem;
            font-weight: 800;
            display: block;
            color: var(--gold);
        }

        .countdown-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        .contact-info {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 2px solid #e2e8f0;
        }

        .contact-info h5 {
            color: var(--navy);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .contact-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 1rem;
            color: #64748b;
            font-size: 0.95rem;
        }

        .contact-item i {
            color: var(--gold);
        }

        .logo-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .govt-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .govt-header img {
            height: 70px;
        }

        .ministry-text {
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            line-height: 1.3;
        }

        .ministry-text .main {
            color: var(--navy);
            font-weight: 700;
            font-size: 0.95rem;
            display: block;
            margin-bottom: 2px;
        }

        .ministry-text .sub {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nielit-logo {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
        }

        .nielit-logo img {
            height: 55px;
        }

        .progress-bar-custom {
            height: 6px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 2rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--blue), var(--gold));
            border-radius: 10px;
            animation: progress 2s infinite;
        }

        @keyframes progress {
            0% { width: 0%; }
            50% { width: 100%; }
            100% { width: 0%; }
        }

        @media (max-width: 768px) {
            .maintenance-container {
                padding: 2rem 1.5rem;
            }

            .maintenance-title {
                font-size: 1.8rem;
            }

            .countdown-box {
                min-width: 80px;
                padding: 1rem 0.75rem;
            }

            .countdown-number {
                font-size: 2rem;
            }

            .govt-header {
                flex-direction: column;
                gap: 1rem;
            }

            .govt-header img {
                height: 50px;
            }

            .nielit-logo img {
                height: 45px;
            }

            .ministry-text .main {
                font-size: 0.85rem;
            }

            .ministry-text .sub {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Floating particles -->
    <?php for ($i = 0; $i < 20; $i++): ?>
    <div class="particle" style="
        left: <?php echo rand(0, 100); ?>%;
        top: <?php echo rand(0, 100); ?>%;
        animation-delay: <?php echo rand(0, 10); ?>s;
    "></div>
    <?php endfor; ?>

    <div class="maintenance-container">
        <!-- Logo Section with Government Header -->
        <div class="logo-section">
            <div class="govt-header">
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="National Emblem">
                <div class="ministry-text">
                    <span class="main">MINISTRY OF ELECTRONICS & INFORMATION TECHNOLOGY</span>
                    <span class="sub">Government of India</span>
                </div>
            </div>
            <div class="nielit-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Bhubaneswar">
            </div>
        </div>

        <!-- Maintenance Icon -->
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>

        <!-- Title & Message -->
        <h1 class="maintenance-title"><?php echo htmlspecialchars($title); ?></h1>
        <p class="maintenance-message"><?php echo nl2br(htmlspecialchars($message)); ?></p>

        <!-- Countdown Timer -->
        <?php if ($show_countdown && $end_time): ?>
        <div class="countdown-container" id="countdown">
            <div class="countdown-box">
                <span class="countdown-number" id="days">00</span>
                <span class="countdown-label">Days</span>
            </div>
            <div class="countdown-box">
                <span class="countdown-number" id="hours">00</span>
                <span class="countdown-label">Hours</span>
            </div>
            <div class="countdown-box">
                <span class="countdown-number" id="minutes">00</span>
                <span class="countdown-label">Minutes</span>
            </div>
            <div class="countdown-box">
                <span class="countdown-number" id="seconds">00</span>
                <span class="countdown-label">Seconds</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Progress Bar -->
        <div class="progress-bar-custom">
            <div class="progress-fill"></div>
        </div>

        <!-- Contact Information -->
        <?php if ($show_contact): ?>
        <div class="contact-info">
            <h5><i class="fas fa-headset"></i> Need Urgent Help?</h5>
            <div class="contact-item">
                <i class="fas fa-phone-alt"></i>
                <span>0674-2960354</span>
            </div>
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <span>dir-bbsr@nielit.gov.in</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($show_countdown && $end_time): ?>
    <script>
        // Set the end time from PHP
        const endTime = new Date("<?php echo date('Y-m-d H:i:s', strtotime($end_time)); ?>").getTime();

        // Update the countdown every 1 second
        const countdownTimer = setInterval(function() {
            const now = new Date().getTime();
            const distance = endTime - now;

            // Calculate time units
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Display the result
            document.getElementById("days").innerHTML = String(days).padStart(2, '0');
            document.getElementById("hours").innerHTML = String(hours).padStart(2, '0');
            document.getElementById("minutes").innerHTML = String(minutes).padStart(2, '0');
            document.getElementById("seconds").innerHTML = String(seconds).padStart(2, '0');

            // If countdown is finished
            if (distance < 0) {
                clearInterval(countdownTimer);
                document.getElementById("countdown").innerHTML = `
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> Maintenance completed! Refreshing page...
                    </div>
                `;
                // Reload page after 3 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
