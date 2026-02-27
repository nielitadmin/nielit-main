<?php
session_start();  // Start the session

// Destroy the session to log out
session_unset();  // Unset all session variables
session_destroy(); // Destroy the session

// Redirect to the admin login page
header("Location: login_new.php");
exit();
?>
