<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Get current admin details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'admin'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Handle profile picture upload
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
            $profile_picture = $admin['profile_picture'] ?? '';

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        // Delete old profile picture if exists
                        if ($profile_picture && file_exists($profile_picture)) {
                            unlink($profile_picture);
                        }
                        $profile_picture = $upload_path;
                    } else {
                        $error = 'Failed to upload profile picture.';
                    }
                } else {
                    $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                }
            }

            if (!$error) {
                // Update profile
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, profile_picture = ? WHERE user_id = ? AND role = 'admin'");
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $profile_picture, $user_id);

                if ($stmt->execute()) {
                    $message = 'Profile updated successfully.';
                    // Refresh admin data
                    $admin['full_name'] = $full_name;
                    $admin['email'] = $email;
                    $admin['phone'] = $phone;
                    $admin['profile_picture'] = $profile_picture;
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
        } elseif (!password_verify($current_password, $admin['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND role = 'admin'");
            $stmt->bind_param("si", $new_hash, $user_id);

            if ($stmt->execute()) {
                $message = 'Password changed successfully.';
            } else {
                $error = 'Failed to change password.';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['update_settings'])) {
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $theme = $_POST['theme'] ?? 'light';
        $language = $_POST['language'] ?? 'en';

        // For now, we'll store these in session, but in a real app you'd save to database
        $_SESSION['notifications'] = $notifications;
        $_SESSION['theme'] = $theme;
        $_SESSION['language'] = $language;

        $message = 'Settings updated successfully.';
    }
}

// Get system statistics
$stats = [
    'total_members' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")->fetch_assoc()['count'],
    'active_members' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member' AND status = 'active'")->fetch_assoc()['count'],
    'total_finances' => $conn->query("SELECT COUNT(*) as count FROM finance")->fetch_assoc()['count'],
    'total_revenue' => $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type IN ('contribution', 'sale')")->fetch_assoc()['total'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Profile - Agrilink</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="admin.css" />
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

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .header {
      background: #2a9d8f;
      color: white;
      padding: 15px 30px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }

    .header h1 {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }

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
    }

    .header-nav a:hover {
      color: var(--secondary);
    }

    .main-content {
      flex: 1;
      padding: 30px 20px;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
    }

    .back-link {
      background: linear-gradient(135deg, #2a9d8f, #247b73);
      color: white;
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      text-decoration: none;
      font-size: 0.95rem;
      margin-bottom: 20px;
    }

    .back-link:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(42, 157, 143, 0.4);
      color: white;
    }

    .back-link i {
      font-size: 1rem;
    }

    .page-header {
      background: white;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 30px;
      text-align: center;
    }

    .page-header h2 {
      color: var(--primary);
      margin: 0;
      font-size: 1.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .alerts-container {
      margin-bottom: 25px;
    }

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
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid var(--success);
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border-left: 4px solid var(--danger);
    }

    .alert i {
      font-size: 1.2rem;
    }

    .profile-grid {
      display: grid;
      grid-template-columns: 300px 1fr;
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
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid var(--primary);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .profile-picture-placeholder {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), #247b73);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 3rem;
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
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .upload-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .admin-info h3 {
      color: var(--primary);
      margin-bottom: 5px;
      font-size: 1.3rem;
    }

    .admin-role {
      color: var(--text-muted);
      font-size: 0.9rem;
      margin-bottom: 20px;
    }

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
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      display: block;
    }

    .stat-label {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 5px;
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
      margin-bottom: 20px;
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      color: var(--text);
      margin-bottom: 8px;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 12px 14px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 80px;
    }

    .file-input-wrapper {
      position: relative;
      display: inline-block;
    }

    .file-input {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }

    .file-input-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #f8f9fa;
      border: 2px dashed #dee2e6;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s ease;
      color: var(--text-muted);
    }

    .file-input-label:hover {
      background: #e9ecef;
      border-color: var(--primary);
      color: var(--primary);
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 10px 0;
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
      margin: 0;
    }

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
      text-decoration: none;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(42, 157, 143, 0.3);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    }

    .btn-secondary:hover {
      box-shadow: 0 6px 16px rgba(127, 140, 141, 0.3);
    }

    .btn-danger {
      background: linear-gradient(135deg, var(--danger), #c0392b);
    }

    .btn-danger:hover {
      box-shadow: 0 6px 16px rgba(231, 76, 60, 0.3);
    }

    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
    }

    .footer {
      background: #2a9d8f;
      color: white;
      text-align: center;
      padding: 20px 30px;
      font-size: 0.9rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      margin-top: auto;
      width: 100%;
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }

    @media (max-width: 1024px) {
      .profile-grid {
        grid-template-columns: 1fr;
      }

      .profile-sidebar {
        text-align: center;
      }
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }

      .form-actions {
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>
  <!-- Header -->
  <header class="header">
    <h1><i class="fas fa-user-cog"></i> Admin Profile</h1>
    <nav class="header-nav">
      <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
      <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
      <a href="admin_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
      
<a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="text-decoration:none; margin-right: 15px;">
  <span class="notif-wrapper" style="position:relative; display:inline-flex; align-items:center;">
    <i class="fa-solid fa-bell"></i>
    <?php if ($notifCount > 0): ?>
      <span class="notif-badge" style="position:absolute; top:-8px; right:-10px; background:#ef4444; color:white; font-size:0.65rem; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #2a9d8f;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
    <?php endif; ?>
  </span>
</a>
<a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </header>

  <!-- Main Content -->
  <div class="main-content">
    <a href="admin.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <!-- Page Header -->
    <div class="page-header">
      <h2><i class="fas fa-id-card"></i> Administrator Profile & Settings</h2>
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
          <?php if (!empty($admin['profile_picture']) && file_exists($admin['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($admin['profile_picture']) ?>" alt="Profile Picture" class="profile-picture">
          <?php else: ?>
            <div class="profile-picture-placeholder">
              <i class="fas fa-user"></i>
            </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data" style="display: inline;">
            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;">
            <button type="button" class="upload-btn" onclick="document.getElementById('profile_picture').click();">
              <i class="fas fa-camera"></i>
            </button>
          </form>
        </div>

        <div class="admin-info">
          <h3><?= htmlspecialchars($admin['full_name']) ?></h3>
          <div class="admin-role">System Administrator</div>
        </div>

        <div class="stats-grid">
          <div class="stat-item">
            <span class="stat-value"><?= number_format($stats['total_members']) ?></span>
            <div class="stat-label">Total Members</div>
          </div>
          <div class="stat-item">
            <span class="stat-value">MWK <?= number_format($stats['total_revenue'], 0) ?></span>
            <div class="stat-label">Total Revenue</div>
          </div>
          <div class="stat-item">
            <span class="stat-value"><?= number_format($stats['active_members']) ?></span>
            <div class="stat-label">Active Members</div>
          </div>
          <div class="stat-item">
            <span class="stat-value"><?= number_format($stats['total_finances']) ?></span>
            <div class="stat-label">Transactions</div>
          </div>
        </div>
      </div>

      <!-- Profile Content -->
      <div class="profile-content">
        <!-- Personal Information -->
        <div class="settings-card">
          <h3><i class="fas fa-user-edit"></i> Personal Information</h3>
          <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
              <div class="form-group">
                <label for="full_name">
                  <i class="fas fa-user"></i>
                  Full Name *
                </label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($admin['full_name']) ?>" required>
              </div>

              <div class="form-group">
                <label for="email">
                  <i class="fas fa-envelope"></i>
                  Email Address *
                </label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
              </div>

              <div class="form-group">
                <label for="phone">
                  <i class="fas fa-phone"></i>
                  Phone Number
                </label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="+265 XXX XXX XXX">
              </div>

              <div class="form-group">
                <label for="profile_picture_file">
                  <i class="fas fa-image"></i>
                  Profile Picture
                </label>
                <div class="file-input-wrapper">
                  <input type="file" name="profile_picture" id="profile_picture_file" class="file-input" accept="image/*">
                  <label for="profile_picture_file" class="file-input-label">
                    <i class="fas fa-upload"></i>
                    Choose Image
                  </label>
                </div>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="update_profile" class="btn">
                <i class="fas fa-save"></i> Update Profile
              </button>
            </div>
          </form>
        </div>

        <!-- Security Settings -->
        <div class="settings-card">
          <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
          <form method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label for="current_password">
                  <i class="fas fa-lock"></i>
                  Current Password
                </label>
                <input type="password" id="current_password" name="current_password" required>
              </div>

              <div class="form-group">
                <label for="new_password">
                  <i class="fas fa-key"></i>
                  New Password
                </label>
                <input type="password" id="new_password" name="new_password" minlength="8" required>
              </div>

              <div class="form-group">
                <label for="confirm_password">
                  <i class="fas fa-key"></i>
                  Confirm New Password
                </label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="change_password" class="btn btn-danger">
                <i class="fas fa-key"></i> Change Password
              </button>
            </div>
          </form>
        </div>

        <!-- System Settings -->
        <div class="settings-card">
          <h3><i class="fas fa-cog"></i> System Settings</h3>
          <form method="POST">
            <div class="checkbox-group">
              <input type="checkbox" id="notifications" name="notifications" value="1"
                     <?= (isset($_SESSION['notifications']) && $_SESSION['notifications']) ? 'checked' : '' ?>>
              <label for="notifications">Enable email notifications for important system events</label>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label for="theme">
                  <i class="fas fa-palette"></i>
                  Theme Preference
                </label>
                <select id="theme" name="theme">
                  <option value="light" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] == 'light') ? 'selected' : '' ?>>Light Theme</option>
                  <option value="dark" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] == 'dark') ? 'selected' : '' ?>>Dark Theme</option>
                  <option value="auto" <?= (!isset($_SESSION['theme']) || $_SESSION['theme'] == 'auto') ? 'selected' : '' ?>>Auto (System)</option>
                </select>
              </div>

              <div class="form-group">
                <label for="language">
                  <i class="fas fa-globe"></i>
                  Language
                </label>
                <select id="language" name="language">
                  <option value="en" <?= (isset($_SESSION['language']) && $_SESSION['language'] == 'en') ? 'selected' : '' ?>>English</option>
                  <option value="ny" <?= (isset($_SESSION['language']) && $_SESSION['language'] == 'ny') ? 'selected' : '' ?>>Chichewa</option>
                </select>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="update_settings" class="btn">
                <i class="fas fa-save"></i> Save Settings
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>

  <script>
    // Profile picture preview
    document.getElementById('profile_picture').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          // Auto-submit the form when file is selected
          const form = document.querySelector('form[enctype="multipart/form-data"]');
          if (form) {
            form.submit();
          }
        };
        reader.readAsDataURL(file);
      }
    });

    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
      const newPassword = document.getElementById('new_password').value;
      const confirmPassword = this.value;

      if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
      } else {
        this.setCustomValidity('');
      }
    });

    // File input label update
    document.getElementById('profile_picture_file').addEventListener('change', function(e) {
      const file = e.target.files[0];
      const label = document.querySelector('.file-input-label');

      if (file) {
        label.innerHTML = '<i class="fas fa-check"></i> ' + file.name;
        label.style.color = 'var(--success)';
      } else {
        label.innerHTML = '<i class="fas fa-upload"></i> Choose Image';
        label.style.color = 'var(--text-muted)';
      }
    });

    // Form validation
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('input[required]');
        let isValid = true;

        requiredFields.forEach(field => {
          if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger)';
            isValid = false;
          } else {
            field.style.borderColor = '#e0e0e0';
          }
        });

        if (!isValid) {
          e.preventDefault();
          alert('Please fill in all required fields.');
        }
      });
    });
  </script>
</body>
</html>

