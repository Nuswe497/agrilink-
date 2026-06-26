<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($role != 'member') {
    header("Location: admin.php");
    exit;
}

// Fetch member's finances
$stmt = $conn->prepare("SELECT * FROM finance WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$finances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total
$total = 0;
foreach ($finances as $f) {
    $total += floatval($f['amount']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Finances</title>
  <link rel="stylesheet" href="theme.css">
  <link rel="stylesheet" href="member_dashboard.css">
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h2>?? Agrilink</h2>
      <ul>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="Hive_Inspection.php">Inspections</a></li>
        <li><a href="training_hub.php">Training Hub</a></li>
        <li><a href="view_finances.php">Finances</a></li>
        <li><a href="#">Notifications</a></li>
        <li><a href="#">Profile</a></li>
        <li>
<a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="text-decoration:none; margin-right: 15px;">
  <span class="notif-wrapper" style="position:relative; display:inline-flex; align-items:center;">
    <i class="fa-solid fa-bell"></i>
    <?php if ($notifCount > 0): ?>
      <span class="notif-badge" style="position:absolute; top:-8px; right:-10px; background:#ef4444; color:white; font-size:0.65rem; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #2a9d8f;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
    <?php endif; ?>
  </span>
</a>
<a href="logout.php">Logout</a></li>
      </ul>
    </aside>

    <!-- Main area -->
    <div class="main-area">
      <!-- Header -->
      <header>
        <h1>Your Finances</h1>
        <p>Track your contributions and earnings</p>
      </header>

      <!-- Main content -->
      <main>
        <!-- Summary -->
        <section class="card">
          <h3>?? Financial Summary</h3>
          <p>Total Balance: MWK <?php echo number_format($total, 2); ?></p>
        </section>

        <!-- Finances List -->
        <section class="card full-width">
          <h3>Detailed Transactions</h3>
          <ul>
            <?php foreach ($finances as $f): ?>
              <li>
                <?php if (!empty($f['transaction_type'])): ?>
                  <strong><?= htmlspecialchars(ucfirst($f['transaction_type'])) ?>:</strong>
                <?php endif; ?>
                <?= number_format($f['amount'], 2) ?> MWK on <?= htmlspecialchars($f['date']) ?>
                <?php if (!empty($f['description'])): ?>
                  — <?= htmlspecialchars($f['description']) ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      </main>

      <!-- Footer -->
      <footer>
        <p>&copy; 2026 Agrilink Cooperative | Support: livingstoniaagrilink@gmail.com</p>
      </footer>
    </div>
  </div>
</body>
</html>
