<?php
session_start();
require 'db.php';
require 'notif_count.php'; // provides $notifCount

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header("Location: login.php");
    exit;
}

$user_id  = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Member';

$hour = date("H");
$greeting = match(true) {
    $hour < 12 => "Good morning",
    $hour < 18 => "Good afternoon",
    default    => "Good evening"
};

$stmt = $conn->prepare("
    SELECT i.scheduled_date, i.status, u.full_name AS inspector_name, i.hive_id, i.notes
    FROM inspections i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.user_id = ?
    ORDER BY i.scheduled_date ASC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$inspections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(hive_count) AS hive_count FROM hives WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$hiveCount = $stmt->get_result()->fetch_assoc()['hive_count'] ?? 0;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Member Inspections | Agrilink</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --green:     #2a9d8f;
      --orange:    #f4a261;
      --card:      #ffffff;
      --text:      #1e293b;
      --text-light:#475569;
      --border:    #e2e8f0;
    }

    * { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Header */
    .header {
      background: var(--green);
      padding: 1rem 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
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
    .navbar ul { display: flex; list-style: none; gap: 2.5rem; align-items: center; margin: 0; padding: 0; }
    .navbar a { 
      color: white; 
      text-decoration: none; 
      font-weight: 600; 
      font-size: 0.95rem; 
      transition: all 0.3s ease; 
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .navbar a:hover, .navbar a.active { color: var(--orange); }
    .navbar a::after {
      content: '';
      position: absolute;
      width: 0%;
      height: 2px;
      bottom: -4px;
      left: 0;
      background-color: var(--orange);
      transition: width 0.3s ease;
    }
    .navbar a:hover::after, .navbar a.active::after { width: 100%; }

    /* Notification badge */
    .notif-wrapper { position: relative; display: inline-flex; align-items: center; }
    .notif-badge {
      position: absolute; top: -9px; right: -11px;
      background: #ef4444; color: white;
      font-size: 0.6rem; font-weight: 700;
      min-width: 17px; height: 17px; border-radius: 999px;
      display: flex; align-items: center; justify-content: center;
      padding: 0 3px; border: 2px solid var(--green); line-height: 1;
      animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
      0%, 100% { transform: scale(1); }
      50%       { transform: scale(1.2); }
    }

    .main-content {
      flex: 1;
      padding: 2.5rem 1.5rem;
      max-width: 1100px;
      margin: 0 auto;
      width: 100%;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--green);
      margin-bottom: 2rem;
    }

    .card {
      background: var(--card);
      border-radius: 12px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.07);
      margin-bottom: 2rem;
      overflow: hidden;
    }
    .card-header {
      background: var(--green);
      color: white;
      padding: 1.2rem 1.6rem;
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }
    .card-body { padding: 1.8rem; }

    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th,td { padding:14px 16px; border-bottom:1px solid var(--border); text-align:left; }
    th { background: var(--orange); color: white; font-weight: 600; }
    tr:last-child { border-bottom: none; }

    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 600;
    }
    .status-pending  { background: #fef3c7; color: #92400e; }
    .status-healthy  { background: #d1fae5; color: #065f46; }
    .status-attention{ background: #fee2e2; color: #991b1b; }
    .status-completed{ background: #dbeafe; color: #1e40af; }

    footer {
      background: #2a9d8f;
      color: white;
      text-align: center;
      padding: 2rem;
      margin-top: auto;
      font-size: 0.95rem;
      border-top: 4px solid #f4a261;
      font-weight: 500;
    }

    @media (max-width: 900px) {
      .header { flex-direction: column; gap: 1.2rem; padding: 1.2rem 1.5rem; }
      .navbar ul { flex-wrap: wrap; justify-content: center; gap: 1.2rem; }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
    <nav class="navbar">
      <ul>
        <li><a href="member.php">Home</a></li>
        <li><a href="Hives.php">Hives</a></li>
        <li><a href="member-inspection.php" class="active">Inspections</a></li>
        <li><a href="finances.php">Finances</a></li>
        <li><a href="view_suppliers.php">Suppliers</a></li>
        <li><a href="training.php">Training Hub</a></li>
        <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i></a></li>
        <li>
          <a href="notification.php" title="Notifications">
            <span class="notif-wrapper">
              <i class="fa-solid fa-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
              <?php endif; ?>
            </span>
          </a>
        </li>
        <li><a href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
      </ul>
    </nav>
  </header>

  <div class="main-content">
    <h1 class="page-title"><?= $greeting ?>, <?= htmlspecialchars($fullName) ?></h1>

    <div class="card">
      <div class="card-header"><i class="fa-solid fa-cubes"></i> Your Hive Summary</div>
      <div class="card-body">
        <p>You currently have <strong><?= number_format((int)$hiveCount) ?></strong> hives registered.</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="fa-solid fa-clipboard-check"></i> Scheduled Inspections</div>
      <div class="card-body">
        <?php if (empty($inspections)): ?>
          <p style="text-align:center; color:var(--text-light); padding:2rem 0;">No inspections scheduled at this time.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>Date</th><th>Inspector</th><th>Hive ID</th><th>Notes</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($inspections as $insp): ?>
                <tr>
                  <td><?= date('d M Y', strtotime($insp['scheduled_date'])) ?></td>
                  <td><?= htmlspecialchars($insp['inspector_name']) ?></td>
                  <td><strong><?= htmlspecialchars($insp['hive_id']) ?></strong></td>
                  <td><?= htmlspecialchars($insp['notes'] ?: '—') ?></td>
                  <td>
                    <?php
                      $statusClass = strtolower(str_replace(' ', '-', $insp['status']));
                      $statusClass = in_array($statusClass, ['pending','healthy','attention','completed']) 
                                   ? $statusClass : 'pending';
                    ?>
                    <span class="status-badge status-<?= $statusClass ?>">
                      <?= htmlspecialchars($insp['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <footer>
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>
</body>
</html>

