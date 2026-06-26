<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($memberId <= 0) {
    header('Location: admin_members.php');
    exit;
}

$message = '';
$error = '';


$stmt = $conn->prepare('SELECT user_id, full_name, email, status, role, stakeholder_type 
                        FROM users 
                        WHERE user_id = ?');
$stmt->bind_param('i', $memberId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$stmt->close();

if (!$member) {
    header('Location: admin_members.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $status = ($_POST['status'] === 'inactive') ? 'inactive' : 'active';
    $raw_role = $_POST['role'] ?? $member['role']; // capture role from dropdown
    $role = $raw_role;
    $stakeholder_type = $member['stakeholder_type'];

    if (in_array($raw_role, ['supplier', 'buyer', 'ngo'])) {
        $role = 'external';
        $stakeholder_type = $raw_role;
    } elseif (in_array($raw_role, ['member', 'admin', 'secretary', 'treasurer'])) {
        $stakeholder_type = NULL;
    }

    if ($fullName === '' || $email === '') {
        $error = 'Name and email are required.';
    } else {
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users 
                                    SET full_name = ?, email = ?, password_hash = ?, status = ?, role = ?, stakeholder_type = ? 
                                    WHERE user_id = ?');
            $stmt->bind_param('ssssssi', $fullName, $email, $passwordHash, $status, $role, $stakeholder_type, $memberId);
        } else {
            $stmt = $conn->prepare('UPDATE users 
                                    SET full_name = ?, email = ?, status = ?, role = ?, stakeholder_type = ? 
                                    WHERE user_id = ?');
            $stmt->bind_param('sssssi', $fullName, $email, $status, $role, $stakeholder_type, $memberId);
        }

        if ($stmt->execute()) {
            $message = ucfirst($raw_role) . ' updated successfully.';
            $member['full_name'] = $fullName;
            $member['email'] = $email;
            $member['status'] = $status;
            $member['role'] = $role;
            $member['stakeholder_type'] = $stakeholder_type;
        } else {
            $error = 'Unable to update user. ' . $stmt->error;
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
  <title>Edit Member - Agrilink Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="admin.css" />
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

    .header-logo {
      font-size: 1.3rem;
      font-weight: 700;
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
      color: #f4a261;
    }

    .main-content {
      flex: 1;
      padding: 30px 20px;
    }

    .dashboard-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .footer {
      backgro9=8`und: #2c3e50;
      color: white;
      text-align: center;
      padding: 20px 30px;
      margin-top: 40px;
      font-size: 0.9rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      width: 100%;
    }

    .footer a {
      color: #f4a261;
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }

    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 25px;
      font-size: 0.95rem;
    }

    .breadcrumb a {
      color: var(--primary);
      text-decoration: none;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .breadcrumb a:hover {
      color: var(--secondary);
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
    }

    .back-link:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(42, 157, 143, 0.4);
      color: white;
    }

    .back-link i {
      font-size: 1rem;
    }

    .breadcrumb span {
      color: var(--text-muted);
    }

    .page-header {
      background: white;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 30px;
    }

    .page-header h1 {
      font-size: 1.8rem;
      color: var(--primary);
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 5px;
    }

    .page-header p {
      color: var(--text-muted);
      font-size: 0.95rem;
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

    /* Form Section */
    .form-section {
      background: white;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }

    .form-section h2 {
      font-size: 1.3rem;
      color: var(--primary);
      font-weight: 600;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 30px;
    }

    .form-grid.full {
      grid-template-columns: 1fr;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      color: var(--text);
      margin-bottom: 10px;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .form-group label .required {
      color: var(--danger);
    }

    .form-group input,
    .form-group select {
      padding: 12px 14px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
      background: #fafafa;
    }

    .form-group input::placeholder {
      color: #bbb;
    }

    .form-group input:disabled,
    .form-group select:disabled {
      background: #f5f5f5;
      color: var(--text-muted);
      cursor: not-allowed;
    }

    .member-info {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      padding: 20px;
      background: #f8fafb;
      border-radius: 8px;
      margin-bottom: 30px;
      border-left: 4px solid var(--primary);
    }

    .info-item {
      display: flex;
      flex-direction: column;
    }

    .info-item label {
      font-size: 0.85rem;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 5px;
    }

    .info-item span {
      font-size: 1rem;
      color: var(--text);
      font-weight: 500;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      width: fit-content;
    }

    .status-active {
      background: #d4edda;
      color: #155724;
    }

    .status-inactive {
      background: #f8d7da;
      color: #721c24;
    }

    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
    }

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
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), #247b73);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(42, 157, 143, 0.3);
    }

    .btn-secondary {
      background: #e0e0e0;
      color: var(--text);
      border: 2px solid #d0d0d0;
    }

    .btn-secondary:hover {
      background: #d5d5d5;
      transform: translateY(-2px);
    }

    .help-text {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 5px;
      font-style: italic;
    }

    .divider {
      height: 1px;
      background: #e0e0e0;
      margin: 30px 0;
    }

    .section-subtitle {
      font-size: 1.1rem;
      color: var(--primary);
      font-weight: 600;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    @media (max-width: 768px) {
      .page-header {
        padding: 20px 25px;
      }

      .page-header h1 {
        font-size: 1.4rem;
      }

      .form-section {
        padding: 20px 25px;
      }

      .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .member-info {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .form-actions {
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>
  <!-- Header -->
  <header class="header">
  <div class="header-logo">
    <i class="fas fa-leaf"></i>
    Agrilink
  </div>
  <nav class="header-nav">
    <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
    
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
  <div class="dashboard-container">
    <!-- Back Button -->
    <div class="breadcrumb">
      <a href="admin_members.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Members
      </a>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <h1><i class="fas fa-user-edit"></i> Edit Member</h1>
      <p>Update profile details and account status for the cooperative member</p>
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

    <!-- Form Section -->
    <div class="form-section">
      <!-- Member Info Display -->
      <div class="member-info">
        <div class="info-item">
          <label>Member ID</label>
          <span>#<?php echo str_pad($member['user_id'], 4, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="info-item">
          <label>Current Status</label>
          <span class="status-badge <?php echo $member['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
            <i class="fas fa-<?php echo $member['status'] === 'active' ? 'check-circle' : 'ban'; ?>"></i>
            <?php echo ucfirst($member['status']); ?>
          </span>
        </div>
      </div>

      <!-- Form -->
      <form method="POST">
        <div class="section-subtitle">
          <i class="fas fa-user-circle"></i>
          Personal Information
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="full_name">
              <i class="fas fa-user"></i>
              Full Name
              <span class="required">*</span>
            </label>
            <input 
              type="text" 
              id="full_name" 
              name="full_name" 
              value="<?= htmlspecialchars($member['full_name']) ?>" 
              placeholder="Enter member's full name"
              required 
            />
          </div>

          <div class="form-group">
            <label for="email">
              <i class="fas fa-envelope"></i>
              Email Address
              <span class="required">*</span>
            </label>
            <input 
              type="email" 
              id="email" 
              name="email" 
              value="<?= htmlspecialchars($member['email']) ?>" 
              placeholder="member@example.com"
              required 
            />
          </div>
        </div>

        <div class="divider"></div>

        <div class="section-subtitle">
          <i class="fas fa-lock"></i>
          Security
        </div>

        <div class="form-grid full">
          <div class="form-group">
            <label for="password">
              <i class="fas fa-key"></i>
              New Password
            </label>
            <input 
              type="password" 
              id="password" 
              name="password" 
              placeholder="Leave blank to keep current password"
              autocomplete="new-password"
            />
            <span class="help-text">Only fill this if you want to change the password</span>
          </div>
        </div>

        <div class="divider"></div>

        <div class="section-subtitle">
          <i class="fas fa-cog"></i>
          Account Settings
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="status">
              <i class="fas fa-toggle-on"></i>
              Account Status
              <span class="required">*</span>
            </label>
            <select id="status" name="status" required>
              <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>
                Active - Member can access the system
              </option>
              <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>
                Inactive - Member access is restricted
              </option>
            </select>
          </div>

          <!-- NEW Role Dropdown -->
          <div class="form-group">
            <label for="role">
              <i class="fas fa-user-tag"></i>
              Role
              <span class="required">*</span>
            </label>
            <select id="role" name="role" required>
              <?php 
                $display_role = $member['role'];
                if ($display_role === 'external') {
                    $display_role = $member['stakeholder_type'];
                }
              ?>
              <option value="member" <?= $display_role === 'member' ? 'selected' : '' ?>>Member</option>
              <option value="supplier" <?= $display_role === 'supplier' ? 'selected' : '' ?>>Supplier</option>
              <option value="buyer" <?= $display_role === 'buyer' ? 'selected' : '' ?>>Buyer</option>
              <option value="ngo" <?= $display_role === 'ngo' ? 'selected' : '' ?>>NGO</option>
              <option value="admin" <?= $display_role === 'admin' ? 'selected' : '' ?>>Admin</option>
              <option value="secretary" <?= $display_role === 'secretary' ? 'selected' : '' ?>>Secretary</option>
              <option value="treasurer" <?= $display_role === 'treasurer' ? 'selected' : '' ?>>Treasurer</option>
            </select>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <a href="admin_members.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
          </a>
          <button type="submit" name="update_member" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="footer">
  <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
</footer>

<script>
  // Form validation
  const form = document.querySelector('form');
  form.addEventListener('submit', function(e) {
    const fullName = document.getElementById('full_name').value.trim();
    const email = document.getElementById('email').value.trim();

    if (!fullName || !email) {
      e.preventDefault();
      alert('Please fill in all required fields');
      return false;
    }

    // Simple email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      e.preventDefault();
      alert('Please enter a valid email address');
      return false;
    }
  });

  // Password field feedback
  const passwordField = document.getElementById('password');
  passwordField.addEventListener('focus', function() {
    document.querySelector('.help-text').style.color = 'var(--primary)';
  });
  passwordField.addEventListener('blur', function() {
    document.querySelector('.help-text').style.color = 'var(--text-muted)';
  });
</script>
</body>
</html>

