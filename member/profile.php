<?php
session_start();
require 'db.php';
require 'notif_count.php'; // provides $notifCount

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current member details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
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
            // Handle profile picture upload
            $profile_picture = $user['profile_picture'] ?? '';

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../admin/uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'member_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    // Note: upload path needs correct relative storage, but DB should store the full relative path matching how other parts fetch it.
                    // If member dashboard images are pulled like `../uploads/profile_pictures/...`, the DB path should be `uploads/profile_pictures/member_X.jpg`.
                    $db_upload_path = 'uploads/profile_pictures/' . $new_filename;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        // Delete old profile picture if exists
                        if ($profile_picture && file_exists('../admin/' . $profile_picture)) {
                            unlink('../admin/' . $profile_picture);
                        }
                        $profile_picture = $db_upload_path;
                    } else {
                        $error = 'Failed to upload profile picture. Please check permissions.';
                    }
                } else {
                    $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                }
            }

            if (!$error) {
                // Update profile
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, profile_picture = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $profile_picture, $user_id);

                if ($stmt->execute()) {
                    $message = 'Profile updated successfully.';
                    $_SESSION['full_name'] = $full_name;
                    $user['full_name'] = $full_name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['profile_picture'] = $profile_picture;
                } else {
                    $error = 'Failed to update profile.';
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hash, $user_id);

            if ($stmt->execute()) {
                $message = 'Password changed successfully.';
            } else {
                $error = 'Failed to change password.';
            }
            $stmt->close();
        }
    }
}

