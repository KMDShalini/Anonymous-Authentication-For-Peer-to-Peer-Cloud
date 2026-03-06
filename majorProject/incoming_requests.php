<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include "db_connection.php";

/* ---------- SESSION CHECK ---------- */
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$app_db = openAppDB();

$receiver_username = $_SESSION['username'];
$receiver_anon_id  = $_SESSION['anon_id'];
$receiver_cloud    = $_SESSION['cloud'];

/* ---------- HANDLE APPROVE / REJECT ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $request_id = $_POST['request_id'];
    $action     = $_POST['action'];

    if ($action === "reject") {

        $stmt = $app_db->prepare(
            "UPDATE transfer_requests SET status = 'rejected' WHERE id = ?"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();

    } elseif ($action === "approve") {

        /* Get receiver public key from cloud DB */
        $cloud_db = ($receiver_cloud === "A") ? openCloudADB() : openCloudBDB();

        $stmt1 = $cloud_db->prepare(
            "SELECT ecc_public_key FROM cloud_users WHERE anon_id = ?"
        );
        $stmt1->bind_param("s", $receiver_anon_id);
        $stmt1->execute();
        $stmt1->bind_result($receiver_public_key);
        $stmt1->fetch();
        $stmt1->close();

        /* Store public key in transfer_requests */
        $stmt2 = $app_db->prepare(
            "UPDATE transfer_requests
             SET status = 'approved', receiver_public_key = ?
             WHERE id = ?"
        );
        $stmt2->bind_param("si", $receiver_public_key, $request_id);
        $stmt2->execute();
        $stmt2->close();
    }

    header("Location: incoming_requests.php");
    exit();
}

/* ---------- FETCH PENDING REQUESTS ---------- */
$stmt = $app_db->prepare(
    "SELECT id,sender_alias_name FROM transfer_requests
     WHERE receiver_username = ? AND status = 'pending'"
);
$stmt->bind_param("s", $receiver_username);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incoming Requests</title>
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
        <h3>Incoming Transfer Requests</h3>

        <?php if ($result->num_rows === 0) { ?>
            <p>No pending requests.</p>
        <?php } else { ?>

        <table class="table table-bordered">
            <tr>
                <th>Sender</th>
                <th>Action</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['sender_alias_name']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit">Approve</button>
                        </form>

                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>

        </table>

        <?php } ?>
    </div>
</div>

</body>
</html>
