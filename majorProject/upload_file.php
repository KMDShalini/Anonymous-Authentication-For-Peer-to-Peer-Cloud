<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include "db_connection.php";

/* ---------- SESSION CHECK ---------- */
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$anon_id = $_SESSION['anon_id'];
$cloud   = $_SESSION['cloud'];

/* ---------- SELECT CLOUD DB ---------- */
$cloud_db = ($cloud === "A") ? openCloudADB() : openCloudBDB();

/* ---------- HANDLE FILE UPLOAD ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed");
    }

    /* ---------- FILE SIZE LIMIT ---------- */
    $MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    if ($_FILES['file']['size'] > $MAX_FILE_SIZE) {
        die("File size exceeds 5MB");
    }

    /* ---------- EXTENSION CHECK ---------- */
    $allowed_extensions = ['pdf','docx','txt','jpg','jpeg','png'];
    $file_name = $_FILES['file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        die("Invalid file type");
    }

    /* ---------- STORE FILE ---------- */
    $file_data = file_get_contents($_FILES['file']['tmp_name']);

    $stmt = $cloud_db->prepare(
        "INSERT INTO cloud_files (owner_anon_id, file_name, file_data)
         VALUES (?,?,?)"
    );
    $stmt->bind_param("sss", $anon_id, $file_name, $file_data);
    $stmt->execute();
    $stmt->close();

    header("Location: user_dashboard.php");
    exit();
}
?>



<!DOCTYPE html>
<html>
<head>
    <title>Upload File</title>
    <link rel="stylesheet" href="user_dashboard.css">
</head>
<body>

<div class="container mt-5 pt-5">
    <div class="card">
        <h3>Upload File to Cloud</h3>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <p class="note">Max file size: 5 MB</p>
            <button type="submit">Upload</button>
        </form>
    </div>
</div>

</body>
</html>
