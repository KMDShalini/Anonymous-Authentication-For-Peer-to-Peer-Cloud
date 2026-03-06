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
$ft_start = $_GET['ft_start'] ?? date('Y-m-d');
$ft_end   = $_GET['ft_end'] ?? date('Y-m-d');

$is_single_day = ($ft_start === $ft_end);

/* ================= INITIALIZE ARRAYS ================= */
$file_dates = [];
$cloud_dates = [];

if ($is_single_day) {
    // Per hour
    // for ($h = 0; $h < 24; $h++) {
    //     $hour_label = sprintf("%02d:00", $h);
    //     $file_dates[$hour_label] = 0;
    //     $cloud_dates[$hour_label] = ['A'=>0,'B'=>0];
    // }
  for ($h = 0; $h < 24; $h++) {
    $hour_label = date("g A", strtotime("$h:00"));
    $file_dates[$hour_label] = 0;
    $cloud_dates[$hour_label] = ['A'=>0,'B'=>0];
}

    /* ================= FILE TRANSFERS PER HOUR ================= */
    $file_sql = "SELECT HOUR(created_at) as hr, COUNT(*) as total
                 FROM encrypted_files
                 WHERE DATE(created_at) = '$ft_start'
                 GROUP BY HOUR(created_at)
                 ORDER BY hr ASC";
    $file_query = $app_db->query($file_sql);
    while ($row = $file_query->fetch_assoc()) {
        // $hour_label = sprintf("%02d:00", $row['hr']);
        $hour_label = date("g A", strtotime($row['hr'] . ":00"));
        $file_dates[$hour_label] = $row['total'];
    }

    /* ================= CLOUD A vs B PER HOUR ================= */
    $cloud_sql = "SELECT HOUR(ef.created_at) as hr, u.cloud, COUNT(*) as total
                  FROM encrypted_files ef
                  JOIN app_users u ON ef.sender_anon_id = u.anon_id
                  WHERE DATE(ef.created_at) = '$ft_start'
                  GROUP BY HOUR(ef.created_at), u.cloud
                  ORDER BY hr ASC";
    $cloud_query = $app_db->query($cloud_sql);
    while ($row = $cloud_query->fetch_assoc()) {
        // $hour_label = sprintf("%02d:00", $row['hr']);
        $hour_label = date("g A", strtotime($row['hr'] . ":00"));
        $cloud_dates[$hour_label][$row['cloud']] = $row['total'];
    }

} else {
    // Per day
    $ft_start_dt = new DateTime($ft_start);
    $ft_end_dt   = new DateTime($ft_end);
    $ft_end_dt->modify('+1 day');

    for ($date = $ft_start_dt; $date < $ft_end_dt; $date->modify('+1 day')) {
        $file_dates[$date->format('Y-m-d')] = 0;
        $cloud_dates[$date->format('Y-m-d')] = ['A'=>0,'B'=>0];
    }

    /* ================= FILE TRANSFERS PER DAY ================= */
    $file_sql = "SELECT DATE(created_at) as trans_date, COUNT(*) as total
                 FROM encrypted_files
                 WHERE DATE(created_at) BETWEEN '$ft_start' AND '$ft_end'
                 GROUP BY DATE(created_at)
                 ORDER BY trans_date ASC";
    $file_query = $app_db->query($file_sql);
    while ($row = $file_query->fetch_assoc()) {
        $file_dates[$row['trans_date']] = $row['total'];
    }

    /* ================= CLOUD A vs B PER DAY ================= */
    $cloud_sql = "SELECT DATE(ef.created_at) as trans_date, u.cloud, COUNT(*) as total
                  FROM encrypted_files ef
                  JOIN app_users u ON ef.sender_anon_id = u.anon_id
                  WHERE DATE(ef.created_at) BETWEEN '$ft_start' AND '$ft_end'
                  GROUP BY DATE(ef.created_at), u.cloud
                  ORDER BY trans_date ASC";
    $cloud_query = $app_db->query($cloud_sql);
    while ($row = $cloud_query->fetch_assoc()) {
        $cloud_dates[$row['trans_date']][$row['cloud']] = $row['total'];
    }
}

