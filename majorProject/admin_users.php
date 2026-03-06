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
$cloud_filter = $_GET['cloud'] ?? 'ALL';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clause = "";
if ($cloud_filter === 'A') {
    $where_clause = "WHERE cloud='A'";
} elseif ($cloud_filter === 'B') {
    $where_clause = "WHERE cloud='B'";
}

/* ================= USERS PER CLOUD ================= */
$cloudA = $app_db->query("SELECT COUNT(*) as total FROM app_users WHERE cloud='A'")
                  ->fetch_assoc()['total'];
$cloudB = $app_db->query("SELECT COUNT(*) as total FROM app_users WHERE cloud='B'")
                  ->fetch_assoc()['total'];

/* ================= REGISTRATION TREND FOR FIRST LINE CHART ================= */
// Get min and max dates for selected cloud
$range_sql = "SELECT MIN(DATE(created_at)) as min_date, MAX(DATE(created_at)) as max_date FROM app_users $where_clause";
$range_result = $app_db->query($range_sql)->fetch_assoc();

$start = new DateTime($range_result['min_date']);
$end = new DateTime($range_result['max_date']);
$end->modify('+1 day'); // include last day

// Initialize all dates with 0
$dates = [];
for ($date = $start; $date < $end; $date->modify('+1 day')) {
    $dates[$date->format('Y-m-d')] = 0;
}

// Fetch actual registration counts
$trend_sql = "
    SELECT DATE(created_at) as reg_date, COUNT(*) as total
    FROM app_users
    $where_clause
    GROUP BY DATE(created_at)
    ORDER BY reg_date ASC
";
$trend_query = $app_db->query($trend_sql);
while ($row = $trend_query->fetch_assoc()) {
    $dates[$row['reg_date']] = $row['total'];
}

$chart_dates = array_keys($dates);
$chart_counts = array_values($dates);

/* ================= REGISTRATION TREND COMPARISON (CLOUD A vs B) ================= */
// Determine start and end dates for comparison chart
if ($start_date && $end_date) {
    $comp_start = new DateTime($start_date);
    $comp_end = new DateTime($end_date);
} else {
    $min_max = $app_db->query("SELECT MIN(DATE(created_at)) as min_date, MAX(DATE(created_at)) as max_date FROM app_users")->fetch_assoc();
    $comp_start = new DateTime($min_max['min_date']);
    $comp_end = new DateTime($min_max['max_date']);
}
$comp_end->modify('+1 day');

// Initialize comparison dates
$comp_dates = [];
for ($date = $comp_start; $date < $comp_end; $date->modify('+1 day')) {
    $comp_dates[$date->format('Y-m-d')] = ['A'=>0,'B'=>0];
}

// Fetch Cloud A counts
$date_where = ($start_date && $end_date) ? "AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'" : "";
$a_sql = "SELECT DATE(created_at) as reg_date, COUNT(*) as total FROM app_users WHERE cloud='A' $date_where GROUP BY DATE(created_at) ORDER BY reg_date ASC";
$a_query = $app_db->query($a_sql);
while ($row = $a_query->fetch_assoc()) {
    $comp_dates[$row['reg_date']]['A'] = $row['total'];
}

// Fetch Cloud B counts
$b_sql = "SELECT DATE(created_at) as reg_date, COUNT(*) as total FROM app_users WHERE cloud='B' $date_where GROUP BY DATE(created_at) ORDER BY reg_date ASC";
$b_query = $app_db->query($b_sql);
while ($row = $b_query->fetch_assoc()) {
    $comp_dates[$row['reg_date']]['B'] = $row['total'];
}

// Prepare arrays for Chart.js
$comp_chart_dates = array_keys($comp_dates);
$comp_chart_a = array_map(fn($v)=>$v['A'], $comp_dates);
$comp_chart_b = array_map(fn($v)=>$v['B'], $comp_dates);
?>
<!DOCTYPE html>
<html>
<head>
<title>User Analytics | Admin</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="admin_dashboard.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Scrollable line charts */
.chart-container {
    overflow-x: auto;
    padding-bottom: 20px;
}
.line-chart {
    height: 400px;
}
</style>
</head>
<body>



<nav class="navbar navbar-dark custom-nav fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">☁ CloudBridge Secure - User Analytics</span>
    <span class="text-light">
        Admin: <b><?php echo htmlspecialchars($admin_name); ?></b>
    </span>
    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
  </div>
</nav>

