<?php
session_start();
require 'db.php';
require 'notif_count.php'; // provides $notifCount

// Use null coalescing to avoid undefined key warnings
$role     = $_SESSION['role']     ?? null;
$user_id  = $_SESSION['user_id']  ?? null;
$fullName = $_SESSION['full_name'] ?? 'Member'; 
$email    = $_SESSION['email']    ?? '';

// Always initialize $member
$member = [
    'full_name'    => $fullName,
    'email'        => $email,
    'profile_picture' => ''
];

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Hive registration finalization via Paychangu removed

// Initial Hive Registration Step (Save to session & trigger payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_hive_reg'])) {
    $hive_count = (int)$_POST['hive_count'];
    $location = trim($_POST['location']);
    
    if ($hive_count > 0 && !empty($location)) {
        $stmt = $conn->prepare("INSERT INTO hives (user_id, hive_count, location) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $hive_count, $location);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Successfully registered $hive_count hives!";
        } else {
            $_SESSION['error_msg'] = "Failed to register hives.";
        }
        $stmt->close();
        header("Location: member.php");
        exit;
    } else {
        $error_msg = "Please enter a valid hive count and location.";
    }
}

// Only query DB if role and user_id are set
if ($role === 'member' && $user_id) {
    $stmt = $conn->prepare("SELECT full_name, email, profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dbMember = $result->fetch_assoc();
    $stmt->close();

    if ($dbMember) {
        $member = array_merge($member, $dbMember);
    }
}

// Greeting based on time of day
$hour = date("H");
$greeting = match(true) {
    $hour < 12 => "Good morning",
    $hour < 18 => "Good afternoon",
    default    => "Good evening"
};

// Count hives for this member
if ($user_id) {
    $stmt = $conn->prepare("SELECT SUM(hive_count) AS hive_count FROM hives WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $hiveCount = $stmt->get_result()->fetch_assoc()['hive_count'] ?? 0;
    $stmt->close();

    // Count upcoming inspections
    $stmt = $conn->prepare("SELECT COUNT(*) AS insp_count FROM inspections WHERE user_id = ? AND scheduled_date >= CURDATE()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $inspCount = $stmt->get_result()->fetch_assoc()['insp_count'] ?? 0;
    $stmt->close();

    // Fetch upcoming inspections (next 3) for sidebar
    $stmt = $conn->prepare("SELECT scheduled_date, status, hive_id FROM inspections WHERE user_id = ? AND scheduled_date >= CURDATE() ORDER BY scheduled_date ASC LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $upcomingInspections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch balance from finances (latest record)
    $stmt = $conn->prepare("SELECT amount FROM finance WHERE user_id = ? ORDER BY date DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $balanceRow = $stmt->get_result()->fetch_assoc();
    $balance = $balanceRow ? $balanceRow['amount'] : 0;
    $stmt->close();
    // Fetch upcoming markets (next 3)
    require_once '../dynamic_markets.php';
    $upcomingMarkets = getUpcomingMarkets($conn, 3);
    
    // Fetch latest news
    $stmt = $conn->prepare("SELECT title, message, created_at FROM notifications WHERE target_role = 'member' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $latestNews = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $hiveCount = 0;
    $inspCount = 0;
    $balance   = 0;
    $upcomingInspections = [];
    $upcomingMarkets = [];
    $latestNews = null;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="robots" content="noindex, nofollow">
  <title>Member Dashboard | Agrilink</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary: #2a9d8f;
      --primary-dark: #21867a;
      --secondary: #e9c46a;
      --accent: #f4a261;
      --accent-hover: #e76f51;
      --bg: #f8fafc;
      --card-bg: rgba(255, 255, 255, 0.85);
      --text: #1e293b;
      --text-muted: #64748b;
      --success: #10b981;
      --danger: #ef4444;
      --glass-border: rgba(255, 255, 255, 0.4);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    /* Header */
    .header {
      background: var(--primary);
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
    
    .navbar ul { display: flex; list-style: none; gap: 2.5rem; align-items: center; }
    .navbar a { 
      color: white; 
      text-decoration: none; 
      font-weight: 600; 
      font-size: 0.95rem; 
      transition: all 0.3s ease; 
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .navbar a:hover, .navbar a.active { color: var(--accent); }
    .navbar a::after {
      content: '';
      position: absolute;
      width: 0%;
      height: 2px;
      bottom: -4px;
      left: 0;
      background-color: var(--accent);
      transition: width 0.3s ease;
    }
    .navbar a:hover::after, .navbar a.active::after { width: 100%; }

    /* Notification badge */
    .notif-wrapper { position: relative; display: inline-flex; align-items: center; }
    .notif-badge {
      position: absolute;
      top: -9px; right: -11px;
      background: #ef4444;
      color: white;
      font-size: 0.6rem;
      font-weight: 700;
      min-width: 17px; height: 17px;
      border-radius: 999px;
      display: flex; align-items: center; justify-content: center;
      padding: 0 3px;
      border: 2px solid var(--primary);
      line-height: 1;
      animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
      0%, 100% { transform: scale(1); }
      50%       { transform: scale(1.2); }
    }

    .profile-link i { font-size: 1.2rem; }

    /* Upcoming inspections sidebar panel */
    .insp-list { list-style: none; margin-top: 0.5rem; }
    .insp-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid #e2e8f0;
    }
    .insp-item:last-child { border-bottom: none; }
    .insp-dot {
      width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
      background: var(--primary);
    }
    .insp-dot.pending  { background: #f59e0b; }
    .insp-dot.completed{ background: #10b981; }
    .insp-date { font-weight: 600; font-size: 0.92rem; color: var(--text); }
    .insp-meta { font-size: 0.82rem; color: var(--text-muted); }

    /* Main Container */
    main {
      flex: 1;
      padding: 3rem;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
      display: grid;
      grid-template-columns: 3fr 1fr;
      gap: 2rem;
    }

    @media(max-width: 1024px) {
      main { grid-template-columns: 1fr; }
    }

    /* Hero Section */
    .hero-panel {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      border-radius: 24px;
      padding: 3rem;
      color: white;
      box-shadow: 0 20px 40px rgba(42, 157, 143, 0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
    }
    .hero-panel::after {
      content: '\f434'; /* Bee/Bug icon */
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      position: absolute;
      right: -20px;
      bottom: -40px;
      font-size: 14rem;
      color: rgba(255,255,255,0.05);
      transform: rotate(-15deg);
    }
    .hero-content h1 {
      font-size: 2.8rem;
      margin-bottom: 0.5rem;
      font-weight: 700;
      letter-spacing: -1px;
    }
    .hero-content p {
      font-size: 1.1rem;
      opacity: 0.9;
      font-weight: 300;
    }
    .hero-profile {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      z-index: 2;
    }
    .avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      border: 4px solid rgba(255,255,255,0.3);
      background: white;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 2.5rem;
      color: var(--primary);
      overflow: hidden;
    }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }
    
    .status-badge {
      background: rgba(255,255,255,0.2);
      backdrop-filter: blur(5px);
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid rgba(255,255,255,0.4);
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.5rem;
    }
    .stat-card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid white;
      border-radius: 20px;
      padding: 1.8rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.04);
      display: flex;
      align-items: center;
      gap: 1.5rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
      border-color: var(--accent);
    }
    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 1.8rem;
    }
    .stat-hives .stat-icon { background: rgba(244, 162, 97, 0.15); color: var(--accent); }
    .stat-insp .stat-icon { background: rgba(42, 157, 143, 0.15); color: var(--primary); }
    .stat-bal .stat-icon { background: rgba(16, 185, 129, 0.15); color: var(--success); }
    
    .stat-info h3 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 2px; }
    .stat-info p { font-size: 0.95rem; color: var(--text-muted); font-weight: 500; }

    /* Side Panel */
    .side-panel {
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }
    .action-card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid white;
      border-radius: 20px;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    }
    .action-card h3 {
      font-size: 1.3rem;
      margin-bottom: 1.5rem;
      color: var(--primary-dark);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 1rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
      text-decoration: none;
      margin-bottom: 1rem;
    }
    .btn-primary {
      background: var(--primary);
      color: white;
      box-shadow: 0 4px 15px rgba(42, 157, 143, 0.3);
    }
    .btn-primary:hover { background: var(--primary-dark); transform: scale(1.02); }
    
    .btn-outline {
      background: transparent;
      border: 2px solid var(--primary);
      color: var(--primary);
    }
    .btn-outline:hover { background: rgba(42, 157, 143, 0.05); }
    
    .btn-accent {
      background: var(--accent);
      color: white;
      box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
    }
    .btn-accent:hover { background: var(--accent-hover); transform: scale(1.02); }

    .news-banner {
      background: linear-gradient(135deg, #fff7ed, #ffedd5);
      border-left: 4px solid var(--accent);
      padding: 1.2rem;
      border-radius: 12px;
    }
    .news-banner h4 { color: var(--accent-hover); margin-bottom: 5px; font-size: 0.95rem; }
    .news-banner p { color: #78350f; font-size: 0.95rem; font-weight: 500; }

    /* Modal */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(5px);
      z-index: 1000;
      justify-content: center;
      align-items: center;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .modal {
      background: white;
      padding: 2.5rem;
      border-radius: 24px;
      width: 100%;
      max-width: 450px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      transform: translateY(20px);
      transition: transform 0.3s ease;
      position: relative;
    }
    .modal.show { transform: translateY(0); }
    
    .modal-close {
      position: absolute;
      top: 20px;
      right: 20px;
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-muted);
      transition: color 0.2s;
    }
    .modal-close:hover { color: var(--danger); }
    
    .modal h2 { color: var(--primary-dark); margin-bottom: 1.5rem; }
    
    .form-group { margin-bottom: 1.5rem; }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: var(--text);
      font-size: 0.95rem;
    }
    .form-group input {
      width: 100%;
      padding: 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-family: inherit;
      font-size: 1rem;
      transition: all 0.2s;
      outline: none;
    }
    .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1); }
    
    .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 500; }
    .alert-success { background: #d1fae5; color: #065f46; }
    .alert-danger { background: #fee2e2; color: #991b1b; }

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
  </style>
  <!-- Paychangu script removed -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <!-- Header -->
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
    <nav class="navbar">
      <ul>
        <li><a href="member.php" class="active">Home</a></li>
        <li><a href="Hives.php">Hives</a></li>
        <li><a href="member-inspection.php">Inspections</a></li>
        <li><a href="finances.php">Finances</a></li>
        <li><a href="training.php">Training Hub</a></li>
        <li><a href="contact_cooperative.php">Contact</a></li>
        <li><a href="#" onclick="toggleNotifPanel(event)" title="Notifications">
            <span class="notif-wrapper">
              <i class="fa-solid fa-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
              <?php endif; ?>
            </span>
          </a>
        </li>
        <li><a href="profile.php" class="profile-link" title="Profile"><i class="fa-solid fa-circle-user"></i></a></li>
        <li><a href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
      </ul>
    </nav>
  </header>

  <!-- Main Area -->
  <main>
    <div class="main-column">
      
      <?php if(!empty($success_msg)): ?>
        <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div>
      <?php endif; ?>
      <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?></div>
      <?php endif; ?>

      <!-- Hero Banner -->
      <section class="hero-panel">
        <div class="hero-content">
          <h1><?php echo $greeting; ?>, <br><?php echo explode(' ', htmlspecialchars($member['full_name']))[0]; ?>!</h1>
          <p>Welcome back to the Agrilink Co-op Dashboard.</p>
        </div>
        <div class="hero-profile">
          <div class="avatar">
            <?php if (!empty($member['profile_picture'])): ?>
              <img src="../admin/<?php echo htmlspecialchars($member['profile_picture']); ?>" alt="Profile">
            <?php else: ?>
              <i class="fa-solid fa-user"></i>
            <?php endif; ?>
          </div>
          <span class="status-badge"><i class="fa-solid fa-circle-check"></i> Active Member</span>
        </div>
      </section>

      <!-- Stats Grid -->
      <section class="stats-grid">
        <div class="stat-card stat-hives">
          <div class="stat-icon"><i class="fa-solid fa-cubes"></i></div>
          <div class="stat-info">
            <h3><?php echo number_format($hiveCount); ?></h3>
            <p>Registered Hives</p>
          </div>
        </div>

        <div class="stat-card stat-insp">
          <div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
          <div class="stat-info">
            <h3><?php echo number_format($inspCount); ?></h3>
            <p>Pending Inspections</p>
          </div>
        </div>

        <div class="stat-card stat-bal">
          <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
          <div class="stat-info">
            <h3><span style="font-size: 1.2rem;">MWK</span> <?php echo number_format((float)$balance); ?></h3>
            <p>Current Balance</p>
          </div>
        </div>
      </section>

      <!-- New Horizontal Row for Active Updates -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
        
        <!-- Upcoming Inspections Card -->
        <div class="action-card" style="margin-bottom: 0;">
          <h3 style="color: var(--primary);"><i class="fa-solid fa-calendar-check"></i> Upcoming Inspections</h3>
          <?php if (empty($upcomingInspections)): ?>
            <div style="text-align: center; padding: 2rem 0;">
              <i class="fa-solid fa-check-double" style="font-size: 2.5rem; color: #e2e8f0; margin-bottom: 1rem; display: block;"></i>
              <p style="color: var(--text-muted); font-size: 0.95rem;">No upcoming inspections scheduled.</p>
            </div>
          <?php else: ?>
            <ul class="insp-list" style="margin-bottom: 1rem;">
              <?php foreach ($upcomingInspections as $insp): 
                $dotColor = strtolower($insp['status']) === 'completed' ? '#10b981' : '#f59e0b';
              ?>
              <li class="insp-item">
                <span class="insp-dot" style="background: <?= $dotColor ?>;"></span>
                <div>
                  <div class="insp-date"><?= date('d M Y', strtotime($insp['scheduled_date'])) ?></div>
                  <div class="insp-meta">
                    <?= !empty($insp['hive_id']) ? 'Hive: ' . htmlspecialchars($insp['hive_id']) . ' &bull; ' : '' ?>
                    <span style="color:<?= $dotColor ?>; font-weight:600;"><?= htmlspecialchars($insp['status']) ?></span>
                  </div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
            <a href="member-inspection.php" style="color: var(--primary); font-weight: 600; text-decoration: none; font-size: 0.9rem;">View All Inspections &rarr;</a>
          <?php endif; ?>
        </div>

        <!-- Upcoming Markets Card -->
        <div class="action-card" style="margin-bottom: 0; border-top: 4px solid var(--accent);">
          <h3 style="color: var(--accent-hover);"><i class="fa-solid fa-store"></i> Upcoming Markets</h3>
          <?php if (empty($upcomingMarkets)): ?>
            <div style="text-align: center; padding: 2rem 0;">
              <i class="fa-solid fa-store-slash" style="font-size: 2.5rem; color: #e2e8f0; margin-bottom: 1rem; display: block;"></i>
              <p style="color: var(--text-muted); font-size: 0.95rem;">No active markets at the moment.</p>
            </div>
          <?php else: ?>
            <ul class="insp-list">
              <?php foreach ($upcomingMarkets as $market): ?>
              <li class="insp-item">
                <span class="insp-dot" style="background: var(--accent);"></span>
                <div>
                  <div class="insp-date"><?= date('d M Y', strtotime($market['market_date'])) ?></div>
                  <div class="insp-meta">
                    <strong><?= htmlspecialchars($market['location']) ?></strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($market['description']) ?></div>
                  </div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <!-- Side Column -->
    <div class="side-panel">
      
      <div class="action-card">
        <h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
        
        <button onclick="openHiveModal()" class="btn btn-accent">
          <i class="fa-solid fa-plus-circle"></i> Register Hives
        </button>
        
        <?php if ($hiveCount <= 5): ?>
          <a href="finances.php" class="btn btn-primary" style="opacity: 0.7; cursor: pointer;" title="You need more than 5 hives">
            <i class="fa-solid fa-lock"></i> Buy Shares (Locked)
          </a>
        <?php else: ?>
          <a href="finances.php" class="btn btn-primary">
            <i class="fa-solid fa-money-bill-trend-up"></i> Buy Shares
          </a>
        <?php endif; ?>
        
        <a href="training.php" class="btn btn-outline">
          <i class="fa-solid fa-book-open"></i> Access Training
        </a>
      </div>

      <div class="news-banner">
        <h4><i class="fa-solid fa-bullhorn"></i> Cooperative News</h4>
        <?php if ($latestNews): ?>
          <p title="<?= htmlspecialchars($latestNews['message']) ?>">
             <?= htmlspecialchars(mb_strimwidth($latestNews['message'], 0, 70, "...")) ?>
          </p>
          <a href="#" onclick="toggleNotifPanel(event)" style="font-size: 0.8rem; color: var(--accent-hover); font-weight: bold; text-decoration: none; display: inline-block; margin-top: 5px;">View all &rarr;</a>
        <?php else: ?>
          <p>No new announcements at this time.</p>
        <?php endif; ?>
      </div>


    </div>
  </main>

  <!-- Register Hives Modal -->
  <div id="hiveModal" class="modal-overlay">
    <div class="modal" id="hiveModalContent">
      <button class="modal-close" onclick="closeHiveModal()"><i class="fa-solid fa-xmark"></i></button>
      <h2><i class="fa-solid fa-box-open"></i> Register New Hives</h2>
      <form method="POST">
        <div class="form-group">
          <label for="hive_count">Number of Hives</label>
          <input type="number" id="hive_count" name="hive_count" required min="1" placeholder="e.g., 5">
        </div>
        <div class="form-group">
          <label for="location">Location / Apiary Name</label>
          <input type="text" id="location" name="location" required placeholder="e.g., North Field Forest">
        </div>
        <div class="alert alert-info" style="font-size: 0.9rem; padding: 0.8rem;">
          <i class="fa-solid fa-circle-info"></i> Hive Registration Form: <strong>Livingstonia Bee Keeping Cooperative</strong>
        </div>
        <button type="submit" name="init_hive_reg" class="btn btn-primary" style="margin-top: 1rem;">
          <i class="fa-solid fa-box-open"></i> Register Hives
        </button>
      </form>
    </div>
  </div>

  <footer>
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>

  <script>
    function openHiveModal() {
      const overlay = document.getElementById('hiveModal');
      const content = document.getElementById('hiveModalContent');
      overlay.style.display = 'flex';
      // Trigger reflow
      void overlay.offsetWidth;
      overlay.style.opacity = '1';
      content.classList.add('show');
    }

    function closeHiveModal() {
      const overlay = document.getElementById('hiveModal');
      const content = document.getElementById('hiveModalContent');
      overlay.style.opacity = '0';
      content.classList.remove('show');
      setTimeout(() => {
        overlay.style.display = 'none';
      }, 300);
    }

    // Close on outside click
    window.onclick = function(event) {
      const modal = document.getElementById('hiveModal');
      if (event.target == modal) {
        closeHiveModal();
      }
    }

  </script>

</body>
</html>
