<?php
session_start();
include "db_connection.php";

if (!isset($_SESSION['admin_name'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$app_db = openAppDB();

/* ================= GET FILTERS ================= */
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$status     = $_GET['status'] ?? 'ALL';

$single_day = ($start_date === $end_date);

/* ================= WHERE CLAUSE ================= */
$where_clause = $single_day 
    ? "WHERE DATE(created_at) = '$start_date'"
    : "WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";

if ($status !== 'ALL') $where_clause .= " AND status='$status'";

/* ================= TOTAL TRANSFERS ================= */
$total_transfers = $app_db->query("SELECT COUNT(*) as total FROM transfer_requests $where_clause")
                          ->fetch_assoc()['total'];

/* ================= TRANSFER TREND ================= */
$trend_data = [];


if ($single_day) {
    // Initialize 24 hours
    for ($h = 0; $h < 24; $h++) $trend_data[sprintf('%02d:00', $h)] = 0;

    $trend_sql = "SELECT HOUR(created_at) as hour, COUNT(*) as total
                  FROM transfer_requests
                  $where_clause
                  GROUP BY HOUR(created_at)
                  ORDER BY hour ASC";
} else {
    $start_dt = new DateTime($start_date);
    $end_dt   = new DateTime($end_date);
    $end_dt->modify('+1 day');
    for ($date = $start_dt; $date < $end_dt; $date->modify('+1 day')) $trend_data[$date->format('Y-m-d')] = 0;

    $trend_sql = "SELECT DATE(created_at) as trans_date, COUNT(*) as total
                  FROM transfer_requests
                  $where_clause
                  GROUP BY DATE(created_at)
                  ORDER BY trans_date ASC";
}

$trend_query = $app_db->query($trend_sql);
while ($row = $trend_query->fetch_assoc()) {
    $label = $single_day ? sprintf('%02d:00', $row['hour']) : $row['trans_date'];
    $trend_data[$label] = $row['total'];
}

// $chart_labels = array_keys($trend_data);

if ($single_day) {
    // Convert 24-hour keys to 12-hour format with AM/PM
    $chart_labels = array_map(function($hour24) {
        $h = intval(substr($hour24, 0, 2));
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h = $h % 12;
        if ($h == 0) $h = 12;
        return $h . ':00 ' . $ampm;
    }, array_keys($trend_data));
} else {
    $chart_labels = array_keys($trend_data);
}


$chart_counts = array_values($trend_data);


/* ================= CLOUD A vs B ================= */
$comp_data = [];
if ($single_day) {
    // Initialize 24 hours
    for ($h = 0; $h < 24; $h++) $comp_data[sprintf('%02d:00', $h)] = ['A'=>0,'B'=>0];

    $cloud_sql = "SELECT HOUR(tr.created_at) as hour, u.cloud, COUNT(*) as total
                  FROM transfer_requests tr
                  JOIN app_users u ON tr.sender_anon_id = u.anon_id
                  WHERE DATE(tr.created_at) = '$start_date' " . ($status!=='ALL' ? "AND tr.status='$status'" : "") . "
                  GROUP BY HOUR(tr.created_at), u.cloud
                  ORDER BY hour ASC";
} else {
    $start_dt = new DateTime($start_date);
    $end_dt   = new DateTime($end_date);
    $end_dt->modify('+1 day');
    for ($date = $start_dt; $date < $end_dt; $date->modify('+1 day')) $comp_data[$date->format('Y-m-d')] = ['A'=>0,'B'=>0];

    $cloud_sql = "SELECT DATE(tr.created_at) as trans_date, u.cloud, COUNT(*) as total
                  FROM transfer_requests tr
                  JOIN app_users u ON tr.sender_anon_id = u.anon_id
                  WHERE DATE(tr.created_at) BETWEEN '$start_date' AND '$end_date' " . ($status!=='ALL' ? "AND tr.status='$status'" : "") . "
                  GROUP BY DATE(tr.created_at), u.cloud
                  ORDER BY trans_date ASC";
}

$cloud_query = $app_db->query($cloud_sql);
while ($row = $cloud_query->fetch_assoc()) {
    $label = $single_day ? sprintf('%02d:00', $row['hour']) : $row['trans_date'];
    $comp_data[$label][$row['cloud']] = $row['total'];
}

// if ($single_day) {
//     // Convert 24-hour keys to 12-hour format with AM/PM, in order 0–23
//     $comp_labels = [];
//     foreach (array_keys($comp_data) as $hour24) {
//         $h = intval(substr($hour24, 0, 2));
//         $ampm = $h >= 12 ? 'PM' : 'AM';
//         $h = $h % 12;
//         if ($h == 0) $h = 12;
//         $comp_labels[] = $h . ':00 ' . $ampm;
//     }
// } else {
//     $comp_labels = array_keys($comp_data);
// }

$comp_labels = [];
$comp_a = [];
$comp_b = [];

if ($single_day) {
    for ($h = 0; $h < 24; $h++) {
        $hour24 = sprintf('%02d:00', $h);
        $ampm_hour = $h % 12;
        if ($ampm_hour == 0) $ampm_hour = 12;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $comp_labels[] = $ampm_hour . ':00 ' . $ampm;

        // Make sure we get data in the same order as labels
        $comp_a[] = $comp_data[$hour24]['A'] ?? 0;
        $comp_b[] = $comp_data[$hour24]['B'] ?? 0;
    }
} else {
    $comp_labels = array_keys($comp_data);
    $comp_a = array_map(fn($v)=>$v['A'], $comp_data);
    $comp_b = array_map(fn($v)=>$v['B'], $comp_data);
}


// $comp_labels = array_keys($comp_data);
// $comp_a = array_map(fn($v)=>$v['A'], $comp_data);
// $comp_b = array_map(fn($v)=>$v['B'], $comp_data);

?>

<!DOCTYPE html>
<html>
<head>
<title>Transfer Requests | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.chart-container { overflow-x:auto; padding-bottom:20px; }
.line-chart { height:400px; }
</style>
</head>
<body>

<nav class="navbar navbar-dark custom-nav fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">☁ CloudBridge Secure - Transfer Requests</span>
    <span class="text-light">
        Admin: <b><?= htmlspecialchars($admin_name) ?></b>
    </span>
    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
  </div>
</nav>

<div class="container" style="margin-top:100px;">
    <h2 class="mb-4">Transfer Requests Report</h2>

    <div class="card p-3 mb-4">
        <h5>Filter Requests</h5>
        <form method="GET" class="d-flex align-items-center flex-wrap">
            <label><b>Start:</b></label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control ms-2">
            <label class="ms-3"><b>End:</b></label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control ms-2">
            
            <label class="ms-3"><b>Status:</b></label>
            <select name="status" class="form-select ms-2 w-auto">
                <option value="ALL" <?= $status=='ALL'?'selected':'' ?>>All</option>
                <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
            </select>

            <button type="submit" class="btn btn-primary ms-3">Apply</button>
        </form>
    </div>

    <div class="card p-3 mb-4">
        <h5>Total Requests: <?= $total_transfers ?></h5>
        <div class="chart-container">
            <canvas id="transferChart" class="line-chart"></canvas>
        </div>
    </div>

    <div class="card p-3">
        <h5>Comparison: Cloud A vs Cloud B</h5>
        <div class="chart-container">
            <canvas id="compareChart" class="line-chart"></canvas>
        </div>
    </div>
</div>

<script>
/* ================= TREND CHART ================= */
let numDates1 = <?= count($chart_labels) ?>;
document.getElementById('transferChart').style.width = Math.max(numDates1*30,800)+'px';
new Chart(document.getElementById('transferChart'), {
    type:'line',
    data:{
        labels: <?= json_encode($chart_labels) ?>,
        datasets:[{
            label:'Transfers',
            data: <?= json_encode($chart_counts) ?>,
            borderColor:'#fd7e14',
            backgroundColor:'rgba(253,126,20,0.1)',
            fill:true,
            tension:0.3,
            pointRadius:4,
            pointHoverRadius:6
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        interaction:{mode:'nearest',axis:'x',intersect:false},
        scales:{x:{title:{display:true,text:'<?= $single_day ? "Hour" : "Date" ?>'}},y:{title:{display:true,text:'Transfers'},beginAtZero:true}}
    }
});

/* ================= CLOUD COMPARISON ================= */
let numDates2 = <?= count($comp_labels) ?>;
document.getElementById('compareChart').style.width = Math.max(numDates2*30,800)+'px';
new Chart(document.getElementById('compareChart'), {
    type:'line',
    data:{
        labels: <?= json_encode($comp_labels) ?>,
        datasets:[
            {label:'Cloud A', data: <?= json_encode($comp_a) ?>, borderColor:'#007bff', backgroundColor:'rgba(0,123,255,0.1)', fill:true, tension:0.3, pointRadius:4, pointHoverRadius:6},
            {label:'Cloud B', data: <?= json_encode($comp_b) ?>, borderColor:'#28a745', backgroundColor:'rgba(40,167,69,0.1)', fill:true, tension:0.3, pointRadius:4, pointHoverRadius:6}
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:true}},
        interaction:{mode:'nearest',axis:'x',intersect:false},
        scales:{x:{title:{display:true,text:'<?= $single_day ? "Hour" : "Date" ?>'}},y:{title:{display:true,text:'Transfers'},beginAtZero:true}}
    }
});
</script>

</body>
</html>
