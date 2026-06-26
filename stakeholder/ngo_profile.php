<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'external' || $_SESSION['stakeholder_type'] != 'ngo') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch fresh user data early so we have the profile picture for deletions
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ngo_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    if (empty($full_name) || empty($email)) {
        $error = "Name and email are required fields.";
    } else {
        $profile_picture = $ngo_info['profile_picture'] ?? '';

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'ngo_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
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
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, profile_picture = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $full_name, $phone, $email, $profile_picture, $user_id);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $message = "Profile updated successfully.";
                $ngo_info['full_name'] = $full_name;
                $ngo_info['phone'] = $phone;
                $ngo_info['email'] = $email;
                $ngo_info['profile_picture'] = $profile_picture;
            } else {
                $error = "Failed to update profile. Email might already be in use.";
            }
            $stmt->close();
        }
    }
}

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if (!password_verify($current_pass, $ngo_info['password_hash'])) {
        $error = "Current password check failed. Please try again.";
    } elseif (strlen($new_pass) < 6) {
        $error = "The new password must be at least 6 characters long.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "The new passwords you typed do not match.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);
        if ($stmt->execute()) {
            $message = "Password changed securely.";
        } else {
            $error = "Database error while updating your password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NGO Profile - Agrilink</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
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
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Navbar Standardized */
    .navbar-standard {
      background: #2a9d8f !important;
      color: white;
      padding: 0;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      position: sticky;
      top: 0;
      z-index: 1000;
      width: 100%;
    }
    .nav-inner {
      max-width: 1380px;
      margin: 0 auto;
      padding: 0 30px;
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .logo-std {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      text-decoration: none;
    }
    .logo-icon-wrap {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.18);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.25);
    }
    .logo-icon-wrap i { color: #fff; font-size: 1.05rem; }
    .logo-text {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: -0.3px;
    }
    .logo-text span { color: var(--secondary); }

    .navbar-nav-std { display: flex; align-items: center; gap: 20px; }
    .navbar-nav-std a {
      color: white;
      text-decoration: none;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .navbar-nav-std a:hover { color: var(--secondary); }

    /* CONTENT WRAPPERS */
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
    .back-link:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(42, 157, 143, 0.4); color: white; }

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

    /* ALERTS */
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
    
    /* GRID LAYOUT */
    .profile-grid {
      display: grid;
      grid-template-columns: 300px 1fr;
      gap: 30px;
      margin-bottom: 30px;
    }

    @media (max-width: 1024px) {
      .profile-grid { grid-template-columns: 1fr; }
    }

    /* SIDEBAR */
    .profile-sidebar {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      text-align: center;
      align-self: start;
    }
    .profile-picture-container { position: relative; display: inline-block; margin-bottom: 20px; }
    
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

    .ngo-info h3 { color: var(--primary); margin-bottom: 5px; font-size: 1.3rem; }
    .ngo-role { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px; }

    /* NOTIFICATION BOX */
    .notification-box {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      border: 1px solid #eee;
    }
    .notification-box i { color: var(--secondary); font-size: 1.5rem; margin-bottom: 10px; display: block; }
    .notification-box p { font-size: 0.85rem; color: var(--text-muted); }

    /* FORMS & SETTINGS CARDS */
    .profile-content { display: grid; gap: 30px; }
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
    @media (max-width: 768px) {
      .form-grid { grid-template-columns: 1fr; }
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
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    .form-group input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    .file-input-wrapper { position: relative; display: inline-block; }
    .file-input { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
    .file-input-label {
      display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px;
      background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 6px;
      cursor: pointer; transition: all 0.3s ease; color: var(--text-muted);
    }
    .file-input-label:hover { background: #e9ecef; border-color: var(--primary); color: var(--primary); }

    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
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
    .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(42, 157, 143, 0.3); }

    .btn-danger { background: linear-gradient(135deg, var(--danger), #c0392b); }
    .btn-danger:hover { box-shadow: 0 6px 16px rgba(231, 76, 60, 0.3); }

    /* FOOTER */
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
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>

  <!-- Navbar -->
  <nav class="navbar-standard">
    <div class="nav-inner">
      <a href="../index.php" class="logo-std">
        <div class="logo-icon-wrap">
          <i class="fas fa-bee"></i>
        </div>
        <div class="logo-text">Agri<span>link</span></div>
      </a>
      <nav class="navbar-nav-std">
        <a href="ngo_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="ngo_profile.php" style="color:var(--secondary);"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="main-content">
    <a href="ngo_dashboard.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <!-- Page Header -->
    <div class="page-header">
      <h2><i class="fas fa-id-card"></i> NGO Profile & Settings</h2>
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
          <?php if (!empty($ngo_info['profile_picture']) && file_exists($ngo_info['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($ngo_info['profile_picture']) ?>" alt="Profile Picture" class="profile-picture">
          <?php else: ?>
            <div class="profile-picture-placeholder">
              <i class="fas fa-user"></i>
            </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data" style="display: inline;">
            <input type="file" name="profile_picture" id="profile_picture_quick" accept="image/*" style="display: none;" onchange="this.form.submit()">
            <input type="hidden" name="update_profile" value="1">
            <input type="hidden" name="full_name" value="<?= htmlspecialchars($ngo_info['full_name']) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($ngo_info['email']) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars($ngo_info['phone'] ?? '') ?>">
            <button type="button" class="upload-btn" onclick="document.getElementById('profile_picture_quick').click();">
              <i class="fas fa-camera"></i>
            </button>
          </form>
        </div>

        <div class="ngo-info">
          <h3><?= htmlspecialchars($ngo_info['full_name']) ?></h3>
          <div class="ngo-role">Verified NGO Partner</div>
        </div>

        <div class="notification-box">
          <i class="fas fa-shield-alt"></i>
          <p>Your profile and personal information is kept secure inside the Agrilink database.</p>
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
                <label for="full_name"><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($ngo_info['full_name']) ?>" required>
              </div>

              <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($ngo_info['email']) ?>" required>
              </div>

              <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($ngo_info['phone'] ?? '') ?>" placeholder="+265 XXX XXX XXX">
              </div>

              <div class="form-group">
                <label for="profile_picture_file"><i class="fas fa-image"></i> Profile Picture</label>
                <div class="file-input-wrapper">
                  <input type="file" name="profile_picture" id="profile_picture_file" class="file-input" accept="image/*">
                  <label for="profile_picture_file" class="file-input-label">
                    <i class="fas fa-upload"></i> Choose Image
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
          <h3><i class="fas fa-lock"></i> Security Settings</h3>
          <form method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
              </div>

              <div class="form-group">
                <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                <input type="password" id="new_password" name="new_password" minlength="6" required>
              </div>

              <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="change_password" class="btn btn-danger">
                <i class="fas fa-key"></i> Change Password
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

</body>
</html>

