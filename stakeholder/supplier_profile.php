<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'external' || $_SESSION['stakeholder_type'] != 'supplier') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch fresh user data early
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    if (empty($full_name) || empty($email)) {
        $error = "Name and email are required fields.";
    } else {
        $profile_picture = $supplier_info['profile_picture'] ?? '';

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'supplier_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    if ($profile_picture && file_exists($profile_picture)) {
                        unlink($profile_picture);
                    }
                    $profile_picture = 'uploads/profile_pictures/' . $new_filename;
                } else {
                    $error = 'Failed to upload profile picture.';
                }
            } else {
                $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            }
        }

        if (!$error) {
            $payout_phone = trim($_POST['payout_phone'] ?? '');
            $payout_operator = $_POST['payout_operator'] ?? 'airtel';
            $payout_method = $_POST['payout_method'] ?? 'mobile_money';

            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, profile_picture = ?, payout_phone = ?, payout_operator = ?, payout_method = ? WHERE user_id = ?");
            $stmt->bind_param("sssssssi", $full_name, $phone, $email, $profile_picture, $payout_phone, $payout_operator, $payout_method, $user_id);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $message = "Profile updated successfully.";
                $supplier_info['full_name'] = $full_name;
                $supplier_info['phone'] = $phone;
                $supplier_info['email'] = $email;
                $supplier_info['profile_picture'] = $profile_picture;
                $supplier_info['payout_phone'] = $payout_phone;
                $supplier_info['payout_operator'] = $payout_operator;
                $supplier_info['payout_method'] = $payout_method;
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
    
    if (!password_verify($current_pass, $supplier_info['password_hash'])) {
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

// Stats for Supplier
$total_items = $conn->query("SELECT COUNT(*) as c FROM supplier_stock WHERE supplier_id = $user_id")->fetch_assoc()['c'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total_price) as r FROM purchases WHERE supplier_id = $user_id AND status = 'completed'")->fetch_assoc()['r'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Supplier Profile | Agrilink</title>
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
      background-color: #f8fafc;
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
    .logo-area { display: flex; align-items: center; gap: 15px; text-decoration: none; }
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
    .profile-picture-placeholder { width: 120px; height: 120px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; border: 4px solid var(--secondary); font-weight: 800; }
    
    .stats-item { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; text-align: center; }
    .stat-value { font-size: 1.3rem; font-weight: 700; color: var(--primary); display: block; }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); }

    .settings-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08); margin-bottom: 30px; }
    .settings-card h3 { color: var(--primary); margin-bottom: 20px; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
    .form-group label { font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; color: var(--dark); }
    .form-group input, .form-group select { padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; font-size: 1rem; transition: border-color 0.3s; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); }

    .btn { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(42, 157, 143, 0.2); background: #21867a; }

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
  
  <header class="header">
    <a href="../index.php" class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </a>
    <nav class="header-nav">
      <a href="supplier_dashboard.php"><i class="fas fa-home"></i> Home</a>
      <a href="supplier_profile.php" class="active"><i class="fas fa-user-cog"></i> Profile</a>
      <a href="../logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </nav>
  </header>

  <div class="main-content">
    <div class="page-header">
      <h2><i class="fas fa-id-card"></i> Supplier Profile & Settings</h2>
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
          <?php if (!empty($supplier_info['profile_picture']) && file_exists('../' . $supplier_info['profile_picture'])): ?>
            <img src="../<?= htmlspecialchars($supplier_info['profile_picture']) ?>" alt="Profile" class="profile-picture">
          <?php else: ?>
            <div class="profile-picture-placeholder"><?= strtoupper(substr($supplier_info['full_name'], 0, 1)) ?></div>
          <?php endif; ?>
        </div>
        <h3 style="color: var(--primary); margin-bottom: 5px;"><?= htmlspecialchars($supplier_info['full_name']) ?></h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Verified Supplier</p>
        
        <div class="stats-item">
          <span class="stat-value"><?= number_format($total_items) ?></span>
          <span class="stat-label">Total Inventory Items</span>
        </div>
        <div class="stats-item">
          <span class="stat-value">MWK <?= number_format($total_revenue, 0) ?></span>
          <span class="stat-label">Total Earnings</span>
        </div>
      </div>

      <div class="profile-content">
        <div class="settings-card">
          <h3><i class="fas fa-user-edit"></i> Account Information</h3>
          <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
              <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($supplier_info['full_name']) ?>" required>
              </div>
              <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($supplier_info['email']) ?>" required>
              </div>
              <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($supplier_info['phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label><i class="fas fa-image"></i> Change Picture</label>
                <input type="file" name="profile_picture" accept="image/*">
              </div>
            </div>
            
            <h3 style="margin-top: 25px; margin-bottom: 15px; font-size: 1.1rem; color: var(--primary);"><i class="fa-solid fa-money-bill-transfer"></i> Payout Settings (Paychangu)</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Payout Phone Number</label>
                    <input type="text" name="payout_phone" value="<?= htmlspecialchars($supplier_info['payout_phone'] ?? '') ?>" placeholder="e.g. 0991234567">
                </div>
                <div class="form-group">
                    <label>Mobile Operator</label>
                    <select name="payout_operator">
                        <option value="airtel" <?= ($supplier_info['payout_operator'] ?? '') == 'airtel' ? 'selected' : '' ?>>Airtel Money</option>
                        <option value="tnm" <?= ($supplier_info['payout_operator'] ?? '') == 'tnm' ? 'selected' : '' ?>>TNM Mpamba</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Preferred Method</label>
                    <select name="payout_method">
                        <option value="mobile_money" <?= ($supplier_info['payout_method'] ?? '') == 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                        <option value="bank_transfer" <?= ($supplier_info['payout_method'] ?? '') == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="update_profile" class="btn" style="margin-top: 20px; width: 100%;"><i class="fa-solid fa-floppy-disk"></i> Save Profile & Payout Details</button>
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
              <div class="form-group" style="grid-column: span 2;">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
              </div>
            </div>
            <button type="submit" name="change_password" class="btn" style="margin-top: 20px; background: var(--secondary);"><i class="fa-solid fa-key"></i> Update Password</button>
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
