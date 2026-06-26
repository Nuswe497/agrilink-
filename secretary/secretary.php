<?php
session_start();
require 'db.php';
require_once '../mailer_helper.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Send Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    if (!empty($title) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, 'member')");
        $stmt->bind_param("ss", $title, $message);
        if ($stmt->execute()) {
            $success_msg = "Announcement posted successfully!";
            // Send email to all active members
            $mailResult = sendAnnouncementToAllMembers($conn, $title, $message);
            if ($mailResult['sent'] > 0) {
                $success_msg .= " Emails sent to {$mailResult['sent']} member(s).";
            }
            if ($mailResult['failed'] > 0) {
                $success_msg .= " ({$mailResult['failed']} email(s) failed to send.)";
            }
        } else {
            $error_msg = "Failed to send announcement.";
        }
        $stmt->close();
    } else {
        $error_msg = "Please provide both a title and message.";
    }
}

// Handle Approve Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_member'])) {
    $member_id = (int)$_POST['member_id'];
    if ($member_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $member_id);
        if ($stmt->execute()) {
            $success_msg = "Member approved successfully!";
        } else {
            $error_msg = "Failed to approve member.";
        }
        $stmt->close();
    }
}

// Handle Record Inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_inspection'])) {
    $inspection_id = (int)$_POST['inspection_id'];
    $actual_date = trim($_POST['actual_date']);
    $health_status = trim($_POST['health_status']);
    $findings = trim($_POST['findings']);
    $notes = trim($_POST['notes']);
    $status = trim($_POST['status']);
    
    if ($inspection_id > 0 && !empty($actual_date)) {
        $stmt = $conn->prepare("UPDATE inspections SET actual_date = ?, health_status = ?, findings = ?, notes = ?, status = ? WHERE inspection_id = ?");
        $stmt->bind_param("sssssi", $actual_date, $health_status, $findings, $notes, $status, $inspection_id);
        if ($stmt->execute()) {
            $success_msg = "Inspection recorded successfully!";
        } else {
            $error_msg = "Failed to record inspection.";
        }
        $stmt->close();
    } else {
        $error_msg = "Please provide valid inspection data.";
    }
}

// Handle Schedule Inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_inspection'])) {
    $ins_user_id = (int)$_POST['user_id'];
    $scheduled_date = trim($_POST['scheduled_date']);
    $hive_id = trim($_POST['hive_id']);
    
    if ($ins_user_id > 0 && !empty($scheduled_date)) {
        $stmt = $conn->prepare("INSERT INTO inspections (user_id, scheduled_date, hive_id, status) VALUES (?, ?, ?, 'Pending')");
        $stmt->bind_param("iss", $ins_user_id, $scheduled_date, $hive_id);
        if ($stmt->execute()) {
            $success_msg = "Inspection scheduled successfully!";

            // Record dashboard notification for the member
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
            if (sendInspectionEmail($conn, $ins_user_id, $scheduled_date, $hive_id, $emailErr)) {
                $success_msg .= " A notification email has been sent to the member.";
            } else {
                $success_msg .= " (Note: Email notification could not be sent — {$emailErr})";
            }
        } else {
            $error_msg = "Failed to schedule inspection.";
        }
        $stmt->close();
    } else {
        $error_msg = "Please provide member and scheduled date.";
    }
}

// Handle Upload Training Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_training'])) {
    $title = trim($_POST['title']);
    
    if (!empty($title) && isset($_FILES['training_file']) && $_FILES['training_file']['error'] == 0) {
        $target_dir = "../uploads/training/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES["training_file"]["name"]));
        $file_name = time() . "_" . $file_name;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["training_file"]["tmp_name"], $target_file)) {
            $content_path = "uploads/training/" . $file_name;
            $stmt = $conn->prepare("INSERT INTO training_materials (title, content, uploaded_by) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $title, $content_path, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $success_msg = "Training material uploaded successfully!";
            } else {
                $error_msg = "Failed to save material to database.";
            }
            $stmt->close();
        } else {
            $error_msg = "Failed to upload file.";
        }
    } else {
        $error_msg = "Please provide a title and select a valid file.";
    }
}

