<?php
session_start();
require '../db.php';
require '../member/notif_count.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'treasurer'])) {
    header("Location: login.php");
    exit;
}

// Financial summaries
$salesIncome = $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type = 'profit' AND amount > 0")->fetch_assoc()['total'] ?? 0;
$feeIncome = $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type = 'fee' AND amount > 0")->fetch_assoc()['total'] ?? 0;
$donationIncome = $conn->query("SELECT SUM(amount) as total FROM donations")->fetch_assoc()['total'] ?? 0;
$shareCount = $conn->query("SELECT SUM(shares) as total FROM shares")->fetch_assoc()['total'] ?? 0;
$shareIncome = $shareCount * 100; // 100 MWK per share
$totalIncome = $salesIncome + $feeIncome + $donationIncome + $shareIncome;

$financeStats = $conn->query("SELECT
    (SELECT SUM(amount) FROM profits) as total_profits_distributed,
    SUM(f.amount) as total_balance,
    COUNT(*) as total_transactions
    FROM finance f");
$stats = $financeStats->fetch_assoc();
$stats['total_income'] = $totalIncome;

// Registration Fee Stats
$regFeeStats = $conn->query("SELECT 
    COUNT(*) as total_members,
    SUM(CASE WHEN fee_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN fee_status = 'paid' THEN 100 ELSE 0 END) as total_fees_collected
    FROM users WHERE role = 'member'")->fetch_assoc();

// Honey Sales Stats
$honeySalesStats = $conn->query("SELECT 
    SUM(total_amount) as total_sales,
    COUNT(*) as total_orders
    FROM orders 
    WHERE payment_status = 'completed'")->fetch_assoc();

// Member count
$memberCount = $regFeeStats['total_members'];

// Recent transactions
$recentTransactions = $conn->query("SELECT f.*, u.full_name FROM finance f JOIN users u ON f.user_id = u.user_id ORDER BY f.date DESC LIMIT 10");
$transactions = $recentTransactions->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Treasurer Dashboard | Agrilink</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --green: #2a9d8f;
      --green-d: #247b73;
      --orange: #f4a261;
      --orange-d: #e38b3a;
      --bg: linear-gradient(150deg, #fffbe6 0%, #fef6d3 100%);
      --card: #ffffff;
      --text: #1e293b;
      --text-light: #475569;
      --border: #e2e8f0;
      --shadow: 0 4px 14px rgba(0,0,0,0.07);
      --radius: 12px;
      --primary: #2a9d8f;
      --dark: #1e293b;
      --secondary: #f4a261;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; min-height: 100vh; display: flex; flex-direction: column; }
    .header {
      background: var(--primary);
      color: white;
      padding: 1rem 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .logo-area {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .logo-img {
      height: 50px;
      width: auto;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }
    .logo-text {
      color: white;
      font-weight: 800;
      font-size: 1.2rem;
      letter-spacing: 0.5px;
      text-transform: uppercase;
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

    /* Notification badge */
    .notif-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #ef4444;
      color: white;
      font-size: 0.65rem;
      min-width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2px;
      border: 2px solid var(--primary);
    }

    .footer {
      background: #2a9d8f;
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid #f4a261;
      font-weight: 500;
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
    }
    .footer a:hover { text-decoration: underline; }
    main { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
    .treasurer-bio { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 2rem; margin-bottom: 2rem; display: flex; gap: 2rem; align-items: center; }
    .treasurer-avatar { width: 90px; height: 90px; background: var(--orange); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; flex-shrink: 0; }
    .section-title { font-size: 1.5rem; font-weight: 700; color: var(--green-d); margin: 2.5rem 0 1.2rem; display: flex; align-items: center; gap: 10px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.5rem; border-left: 5px solid var(--green); }
    .stat-card h3 { font-size: 1rem; color: var(--text-light); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px; }
    .stat-card .value { font-size: 1.6rem; font-weight: 700; color: var(--green-d); }
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .chart-box { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.5rem; }
    .table-card { background: white; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f8fafc; padding: 12px 20px; text-align: left; font-size: 0.85rem; text-transform: uppercase; color: var(--text-light); border-bottom: 2px solid var(--border); }
    td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
    @media (max-width: 768px) { .treasurer-bio { flex-direction: column; text-align: center; } }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
<?php include 'notif_panel.php'; ?>
  <!-- System Header -->
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
    <nav class="header-nav">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="../admin/admin.php"><i class="fas fa-home"></i> Home</a>
        <a href="../admin/admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
        <a href="../admin/admin_members.php"><i class="fas fa-users"></i> Members</a>
        <a href="../admin/admin_finances.php"><i class="fas fa-money-bill"></i> Finances</a>
        <a href="../admin/admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
        <a href="../admin/admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
      <?php else: ?>
        <a href="treasure.php" class="active"><i class="fas fa-home"></i> Home</a>
        <a href="../admin/admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
        <a href="treasure_profit_dist.php"><i class="fa-solid fa-users-gear"></i> Profit Distribution</a>
        <a href="treasure_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
      <?php endif; ?>
      
      <a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="position: relative;">
        <i class="fa-solid fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span class="notif-badge"><?= $notifCount ?></span>
        <?php endif; ?>
      </a>
      <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </nav>
  </header>

  <main>
    <div class="treasurer-bio">
      <div class="treasurer-avatar"><i class="fas fa-chart-line"></i></div>
      <div class="treasurer-info">
        <h2>Financial Management Hub</h2>
        <p>Oversee cooperative income, member contributions, and profit distributions with full transparency.</p>
      </div>
    </div>

    <h2 class="section-title"><i class="fas fa-pie-chart"></i> Financial Summary</h2>
    <div class="stats-grid">
      <div class="stat-card">
        <h3><i class="fas fa-coins"></i> Total Income</h3>
        <div class="value">MWK <?= number_format($stats['total_income'] ?? 0, 0) ?></div>
      </div>
      <div class="stat-card">
        <h3><i class="fas fa-hand-holding-usd"></i> Profits Distributed</h3>
        <div class="value">MWK <?= number_format($stats['total_profits_distributed'] ?? 0, 0) ?></div>
      </div>
      <div class="stat-card" style="border-left-color: var(--orange);">
        <h3><i class="fas fa-id-card"></i> Registration Fees</h3>
        <div class="value">MWK <?= number_format($regFeeStats['total_fees_collected'] ?? 0, 0) ?></div>
        <small><?= $regFeeStats['paid_count'] ?> Members Paid</small>
      </div>
      <div class="stat-card" style="border-left-color: #3b82f6;">
        <h3><i class="fas fa-boxes"></i> Supplier Inventory</h3>
        <?php $sc = $conn->query("SELECT SUM(quantity) as q FROM supplier_stock")->fetch_assoc(); ?>
        <div class="value"><?= number_format($sc['q'] ?? 0, 1) ?> kg</div>
        <small>Available Stock Items</small>
      </div>
      <div class="stat-card" style="border-left-color: #8b5cf6;">
        <h3><i class="fas fa-chart-pie"></i> Member Shares</h3>
        <div class="value">MWK <?= number_format($shareIncome ?? 0, 0) ?></div>
        <small><?= number_format($shareCount ?? 0) ?> Total Shares Owned</small>
      </div>
    </div>

    <div class="charts-grid">
      <div class="chart-box">
        <h3>Income vs Distributions</h3>
        <canvas id="financeChart"></canvas>
      </div>
      <div class="chart-box">
        <h3>Registration Fee Status</h3>
        <canvas id="memberChart"></canvas>
      </div>
    </div>

    <h2 class="section-title"><i class="fas fa-history"></i> Recent Transactions</h2>
    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $t): ?>
          <tr>
            <td><?= date('M d, Y', strtotime($t['date'])) ?></td>
            <td><?= htmlspecialchars($t['full_name']) ?> - Payment/Fee</td>
            <td style="font-weight: 700;">MWK <?= number_format($t['amount'], 0) ?></td>
            <td><span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">CREDIT</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <footer class="footer">
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
    <p style="font-size: 0.8rem; margin-top: 10px; opacity: 0.7;">Empowering Beekeepers through Transparency & Strategic Partnerships</p>
  </footer>

  <script>
    const financeCtx = document.getElementById('financeChart').getContext('2d');
    new Chart(financeCtx, {
      type: 'bar',
      data: {
        labels: ['Total Income', 'Distributed'],
        datasets: [{
          data: [<?= $stats['total_income'] ?? 0 ?>, <?= $stats['total_profits_distributed'] ?? 0 ?>],
          backgroundColor: ['#2a9d8f', '#f4a261']
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    const memberCtx = document.getElementById('memberChart').getContext('2d');
    new Chart(memberCtx, {
      type: 'doughnut',
      data: {
        labels: ['Paid', 'Unpaid'],
        datasets: [{
          data: [<?= $regFeeStats['paid_count'] ?>, <?= $memberCount - $regFeeStats['paid_count'] ?>],
          backgroundColor: ['#2a9d8f', '#e2e8f0']
        }]
      },
      options: { responsive: true }
    });
  </script>
</body>
</html>

