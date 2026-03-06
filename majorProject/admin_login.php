<?php
session_start();
include "db_connection.php"; // your DB connection file
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$db = openAppDB(); // connect to app_db

$stmt = $db->prepare("SELECT * FROM admins WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
    echo "NO_USER";
    exit();
}

$admin = $res->fetch_assoc();

if (!password_verify($password, $admin['password'])) {
    echo "WRONG_PASSWORD";
    exit();
}

/* OTP GENERATION */
$otp = rand(100000, 999999);
$_SESSION['admin_otp'] = $otp;
$_SESSION['admin_otp_expiry'] = time() + 300; // 5 minutes
$_SESSION['temp_admin'] = $admin;

/* MAIL OTP */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'harshinikanagarla811@gmail.com';
    $mail->Password = 'rakm qbzi ynnd dwtk';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('harshinikanagarla811@gmail.com', 'CloudBridge Secure');
    $mail->addAddress($admin['email']);

    $mail->Subject = "Admin Login OTP";
    $mail->Body    = "Your OTP is: $otp (valid for 5 minutes)";

    $mail->send();
    echo "OTP_SENT";

} catch (Exception $e) {
    echo "Failed to send OTP: {$mail->ErrorInfo}";
}
?>