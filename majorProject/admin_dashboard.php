<?php
session_start();
include "db_connection.php";

if (!isset($_SESSION['admin_name'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$app_db = openAppDB();

/* ================= DASHBOARD STATS ================= */

/* Total Users */
$result1 = $app_db->query("SELECT COUNT(*) AS total FROM app_users");
$total_users = $result1->fetch_assoc()['total'] ?? 0;

/* Pending Transfers */
$result2 = $app_db->query("SELECT COUNT(*) AS total FROM transfer_requests WHERE status='pending'");
$pending_transfers = $result2->fetch_assoc()['total'] ?? 0;

/* Key Failures (Encrypted but not decrypted yet) */
$result3 = $app_db->query("SELECT COUNT(*) AS total FROM encrypted_files WHERE decrypted_hash IS NULL");
$key_failures = $result3->fetch_assoc()['total'] ?? 0;

/* Total Files Transferred */
$result_files = $app_db->query("SELECT COUNT(*) AS total FROM encrypted_files");
$total_files_transferred = $result_files->fetch_assoc()['total'] ?? 0;


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard | CloudBridge Secure</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin_dashboard.css">
</head>
<body>

<!-- ================= TOP NAVBAR ================= -->
<nav class="navbar navbar-dark custom-nav fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">☁ CloudBridge Secure - Admin</span>

    <span class="text-light">
        Admin: <b><?php echo htmlspecialchars($admin_name); ?></b>
    </span>

    <a href="admin_logout.php" class="btn btn-outline-light btn-sm">Logout</a>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">

    <!-- ================= SIDEBAR ================= -->
    <div class="col-md-3 sidebar">
      <h5 class="mb-4">Admin Menu</h5>

      <a href="admin_users.php">👥 Total Users</a>
      <!-- <a href="admin_transfers.php">🔄 File Transfers</a>

      <a href="admin_transfers_report.php">🔄 Transfer Requests Report</a> -->

      <a href="admin_transfers.php">📁 File Transfers</a>
  <a href="admin_transfers_report.php">📊 Transfer Requests Report</a> 

      <!-- <a href="admin_key_failures.php">🔐 Key Exchange Failures</a> -->
      <a href="admin_integrity.php">🧾 Integrity Report</a>
      <!-- <a href="admin_downloads.php">📥 Downloads Report</a>
      <a href="admin_reports.php">📊 System Graph Reports</a> -->
    </div>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="col-md-9 main-content">
      <h2>Welcome, Admin</h2>
      <p class="dashboard-subtext">
        Monitor and manage CloudBridge Secure system activities.
      </p>

      <!-- ================= STATUS CARDS ================= -->
      <div class="row mt-4">

        <div class="col-md-4">
          <div class="info-card text-center">
            <h6>Total Users</h6>
            <p class="count"><?php echo $total_users; ?></p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="info-card text-center">
            <h6>Pending Transfers</h6>
            <p class="count"><?php echo $pending_transfers; ?></p>
          </div>
        </div>

        <!-- <div class="col-md-4">
          <div class="info-card text-center">
            <h6>Key Failures</h6>
            <p class="count"><?php echo $key_failures; ?></p>
          </div>
        </div> -->


        <div class="col-md-4">
  <div class="info-card text-center">
    <h6>Total Files Transferred</h6>
    <p class="count"><?php echo $total_files_transferred; ?></p>
  </div>
</div>


      </div>

      <!-- ================= DESCRIPTION ================= -->
      <div class="mt-4">
        <p>
          This dashboard provides real-time monitoring of anonymous users,
          cross-cloud transfer activity, and cryptographic key exchange integrity.
          User identities remain hidden during operation, but system-level metrics
          are securely monitored by the administrator.
        </p>
      </div>

    </div>
  </div>
</div>

</body>
</html>
