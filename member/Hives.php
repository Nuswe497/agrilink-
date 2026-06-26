<?php
session_start();
require 'db.php';
require 'notif_count.php'; // provides $notifCount

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hive_id      = trim($_POST['hive_id'] ?? '');
    $date         = $_POST['inspection_date'] ?? null;
    $observations = trim($_POST['observations'] ?? '');
    $status       = trim($_POST['status'] ?? 'Pending');

    if ($hive_id && $date && $observations && $status) {
        $stmt = $conn->prepare("INSERT INTO inspections (user_id, hive_id, scheduled_date, status, notes) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $hive_id, $date, $status, $observations);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Inspection recorded successfully.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: " . $stmt->error;
            $_SESSION['msg_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Please complete all required fields.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: Hives.php");
    exit;
}

// Fetch inspections
$stmt = $conn->prepare("SELECT hive_id, scheduled_date, status, notes 
                        FROM inspections 
                        WHERE user_id = ? 
                        ORDER BY scheduled_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$inspections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Hive Logistics | Agrilink Member</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary: #2a9d8f;
      --primary-dark: #21867a;
      --secondary: #e9c46a;
      --accent: #f4a261;
      --bg: #f8fafc;
      --card: #ffffff;
      --text: #1e293b;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --danger: #ef4444;
      --success: #10b981;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', sans-serif;
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #f8fafc;
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
    .navbar a:hover, .navbar a.active { color: var(--secondary); }
    .navbar a::after {
      content: '';
      position: absolute;
      width: 0%;
      height: 2px;
      bottom: -4px;
      left: 0;
      background-color: var(--secondary);
      transition: width 0.3s ease;
    }
    .navbar a:hover::after, .navbar a.active::after { width: 100%; }

    /* Notification badge */
    .notif-wrapper { position: relative; display: inline-flex; align-items: center; }
    .notif-badge {
      position: absolute; top: -9px; right: -11px;
      background: var(--danger); color: white;
      font-size: 0.6rem; font-weight: 700;
      min-width: 17px; height: 17px; border-radius: 999px;
      display: flex; align-items: center; justify-content: center;
      padding: 0 3px; border: 2px solid var(--primary); line-height: 1;
      animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
      0%, 100% { transform: scale(1); }
      50%       { transform: scale(1.2); }
    }

    .container {
      max-width: 1100px;
      margin: 3rem auto;
      padding: 0 1.5rem;
      flex: 1;
    }

    header.page-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    header.page-header h1 {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 0.5rem;
      letter-spacing: -0.5px;
    }

    .message {
      padding: 1rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.8rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .message.success { background: #d1fae5; color: #065f46; border-left: 5px solid var(--success); }
    .message.error { background: #fee2e2; color: #991b1b; border-left: 5px solid var(--danger); }

    .card {
      background: var(--card);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.04);
      border: 1px solid var(--border);
      overflow: hidden;
      margin-bottom: 2.5rem;
    }

    .card-header {
      background: var(--primary);
      color: white;
      padding: 1.25rem 2rem;
      font-size: 1.3rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .card-body { padding: 2rem; }

    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 16px; text-align: left; border-bottom: 1px solid var(--border); }
    th {
      background: #f8fafc;
      color: var(--text-muted);
      font-weight: 700;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    tr:last-child td { border-bottom: none; }

    .status-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: capitalize;
    }
    .status-pending   { background: #fef3c7; color: #92400e; }
    .status-healthy   { background: #d1fae5; color: #065f46; }
    .status-attention { background: #fee2e2; color: #991b1b; }
    .status-completed { background: #dbeafe; color: #1e40af; }

    /* Form styling */
    .form-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(2, 1fr); }
    .form-group { display: flex; flex-direction: column; }
    .form-group.full-width { grid-column: 1 / -1; }
    
    label { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.6rem; color: var(--text); }
    input, textarea {
      padding: 0.9rem 1.2rem;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-family: inherit;
      font-size: 1rem;
      transition: all 0.2s ease;
      background: #f8fafc;
    }
    input:focus, textarea:focus {
      outline: none;
      border-color: var(--primary);
      background: white;
      box-shadow: 0 0 0 4px rgba(42, 157, 143, 0.1);
    }
    textarea { min-height: 120px; resize: vertical; }

    .btn-submit {
      background: var(--primary);
      color: white;
      border: none;
      padding: 1rem 2rem;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 10px 15px -3px rgba(42, 157, 143, 0.2);
    }
    .btn-submit:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 20px 25px -5px rgba(42, 157, 143, 0.3);
    }

    footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 2.5rem;
      font-size: 0.95rem;
      border-top: 5px solid var(--accent);
      font-weight: 500;
    }

    @media (max-width: 768px) {
      .header { flex-direction: column; gap: 1rem; padding: 1.5rem; }
      .navbar ul { gap: 1rem; flex-wrap: wrap; justify-content: center; }
      .form-grid { grid-template-columns: 1fr; }
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
        <li><a href="Hives.php" class="active">Hives</a></li>
        <li><a href="member-inspection.php">Inspections</a></li>
        <li><a href="finances.php">Finances</a></li>
        <li><a href="view_suppliers.php">Suppliers</a></li>
        <li><a href="training.php">Training Hub</a></li>
        <li><a href="profile.php" title="Profile"><i class="fa-solid fa-circle-user"></i></a></li>
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

<div class="container">
  <header class="page-header">
    <h1><i class="fa-solid fa-boxes-stacked"></i> Hive Management</h1>
    <p style="color: var(--text-muted); font-weight: 500;">Monitor and record your colony health</p>
  </header>

  <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?= $_SESSION['msg_type'] ?>">
      <i class="fa-solid <?= $_SESSION['msg_type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
      <?= htmlspecialchars($_SESSION['message']) ?>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
  <?php endif; ?>

  <!-- Upcoming / Recorded Inspections -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-clock-rotate-left"></i>
      Recent Inspection Logs
    </div>
    <div class="card-body">
      <?php if (empty($inspections)): ?>
        <div style="text-align: center; padding: 4rem 1rem;">
          <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--border); margin-bottom: 1.5rem; display: block;"></i>
          <p style="color: var(--text-muted); font-size: 1rem;">No inspection records found for your hives.</p>
        </div>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>Hive ID</th>
                <th>Date</th>
                <th>Status</th>
                <th>Observations</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inspections as $insp): ?>
                <tr>
                  <td><strong style="color: var(--primary);"><?= htmlspecialchars($insp['hive_id']) ?></strong></td>
                  <td style="font-weight: 500;"><?= date('d M Y', strtotime($insp['scheduled_date'])) ?></td>
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
                  <td style="color: var(--text-light); font-size: 0.9rem;"><?= htmlspecialchars($insp['notes'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Record New Inspection -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-file-pen"></i>
      New Inspection Entry
    </div>
    <div class="card-body">
      <form method="POST" class="form-grid">
        <div class="form-group">
          <label for="hive_id">Target Hive ID <span style="color:var(--danger);">*</span></label>
          <input type="text" id="hive_id" name="hive_id" required placeholder="e.g. HIVE-042">
        </div>

        <div class="form-group">
          <label for="inspection_date">Inspection Date <span style="color:var(--danger);">*</span></label>
          <input type="date" id="inspection_date" name="inspection_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group full-width">
          <label for="status">Health Assessment <span style="color:var(--danger);">*</span></label>
          <input type="text" id="status" name="status" required 
                 placeholder="e.g. Healthy, Needs Attention, Treatment Applied">
        </div>

        <div class="form-group full-width">
          <label for="observations">Detailed Observations <span style="color:var(--danger);">*</span></label>
          <textarea id="observations" name="observations" required 
                    placeholder="Describe colony strength, queen sign, honey stores, or pests..."></textarea>
        </div>

        <div class="full-width">
          <button type="submit" class="btn-submit">
            <i class="fa-solid fa-save"></i>
            Record Inspection
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer>
  <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
</footer>

</body>
</html>