// Stats gathering
$stats = [
    'hives' => $conn->query("SELECT SUM(hive_count) as c FROM hives WHERE user_id = $user_id")->fetch_assoc()['c'] ?? 0,
    'inspections' => $conn->query("SELECT COUNT(*) as c FROM inspections WHERE user_id = $user_id AND scheduled_date >= CURDATE()")->fetch_assoc()['c'] ?? 0,
    'balance' => $conn->query("SELECT amount FROM finance WHERE user_id = $user_id ORDER BY date DESC LIMIT 1")->fetch_assoc()['amount'] ?? 0,
    'fee' => $user['fee'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Member Profile | Agrilink</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
      
      --green: #2a9d8f;
      --orange: #f4a261;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Fixed Member Navbar Matching member.php */
    .header {
      background: var(--green);
      color: white;
      padding: 1rem 2.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
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
    .navbar ul {
      display: flex;
      list-style: none;
      gap: 2rem;
      align-items: center;
    }
    .navbar a {
      color: white;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.98rem;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .navbar a:hover, .navbar a:focus, .navbar a.active {
      color: var(--orange);
      transform: translateY(-1px);
    }

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
      padding: 30px 20px;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
    }

    .page-header {
      background: white;
      padding: 25px 35px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 25px;
    }
    .page-header h2 {
      color: var(--primary);
      margin: 0;
      font-size: 1.8rem;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alerts-container { margin-bottom: 25px; }
    .alert {
      padding: 16px 20px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
      animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .alert-success { background: #d4edda; color: #155724; border-left: 4px solid var(--success); }
    .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid var(--danger); }
    .alert i { font-size: 1.2rem; }

    .profile-grid {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 30px;
      margin-bottom: 30px;
    }

    .profile-sidebar {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      text-align: center;
    }

    .profile-picture-container {
      position: relative;
      display: inline-block;
      margin-bottom: 20px;
    }
    .profile-picture {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid var(--primary);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .profile-picture-placeholder {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), #247b73);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 3.5rem;
      border: 4px solid var(--secondary);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .upload-btn {
      position: absolute;
      bottom: 0;
      right: 0;
      background: var(--secondary);
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      font-size: 1.1rem;
    }
    .upload-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    
    #file-name-display {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 5px;
      display: block;
    }

    .admin-info h3 { color: var(--primary); margin-bottom: 5px; font-size: 1.4rem; font-weight: 700; }
    .admin-role { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 25px; font-weight: 500;}

    .stats-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }
    .stat-item {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      border: 1px solid var(--border);
    }
    .stat-value {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--primary);
      display: block;
    }
    .stat-label {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 5px;
      font-weight: 500;
    }

    .profile-content {
      display: grid;
      gap: 30px;
    }

    .settings-card {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }
    .settings-card h3 {
      color: var(--primary);
      margin-bottom: 25px;
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 15px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    .form-group { display: flex; flex-direction: column; }
    .form-group label {
      font-weight: 600;
      color: var(--text);
      margin-bottom: 8px;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .form-group input {
      padding: 12px 14px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    .form-group input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }
    .form-group input[readonly] { background: #f8fafc; color: #64748b; cursor: not-allowed; }

    .btn {
      background: linear-gradient(135deg, var(--primary), #247b73);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.95rem;
    }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(42, 157, 143, 0.3); }

    .btn-danger { background: linear-gradient(135deg, var(--danger), #c0392b); }
    .btn-danger:hover { box-shadow: 0 6px 16px rgba(231, 76, 60, 0.3); }

    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
    }

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
      .profile-grid { grid-template-columns: 1fr; }
      .form-grid { grid-template-columns: 1fr; }
      .header { flex-direction: column; gap: 1.2rem; padding: 1.2rem 1.5rem; }
      .navbar ul { flex-wrap: wrap; justify-content: center; gap: 1.2rem; }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <!-- Single Standard Member Header -->
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
    <nav class="navbar">
      <ul>
        <?php if ($role === 'admin'): ?>
          <li><a href="../admin/admin.php">Home</a></li>
          <li><a href="../admin/admin_stakeholder.php">Stakeholders</a></li>
          <li><a href="../admin/admin_finances.php">Finances</a></li>
          <li><a href="../admin/admin_reports.php">Reports</a></li>
        <?php elseif ($role === 'treasurer'): ?>
          <li><a href="../treasure/treasure.php">Home</a></li>
          <li><a href="../treasure/treasure_finances.php">Finances</a></li>
        <?php else: ?>
          <li><a href="member.php">Home</a></li>
          <li><a href="Hives.php">Hive</a></li>
          <li><a href="member-inspection.php">Inspections</a></li>
          <li><a href="finances.php">Finances</a></li>
          <li><a href="view_suppliers.php">Suppliers</a></li>
          <li><a href="training.php">Training Hub</a></li>
          <li><a href="contact_cooperative.php">Contact</a></li>
        <?php endif; ?>
        <li><a href="profile.php" class="active" title="Profile"><i class="fa-solid fa-user"></i></a></li>
        <li>
          <a href="#" onclick="toggleNotifPanel(event)" title="Notifications">
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

  <!-- Main Content -->
  <div class="main-content">
    <div class="page-header">
      <h2><i class="fas fa-id-card"></i> Member Profile & Settings</h2>
    </div>

    <!-- Alerts -->
    <?php if ($message || $error): ?>
      <div class="alerts-container">
        <?php if ($message): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($message) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Profile Layout -->
    <div class="profile-grid">
      <!-- Profile Sidebar -->
      <div class="profile-sidebar">
        <div class="profile-picture-container">
          <?php if (!empty($user['profile_picture']) && file_exists('../admin/' . $user['profile_picture'])): ?>
            <img src="../admin/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-picture" id="preview_thumb">
          <?php elseif(!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
            <!-- Legacy fallback if image path was stored relatively without admin dir -->
            <img src="../<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-picture" id="preview_thumb">
          <?php else: ?>
            <div class="profile-picture-placeholder">
              <i class="fas fa-user"></i>
            </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data" id="quickAvatarForm">
            <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif" style="display: none;" onchange="submitAvatar()">
            <!-- This is passed to handle update_profile logic using quick upload -->
            <input type="hidden" name="update_profile" value="1">
            <input type="hidden" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($user['email']) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">

            <button type="button" class="upload-btn" onclick="document.getElementById('profile_picture').click();" title="Change Avatar">
              <i class="fas fa-camera"></i>
            </button>
          </form>
        </div>

        <div class="admin-info">
          <h3><?= htmlspecialchars($user['full_name'] ?? 'Member') ?></h3>
          <div class="admin-role">Active Member</div>
        </div>

        <div class="stats-grid">
          <div class="stat-item">
            <span class="stat-value"><?= number_format((float)($stats['hives'] ?? 0)) ?></span>
            <div class="stat-label">Hives Owned</div>
          </div>
          <div class="stat-item">
            <span class="stat-value"><?= number_format((float)($stats['inspections'] ?? 0)) ?></span>
            <div class="stat-label">Pending Inspections</div>
          </div>
          <div class="stat-item">
            <span class="stat-value">MWK <?= number_format((float)($stats['balance'] ?? 0), 0) ?></span>
            <div class="stat-label">Wallet Balance</div>
          </div>
          <div class="stat-item">
            <span class="stat-value">MWK <?= number_format((float)($stats['fee'] ?? 0), 0) ?></span>
            <div class="stat-label">Fee Paid</div>
          </div>
        </div>
      </div>

      <!-- Profile Content Forms -->
      <div class="profile-content">
        <!-- Personal Information -->
        <div class="settings-card">
          <h3><i class="fas fa-user-edit"></i> Personal Details</h3>
          <form method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label for="full_name"><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+265 XXX XXX XXX">
              </div>

              <div class="form-group">
                <label><i class="fas fa-calendar"></i> Date Joined</label>
                <input type="text" value="<?= htmlspecialchars($user['date_joined'] ?? 'N/A') ?>" readonly>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="update_profile" class="btn">
                <i class="fas fa-save"></i> Save Changes
              </button>
            </div>
          </form>
        </div>

        <!-- Security Settings -->
        <div class="settings-card">
          <h3><i class="fas fa-shield-alt"></i> Change Password</h3>
          <form method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label for="current_password"><i class="fas fa-lock"></i> Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
              </div>

              <div class="form-group" style="opacity: 0; pointer-events: none; height: 0; margin: 0; padding: 0;"><!-- Layout Spacer --></div>

              <div class="form-group">
                <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" required>
              </div>

              <div class="form-group">
                <label for="confirm_password"><i class="fas fa-key"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="change_password" class="btn btn-danger">
                <i class="fas fa-key"></i> Update Security
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  <footer>
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>

  <script>
    function submitAvatar() {
        if(document.getElementById('profile_picture').files.length > 0) {
            document.getElementById('quickAvatarForm').submit();
        }
    }
  </script>
</body>
</html>
