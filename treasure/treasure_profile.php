<?php
session_start();
require '../db.php';
require '../member/notif_count.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'treasurer'])) {
    header("Location: login.php");
    exit;
}

// Get current user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$treasurer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($full_name === '' || $email === '') {
            $error = 'Name and email are required.';
        } else {
            $profile_picture = $treasurer['profile_picture'] ?? '';
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profile_pictures/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $new_name = 'treasurer_' . $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        if ($profile_picture && file_exists($profile_picture)) unlink($profile_picture);
                        $profile_picture = $upload_path;
                    } else { $error = 'Upload failed.'; }
                } else { $error = 'Invalid file type.'; }
            }

            if (!$error) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, profile_picture = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $profile_picture, $user_id);
                if ($stmt->execute()) {
                    $message = 'Profile updated successfully.';
                    $treasurer['full_name'] = $full_name;
                    $treasurer['email'] = $email;
                    $treasurer['phone'] = $phone;
                    $treasurer['profile_picture'] = $profile_picture;
                } else { $error = 'Update failed.'; }
                $stmt->close();
            }
        }
    }
    if (isset($_POST['change_password'])) {
        $curr = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if ($new !== $conf) $error = 'Passwords do not match.';
        elseif (strlen($new) < 8) $error = 'Password too short.';
        elseif (!password_verify($curr, $treasurer['password_hash'])) $error = 'Incorrect current password.';
        else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            if ($stmt->execute()) $message = 'Password changed.';
            else $error = 'Failed to change password.';
            $stmt->close();
        }
    }
}

// Get financial statistics
$stats = [
    'total_members' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")->fetch_assoc()['count'],
    'total_income' => $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type IN ('contribution', 'sale', 'fee')")->fetch_assoc()['total'] ?? 0,
    'total_profits' => $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type = 'profit'")->fetch_assoc()['total'] ?? 0,
    'total_balance' => $conn->query("SELECT SUM(CASE WHEN transaction_type IN ('contribution', 'sale', 'fee') THEN amount ELSE -amount END) as total FROM finance")->fetch_assoc()['total'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Treasurer Profile | Agrilink Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #f4a261;
      --success: #27ae60;
      --danger: #e74c3c;
      --warning: #f39c12;
      --info: #3498db;
      --text: #2c3e50;
      --text-muted: #95a5a6;
      --light: #ecf0f1;
      --dark: #2c3e50;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', sans-serif;
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .header {
      background: var(--primary);
      padding: 1rem 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .logo-area { display: flex; align-items: center; gap: 15px; }
    .logo-img { height: 50px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
    .logo-text { color: white; font-weight: 800; font-size: 1.2rem; text-transform: uppercase; }

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
      padding: 30px 20px;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
    }

    .page-header {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 30px;
      text-align: center;
    }
    .page-header h2 { color: var(--primary); font-size: 1.8rem; display: flex; align-items: center; justify-content: center; gap: 12px; }

    .alert { padding: 16px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-weight: 500; }
    .alert-success { background: #d4edda; color: #155724; border-left: 4px solid var(--success); }
    .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid var(--danger); }

    .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 30px; }
    .profile-sidebar { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08); text-align: center; }
    .profile-picture-container { position: relative; display: inline-block; margin-bottom: 20px; }
    .profile-picture { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary); }
    .profile-picture-placeholder { width: 120px; height: 120px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; border: 4px solid var(--secondary); }
    
    .stats-item { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; text-align: center; }
    .stat-value { font-size: 1.3rem; font-weight: 700; color: var(--primary); display: block; }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); }

    .settings-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08); margin-bottom: 30px; }
    .settings-card h3 { color: var(--primary); margin-bottom: 20px; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
    .form-group label { font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
    .form-group input { padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; }
    .form-group input:focus { outline: none; border-color: var(--primary); }

    .btn { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(42, 157, 143, 0.2); }

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
    @media (max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php include 'glow_bg.php'; ?>
  <?php include 'notif_panel.php'; ?>
  
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
        <a href="treasure.php"><i class="fas fa-home"></i> Home</a>
        <a href="../admin/admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
        <a href="treasure_profit_dist.php"><i class="fa-solid fa-users-gear"></i> Profit Distribution</a>
        <a href="treasure_profile.php" class="active"><i class="fas fa-user-cog"></i> Profile</a>
      <?php endif; ?>
      <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </nav>
  </header>

  <div class="main-content">
    <div class="page-header">
      <h2><i class="fas fa-id-card"></i> Treasurer Profile & Settings</h2>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="profile-grid">
      <div class="profile-sidebar">
        <div class="profile-picture-container">
          <?php if (!empty($treasurer['profile_picture']) && file_exists($treasurer['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($treasurer['profile_picture']) ?>" alt="Profile" class="profile-picture">
          <?php else: ?>
            <div class="profile-picture-placeholder"><i class="fas fa-user-tie"></i></div>
          <?php endif; ?>
        </div>
        <h3 style="color: var(--primary); margin-bottom: 5px;"><?= htmlspecialchars($treasurer['full_name']) ?></h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Treasurer Portal Access</p>
        
        <div class="stats-item">
          <span class="stat-value"><?= number_format($stats['total_members']) ?></span>
          <span class="stat-label">Total Members</span>
        </div>
        <div class="stats-item">
          <span class="stat-value">MWK <?= number_format($stats['total_balance'], 0) ?></span>
          <span class="stat-label">Current Balance</span>
        </div>
      </div>

      <div class="profile-content">
        <div class="settings-card">
          <h3><i class="fas fa-user-edit"></i> Account Information</h3>
          <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
              <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($treasurer['full_name']) ?>" required>
              </div>
              <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($treasurer['email']) ?>" required>
              </div>
              <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($treasurer['phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label><i class="fas fa-image"></i> Change Picture</label>
                <input type="file" name="profile_picture" accept="image/*">
              </div>
            </div>
            <button type="submit" name="update_profile" class="btn" style="margin-top: 20px;">Save Changes</button>
          </form>
        </div>

        <div class="settings-card">
          <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
          <form method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required>
              </div>
              <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
              </div>
            </div>
            <button type="submit" name="change_password" class="btn" style="margin-top: 20px;">Change Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <footer class="footer">
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>
</body>
</html>

