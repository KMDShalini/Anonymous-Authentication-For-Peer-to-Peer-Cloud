<?php
session_start();

if (!isset($_SESSION['otp'])) {
    echo "Session expired";
    exit();
}

if (time() > $_SESSION['otp_expiry']) {
    session_destroy();
    echo "OTP expired";
    exit();
}

if ($_POST['otp'] != $_SESSION['otp']) {
    echo "Invalid OTP";
    exit();
}

/* FINAL LOGIN */
$user = $_SESSION['temp_user'];

$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['anon_id']  = $user['anon_id'];
$_SESSION['alias_name'] = $user['alias'];
$_SESSION['cloud']    = $user['cloud'];

unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['temp_user']);

echo "SUCCESS";