// Fetch all active members for scheduling
$all_members = [];
$res = $conn->query("SELECT user_id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $all_members[] = $row;
    }
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$secretaryInfo = $result->fetch_assoc();
$stmt->close();

$full_name = $secretaryInfo['full_name'] ?? 'Secretary';

// Fetch stats specific to Secretary duties
$stats = [
    'pending_registrations' => 0,
    'upcoming_inspections' => 0,
    'training_materials' => 0,
];

// Pending members stat
$res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 'pending' AND role = 'member'");
if ($res && $row = $res->fetch_assoc()) {
    $stats['pending_registrations'] = $row['c'];
}

// Upcoming inspections stat
$res = $conn->query("SELECT COUNT(*) AS c FROM inspections WHERE status = 'Pending'");
if ($res && $row = $res->fetch_assoc()) {
    $stats['upcoming_inspections'] = $row['c'];
}

// Training materials count
$res = $conn->query("SELECT COUNT(*) AS c FROM training_materials");
if ($res && $row = $res->fetch_assoc()) {
    $stats['training_materials'] = $row['c'];
}

// Fetch recent upcoming inspections
$recent_inspections = [];
$res = $conn->query("SELECT i.inspection_id, i.scheduled_date, i.status, u.full_name as member_name 
                     FROM inspections i 
                     LEFT JOIN users u ON i.user_id = u.user_id 
                     WHERE i.status = 'Pending' 
                     ORDER BY i.scheduled_date ASC LIMIT 5");
if ($res) {
    $recent_inspections = $res->fetch_all(MYSQLI_ASSOC);
}

// Fetch pending members for review
$pending_members = [];
$res = $conn->query("SELECT user_id, full_name, email, date_joined FROM users WHERE status = 'pending' AND role = 'member' ORDER BY date_joined DESC LIMIT 10");
if ($res) {
    while($row = $res->fetch_assoc()){
         $pending_members[] = $row;
    }
}
require_once '../dynamic_markets.php';
$upcomingMarkets = getUpcomingMarkets($conn, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Secretary Desk | Agrilink</title>
  <meta name="robots" content="noindex, nofollow">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../theme.css">
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #f4a261;
      --danger: #e74c3c;
      --success: #27ae60;
      --warning: #f39c12;
      --info: #3498db;
      --light: #ecf0f1;
      --dark: #2c3e50;
      --text: #2c3e50;
      --text-muted: #95a5a6;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Outfit', sans-serif;
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      position: relative;
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

    .main-content { flex: 1; padding: 30px 20px; }
    .dashboard-container { max-width: 1400px; margin: 0 auto; }

    .page-header {
      background: white;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .page-header h1 {
      font-size: 2rem;
      color: var(--primary);
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .page-header p { color: var(--text-muted); margin-top: 5px; font-size: 0.95rem; }

    /* Alert styles */
    .alert {
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 25px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .alert-success { background: #d4edda; color: #155724; border-left: 4px solid var(--success); }
    .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid var(--danger); }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      background: rgba(42, 157, 143, 0.1);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
    }

    .stat-details h3 { font-size: 1.8rem; color: var(--text); margin-bottom: 5px; }
    .stat-details p { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; }

    .card-warning .stat-icon { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
    .card-success .stat-icon { background: rgba(39, 174, 96, 0.1); color: var(--success); }

    .dashboard-sections {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
    }

    .section-card {
      background: white;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 30px;
    }

    .section-title {
      font-size: 1.3rem;
      color: var(--primary);
      font-weight: 600;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    thead { background: linear-gradient(135deg, #f8f9fa, #f0f2f5); border-bottom: 2px solid #e0e0e0; }
    th { padding: 16px; text-align: left; font-weight: 600; color: var(--text); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;}
    td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 0.95rem; }
    tbody tr { transition: all 0.2s ease; }
    tbody tr:hover { background: #f8fafb; box-shadow: inset 0 0 8px rgba(0, 0, 0, 0.03); }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 0.95rem;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      width: 100%;
      justify-content: center;
      margin-bottom: 15px;
    }

    .btn-primary { background: linear-gradient(135deg, var(--primary), #247b73); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(42, 157, 143, 0.3); color: white;}

    .btn-secondary { background: var(--secondary); color: white; }
    .btn-secondary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(244, 162, 97, 0.3); color: white;}

    .footer {
      background: #2a9d8f;
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid #f4a261;
      font-weight: 500;
      width: 100%;
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
    }

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      z-index: 999;
      justify-content: center;
      align-items: center;
    }
    .modal {
      background: white;
      padding: 30px;
      border-radius: 12px;
      width: 500px;
      max-width: 90%;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .modal-header h2 {
      font-size: 1.3rem;
      color: var(--primary);
    }
    .modal-close {
      cursor: pointer;
      font-size: 1.5rem;
      color: var(--text-muted);
      border: none;
      background: none;
    }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.95rem; }
    .form-group input, .form-group textarea { 
      width: 100%; 
      padding: 10px; 
      border: 1px solid #ddd; 
      border-radius: 6px; 
      font-family: inherit;
    }
    
    @media (max-width: 1024px) {
      .dashboard-sections { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>

  <!-- System Header -->
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
    <nav class="header-nav">
      <a href="secretary.php" class="active"><i class="fas fa-home"></i> Home</a>
      <a href="../admin/admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
      <a href="../admin/admin_markets.php"><i class="fas fa-store"></i> Markets</a>
      <a href="secretary_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
      <a href="../logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </nav>
  </header>

  <!-- Main Content -->
  <div class="main-content">
    <div class="dashboard-container">
      
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1><i class="fas fa-clipboard-list"></i> Secretary Desk</h1>
          <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>. Here is your overview for today.</p>
        </div>
        <div style="text-align: right;">
          <span style="color: var(--text-muted); font-weight: 500;">
            <i class="far fa-calendar-alt"></i> <?php echo date("l, d F Y"); ?>
          </span>
        </div>
      </div>

      <!-- Alerts -->
      <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
           <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error">
           <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
      <?php endif; ?>

      <!-- Quick Stats -->
      <div class="stats-grid">
        <div class="stat-card card-warning">
          <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
          <div class="stat-details">
            <h3><?php echo (int)$stats['pending_registrations']; ?></h3>
            <p>Pending Members</p>
          </div>
        </div>
        
        <div class="stat-card card-success">
          <div class="stat-icon"><i class="fas fa-search-location"></i></div>
          <div class="stat-details">
            <h3><?php echo (int)$stats['upcoming_inspections']; ?></h3>
            <p>Upcoming Inspections</p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-book"></i></div>
          <div class="stat-details">
            <h3><?php echo (int)$stats['training_materials']; ?></h3>
            <p>Training Resources</p>
          </div>
        </div>
      </div>

      <div class="dashboard-sections">
        
        <!-- Left Column: Upcoming Work -->
        <div class="section-card" style="display: flex; flex-direction: column;">
          <div class="section-title">
            <i class="fas fa-search"></i> Upcoming Hive Inspections
          </div>
          <?php if (empty($recent_inspections)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px;">
              <i class="fas fa-check-circle" style="font-size: 2rem; opacity: 0.3; display:block; margin-bottom: 10px;"></i>
              No upcoming inspections at the moment.
            </p>
          <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Member</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($recent_inspections as $insp): ?>
              <tr>
                <td><?php echo htmlspecialchars($insp['scheduled_date']); ?></td>
                <td><?php echo htmlspecialchars($insp['member_name'] ?? 'Unknown'); ?></td>
                <td>
                  <span style="display:inline-block; padding: 4px 10px; background: rgba(243, 156, 18, 0.1); color: var(--warning); border-radius: 20px; font-size: 0.85rem; font-weight: bold;">
                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($insp['status']); ?>
                  </span>
                </td>
                <td>
                  <button type="button" onclick="openRecordModal(<?php echo $insp['inspection_id']; ?>)" style="padding: 6px 12px; background: var(--info); color: white; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fas fa-pen"></i> Record
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
        
        <!-- Right Column: Quick Links -->
        <div class="section-card">
          <div class="section-title">
            <i class="fas fa-bolt"></i> Quick Actions
          </div>
          
          <button type="button" onclick="openScheduleModal()" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Schedule Inspection</button>
          <button type="button" onclick="openUploadModal()" class="btn btn-primary"><i class="fas fa-file-upload"></i> Upload Training Material</button>
          
          <button type="button" class="btn btn-secondary" onclick="openAnnouncementModal()"><i class="fas fa-bullhorn"></i> Send Announcement</button>
          
          <a href="#pending-members-section" class="btn btn-secondary"><i class="fas fa-users"></i> Review Pending Members</a>
          
          <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
            <h3 style="font-size: 1rem; margin-bottom: 15px; color: var(--primary);"><i class="fas fa-store"></i> Upcoming Markets</h3>
            <?php if (empty($upcomingMarkets)): ?>
              <p style="font-size: 0.85rem; color: var(--text-muted);">No upcoming markets.</p>
            <?php else: ?>
              <ul style="list-style: none; padding: 0;">
                <?php foreach ($upcomingMarkets as $m): ?>
                <li style="font-size: 0.85rem; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee;">
                  <strong style="display: block;"><?= date('M d, Y', strtotime($m['market_date'])) ?></strong>
                  <span style="color: var(--primary);"><?= htmlspecialchars($m['location']) ?></span>
                </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pending Members Section (Full Width) -->
        <div class="section-card" id="pending-members-section" style="grid-column: 1 / -1;">
          <div class="section-title">
            <i class="fas fa-user-plus"></i> Pending Member Registrations
          </div>
          <?php if (empty($pending_members)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px;">
              <i class="fas fa-check-circle" style="font-size: 2rem; opacity: 0.3; display:block; margin-bottom: 10px;"></i>
              No pending registrations currently require review.
            </p>
          <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Date Applied</th>
                <th>Name</th>
                <th>Email</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($pending_members as $pm): ?>
              <tr>
                <td><?php echo htmlspecialchars($pm['date_joined']); ?></td>
                <td><?php echo htmlspecialchars($pm['full_name']); ?></td>
                <td><?php echo htmlspecialchars($pm['email']); ?></td>
                <td>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to approve this member?');">
                    <input type="hidden" name="member_id" value="<?php echo $pm['user_id']; ?>">
                    <button type="submit" name="approve_member" style="padding: 6px 12px; background: var(--success); color: white; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-weight: bold;">
                      <i class="fas fa-check"></i> Approve
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <footer class="footer">
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>

  <!-- Announcement Modal -->
  <div id="announcementModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h2><i class="fas fa-bullhorn"></i> Send Announcement</h2>
        <button class="modal-close" onclick="closeAnnouncementModal()">&times;</button>
      </div>
      <form method="POST">
        <div class="form-group">
          <label for="title">Announcement Title</label>
          <input type="text" id="title" name="title" required placeholder="Enter an eye-catching title">
        </div>
        <div class="form-group">
          <label for="message">Message Body</label>
          <textarea id="message" name="message" rows="5" required placeholder="Write the full announcement here..."></textarea>
        </div>
        <button type="submit" name="send_announcement" class="btn btn-primary" style="margin-bottom: 0;">
          <i class="fas fa-paper-plane"></i> Publish Announcement
        </button>
      </form>
    </div>
  </div>

  <!-- Schedule Inspection Modal -->
  <div id="scheduleModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h2><i class="fas fa-calendar-plus"></i> Schedule Inspection</h2>
        <button class="modal-close" onclick="closeScheduleModal()">&times;</button>
      </div>
      <form method="POST">
        <div class="form-group">
          <label for="user_id">Select Member</label>
          <select id="user_id" name="user_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            <option value="">-- Choose Member --</option>
            <?php foreach($all_members as $m): ?>
              <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?> (<?php echo htmlspecialchars($m['role']); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="scheduled_date">Date</label>
          <input type="date" id="scheduled_date" name="scheduled_date" required min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label for="hive_id">Hive ID (optional)</label>
          <input type="text" id="hive_id" name="hive_id" placeholder="e.g. Hive-001">
        </div>
        <button type="submit" name="schedule_inspection" class="btn btn-primary" style="margin-bottom: 0;">
          <i class="fas fa-save"></i> Schedule
        </button>
      </form>
    </div>
  </div>

  <!-- Upload Training Material Modal -->
  <div id="uploadModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h2><i class="fas fa-file-upload"></i> Upload Material</h2>
        <button class="modal-close" onclick="closeUploadModal()">&times;</button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label for="training_title">Title</label>
          <input type="text" id="training_title" name="title" required placeholder="Material title">
        </div>
        <div class="form-group">
          <label for="training_file">File</label>
          <input type="file" id="training_file" name="training_file" required accept=".pdf,.doc,.docx,.ppt,.pptx">
        </div>
        <button type="submit" name="upload_training" class="btn btn-primary" style="margin-bottom: 0;">
          <i class="fas fa-upload"></i> Upload
        </button>
      </form>
    </div>
  </div>

  <!-- Record Inspection Modal -->
  <div id="recordModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h2><i class="fas fa-clipboard-check"></i> Record Inspection</h2>
        <button class="modal-close" onclick="closeRecordModal()">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" id="record_inspection_id" name="inspection_id">
        <div class="form-group">
          <label for="actual_date">Actual Date</label>
          <input type="date" id="actual_date" name="actual_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label for="health_status">Health Status</label>
          <input type="text" id="health_status" name="health_status" placeholder="e.g. Healthy, Weak, Requires attention" required>
        </div>
        <div class="form-group">
          <label for="findings">Findings</label>
          <textarea id="findings" name="findings" rows="3" placeholder="Detailed findings"></textarea>
        </div>
        <div class="form-group">
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" rows="2" placeholder="Actionable notes"></textarea>
        </div>
        <div class="form-group">
          <label for="status">Outcome Status</label>
          <select id="status" name="status" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            <option value="Completed">Completed - Satisfactory</option>
            <option value="Issue Found">Completed - Issue Found</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        <button type="submit" name="record_inspection" class="btn btn-primary" style="margin-bottom: 0;">
          <i class="fas fa-check"></i> Save Record
        </button>
      </form>
    </div>
  </div>

  <script>
    function openAnnouncementModal() { document.getElementById('announcementModal').style.display = 'flex'; }
    function closeAnnouncementModal() { document.getElementById('announcementModal').style.display = 'none'; }

    function openScheduleModal() { document.getElementById('scheduleModal').style.display = 'flex'; }
    function closeScheduleModal() { document.getElementById('scheduleModal').style.display = 'none'; }

    function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
    function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }

    function openRecordModal(id) { 
      document.getElementById('record_inspection_id').value = id;
      document.getElementById('recordModal').style.display = 'flex'; 
    }
    function closeRecordModal() { document.getElementById('recordModal').style.display = 'none'; }

    window.onclick = function(event) {
      if (typeof event.target.className === 'string' && event.target.className.includes('modal-overlay')) {
        event.target.style.display = 'none';
      }
    }
  </script>

</body>
</html>

