<?php
session_start();

if (!isset($_SESSION['admin_otp'])) {
    echo "Session expired";
    exit();
}

if (time() > $_SESSION['admin_otp_expiry']) {
    session_destroy();
    echo "OTP expired";
    exit();
}

if ($_POST['otp'] != $_SESSION['admin_otp']) {
    echo "Invalid OTP";
    exit();
}

/* FINAL ADMIN LOGIN */
$admin = $_SESSION['temp_admin'];

$_SESSION['admin_id']   = $admin['id'];
$_SESSION['admin_name'] = $admin['username'];

$_SESSION['admin'] = $admin['username']; // <-- ADD THIS


unset($_SESSION['admin_otp'], $_SESSION['admin_otp_expiry'], $_SESSION['temp_admin']);

echo "SUCCESS";
?>