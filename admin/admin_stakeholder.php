<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'treasurer'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Fetch all stakeholders (external users)
$result = $conn->query("SELECT u.user_id, u.full_name, u.email, u.stakeholder_type, u.status, u.date_joined FROM users u WHERE u.role = 'external' ORDER BY u.stakeholder_type, u.full_name");
$stakeholders = $result->fetch_all(MYSQLI_ASSOC);

// Function to get stakeholder details
function getStakeholderDetails($conn, $user_id, $type) {
    if ($type === 'supplier') {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_items, COALESCE(SUM(quantity), 0) as total_quantity FROM supplier_stock WHERE supplier_id = ?");
        $stmt->bind_param("i", $user_id);
    } elseif ($type === 'buyer') {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_purchases, COALESCE(SUM(total_amount), 0) as total_spent FROM orders WHERE user_id = ? AND payment_status = 'completed'");
        $stmt->bind_param("i", $user_id);
    } else {
        return ['grants_received' => 0, 'projects_supported' => 0, 'total_items' => 0, 'total_quantity' => 0, 'total_purchases' => 0, 'total_spent' => 0];
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $details = $res->fetch_assoc();
    $stmt->close();
    return $details;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Stakeholder Management | Agrilink Portal</title>
    <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #f4a261;
      --dark: #2c3e50;
      --text: #2c3e50;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      --radius: 12px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* System Header Standardized */
    .header {
      background: #2a9d8f !important;
      color: white;
      padding: 0;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      width: 100%;
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .nav-inner {
      max-width: 1380px;
      margin: 0 auto;
      padding: 0 30px;
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .logo-std {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      text-decoration: none;
    }
    .logo-icon-wrap {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.18);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.25);
    }
    .logo-icon-wrap i { color: #fff; font-size: 1.05rem; }
    .logo-text {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: -0.3px;
    }
    .logo-text span { color: var(--secondary); }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .header-nav a {
      color: white;
      text-decoration: none;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
      font-weight: 600;
    }
    .header-nav a:hover, .header-nav a.active {
      color: var(--secondary);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 30px 20px;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .page-title {
      background: white;
      padding: 25px 35px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .page-title h1 {
      font-size: 1.8rem;
      color: var(--primary);
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .card {
      background: white;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 30px;
      margin-bottom: 30px;
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 25px;
    }

    .stat-item {
      background: #f8fafc;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      border: 1px solid var(--border);
    }

    .stat-val {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 0.9rem;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 15px; background: #f8fafc; color: var(--primary); font-weight: 700; border-bottom: 2px solid var(--border); font-size: 0.85rem; text-transform: uppercase; }
    td { padding: 15px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 0.95rem; }

    .badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .badge-supplier { background: #dcfce7; color: #166534; }
    .badge-buyer { background: #eff6ff; color: #1e40af; }
    .badge-ngo { background: #fef3c7; color: #92400e; }
    
    .status-active { color: #10b981; font-weight: 700; }
    .status-inactive { color: #ef4444; font-weight: 700; }

    /* Footer */
    .footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 25px 30px;
      margin-top: 40px;
      font-size: 0.9rem;
      border-top: 5px solid var(--secondary);
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
    }
    .footer a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <!-- System Header -->
  <header class="header">
    <div class="nav-inner">
      <a href="admin.php" class="logo-std">
        <div class="logo-icon-wrap">
          <i class="fas fa-bee"></i>
        </div>
        <div class="logo-text">Agri<span>link</span> Admin</div>
      </a>
      <nav class="header-nav">
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <a href="admin.php"><i class="fas fa-home"></i> Home</a>
          <a href="admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
          <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
          <a href="admin_finances.php"><i class="fas fa-money-bill"></i> Finances</a>
          <a href="admin_stakeholder.php" class="active"><i class="fas fa-handshake"></i> Stakeholders</a>
          <a href="admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
        <?php else: ?>
          <a href="../treasure/treasure.php"><i class="fas fa-home"></i> Home</a>
          <a href="admin_stakeholder.php" class="active"><i class="fas fa-handshake"></i> Stakeholders</a>
          <a href="../treasure/treasure_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
        <?php endif; ?>
        
        <a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="text-decoration:none;">
          <span class="notif-wrapper" style="position:relative; display:inline-flex; align-items:center;">
            <i class="fa-solid fa-bell"></i>
            <?php if ($notifCount > 0): ?>
              <span class="notif-badge" style="position:absolute; top:-8px; right:-10px; background:#ef4444; color:white; font-size:0.65rem; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #2a9d8f;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
            <?php endif; ?>
          </span>
        </a>
        <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
      </nav>
    </div>
  </header>

  <main class="main-content">
    
    <div class="page-title">
      <h1><i class="fas fa-handshake"></i> Stakeholder Management</h1>
      <span style="color: var(--text-muted); font-weight: 600;">Oversee external partners & market participants</span>
    </div>

    <!-- Stakeholder Statistics -->
    <div class="card">
      <h3><i class="fas fa-chart-pie"></i> Partner Distribution</h3>
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-val"><?php echo count(array_filter($stakeholders, fn($s) => $s['stakeholder_type'] === 'supplier')); ?></div>
          <div class="stat-label">Suppliers</div>
        </div>
        <div class="stat-item">
          <div class="stat-val"><?php echo count(array_filter($stakeholders, fn($s) => $s['stakeholder_type'] === 'buyer')); ?></div>
          <div class="stat-label">Buyers</div>
        </div>
        <div class="stat-item">
          <div class="stat-val"><?php echo count(array_filter($stakeholders, fn($s) => $s['stakeholder_type'] === 'ngo')); ?></div>
          <div class="stat-label">NGOs</div>
        </div>
        <div class="stat-item">
          <div class="stat-val"><?php echo count($stakeholders); ?></div>
          <div class="stat-label">Total Partners</div>
        </div>
      </div>
    </div>

    <!-- Stakeholder Directory -->
    <div class="card">
      <h3><i class="fas fa-address-book"></i> Active Stakeholders</h3>
      <div class="table-container">
        <?php if (empty($stakeholders)): ?>
          <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No stakeholders registered in the system yet.</p>
          </div>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Partner Name</th>
                <th>Email Address</th>
                <th>Classification</th>
                <th>Status</th>
                <th>Activity Summary</th>
                <th>Joined On</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stakeholders as $stakeholder): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($stakeholder['full_name']) ?></strong></td>
                  <td><a href="mailto:<?= htmlspecialchars($stakeholder['email']) ?>" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($stakeholder['email']) ?></a></td>
                  <td>
                    <span class="badge badge-<?= $stakeholder['stakeholder_type'] ?>">
                      <?= strtoupper($stakeholder['stakeholder_type']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="status-<?= $stakeholder['status'] ?>">
                      <i class="fas fa-circle" style="font-size: 0.6rem; vertical-align: middle; margin-right: 5px;"></i>
                      <?= strtoupper($stakeholder['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php
                    $details = getStakeholderDetails($conn, $stakeholder['user_id'], $stakeholder['stakeholder_type']);
                    if ($stakeholder['stakeholder_type'] === 'supplier') {
                      echo "<small>Items: <strong>{$details['total_items']}</strong> | Stock: <strong>" . number_format($details['total_quantity'], 1) . " kg</strong></small>";
                    } elseif ($stakeholder['stakeholder_type'] === 'buyer') {
                      echo "<small>Purchases: <strong>{$details['total_purchases']}</strong> | Total: <strong>MWK " . number_format($details['total_spent']) . "</strong></small>";
                    } elseif ($stakeholder['stakeholder_type'] === 'ngo') {
                      echo "<small>External Support / NGO Partner</small>";
                    }
                    ?>
                  </td>
                  <td style="color: var(--text-muted);"><?= date('M d, Y', strtotime($stakeholder['date_joined'])) ?></td>
                  <td style="text-align: center;">
                       <?php if ($stakeholder['stakeholder_type'] === 'supplier'): ?>
                           <a href="view_supplier_stock.php?id=<?= $stakeholder['user_id'] ?>" class="badge" style="background: var(--primary); color: white; text-decoration: none;">
                               <i class="fas fa-warehouse"></i> Inventory
                           </a>
                       <?php endif; ?>
                   </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </main>

  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>

</body>
</html>


