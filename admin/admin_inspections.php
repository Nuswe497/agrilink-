<?php
session_start();
require 'db.php';
require 'notif_count.php';
require_once '../mailer_helper.php';

// Ensure only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id       = (int)$_POST['user_id'];       // inspector is a member, so use user_id
    $scheduled_date= $_POST['scheduled_date'];

    $stmt = $conn->prepare("INSERT INTO inspections (user_id, scheduled_date) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $scheduled_date);
    if ($stmt->execute()) {
        // Create a dashboard notification for the assigned member
        $notifTitle = "Hive Inspection Scheduled";
        $notifMessage = "A hive inspection has been scheduled for you on {$scheduled_date}. Please check your portal for details.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, 'member')");
        if ($notifStmt) {
            $notifStmt->bind_param("ss", $notifTitle, $notifMessage);
            $notifStmt->execute();
            $notifStmt->close();
        }

        // Send email notification to the assigned member
        $emailErr = '';
        sendInspectionEmail($conn, $user_id, $scheduled_date, '', $emailErr);
        // (email errors are non-blocking for admin)
    }
    $stmt->close();
}

// Fetch members (these are the inspectors)
$members_result = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'member'");
$members = $members_result->fetch_all(MYSQLI_ASSOC);

// Fetch upcoming assignments
$assignments_result = $conn->query("
    SELECT i.scheduled_date, u.full_name AS inspector_name
    FROM inspections i
    JOIN users u ON i.user_id = u.user_id
    ORDER BY i.scheduled_date ASC
    LIMIT 10
");
$assignments = $assignments_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inspection Scheduling | Agrilink Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #f4a261;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --info: #3b82f6;
      --light: #ecf0f1;
      --dark: #1e293b;
      --text: #2c3e50;
      --text-muted: #64748b;
      --glass-bg: rgba(255, 255, 255, 0.45);
      --glass-border: rgba(255, 255, 255, 0.6);
      --shadow: 0 8px 32px rgba(42, 157, 143, 0.08);
      --radius: 16px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', sans-serif;
      color: var(--text);
      background: linear-gradient(135deg, #fffbe6, #fef6d3);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow-x: hidden;
    }

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
    .header-logo { font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 0.8rem; }
    .header-nav { display: flex; align-items: center; gap: 1.5rem; }
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
    .header-nav a:hover, .header-nav a.active { color: white; background: rgba(255, 255, 255, 0.15); }

    .main-content {
      flex: 1;
      padding: 3rem 5%;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .glass-panel {
      background: var(--glass-bg);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 2.5rem;
      margin-bottom: 2.5rem;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-bottom: 2.5rem;
    }
    .page-header h1 { font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-bottom: 5px; }
    .page-header p { color: var(--text-muted); font-size: 1.1rem; }

    /* Forms */
    .section-title { font-size: 1.3rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
    .form-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; align-items: end; }
    
    .form-group { position: relative; display: flex; flex-direction: column; }
    .form-group input, .form-group select {
      width: 100%; padding: 1.2rem 1rem 0.6rem; border: 2px solid rgba(255,255,255,0.8);
      background: rgba(255,255,255,0.5); border-radius: 10px; font-size: 1rem; font-family: inherit; color: var(--dark); font-weight: 500; transition: 0.3s;
    }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 4px 15px rgba(42, 157, 143, 0.1); }
    .form-group label {
      position: absolute; left: 1rem; top: 1rem; color: var(--text-muted); font-size: 0.95rem; font-weight: 500; transition: 0.2s ease all; pointer-events: none;
    }
    .form-group input:focus ~ label, .form-group input:not(:placeholder-shown) ~ label,
    .form-group select:focus ~ label, .form-group select:valid ~ label {
      top: 0.3rem; font-size: 0.7rem; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    }

    .btn { padding: 1rem 1.5rem; border: none; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; height: 56px;}
    .btn-primary { background: linear-gradient(135deg, var(--primary), #21867a); color: white; box-shadow: 0 4px 15px rgba(42, 157, 143, 0.3); }
    .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(42, 157, 143, 0.4); }

    /* Table */
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    th { padding: 1rem 1.5rem; text-align: left; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); border-bottom: 2px solid rgba(0,0,0,0.05); }
    td { padding: 1.2rem 1.5rem; background: rgba(255,255,255,0.4); transition: 0.3s; vertical-align: middle; }
    td:first-child { border-radius: 12px 0 0 12px; font-weight: 700; color: var(--dark); }
    td:last-child { border-radius: 0 12px 12px 0; }
    tbody tr { transition: transform 0.2s, box-shadow 0.2s; }
    tbody tr:hover td { background: rgba(255,255,255,0.8); }
    tbody tr:hover { transform: scale(1.01); box-shadow: 0 4px 15px rgba(0,0,0,0.05); z-index: 10; position: relative; }

    .no-data { text-align: center; padding: 3rem; color: var(--text-muted); }
    .no-data i { font-size: 3rem; opacity: 0.3; margin-bottom: 1rem; }

    .footer { background: var(--primary); color: white; text-align: center; padding: 3rem; margin-top: auto; border-top: 5px solid var(--secondary); }
    .footer a { color: var(--secondary); text-decoration: none; font-weight: 700; }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <!-- Header -->
  <header class="header">
    <div class="header-logo">
      <img src="../assets/logo.png" alt="Agrilink Logo" style="height: 40px; width: auto; margin-right: 10px;">
      AGRILINK ADMIN
    </div>
    <nav class="header-nav">
      <a href="admin.php"><i class="fa-solid fa-house"></i> Home</a>
      <a href="admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
      <a href="admin_members.php"><i class="fa-solid fa-users"></i> Members</a>
      <a href="admin_finances.php"><i class="fa-solid fa-money-bill"></i> Finances</a>
      <a href="admin_stakeholder.php"><i class="fa-solid fa-handshake"></i> Stakeholders</a>
      <a href="admin_profile.php"><i class="fa-solid fa-user-cog"></i> Profile</a>
      
      <a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="position:relative; margin-right: 15px;">
        <i class="fa-solid fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span style="position:absolute; top:-5px; right:-10px; background:#ef4444; color:white; font-size:0.6rem; font-weight:800; padding:2px 5px; border-radius:10px; border:2px solid var(--primary);"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
        <?php endif; ?>
      </a>
      <a href="logout.php" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
    </nav>
  </header>

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <div>
        <h1>Manage Inspections</h1>
        <p>Schedule and monitor cooperative member hive inspections.</p>
      </div>
    </div>

    <!-- Schedule Inspection Form -->
    <div class="glass-panel">
      <div class="section-title"><i class="fas fa-calendar-plus"></i> Schedule New Inspection</div>
      <form method="POST" class="form-container">
        <div class="form-group">
          <select id="user" name="user_id" required>
            <option value="">-- Select Member --</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= $m['user_id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="user">Assign Inspector (Member)</label>
        </div>
        <div class="form-group">
          <input type="date" id="date" name="scheduled_date" required>
          <label for="date">Scheduled Date</label>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-calendar-check"></i> Schedule Inspection
        </button>
      </form>
    </div>

    <!-- Upcoming Inspections -->
    <div class="glass-panel">
      <div class="section-title"><i class="fas fa-list-check"></i> Upcoming Inspections</div>
      <?php if (empty($assignments)): ?>
        <div class="no-data">
          <i class="fas fa-inbox"></i>
          <p>No inspections scheduled yet. Create one using the form above.</p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>Inspector (Member)</th>
                <th>Scheduled Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignments as $a): ?>
                <tr>
                  <td><i class="fa-solid fa-user-shield" style="color: var(--secondary); margin-right: 8px;"></i> <?= htmlspecialchars($a['inspector_name']) ?></td>
                  <td><strong><?= date('d M Y', strtotime($a['scheduled_date'])) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>
</body>
</html>

