<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Helper function to map transaction type
function getTransactionType($value) {
    // Map INT values to string types (legacy data)
    $intMap = [0 => 'contribution', 1 => 'fee', 2 => 'sale', 3 => 'profit'];
    if (is_numeric($value)) {
        return $intMap[(int)$value] ?? 'contribution';
    }
    return $value ?? 'contribution';
}

$reportDate = date('d F Y, H:i');

// ── Summary Statistics ─────────────────────────────────────────────────────
$memberData   = $conn->query("SELECT COUNT(*) as total_members,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_members,
    SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive_members,
    COUNT(CASE WHEN date_joined >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
    FROM users WHERE role='member'")->fetch_assoc();

// Finance: real transaction_type values = contribution(0), fee(1), sale(2), profit(3) as INT legacy, or as VARCHAR strings
// Calculate Total Income (Gross Revenue)
$salesIncome = $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type IN (3,'profit') AND amount > 0")->fetch_assoc()['total'] ?? 0;
$feeIncome = $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type IN (1,'fee') AND amount > 0")->fetch_assoc()['total'] ?? 0;
$donationIncome = $conn->query("SELECT SUM(amount) as total FROM donations")->fetch_assoc()['total'] ?? 0;
$shareCount = $conn->query("SELECT SUM(shares) as total FROM shares")->fetch_assoc()['total'] ?? 0;
$shareIncome = $shareCount * 100; // 100 MWK per share
$totalIncome = $salesIncome + $feeIncome + $donationIncome + $shareIncome;

// Detailed Finance Data (Only positive revenue)
$financeData  = $conn->query("SELECT
    COUNT(*) as total_transactions,
    COALESCE(SUM(CASE WHEN transaction_type IN (0,'contribution') AND amount > 0 THEN amount ELSE 0 END),0) as contributions,
    COALESCE(SUM(CASE WHEN transaction_type IN (1,'fee') AND amount > 0 THEN amount ELSE 0 END),0) as fees,
    COALESCE(SUM(CASE WHEN transaction_type IN (2,'sale') AND amount > 0 THEN amount ELSE 0 END),0) as sales,
    COALESCE(SUM(CASE WHEN transaction_type IN (3,'profit') AND amount > 0 THEN amount ELSE 0 END),0) as profits
    FROM finance")->fetch_assoc();
$financeData['total_revenue'] = $totalIncome;

// Shares (separate table)
$sharesData = $conn->query("SELECT COALESCE(SUM(shares),0) as total_shares, COUNT(DISTINCT user_id) as shareholders FROM shares")->fetch_assoc();

// Honey contributions (separate table)
$honeyData  = $conn->query("SELECT COALESCE(SUM(quantity),0) as total_honey_kg, COUNT(*) as total_entries FROM contributions")->fetch_assoc();

$hiveData     = $conn->query("SELECT COUNT(*) as total_hives, COALESCE(SUM(hive_count),0) as total_hive_count FROM hives")->fetch_assoc();
$inspData     = $conn->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) as pending
    FROM inspections")->fetch_assoc();
$stakeData    = $conn->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN stakeholder_type='buyer'    THEN 1 ELSE 0 END) as buyers,
    SUM(CASE WHEN stakeholder_type='supplier' THEN 1 ELSE 0 END) as suppliers,
    SUM(CASE WHEN stakeholder_type='ngo'      THEN 1 ELSE 0 END) as ngos
    FROM users WHERE role='external'")->fetch_assoc();

// ── Detailed Table Data ────────────────────────────────────────────────────
$members = $conn->query("SELECT u.user_id, u.full_name, u.email, u.phone_number, u.status, u.date_joined,
    COALESCE((SELECT SUM(h.hive_count) FROM hives h WHERE h.user_id=u.user_id),0) as hives,
    COALESCE((SELECT SUM(s.shares) FROM shares s WHERE s.user_id=u.user_id),0) as total_shares,
    COALESCE((SELECT SUM(f.amount) FROM finance f WHERE f.user_id=u.user_id),0) as finance_total
    FROM users u WHERE u.role='member' ORDER BY u.date_joined DESC");

$transactions = $conn->query("SELECT f.*, u.full_name
    FROM finance f LEFT JOIN users u ON u.user_id=f.user_id
    ORDER BY f.date DESC LIMIT 200");

$sharesQ = $conn->query("SELECT s.share_id, s.shares, s.purchase_date, u.full_name
    FROM shares s LEFT JOIN users u ON u.user_id=s.user_id
    ORDER BY s.purchase_date DESC LIMIT 100");

$honeyQ = $conn->query("SELECT c.*, u.full_name
    FROM contributions c LEFT JOIN users u ON u.user_id=c.user_id
    ORDER BY c.contribution_date DESC LIMIT 100");

$hives = $conn->query("SELECT h.hive_id, h.location, h.hive_count, u.full_name,
    CURDATE() as registration_date
    FROM hives h LEFT JOIN users u ON u.user_id=h.user_id
    ORDER BY h.hive_id DESC");

$inspections = $conn->query("SELECT i.*, u.full_name
    FROM inspections i LEFT JOIN users u ON u.user_id=i.user_id
    ORDER BY i.scheduled_date DESC LIMIT 200");

$stakeholders = $conn->query("SELECT user_id, full_name, email, phone_number, stakeholder_type, date_joined, status
    FROM users WHERE role='external' ORDER BY date_joined DESC");

// Monthly finance trend (last 6 months)
$trendQ    = $conn->query("SELECT DATE_FORMAT(date,'%Y-%m') as month, SUM(amount) as total
    FROM finance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month");
$trendData = []; while ($r = $trendQ->fetch_assoc()) $trendData[] = $r;

// Monthly member growth
$growthQ    = $conn->query("SELECT DATE_FORMAT(date_joined,'%Y-%m') as month, COUNT(*) as count
    FROM users WHERE role='member' AND date_joined >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month");
$growthData = []; while ($r = $growthQ->fetch_assoc()) $growthData[] = $r;

// ── CSV Export ─────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $section = $_GET['section'] ?? 'members';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="agrilink_'.$section.'_report_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');

    if ($section === 'members') {
        fputcsv($out, ['ID','Full Name','Email','Phone','Status','Date Joined','Hives','Balance (MWK)']);
        $res = $conn->query("SELECT u.user_id, u.full_name, u.email, u.phone_number, u.status, u.date_joined,
            COALESCE(SUM(h.hive_count),0) as hives,
            COALESCE((SELECT SUM(f.amount) FROM finance f WHERE f.user_id=u.user_id),0) as balance
            FROM users u LEFT JOIN hives h ON h.user_id=u.user_id
            WHERE u.role='member' GROUP BY u.user_id ORDER BY u.date_joined DESC");
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['date_joined'])) {
                $row['date_joined'] = ' ' . date('M d, Y', strtotime($row['date_joined']));
            }
            fputcsv($out, $row);
        }
    } elseif ($section === 'finance') {
        fputcsv($out, ['Member','Type','Amount (MWK)','Date','Description']);
        $res = $conn->query("SELECT u.full_name, f.transaction_type, f.amount, f.date, f.description
            FROM finance f LEFT JOIN users u ON u.user_id=f.user_id ORDER BY f.date DESC");
        while ($row = $res->fetch_assoc()) {
            $row['transaction_type'] = getTransactionType($row['transaction_type']);
            if (!empty($row['date'])) {
                $row['date'] = ' ' . date('M d, Y', strtotime($row['date']));
            }
            fputcsv($out, $row);
        }
    } elseif ($section === 'hives') {
        fputcsv($out, ['Hive ID','Member','Hive Count','Location']);
        $res = $conn->query("SELECT h.hive_id, u.full_name, h.hive_count, h.location
            FROM hives h LEFT JOIN users u ON u.user_id=h.user_id ORDER BY h.hive_id DESC");
        while ($row = $res->fetch_assoc()) fputcsv($out, $row);
    } elseif ($section === 'inspections') {
        fputcsv($out, ['ID','Member','Scheduled Date','Status','Hive ID','Notes']);
        $res = $conn->query("SELECT i.inspection_id, u.full_name, i.scheduled_date, i.status, i.hive_id, i.notes
            FROM inspections i LEFT JOIN users u ON u.user_id=i.user_id ORDER BY i.scheduled_date DESC");
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['scheduled_date'])) {
                $row['scheduled_date'] = ' ' . date('M d, Y', strtotime($row['scheduled_date']));
            }
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports & Analytics | Agrilink Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --primary: #2a9d8f; --primary-dark: #21867a;
      --accent: #f4a261; --success: #27ae60;
      --danger: #e74c3c; --info: #3498db;
      --text: #1e293b; --muted: #64748b;
      --border: #e2e8f0; --shadow: 0 2px 12px rgba(0,0,0,0.07);
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Segoe UI',sans-serif; color:var(--text); min-height:100vh; display:flex; flex-direction:column; }

    /* ── HEADER ── */
    .header {
      background: var(--primary);
      color: white;
      padding: 1rem 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 200;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .header-logo {
      font-size: 1.4rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .header-nav a {
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      font-size: 0.95rem;
      font-weight: 600;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 0.8rem;
      border-radius: 8px;
    }

    .header-nav a:hover, .header-nav a.active {
      color: white;
      background: rgba(255, 255, 255, 0.15);
    }

    /* ── LAYOUT ── */
    .main-content { flex:1; padding:30px 24px; max-width:1500px; margin:0 auto; width:100%; }

    /* ── PAGE HEADER ── */
    .page-header {
      background:#fff; border-radius:14px; padding:28px 32px;
      box-shadow:var(--shadow); margin-bottom:28px;
      display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;
    }
    .page-header h2 { font-size:1.7rem; color:var(--primary); display:flex; align-items:center; gap:10px; }
    .page-header p { color:var(--muted); font-size:0.9rem; margin-top:4px; }
    .export-group { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:8px; font-weight:600; font-size:0.88rem; border:none; cursor:pointer; text-decoration:none; transition:.25s; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-primary:hover { background:var(--primary-dark); }
    .btn-success { background:var(--success); color:#fff; }
    .btn-success:hover { background:#229954; }
    .btn-accent { background:var(--accent); color:#fff; }
    .btn-accent:hover { background:#e89c4f; }
    .btn-gray { background:#64748b; color:#fff; }
    .btn-gray:hover { background:#475569; }

    /* ── KPI GRID ── */
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:18px; margin-bottom:28px; }
    .kpi-card {
      background:#fff; border-radius:14px; padding:22px 20px;
      box-shadow:var(--shadow); text-align:center; transition:.3s;
      border-top:4px solid transparent;
    }
    .kpi-card:hover { transform:translateY(-4px); box-shadow:0 12px 28px rgba(0,0,0,0.1); }
    .kpi-card.green  { border-color:var(--primary); }
    .kpi-card.orange { border-color:var(--accent); }
    .kpi-card.blue   { border-color:var(--info); }
    .kpi-card.red    { border-color:var(--danger); }
    .kpi-val { font-size:2.1rem; font-weight:800; margin-bottom:4px; }
    .kpi-card.green  .kpi-val { color:var(--primary); }
    .kpi-card.orange .kpi-val { color:var(--accent); }
    .kpi-card.blue   .kpi-val { color:var(--info); }
    .kpi-card.red    .kpi-val { color:var(--danger); }
    .kpi-lbl { font-size:0.8rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .kpi-sub { font-size:0.8rem; color:var(--muted); margin-top:5px; }

    /* ── CHARTS ── */
    .charts-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:22px; margin-bottom:28px; }
    .chart-card { background:#fff; border-radius:14px; padding:26px; box-shadow:var(--shadow); }
    .chart-card h3 { font-size:1.05rem; color:var(--primary); margin-bottom:18px; display:flex; align-items:center; gap:8px; }
    .chart-box { position:relative; height:260px; }

    /* ── TABS ── */
    .tab-section { background:#fff; border-radius:14px; box-shadow:var(--shadow); margin-bottom:28px; overflow:hidden; }
    .tab-nav { display:flex; border-bottom:1px solid var(--border); overflow-x:auto; }
    .tab-btn {
      padding:14px 22px; font-weight:600; font-size:0.9rem; border:none; background:none;
      cursor:pointer; color:var(--muted); white-space:nowrap; border-bottom:3px solid transparent;
      transition:.2s; display:flex; align-items:center; gap:7px;
    }
    .tab-btn.active { color:var(--primary); border-color:var(--primary); background:rgba(42,157,143,0.04); }
    .tab-btn:hover:not(.active) { background:#f8fafc; color:var(--text); }
    .tab-pane { display:none; padding:24px; }
    .tab-pane.active { display:block; }

    /* Tab toolbar */
    .tab-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
    .tab-toolbar h4 { font-size:1rem; color:var(--text); }
    .tab-search { padding:8px 14px; border:1px solid var(--border); border-radius:8px; font-size:0.88rem; width:220px; outline:none; }
    .tab-search:focus { border-color:var(--primary); }

    /* ── TABLE ── */
    .table-wrap { overflow-x:auto; border-radius:10px; border:1px solid var(--border); }
    table { width:100%; border-collapse:collapse; font-size:0.88rem; }
    thead tr { background:#f8fafc; }
    th { padding:12px 14px; text-align:left; font-weight:700; color:var(--muted); text-transform:uppercase; font-size:0.75rem; letter-spacing:.5px; white-space:nowrap; border-bottom:1px solid var(--border); }
    td { padding:11px 14px; border-bottom:1px solid #f1f5f9; color:var(--text); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:#f8fafc; }
    .badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; }
    .badge-green  { background:#d1fae5; color:#065f46; }
    .badge-red    { background:#fee2e2; color:#991b1b; }
    .badge-blue   { background:#dbeafe; color:#1e40af; }
    .badge-orange { background:#fff7ed; color:#9a3412; }
    .badge-purple { background:#ede9fe; color:#5b21b6; }
    .badge-gray   { background:#f1f5f9; color:#475569; }
    .no-data { text-align:center; padding:40px; color:var(--muted); }
    .no-data i { font-size:2.5rem; margin-bottom:10px; display:block; opacity:.3; }

    /* ── FOOTER ── */
    footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid var(--accent);
    }
    footer a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
    }
    footer a:hover { text-decoration: underline; }

    /* ── PRINT ── */
    @media print {
      .header, .back-link, .export-group, .tab-nav, .tab-search, footer { display:none !important; }
      .tab-pane { display:block !important; }
      body { background:white !important; }
      .kpi-card, .chart-card, .tab-section { box-shadow:none; border:1px solid #ddd; }
    }
    @media (max-width:768px) {
      .charts-row { grid-template-columns:1fr; }
      .kpi-grid { grid-template-columns:repeat(2,1fr); }
      .page-header { flex-direction:column; }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

<!-- System Header -->
<header class="header">
  <div class="header-logo">
    <img src="../assets/logo.png" alt="Agrilink Logo" style="height: 40px; width: auto; margin-right: 10px;">
    AGRILINK ADMIN
  </div>
  <nav class="header-nav">
    <a href="admin.php"><i class="fas fa-home"></i> Home</a>
    <a href="admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
    <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
    <a href="admin_finances.php"><i class="fas fa-coins"></i> Finances</a>
    <a href="admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
    <a href="admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
    
<a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="text-decoration:none; margin-right: 15px;">
  <span class="notif-wrapper" style="position:relative; display:inline-flex; align-items:center;">
    <i class="fa-solid fa-bell"></i>
    <?php if ($notifCount > 0): ?>
      <span class="notif-badge" style="position:absolute; top:-8px; right:-10px; background:#ef4444; color:white; font-size:0.65rem; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #2a9d8f;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
    <?php endif; ?>
  </span>
</a>
<a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
  </nav>
</header>

<div class="main-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h2><i class="fas fa-chart-pie"></i> Cooperative Performance Report</h2>
      <p><i class="fas fa-calendar-alt"></i> Generated: <?= $reportDate ?> &nbsp;|&nbsp; <i class="fas fa-user-shield"></i> Livingstonia Bee Keeping Cooperative</p>
    </div>
    <div class="export-group">
      <a href="?export=csv&section=members" class="btn btn-success"><i class="fas fa-file-csv"></i> Members CSV</a>
      <a href="?export=csv&section=finance" class="btn btn-success"><i class="fas fa-file-csv"></i> Finance CSV</a>
      <a href="?export=csv&section=hives" class="btn btn-success"><i class="fas fa-file-csv"></i> Hives CSV</a>
      <button onclick="window.print()" class="btn btn-gray"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>

  <!-- KPI Summary Cards -->
  <div class="kpi-grid">
    <div class="kpi-card green">
      <div class="kpi-val"><?= number_format($memberData['total_members']) ?></div>
      <div class="kpi-lbl">Total Members</div>
      <div class="kpi-sub">+<?= $memberData['new_this_month'] ?> this month</div>
    </div>
    <div class="kpi-card green">
      <div class="kpi-val"><?= number_format($memberData['active_members']) ?></div>
      <div class="kpi-lbl">Active Members</div>
      <div class="kpi-sub"><?= $memberData['total_members'] > 0 ? round(($memberData['active_members'] / $memberData['total_members']) * 100, 1) : 0 ?>% active rate</div>
    </div>
    <div class="kpi-card orange">
      <div class="kpi-val">MWK <?= number_format($totalIncome) ?></div>
      <div class="kpi-lbl">Gross Income (MWK)</div>
      <div class="kpi-sub"><?= number_format($financeData['total_transactions']) ?> transactions recorded</div>
    </div>
    <div class="kpi-card orange">
      <div class="kpi-val"><?= number_format($sharesData['total_shares'] ?? 0) ?></div>
      <div class="kpi-lbl">Shares Owned</div>
      <div class="kpi-sub"><?= $sharesData['shareholders'] ?? 0 ?> shareholders · <?= number_format($honeyData['total_honey_kg'] ?? 0, 1) ?> kg honey</div>
    </div>
    <div class="kpi-card blue">
      <div class="kpi-val"><?= number_format($hiveData['total_hive_count'] ?? 0) ?></div>
      <div class="kpi-lbl">Total Hives</div>
      <div class="kpi-sub"><?= number_format($hiveData['total_hives']) ?> hive records</div>
    </div>
    <div class="kpi-card blue">
      <div class="kpi-val"><?= number_format($inspData['total']) ?></div>
      <div class="kpi-lbl">Inspections</div>
      <div class="kpi-sub"><?= $inspData['completed'] ?> done · <?= $inspData['pending'] ?> pending</div>
    </div>
    <div class="kpi-card red">
      <div class="kpi-val"><?= number_format($stakeData['total']) ?></div>
      <div class="kpi-lbl">Stakeholders</div>
      <div class="kpi-sub"><?= $stakeData['buyers'] ?> buyers · <?= $stakeData['suppliers'] ?> suppliers · <?= $stakeData['ngos'] ?> NGOs</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="charts-row">
    <div class="chart-card">
      <h3><i class="fas fa-chart-line"></i> Member Growth (Last 6 Months)</h3>
      <div class="chart-box"><canvas id="growthChart"></canvas></div>
    </div>
    <div class="chart-card">
      <h3><i class="fas fa-chart-bar"></i> Monthly Revenue (Last 6 Months)</h3>
      <div class="chart-box"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="chart-card">
      <h3><i class="fas fa-chart-pie"></i> Member Status</h3>
      <div class="chart-box"><canvas id="memberChart"></canvas></div>
    </div>
    <div class="chart-card">
      <h3><i class="fas fa-chart-donut"></i> Revenue Breakdown</h3>
      <div class="chart-box"><canvas id="revenueChart"></canvas></div>
    </div>
  </div>

  <!-- Detailed Data Tables (Tabbed) -->
  <div class="tab-section">
    <div class="tab-nav">
      <button class="tab-btn active" onclick="switchTab('members', this)"><i class="fas fa-users"></i> Members (<?= $memberData['total_members'] ?>)</button>
      <button class="tab-btn" onclick="switchTab('finance', this)"><i class="fas fa-coins"></i> Transactions (<?= $financeData['total_transactions'] ?>)</button>
      <button class="tab-btn" onclick="switchTab('hives', this)"><i class="fas fa-cube"></i> Hives (<?= $hiveData['total_hives'] ?>)</button>
      <button class="tab-btn" onclick="switchTab('inspections', this)"><i class="fas fa-clipboard-check"></i> Inspections (<?= $inspData['total'] ?>)</button>
      <button class="tab-btn" onclick="switchTab('stakeholders', this)"><i class="fas fa-handshake"></i> Stakeholders (<?= $stakeData['total'] ?>)</button>
    </div>

    <!-- Members Tab -->
    <div id="tab-members" class="tab-pane active">
      <div class="tab-toolbar">
        <h4><i class="fas fa-users"></i> All Registered Members</h4>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input class="tab-search" type="text" id="searchMembers" placeholder="Search member..." oninput="filterTable('membersTable', this.value)">
          <a href="?export=csv&section=members" class="btn btn-success" style="padding:8px 14px;font-size:0.82rem;"><i class="fas fa-download"></i> CSV</a>
        </div>
      </div>
      <div class="table-wrap">
        <table id="membersTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Hives</th>
              <th>Shares</th>
              <th>Finance (MWK)</th>
              <th>Status</th>
              <th>Date Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; while ($m = $members->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($m['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($m['email']) ?></td>
              <td><?= htmlspecialchars($m['phone_number'] ?? '—') ?></td>
              <td><?= number_format($m['hives']) ?></td>
              <td><?= number_format($m['total_shares']) ?></td>
              <td><?= number_format($m['finance_total']) ?></td>
              <td>
                <span class="badge <?= $m['status'] === 'active' ? 'badge-green' : 'badge-red' ?>">
                  <i class="fas fa-circle" style="font-size:.45rem;"></i> <?= ucfirst($m['status'] ?? 'unknown') ?>
                </span>
              </td>
              <td><?= date('d M Y', strtotime($m['date_joined'])) ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($i === 1): ?>
            <tr><td colspan="8" class="no-data"><i class="fas fa-users"></i>No members found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Finance Tab -->
    <div id="tab-finance" class="tab-pane">
      <div class="tab-toolbar">
        <h4><i class="fas fa-coins"></i> Financial Transactions (latest 100)</h4>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input class="tab-search" type="text" placeholder="Search transactions..." oninput="filterTable('financeTable', this.value)">
          <a href="?export=csv&section=finance" class="btn btn-success" style="padding:8px 14px;font-size:0.82rem;"><i class="fas fa-download"></i> CSV</a>
        </div>
      </div>
      <div class="table-wrap">
        <table id="financeTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Member</th>
              <th>Type</th>
              <th>Amount (MWK)</th>
              <th>Date</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; while ($t = $transactions->fetch_assoc()):
              $typeDisplay = getTransactionType($t['transaction_type']);
              $typeColors = [
                'contribution' => 'badge-green',
                'fee'          => 'badge-blue',
                'sale'         => 'badge-orange',
                'profit'       => 'badge-purple',
              ];
              $tc = $typeColors[$typeDisplay] ?? 'badge-gray';
            ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($t['full_name'] ?? '—') ?></strong></td>
              <td><span class="badge <?= $tc ?>"><?= ucwords(str_replace('_',' ',$typeDisplay)) ?></span></td>
              <td style="font-weight:700;"><?= number_format($t['amount'], 2) ?></td>
              <td><?= date('d M Y', strtotime($t['date'])) ?></td>
              <td style="color:var(--muted);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['description'] ?? '—') ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($i === 1): ?>
            <tr><td colspan="6" class="no-data"><i class="fas fa-coins"></i>No transactions found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Hives Tab -->
    <div id="tab-hives" class="tab-pane">
      <div class="tab-toolbar">
        <h4><i class="fas fa-cube"></i> All Registered Hives</h4>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input class="tab-search" type="text" placeholder="Search hives..." oninput="filterTable('hivesTable', this.value)">
          <a href="?export=csv&section=hives" class="btn btn-success" style="padding:8px 14px;font-size:0.82rem;"><i class="fas fa-download"></i> CSV</a>
        </div>
      </div>
      <div class="table-wrap">
        <table id="hivesTable">
          <thead>
            <tr><th>#</th><th>Member</th><th>Hive Count</th><th>Location / Apiary</th></tr>
          </thead>
          <tbody>
            <?php $i = 1; while ($h = $hives->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($h['full_name'] ?? '—') ?></strong></td>
              <td><span class="badge badge-blue"><?= number_format($h['hive_count']) ?> hives</span></td>
              <td><?= htmlspecialchars($h['location'] ?? '—') ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($i === 1): ?>
            <tr><td colspan="4" class="no-data"><i class="fas fa-cube"></i>No hive records found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Inspections Tab -->
    <div id="tab-inspections" class="tab-pane">
      <div class="tab-toolbar">
        <h4><i class="fas fa-clipboard-check"></i> Inspection Records (latest 100)</h4>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input class="tab-search" type="text" placeholder="Search inspections..." oninput="filterTable('inspTable', this.value)">
          <a href="?export=csv&section=inspections" class="btn btn-success" style="padding:8px 14px;font-size:0.82rem;"><i class="fas fa-download"></i> CSV</a>
        </div>
      </div>
      <div class="table-wrap">
        <table id="inspTable">
          <thead>
            <tr><th>#</th><th>Member</th><th>Scheduled Date</th><th>Hive ID</th><th>Status</th><th>Notes</th></tr>
          </thead>
          <tbody>
            <?php $i = 1; while ($ins = $inspections->fetch_assoc()):
              $sc = $ins['status'] === 'completed' ? 'badge-green' : ($ins['status'] === 'pending' ? 'badge-orange' : 'badge-gray');
            ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($ins['full_name'] ?? '—') ?></strong></td>
              <td><?= date('d M Y', strtotime($ins['scheduled_date'])) ?></td>
              <td><?= htmlspecialchars($ins['hive_id'] ?? '—') ?></td>
              <td><span class="badge <?= $sc ?>"><?= ucfirst($ins['status']) ?></span></td>
              <td style="color:var(--muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($ins['notes'] ?? '—') ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($i === 1): ?>
            <tr><td colspan="6" class="no-data"><i class="fas fa-clipboard-check"></i>No inspection records found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Stakeholders Tab -->
    <div id="tab-stakeholders" class="tab-pane">
      <div class="tab-toolbar">
        <h4><i class="fas fa-handshake"></i> External Stakeholders</h4>
        <input class="tab-search" type="text" placeholder="Search stakeholders..." oninput="filterTable('stakeTable', this.value)">
      </div>
      <div class="table-wrap">
        <table id="stakeTable">
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Status</th><th>Date Joined</th></tr>
          </thead>
          <tbody>
            <?php $i = 1; while ($s = $stakeholders->fetch_assoc()):
              $tc = ['buyer'=>'badge-orange','supplier'=>'badge-blue','ngo'=>'badge-purple'][$s['stakeholder_type']] ?? 'badge-gray';
            ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($s['email']) ?></td>
              <td><?= htmlspecialchars($s['phone_number'] ?? '—') ?></td>
              <td><span class="badge <?= $tc ?>"><?= ucfirst($s['stakeholder_type']) ?></span></td>
              <td><span class="badge <?= ($s['status']??'') === 'active' ? 'badge-green' : 'badge-red' ?>"><?= ucfirst($s['status'] ?? 'unknown') ?></span></td>
              <td><?= date('d M Y', strtotime($s['date_joined'])) ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($i === 1): ?>
            <tr><td colspan="7" class="no-data"><i class="fas fa-handshake"></i>No stakeholders found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- /tab-section -->

</div><!-- /main-content -->

<footer>
  &copy; <?= date('Y') ?> Agrilink Cooperative – Livingstonia Bee Keeping Cooperative |
  <a href="mailto:livingstoniaagrilink@gmail.com">livingstoniaagrilink@gmail.com</a>
</footer>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

// ── Table search filter ────────────────────────────────────────────────────
function filterTable(tableId, query) {
  const q = query.toLowerCase();
  document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Charts ─────────────────────────────────────────────────────────────────
const growthLabels = [<?php foreach($growthData as $d) echo "'" . date('M Y', strtotime($d['month'].'-01')) . "',"; ?>];
const growthValues = [<?php foreach($growthData as $d) echo (int)$d['count'] . ','; ?>];

new Chart(document.getElementById('growthChart'), {
  type: 'line',
  data: { labels: growthLabels, datasets: [{ label: 'New Members', data: growthValues,
    borderColor: '#2a9d8f', backgroundColor: 'rgba(42,157,143,0.1)',
    borderWidth: 3, fill: true, tension: 0.4,
    pointBackgroundColor: '#2a9d8f', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 6 }]
  },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { color: 'rgba(0,0,0,0.05)' } } }
  }
});

const trendLabels = [<?php foreach($trendData as $d) echo "'" . date('M Y', strtotime($d['month'].'-01')) . "',"; ?>];
const trendValues = [<?php foreach($trendData as $d) echo (float)$d['total'] . ','; ?>];

new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: { labels: trendLabels, datasets: [{ label: 'Revenue (MWK)', data: trendValues,
    backgroundColor: 'rgba(244,162,97,0.8)', borderColor: '#f4a261', borderWidth: 1,
    borderRadius: 6, borderSkipped: false }]
  },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' },
           ticks: { callback: v => 'MWK ' + v.toLocaleString() } },
      x: { grid: { color: 'rgba(0,0,0,0.05)' } }
    }
  }
});

new Chart(document.getElementById('memberChart'), {
  type: 'doughnut',
  data: {
    labels: ['Active', 'Inactive'],
    datasets: [{ data: [<?= (int)$memberData['active_members'] ?>, <?= (int)$memberData['inactive_members'] ?>],
      backgroundColor: ['#2a9d8f', '#e74c3c'], borderWidth: 0, hoverOffset: 8 }]
  },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { padding: 18, usePointStyle: true } } }
  }
});

new Chart(document.getElementById('revenueChart'), {
  type: 'pie',
  data: {
    labels: ['Contributions', 'Fees', 'Sales', 'Profits'],
    datasets: [{ data: [<?= (float)($financeData['contributions']??0) ?>, <?= (float)($financeData['fees']??0) ?>, <?= (float)($financeData['sales']??0) ?>, <?= (float)($financeData['profits']??0) ?>],
      backgroundColor: ['#2a9d8f','#3498db','#f4a261','#9b59b6'], borderWidth: 0, hoverOffset: 8 }]
  },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true } },
      tooltip: { callbacks: { label: ctx => ctx.label + ': MWK ' + ctx.parsed.toLocaleString() } }
    }
  }
});
</script>
</body>
</html>