/* ================= FINAL CHART ARRAYS ================= */
$file_chart_dates  = array_keys($file_dates);
$file_chart_counts = array_values($file_dates);
$cloud_chart_dates = array_keys($cloud_dates);
$cloud_chart_a     = array_map(fn($v)=>$v['A'], $cloud_dates);
$cloud_chart_b     = array_map(fn($v)=>$v['B'], $cloud_dates);

?>
<!DOCTYPE html>
<html>
<head>
<title>File Transfers | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.chart-container { overflow-x:auto; padding-bottom:20px; }
.line-chart { height:400px; }
</style>
</head>
<body>

<!-- ================= TOP NAVBAR ================= -->
<nav class="navbar navbar-dark custom-nav fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">☁ CloudBridge Secure - File Transfers</span>
    <span class="text-light">
        Admin: <b><?= htmlspecialchars($admin_name) ?></b>
    </span>
    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
  </div>
</nav>

<div class="container" style="margin-top:100px;">
    <h2 class="mb-4">File Transfers</h2>

    <form method="GET" class="d-flex align-items-center flex-wrap mb-3">
        <label><b>Start:</b></label>
        <input type="date" name="ft_start" value="<?= htmlspecialchars($ft_start) ?>" class="form-control ms-2">
        <label class="ms-3"><b>End:</b></label>
        <input type="date" name="ft_end" value="<?= htmlspecialchars($ft_end) ?>" class="form-control ms-2">
        <button type="submit" class="btn btn-primary ms-3">Apply</button>
    </form>

    <h5>Total Transfers: <?= array_sum($file_chart_counts) ?></h5>

    <div class="chart-container">
        <canvas id="fileTransferChart" class="line-chart"></canvas>
    </div>

    <div class="chart-container mt-3">
        <canvas id="fileCompareChart" class="line-chart"></canvas>
    </div>
</div>

<script>
const xLabel = "<?= $is_single_day ? 'Hour' : 'Date' ?>";

let numDatesFile1 = <?= count($file_chart_dates) ?>;
document.getElementById('fileTransferChart').style.width = Math.max(numDatesFile1*30,800)+'px';
new Chart(document.getElementById('fileTransferChart'), {
    type:'line',
    data:{
        labels: <?= json_encode($file_chart_dates) ?>,
        datasets:[{
            label:'Actual File Transfers',
            data: <?= json_encode($file_chart_counts) ?>,
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
        scales:{
            x:{title:{display:true,text:xLabel}},
            y:{title:{display:true,text:'Transfers'},beginAtZero:true}
        }
    }
});

let numDatesFile2 = <?= count($cloud_chart_dates) ?>;
document.getElementById('fileCompareChart').style.width = Math.max(numDatesFile2*30,800)+'px';
new Chart(document.getElementById('fileCompareChart'), {
    type:'line',
    data:{
        labels: <?= json_encode($cloud_chart_dates) ?>,
        datasets:[
            {label:'Cloud A', data: <?= json_encode($cloud_chart_a) ?>, borderColor:'#007bff', backgroundColor:'rgba(0,123,255,0.1)', fill:true, tension:0.3, pointRadius:4, pointHoverRadius:6},
            {label:'Cloud B', data: <?= json_encode($cloud_chart_b) ?>, borderColor:'#28a745', backgroundColor:'rgba(40,167,69,0.1)', fill:true, tension:0.3, pointRadius:4, pointHoverRadius:6}
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:true}},
        interaction:{mode:'nearest',axis:'x',intersect:false},
        scales:{
            x:{title:{display:true,text:xLabel}},
            y:{title:{display:true,text:'Transfers'},beginAtZero:true}
        }
    }
});
</script>

</body>
</html>