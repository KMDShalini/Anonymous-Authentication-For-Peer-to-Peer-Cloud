<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


include "db_connection.php";

/* Disable mysqli automatic exceptions */
mysqli_report(MYSQLI_REPORT_OFF);

/* ---------------- BASIC SAFETY CHECK ---------------- */
if (!isset($_POST['username'])) {
    header("Location: signup.html");
    exit();
}

/* ---------------- FORM DATA ---------------- */
$username = $_POST['username'];
$email    = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$cloud    = $_POST['cloud']; // A or B

/* ---------------- ANONYMOUS ID ---------------- */
$uuid    = bin2hex(random_bytes(16));
$anon_id = hash("sha256", $uuid);

/* ---------------- PSEUDONYM ---------------- */
$alias = "user_" . substr($anon_id, 0, 6);

/* ---------------- PROFILE PICTURE ---------------- */


/* ---------------- OPEN DATABASES ---------------- */
$app_db = openAppDB();

if ($cloud === "A") {
    $cloud_db = openCloudADB();
} else {
    $cloud_db = openCloudBDB();
}

/* ---------------- START TRANSACTIONS ---------------- */
$app_db->autocommit(false);
$cloud_db->autocommit(false);

/* ---------------- INSERT INTO APP DATABASE ---------------- */
$stmt = $app_db->prepare("
    INSERT INTO app_users
    (username, email, password, alias, anon_id, cloud)
VALUES (?,?,?,?,?,?)
");



$stmt->bind_param(
    "ssssss",
    $username,
    $email,
    $password,
    $alias,
    $anon_id,
    $cloud
);


$app_ok = $stmt->execute();

/* ---------------- ECC KEY GENERATION ---------------- */
$config = [
    "private_key_type" => OPENSSL_KEYTYPE_EC,
    "curve_name"       => "prime256v1"
];

$res = openssl_pkey_new($config);

if ($res !== false) {
    openssl_pkey_export($res, $ecc_private);
    $pub_details = openssl_pkey_get_details($res);
    $ecc_public  = $pub_details['key'];
    $ecc_ok = true;
} else {
    $ecc_ok = false;
}

/* ---------------- INSERT INTO CLOUD DATABASE ---------------- */
$stmt2 = $cloud_db->prepare("
    INSERT INTO cloud_users (anon_id, ecc_private_key, ecc_public_key)
    VALUES (?,?,?)
");

$stmt2->bind_param("sss", $anon_id, $ecc_private, $ecc_public);
$cloud_ok = $stmt2->execute();



if (!$app_ok) {
    die("❌ App DB insert failed: " . $stmt->error);
}

if (!$ecc_ok) {
    die("❌ ECC key generation failed: " . openssl_error_string());
}

if (!$cloud_ok) {
    die("❌ Cloud DB insert failed: " . $stmt2->error);
}

$app_db->commit();
$cloud_db->commit();

echo "<script>
    alert('sign-up successful');
    window.location.href='login.html';
</script>";
exit();
?>