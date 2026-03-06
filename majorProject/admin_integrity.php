<?php
session_start();
include "db_connection.php";

if (!isset($_SESSION['admin_name'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$app_db = openAppDB();

/* ================= FILTER ================= */
/* ================= FILTER WITH NAME MAPPING ================= */
$sender_name = $_GET['sender'] ?? '';
$receiver_name = $_GET['receiver'] ?? '';

$sender_anon_id = '';
$receiver_anon_id = '';

$where = "WHERE original_hash IS NOT NULL AND decrypted_hash IS NOT NULL";

/* Map sender name to anon_id */
if (!empty($sender_name)) {
    $res = $app_db->query("SELECT anon_id FROM app_users WHERE username = '" . $app_db->real_escape_string($sender_name) . "' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $sender_anon_id = $row['anon_id'];
        $where .= " AND sender_anon_id = '" . $app_db->real_escape_string($sender_anon_id) . "'";
    } else {
        // If no user found, force zero results
        $where .= " AND 1=0";
    }
}

/* Map receiver name to anon_id */
if (!empty($receiver_name)) {
    $res = $app_db->query("SELECT anon_id FROM app_users WHERE username = '" . $app_db->real_escape_string($receiver_name) . "' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $receiver_anon_id = $row['anon_id'];
        $where .= " AND receiver_anon_id = '" . $app_db->real_escape_string($receiver_anon_id) . "'";
    } else {
        $where .= " AND 1=0";
    }
}


/* ================= CSV EXPORT ================= */
if (isset($_GET['export']) && $_GET['export'] == 'csv') {

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="integrity_report.csv"');

    $output = fopen("php://output", "w");
    fputcsv($output, ['ID','Sender','Receiver','Status','Date']);

    $export_sql = "SELECT * FROM encrypted_files $where ORDER BY created_at DESC";
    $export_result = $app_db->query($export_sql);

    while ($row = $export_result->fetch_assoc()) {
        $status = ($row['original_hash'] === $row['decrypted_hash']) ? "Verified" : "Corrupted";
        fputcsv($output, [
            $row['id'],
            $row['sender_anon_id'],
            $row['receiver_anon_id'],
            $status,
            $row['created_at']
        ]);
    }

    fclose($output);
    exit();
}

/* ================= INTEGRITY SUMMARY ================= */

$summary_sql = "SELECT 
                    COUNT(*) AS total_files,
                    SUM(original_hash = decrypted_hash) AS verified_files,
                    SUM(original_hash != decrypted_hash) AS corrupted_files
                FROM encrypted_files
                $where";

$summary_result = $app_db->query($summary_sql);
$summary = $summary_result->fetch_assoc();

$total_files = $summary['total_files'] ?? 0;
$verified_files = $summary['verified_files'] ?? 0;
$corrupted_files = $summary['corrupted_files'] ?? 0;

$integrity_percentage = ($total_files > 0) 
    ? round(($verified_files / $total_files) * 100, 2) 
    : 0;


/* ================= MONTHLY DATA ================= */

$monthly_sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(original_hash = decrypted_hash) AS verified
                FROM encrypted_files
                WHERE original_hash IS NOT NULL 
                AND decrypted_hash IS NOT NULL
                GROUP BY month
                ORDER BY month ASC";

$monthly_result = $app_db->query($monthly_sql);

$months = [];
$monthly_verified = [];

while ($row = $monthly_result->fetch_assoc()) {
    $months[] = $row['month'];
    $monthly_verified[] = $row['verified'];
}


/* ================= DETAILED REPORT ================= */

$details_sql = "SELECT id, sender_anon_id, receiver_anon_id,
                       original_hash, decrypted_hash, created_at
                FROM encrypted_files
                $where
                ORDER BY created_at DESC";

$details_result = $app_db->query($details_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Integrity Report | CloudBridge Secure</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.stat-card {
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
.stat-number {
    font-size: 28px;
    font-weight: 600;
}
.verified { color: #28a745; }
.corrupted { color: #dc3545; }
.percentage { color: #007bff; }
</style>
</head>

<body>

<nav class="navbar navbar-dark custom-nav fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">☁ CloudBridge Secure - Integrity Report</span>
    <span class="text-light">
        Admin: <b><?php echo htmlspecialchars($admin_name); ?></b>
    </span>
    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
  </div>
</nav>

<div class="container" style="margin-top:100px;">

<h3 class="mb-4">File Integrity Overview</h3>

<!-- ================= FILTER + EXPORT ================= -->
<form method="GET" class="row mb-4">
    <div class="col-md-3">
        <input type="text" name="sender" class="form-control" 
               placeholder="Filter by Sender"
               value="<?php echo htmlspecialchars($sender_filter); ?>">
    </div>
    <div class="col-md-3">
        <input type="text" name="receiver" class="form-control" 
               placeholder="Filter by Receiver"
               value="<?php echo htmlspecialchars($receiver_filter); ?>">
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-primary">Apply Filter</button>
        <a href="admin_integrity.php" class="btn btn-secondary">Reset</a>
    </div>
    <div class="col-md-3 text-end">
        <a href="?export=csv&sender=<?php echo urlencode($sender_filter); ?>&receiver=<?php echo urlencode($receiver_filter); ?>" 
           class="btn btn-success">📤 Export CSV</a>
    </div>
</form>


<!-- ================= SUMMARY CARDS ================= -->
<div class="row mb-4">

    <div class="col-md-3">
        <div class="stat-card text-center">
            <h6>Total Files Checked</h6>
            <div class="stat-number"><?php echo $total_files; ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card text-center">
            <h6>Verified Files</h6>
            <div class="stat-number verified"><?php echo $verified_files; ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card text-center">
            <h6>Corrupted Files</h6>
            <div class="stat-number corrupted"><?php echo $corrupted_files; ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card text-center">
            <h6>Integrity %</h6>
            <div class="stat-number percentage"><?php echo $integrity_percentage; ?>%</div>
        </div>
    </div>

</div>


<!-- ================= CHARTS ================= -->
<div class="row mb-5">
    <div class="col-md-6">
        <canvas id="integrityPie"></canvas>
    </div>
    <div class="col-md-6">
        <canvas id="monthlyChart"></canvas>
    </div>
</div>


<!-- ================= DETAILED TABLE ================= -->
<div class="card">
<div class="card-header bg-dark text-white">
    Detailed File Integrity Report
</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Sender</th>
            <th>Receiver</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>

    <?php 
if ($details_result && $details_result->num_rows > 0): 

    // Preload all anon_id → username mapping
    $user_map = [];
    $user_res = $app_db->query("SELECT anon_id, username FROM app_users");
    while ($u = $user_res->fetch_assoc()) {
        $user_map[$u['anon_id']] = $u['username'];
    }

    while ($row = $details_result->fetch_assoc()): 
        $is_verified = ($row['original_hash'] === $row['decrypted_hash']);
        $sender_name = $user_map[$row['sender_anon_id']] ?? $row['sender_anon_id'];
        $receiver_name = $user_map[$row['receiver_anon_id']] ?? $row['receiver_anon_id'];
?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo htmlspecialchars($sender_name); ?></td>
    <td><?php echo htmlspecialchars($receiver_name); ?></td>
    <td>
        <?php if ($is_verified): ?>
            <span class="badge bg-success">Verified</span>
        <?php else: ?>
            <span class="badge bg-danger">Corrupted</span>
        <?php endif; ?>
    </td>
    <td><?php echo $row['created_at']; ?></td>
</tr>
<?php endwhile; ?>
<?php endif; ?>

        

    </tbody>
</table>

</div>
</div>

</div>

<script>
new Chart(document.getElementById('integrityPie'), {
    type: 'pie',
    data: {
        labels: ['Verified', 'Corrupted'],
        datasets: [{
            data: [<?= $verified_files ?>, <?= $corrupted_files ?>],
            backgroundColor: ['#28a745','#dc3545']
        }]
    }
});

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($months); ?>,
        datasets: [{
            label: 'Verified Files',
            data: <?= json_encode($monthly_verified); ?>,
            borderColor: '#007bff',
            fill: false,
            tension: 0.3
        }]
    }
});
</script>

</body>
</html>
