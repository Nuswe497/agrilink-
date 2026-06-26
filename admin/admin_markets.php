<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_market'])) {
    $market_id = isset($_POST['market_id']) ? (int)$_POST['market_id'] : 0;
    $location = trim($_POST['location'] ?? '');
    $market_date = $_POST['market_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($location === '' || $market_date === '') {
        $error = 'Location and Date are required.';
    } else {
        if ($market_id > 0) {
            $stmt = $conn->prepare("UPDATE markets SET location = ?, market_date = ?, description = ? WHERE market_id = ?");
            $stmt->bind_param("sssi", $location, $market_date, $description, $market_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO markets (location, market_date, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $location, $market_date, $description);
        }
        if ($stmt->execute()) { $message = 'Market saved successfully.'; } 
        else { $error = 'Error saving market: ' . $conn->error; }
        $stmt->close();
    }
}

if (isset($_GET['delete_market'])) {
    $id = (int)$_GET['delete_market'];
    $conn->query("DELETE FROM markets WHERE market_id = $id");
    header("Location: admin_markets.php?msg=Market+deleted"); exit;
}

$markets = $conn->query("SELECT * FROM markets ORDER BY market_date DESC")->fetch_all(MYSQLI_ASSOC);

require_once '../dynamic_markets.php';
$dynamicMarkets = array_filter(getUpcomingMarkets($conn, 20), function($m) {
    return isset($m['type']) && $m['type'] === 'dynamic';
});

$edit_item = null;
if (isset($_GET['edit_market'])) {
    $id = (int)$_GET['edit_market'];
    $edit_item = $conn->query("SELECT * FROM markets WHERE market_id = $id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Market Logistics | Agrilink Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
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

    /* System Header */
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

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .form-group input, .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s;
    }

    .form-group input:focus, .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
    }

    .btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 12px 25px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn:hover { background: #238b7e; transform: translateY(-2px); }

    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 15px; background: #f8fafc; color: var(--primary); font-weight: 700; border-bottom: 2px solid var(--border); }
    td { padding: 15px; border-bottom: 1px solid var(--border); vertical-align: middle; }

    .badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .badge-upcoming { background: #dcfce7; color: #166534; }
    .badge-past { background: #f1f5f9; color: #64748b; }

    /* Footer */
    .footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid var(--secondary);
      width: 100%;
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
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
      <a href="admin_finances.php"><i class="fas fa-money-bill"></i> Finances</a>
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

  <main class="main-content">
    
    <div class="page-title">
      <h1><i class="fas fa-truck-moving"></i> Market Logistics</h1>
      <span style="color: var(--text-muted); font-weight: 600;">Manage cooperative market schedules</span>
    </div>

    <!-- Alert Messages -->
    <?php if ($message || isset($_GET['msg'])): ?>
      <div class="card" style="border-left: 5px solid var(--primary); padding: 15px;">
        <i class="fas fa-check-circle" style="color: var(--primary);"></i> <?= $message ?: $_GET['msg'] ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="card" style="border-left: 5px solid #ef4444; padding: 15px;">
        <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> <?= $error ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3><i class="fas fa-plus-circle" style="color: var(--primary); margin-right: 10px;"></i> <?= isset($_GET['edit_market']) ? 'Edit Market' : 'Schedule New Market' ?></h3>
      <form method="POST" style="margin-top: 20px;">
        <?php if (isset($_GET['edit_market'])): ?><input type="hidden" name="market_id" value="<?= $edit_item['market_id'] ?>"><?php endif; ?>
        <div class="form-grid">
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" value="<?= $edit_item['location'] ?? '' ?>" placeholder="e.g. Livingstonia Central" required>
          </div>
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="market_date" value="<?= $edit_item['market_date'] ?? '' ?>" required>
          </div>
        </div>
        <div class="form-group" style="margin-top:15px;">
          <label>Description (Optional)</label>
          <textarea name="description" rows="3" placeholder="Provide additional details about the market location or objectives..."><?= $edit_item['description'] ?? '' ?></textarea>
        </div>
        <button type="submit" name="save_market" class="btn" style="margin-top:20px;">
          <i class="fas fa-save"></i> SAVE SCHEDULE
        </button>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary);"><i class="fas fa-robot"></i> Auto-Scheduled National Markets</h3>
      <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;">These major Malawian markets are automatically generated by the system algorithm and do not require manual scheduling.</p>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>STATUS</th>
              <th>MARKET DATE</th>
              <th>LOCATION</th>
              <th>DESCRIPTION</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($dynamicMarkets as $m): $past = strtotime($m['market_date']) < time(); ?>
              <tr style="background: rgba(42, 157, 143, 0.03);">
                <td><span class="badge <?= $past ? 'badge-past' : 'badge-upcoming' ?>"><?= $past ? 'PAST' : 'UPCOMING' ?></span></td>
                <td><strong><?= date('M d, Y', strtotime($m['market_date'])) ?></strong></td>
                <td><i class="fas fa-map-marker-alt" style="color: var(--secondary); margin-right: 5px;"></i> <?= htmlspecialchars($m['location']) ?></td>
                <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($m['description']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary);"><i class="fas fa-edit"></i> Manually Managed Markets</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>STATUS</th>
              <th>MARKET DATE</th>
              <th>LOCATION</th>
              <th style="width: 120px;">ACTION</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($markets) === 0): ?>
              <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">No custom markets scheduled.</td></tr>
            <?php else: ?>
            <?php foreach($markets as $m): $past = strtotime($m['market_date']) < time(); ?>
              <tr>
                <td><span class="badge <?= $past ? 'badge-past' : 'badge-upcoming' ?>"><?= $past ? 'PAST' : 'UPCOMING' ?></span></td>
                <td><strong><?= date('M d, Y', strtotime($m['market_date'])) ?></strong></td>
                <td><?= htmlspecialchars($m['location']) ?></td>
                <td>
                  <a href="?edit_market=<?= $m['market_id'] ?>" style="color: var(--primary); margin-right: 15px;" title="Edit"><i class="fas fa-edit"></i></a>
                  <a href="?delete_market=<?= $m['market_id'] ?>" style="color: #ef4444;" onclick="return confirm('Permanent delete this scheduled market?')" title="Delete"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>

</body>
</html>


