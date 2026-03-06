<?php
session_start();
include "db_connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$cloud    = $_POST['cloud'] ?? '';

$db = openAppDB();
$stmt = $db->prepare("SELECT * FROM app_users WHERE username=? AND cloud=?");
$stmt->bind_param("ss", $username, $cloud);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
    echo "NO_USER";
    exit();
}

$user = $res->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo "WRONG_PASSWORD";
    exit();
}

/* OTP */
$otp = rand(100000, 999999);
$_SESSION['otp'] = $otp;
$_SESSION['otp_expiry'] = time() + 300;
$_SESSION['temp_user'] = $user;

/* MAIL */
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
    $mail->addAddress($user['email']);

    $mail->Subject = "Your Login OTP";
    $mail->Body    = "Your OTP is: $otp (valid for 5 minutes)";

    $mail->send();
    echo "OTP_SENT";

} catch (Exception $e) {
    echo "Failed to send OTP";
}
