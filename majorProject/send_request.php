<?php
session_start();
include "db_connection.php";

/* ---------- SESSION CHECK ---------- */
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$app_db = openAppDB();

$sender_username = $_SESSION['username'];
$sender_anon_id  = $_SESSION['anon_id'];
$sender_cloud    = $_SESSION['cloud'];
$sender_alias_name = $_SESSION['alias_name'];
/* ---------- DETERMINE TARGET CLOUD ---------- */
$target_cloud = ($sender_cloud === "A") ? "B" : "A";

/* ---------- SELECT SENDER CLOUD DB ---------- */
$sender_cloud_db = ($sender_cloud === "A") ? openCloudADB() : openCloudBDB();

/* ---------- FETCH SENDER PUBLIC KEY ---------- */
$stmt_pk = $sender_cloud_db->prepare(
    "SELECT ecc_public_key FROM cloud_users WHERE anon_id = ?"
);
$stmt_pk->bind_param("s", $sender_anon_id);
$stmt_pk->execute();
$stmt_pk->bind_result($sender_public_key);
$stmt_pk->fetch();
$stmt_pk->close();

if (empty($sender_public_key)) {
    die("Sender public key not found.");
}

/* ---------- HANDLE FORM SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $receiver_username = $_POST['receiver_username'];

    /* Prevent self request */
    if ($receiver_username === $sender_username) {
        echo "<script>alert('You cannot send a request to yourself');</script>";
        exit();
    }

    /* ---------- GET RECEIVER ANON ID ---------- */
    $stmt = $app_db->prepare(
        "SELECT anon_id FROM app_users WHERE username = ? AND cloud = ?"
    );
    $stmt->bind_param("ss", $receiver_username, $target_cloud);
    $stmt->execute();
    $stmt->bind_result($receiver_anon_id);
    $stmt->fetch();
    $stmt->close();

    if (empty($receiver_anon_id)) {
        die("Invalid receiver.");
    }

    /* ---------- BLOCK DUPLICATE REQUESTS ---------- */
    $check = $app_db->prepare(
        "SELECT id FROM transfer_requests
         WHERE sender_anon_id = ?
           AND receiver_anon_id = ?
           AND status IN ('pending','approved')"
    );
    $check->bind_param("ss", $sender_anon_id, $receiver_anon_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>
            alert('A transfer request already exists or is approved.');
            window.location.href = 'send_request.php';
        </script>";
        exit();
    }
    $check->close();

    /* ---------- INSERT TRANSFER REQUEST ---------- */
    $status = "pending";

    $stmt2 = $app_db->prepare(
        "INSERT INTO transfer_requests
        (sender_username, sender_alias_name,receiver_username,
         sender_anon_id, receiver_anon_id,
         sender_public_key, status)
        VALUES (?,?,?,?,?,?,?)"
    );
    $stmt2->bind_param(
        "sssssss",
        $sender_username,
        $sender_alias_name,
        $receiver_username,
        $sender_anon_id,
        $receiver_anon_id,
        $sender_public_key,
        $status
    );
    $stmt2->execute();
    $stmt2->close();

    header("Location: user_dashboard.php");
    exit();
}

/* ---------- FETCH USERS (HIDE ALREADY REQUESTED/APPROVED) ---------- */
$stmt = $app_db->prepare(
    "SELECT username FROM app_users
     WHERE cloud = ?
       AND anon_id NOT IN (
           SELECT receiver_anon_id
           FROM transfer_requests
           WHERE sender_anon_id = ?
             AND status IN ('pending','approved')
       )"
);
$stmt->bind_param("ss", $target_cloud, $sender_anon_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Transfer Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="user_dashboard.css">
</head>
<body>

<nav class="navbar navbar-dark custom-nav fixed-top">
    <div class="container-fluid">
        <span class="navbar-brand">☁ CloudBridge Secure</span>
        <a href="user_dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
    </div>
</nav>

<div class="container mt-5 pt-5">
    <div class="card">
        <h3>Send Transfer Request</h3>
        <p>
            Sending data from <b>Cloud <?php echo $sender_cloud; ?></b>
            to <b>Cloud <?php echo $target_cloud; ?></b>
        </p>

        <form method="POST">
            <label>Select Receiver (Other Cloud)</label>

            <select name="receiver_username" required>
                <option value="">-- Select User --</option>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <option value="<?php echo htmlspecialchars($row['username']); ?>">
                        <?php echo htmlspecialchars($row['username']); ?>
                    </option>
                <?php } ?>
            </select>

            <button type="submit">Send Request</button>
        </form>
    </div>
</div>

</body>
</html>
