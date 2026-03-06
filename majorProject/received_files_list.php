<?php
session_start();
include "db_connection.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['encrypted_file_id'])) {
    header("Location: send_file.php");
    exit();
}

$app_db = openAppDB();

$receiver_anon_id  = $_SESSION['anon_id'];
$receiver_cloud    = $_SESSION['cloud'];
$encrypted_file_id = $_POST['encrypted_file_id'];

/* ---------- FETCH ENCRYPTED FILE ---------- */
$stmt = $app_db->prepare(
    "SELECT ef.encrypted_blob, ef.original_hash, ef.original_filename, ef.sender_anon_id,
            tr.sender_public_key
     FROM encrypted_files ef
     JOIN transfer_requests tr
       ON ef.transfer_request_id = tr.id
     WHERE ef.id = ? AND ef.receiver_anon_id = ?"
);
$stmt->bind_param("is", $encrypted_file_id, $receiver_anon_id);
$stmt->execute();
$stmt->bind_result(
    $encrypted_blob,
    $original_hash,
    $original_filename,
    $sender_anon_id,
    $sender_public_key
);
$stmt->fetch();
$stmt->close();

if (!$encrypted_blob) {
    die("Encrypted file not found.");
}

/* ---------- GET RECEIVER PRIVATE KEY ---------- */
$cloud_db = ($receiver_cloud === "A") ? openCloudADB() : openCloudBDB();

$stmt2 = $cloud_db->prepare(
    "SELECT ecc_private_key FROM cloud_users WHERE anon_id = ?"
);
$stmt2->bind_param("s", $receiver_anon_id);
$stmt2->execute();
$stmt2->bind_result($receiver_private_key);
$stmt2->fetch();
$stmt2->close();

if (!$receiver_private_key) {
    die("Receiver private key not found.");
}

/* ---------- DERIVE SHARED SECRET ---------- */
$receiver_priv = openssl_pkey_get_private($receiver_private_key);
$sender_pub    = openssl_pkey_get_public($sender_public_key);

if (!$receiver_priv || !$sender_pub) {
    die("Invalid ECC keys.");
}
$shared_secret = openssl_pkey_derive($sender_pub, $receiver_priv, 32);

if ($shared_secret === false) {
    die("ECDH key derivation failed.");
}

/* ---------- AES DECRYPT ---------- */
$data = base64_decode($encrypted_blob);
$iv   = substr($data, 0, 16);
$ciphertext = substr($data, 16);

$decrypted_data = openssl_decrypt(
    $ciphertext,
    "AES-256-CBC",
    $shared_secret,
    OPENSSL_RAW_DATA,
    $iv
);

if ($decrypted_data === false) {
    die("Decryption failed.");
}

/* ---------- VERIFY INTEGRITY ---------- */
$decrypted_hash = hash("sha256", $decrypted_data);

if ($decrypted_hash !== $original_hash) {
    die("Integrity verification failed.");
}

/* ---------- STORE FILE IN CLOUD B ---------- */
$stmtStore = $cloud_db->prepare(
    "INSERT INTO received_files
     (owner_anon_id, sender_anon_id, original_filename, file_data,file_hash)
     VALUES (?, ?, ?, ?,?)"
);
$stmtStore->bind_param("sssss", $receiver_anon_id, $sender_anon_id, $original_filename, $decrypted_data,$decrypted_hash);
$stmtStore->execute();
$stmtStore->close();

/* ---------- MARK AS DOWNLOADED ---------- */
$stmt4 = $app_db->prepare(
    "UPDATE encrypted_files
     SET decrypted_hash = ?, status = 'downloaded'
     WHERE id = ?"
);
$stmt4->bind_param("si", $decrypted_hash, $encrypted_file_id);
$stmt4->execute();
$stmt4->close();

/* ---------- FORCE DOWNLOAD ---------- */
if (ob_get_length()) ob_end_clean();

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . $original_filename . "\"");
header("Content-Length: " . strlen($decrypted_data));
header("Cache-Control: no-cache");
header("Pragma: public");

echo $decrypted_data;
exit();
?>