<div class="container" style="margin-top:100px;">

    <h2 class="mb-4">User Analytics Overview</h2>

    <div class="row">
        <!-- BAR CHART -->
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Users Per Cloud</h5>
                <canvas id="cloudChart"></canvas>
            </div>
        </div>

        <!-- PIE CHART -->
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Cloud Distribution</h5>
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>

    <br>

    <!-- FIRST LINE CHART (Cloud Filter) -->
    <div class="card p-3">
        <h5>User Growth Trend 
            <?= $cloud_filter=='A'?"(Cloud A)":($cloud_filter=='B'?"(Cloud B)":"(All Clouds)") ?>
        </h5>

        <form method="GET" class="mb-3">
            <label><b>Filter by Cloud:</b></label>
            <select name="cloud" onchange="this.form.submit()" class="form-select w-25 d-inline-block ms-2">
                <option value="ALL" <?= $cloud_filter=='ALL'?'selected':'' ?>>All Clouds</option>
                <option value="A" <?= $cloud_filter=='A'?'selected':'' ?>>Cloud A (AWS)</option>
                <option value="B" <?= $cloud_filter=='B'?'selected':'' ?>>Cloud B</option>
            </select>
        </form>

        <div class="chart-container">
            <canvas id="trendChart" class="line-chart"></canvas>
        </div>
    </div>

    <br>

    <!-- COMPARISON LINE CHART (Date Filter) -->
    <div class="card p-3">
        <h5>Cloud A vs Cloud B Registration Comparison</h5>

        <form method="GET" class="mb-3">
            <input type="hidden" name="cloud" value="<?= htmlspecialchars($cloud_filter) ?>">
            <label><b>Filter by Date Range:</b></label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control d-inline-block w-auto ms-2">
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control d-inline-block w-auto ms-2">
            <button type="submit" class="btn btn-primary ms-2">Apply</button>
        </form>

        <div class="chart-container">
            <canvas id="compareChart" class="line-chart"></canvas>
        </div>
    </div>

</div>

<script>
/* ================= BAR CHART ================= */
new Chart(document.getElementById('cloudChart'), {
    type: 'bar',
    data: {
        labels: ['Cloud A (AWS)','Cloud B'],
        datasets: [{label:'Total Users',data:[<?= $cloudA ?>,<?= $cloudB ?>],backgroundColor:['#007bff','#28a745']}]
    }
});

/* ================= PIE CHART ================= */
new Chart(document.getElementById('pieChart'), {
    type:'pie',
    data:{
        labels:['Cloud A','Cloud B'],
        datasets:[{data:[<?= $cloudA ?>,<?= $cloudB ?>],backgroundColor:['#007bff','#28a745']}]
    },
    options:{
        responsive:true,
        plugins:{
            tooltip:{
                callbacks:{
                    label:function(ctx){
                        let total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                        return ctx.label + ': ' + ((ctx.raw/total)*100).toFixed(2)+'%';
                    }
                }
            }
        }
    }
});

/* ================= FIRST LINE CHART ================= */
let numDates1 = <?= count($chart_dates) ?>;
document.getElementById('trendChart').style.width = Math.max(numDates1*30,800)+'px';

new Chart(document.getElementById('trendChart'), {
    type:'line',
    data:{
        labels: <?= json_encode($chart_dates) ?>,
        datasets:[{
            label:'Registrations',
            data: <?= json_encode($chart_counts) ?>,
            borderColor:'#6610f2',
            backgroundColor:'rgba(102,16,242,0.1)',
            fill:true,
            tension:0.3,
            pointRadius:4,
            pointHoverRadius:6
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            tooltip:{mode:'index',intersect:false,callbacks:{label:ctx=>`Registrations: ${ctx.raw}`}},
            legend:{display:false}
        },
        interaction:{mode:'nearest',axis:'x',intersect:false},
        scales:{
            x:{title:{display:true,text:'Date'},ticks:{maxRotation:90,minRotation:45}},
            y:{title:{display:true,text:'Registrations'},beginAtZero:true}
        }
    }
});

/* ================= COMPARISON LINE CHART ================= */
let numDates2 = <?= count($comp_chart_dates) ?>;
document.getElementById('compareChart').style.width = Math.max(numDates2*30,800)+'px';

new Chart(document.getElementById('compareChart'), {
    type:'line',
    data:{
        labels: <?= json_encode($comp_chart_dates) ?>,
        datasets:[
            {label:'Cloud A',data: <?= json_encode($comp_chart_a) ?>,borderColor:'#007bff',backgroundColor:'rgba(0,123,255,0.1)',fill:true,tension:0.3,pointRadius:4,pointHoverRadius:6},
            {label:'Cloud B',data: <?= json_encode($comp_chart_b) ?>,borderColor:'#28a745',backgroundColor:'rgba(40,167,69,0.1)',fill:true,tension:0.3,pointRadius:4,pointHoverRadius:6}
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            tooltip:{mode:'index',intersect:false},
            legend:{display:true}
        },
        interaction:{mode:'nearest',axis:'x',intersect:false},
        scales:{
            x:{title:{display:true,text:'Date'},ticks:{maxRotation:90,minRotation:45}},
            y:{title:{display:true,text:'Registrations'},beginAtZero:true}
        }
    }
});

/* ================= AUTO SCROLL TO RIGHT ================= */
window.addEventListener('load',function(){
    document.querySelectorAll('.chart-container').forEach(c=>c.scrollLeft=c.scrollWidth);
});
</script>

</body>
</html>
