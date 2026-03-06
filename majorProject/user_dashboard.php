<?php
session_start();
include "db_connection.php";

/* ---------- SESSION CHECK ---------- */
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'];
$anon_id  = $_SESSION['anon_id'];

$app_db = openAppDB();

/* ---------- DASHBOARD COUNTS ---------- */

// Sent requests
$stmt1 = $app_db->prepare(
    "SELECT COUNT(*) FROM transfer_requests WHERE sender_username = ?"
);
$stmt1->bind_param("s", $username);
$stmt1->execute();
$stmt1->bind_result($sent_requests);
$stmt1->fetch();
$stmt1->close();

// Pending approvals (requests received)
$stmt2 = $app_db->prepare(
    "SELECT COUNT(*) FROM transfer_requests 
     WHERE receiver_username = ? AND status = 'pending'"
);
$stmt2->bind_param("s", $username);
$stmt2->execute();
$stmt2->bind_result($pending_requests);
$stmt2->fetch();
$stmt2->close();

// Approved transfers
$stmt3 = $app_db->prepare(
    "SELECT COUNT(*) FROM transfer_requests 
     WHERE sender_username = ? AND status = 'approved'"
);
$stmt3->bind_param("s", $username);
$stmt3->execute();
$stmt3->bind_result($approved_transfers);
$stmt3->fetch();
$stmt3->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Dashboard | CloudBridge Secure</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="user_dashboard.css">
</head>
<body>

<!-- ================= TOP NAVBAR ================= -->
<nav class="navbar navbar-dark custom-nav fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">☁ CloudBridge Secure</span>

    <span class="text-light">
        User: <b><?php echo htmlspecialchars($username); ?></b>
    </span>

    <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">

    <!-- ================= SIDEBAR ================= -->
    <div class="col-md-3 sidebar">
      <h5 class="mb-4">User Menu</h5>

      <a href="send_request.php">📨 Send Transfer Request</a>
      <a href="incoming_requests.php">📥 Incoming Requests</a>
      <a href="approved_transfers.php">✅ Approved Transfers</a>

      <hr>

        <a href="upload_file.php">📤 Upload File to Cloud</a>
        <a href="received_files_list.php">📂 Received Files</a>

    </div>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="col-md-9 main-content">

      <h2>Welcome to Your Dashboard</h2>
      <p class="dashboard-subtext">
        Secure, anonymous, certificate-free cloud-to-cloud file transfer.
      </p>

      <!-- ================= STATUS CARDS ================= -->
      <div class="row mt-4">

        <div class="col-md-4">
          <div class="info-card">
            <h6>Sent Requests</h6>
            <p class="count"><?php echo $sent_requests; ?></p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="info-card">
            <h6>Pending Approvals</h6>
            <p class="count"><?php echo $pending_requests; ?></p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="info-card">
            <h6>Encrypted Transfers</h6>
            <p class="count"><?php echo $approved_transfers; ?></p>
          </div>
        </div>

      </div>

      <!-- ================= DESCRIPTION ================= -->
      <div class="mt-4">
        <p>
          This system uses anonymous identities, ECC-based key agreement,
          and AES encryption to ensure privacy-preserving cloud communication
          without certificates.
        </p>
      </div>

    </div>
  </div>
</div>

</body>
</html>
