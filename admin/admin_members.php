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

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    require_once '../mailer_helper.php';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'member';
    $stakeholder_type = $_POST['stakeholder_type'] ?? 'supplier';

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Please provide name, email, and password.';
    } elseif (!in_array($role, ['member', 'admin', 'external', 'secretary', 'treasurer'])) {
        $error = 'Invalid role selected.';
    } elseif ($role === 'external' && !in_array($stakeholder_type, ['supplier', 'buyer', 'ngo'])) {
        $error = 'Invalid stakeholder type selected.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $username = $email;

        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, email, full_name, status, stakeholder_type, date_joined) VALUES (?, ?, ?, ?, ?, 'active', ?, CURDATE())");
        $stmt->bind_param('ssssss', $username, $hash, $role, $email, $fullName, $stakeholder_type);
        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;
            $message = 'User added successfully.';
            
            // Send Welcome Email
            $mailError = '';
            if (sendWelcomeEmail($conn, $newUserId, $password, $mailError)) {
                $message .= ' Welcome email sent to ' . htmlspecialchars($email) . '.';
            } else {
                $error = 'User added but email failed: ' . $mailError;
            }
        } else {
            $error = 'Unable to add user. Email may already exist.';
        }
        $stmt->close();
    }
}

// Handle member status updates (deactivate/reactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $newStatus = ($_POST['new_status'] === 'active') ? 'active' : 'inactive';

    if ($memberId > 0) {
        if ($memberId === $_SESSION['user_id']) {
            $error = 'You cannot deactivate your own administrative account.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param('si', $newStatus, $memberId);
            if ($stmt->execute()) {
                $message = 'Account status updated.';
            } else {
                $error = 'Unable to update account status.';
            }
            $stmt->close();
        }
    }
}

// Handle fee status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_fee'])) {
    $userId = (int)$_POST['user_id'];
    $currentStatus = $_POST['current_fee_status'];
    $newStatus = ($currentStatus == 'paid') ? 'pending' : 'paid';
    
    $stmt = $conn->prepare("UPDATE users SET fee_status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $newStatus, $userId);
    
    if ($stmt->execute()) {
        if ($newStatus == 'paid') {
            // Add to finance table so it reflects in income
            $amount = 100; // Registration fee is 100 MWK
            $desc = "Registration Fee Payment";
            $type = "fee";
            $stmt_f = $conn->prepare("INSERT INTO finance (user_id, transaction_type, amount, date, description) VALUES (?, ?, ?, CURDATE(), ?)");
            $stmt_f->bind_param("isds", $userId, $type, $amount, $desc);
            $stmt_f->execute();
            $stmt_f->close();
            $message = "Fee marked as PAID and recorded in finances.";
        } else {
            // Optional: Remove from finance if marking back to pending? 
            // For now, just update status
            $message = "Fee status set to PENDING.";
        }
    } else {
        $error = "Error updating fee status.";
    }
    $stmt->close();
}

// Fetch all users
$result = $conn->query("SELECT * FROM users ORDER BY role, user_id");
$users = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Management - Agrilink Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="theme.css">
  <link rel="stylesheet" href="admin.css">
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
      max-width: 1400px;
      margin: 0 auto;
    }

    .footer {
      background: #2a9d8f;
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

    .page-header p {
      color: var(--text-muted);
      margin-top: 5px;
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

    /* Add Member Card */
    .add-member-section {
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

    .form-container {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr auto;
      gap: 15px;
      align-items: flex-end;
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
      background: #fafafa;
    }

    .form-group input::placeholder {
      color: #bbb;
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

    .btn-edit {
      background: var(--info);
      color: white;
      padding: 8px 12px;
      font-size: 0.85rem;
    }

    .btn-edit:hover {
      background: #2980b9;
    }

    .btn-toggle {
      padding: 8px 12px;
      font-size: 0.85rem;
      color: white;
    }

    .btn-toggle.active {
      background: #e74c3c;
    }

    .btn-toggle.inactive {
      background: var(--success);
    }

    .btn-toggle:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Members Table */
    .members-section {
      background: white;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }

    .table-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      gap: 15px;
    }

    .search-box {
      flex: 1;
      position: relative;
      max-width: 300px;
    }

    .search-box input {
      width: 100%;
      padding: 10px 14px 10px 36px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 0.95rem;
    }

    .search-box input:focus {
      outline: none;
      border-color: var(--primary);
    }

    .search-box i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
    }

    .member-count {
      font-size: 0.95rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 5px;
    }

    thead {
      background: linear-gradient(135deg, #f8f9fa, #f0f2f5);
      border-bottom: 2px solid #e0e0e0;
    }

    th {
      padding: 16px;
      text-align: left;
      font-weight: 600;
      color: var(--text);
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td {
      padding: 14px 16px;
      border-bottom: 1px solid #f0f0f0;
      font-size: 0.95rem;
    }

    tbody tr {
      transition: all 0.2s ease;
    }

    tbody tr:hover {
      background: #f8fafb;
      box-shadow: inset 0 0 8px rgba(0, 0, 0, 0.03);
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .status-active {
      background: #d4edda;
      color: #155724;
    }

    .status-inactive {
      background: #f8d7da;
      color: #721c24;
    }

    .member-avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), #247b73);
      color: white;
      font-weight: 600;
      margin-right: 10px;
    }

    .member-name {
      display: flex;
      align-items: center;
    }

    .actions-cell {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .no-members {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-muted);
    }

    .no-members i {
      font-size: 3rem;
      opacity: 0.3;
      margin-bottom: 10px;
    }

    @media (max-width: 1024px) {
      .form-container {
        grid-template-columns: 1fr 1fr;
      }
    }

    @media (max-width: 768px) {
      .form-container {
        grid-template-columns: 1fr;
      }

      .page-header {
        flex-direction: column;
        gap: 15px;
      }

      table {
        font-size: 0.85rem;
      }

      td, th {
        padding: 10px 8px;
      }

      .actions-cell {
        flex-direction: column;
      }

      .btn {
        padding: 8px 12px;
        font-size: 0.85rem;
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
      <a href="admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
      
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
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1><i class="fas fa-users"></i> User Management</h1>
          <p>Manage all users in the system</p>
        </div>
        <div style="text-align: right;">
          <div class="member-count">
            <i class="fas fa-chart-pie"></i>
            Total Users: <strong><?php echo count($users); ?></strong>
          </div>
        </div>
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

    <!-- Add New User Section -->
    <div class="add-member-section">
      <div class="section-title">
        <i class="fas fa-user-plus"></i>
        Add New User
      </div>
      <form method="POST" class="form-container">
        <div class="form-group">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" placeholder="Enter user's full name" required />
        </div>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="user@example.com" required />
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Create a secure password" required />
        </div>
        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" required onchange="toggleStakeholderType()">
            <option value="member">Member</option>
            <option value="secretary">Secretary</option>
            <option value="treasurer">Treasurer</option>
            <option value="admin">Admin</option>
            <option value="external">External (Stakeholder)</option>
          </select>
        </div>
        <div class="form-group" id="stakeholder_type_group" style="display: none;">
          <label for="stakeholder_type">Stakeholder Type</label>
          <select id="stakeholder_type" name="stakeholder_type">
            <option value="supplier">Supplier</option>
            <option value="buyer">Buyer</option>
            <option value="ngo">NGO</option>
          </select>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary">
          <i class="fas fa-plus"></i> Add User
        </button>
      </form>
    </div>

    <!-- Users Table Section -->
    <div class="members-section">
      <div class="section-title">
        <i class="fas fa-list"></i>
        All Users
      </div>
      
      <div class="table-controls">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search users by name or email..." />
        </div>
        <div class="member-count">
          Showing <strong id="recordCount"><?php echo count($users); ?></strong> users
        </div>
      </div>

      <?php if (count($users) > 0): ?>
        <table id="membersTable">
          <thead>
            <tr>
              <th style="width: 8%;">ID</th>
              <th style="width: 15%;">National ID</th>
              <th style="width: 20%;">Name</th>
              <th style="width: 20%;">Email</th>
              <th style="width: 12%;">Role</th>
              <th style="width: 15%;">Type</th>
              <th style="width: 10%;">Status</th>
              <th style="width: 15%;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td>#<?php echo str_pad($user['user_id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($user['national_id'] ?? '-'); ?></td>
                <td>
                  <div class="member-name">
                    <div class="member-avatar">
                      <?php 
                        $name = $user['full_name'];
                        $initials = substr($name, 0, 1);
                        echo strtoupper($initials);
                      ?>
                    </div>
                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                  </div>
                </td>
                <td>
                  <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: var(--primary); text-decoration: none;">
                    <?php echo htmlspecialchars($user['email']); ?>
                  </a>
                </td>
                <td><?php echo ucfirst($user['role']); ?></td>
                <td><?php echo $user['role'] === 'external' ? ucfirst($user['stakeholder_type']) : '-'; ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                        <input type="hidden" name="current_fee_status" value="<?= $user['fee_status'] ?>">
                        <button type="submit" name="toggle_fee" style="background:none; border:none; cursor:pointer; color:<?= $user['fee_status'] == 'paid' ? '#10b981' : '#ef4444' ?>;" title="Toggle Fee Status">
                            <i class="fas fa-<?= $user['fee_status'] == 'paid' ? 'check-circle' : 'times-circle' ?>"></i>
                            <?= strtoupper($user['fee_status']) ?> (100 MWK)
                        </button>
                    </form>
                </td>
                <td>
                  <span class="status-badge <?php echo $user['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                    <i class="fas fa-<?php echo $user['status'] === 'active' ? 'check-circle' : 'ban'; ?>"></i>
                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                  </span>
                </td>
                <td>
                  <div class="actions-cell">
                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                      <a href="edit_member.php?id=<?php echo $user['user_id']; ?>" class="btn btn-edit">
                        <i class="fas fa-edit"></i> Edit
                      </a>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to <?php echo ($user['status'] === 'active' ? 'deactivate' : 'reactivate'); ?> this user?');">
                        <input type="hidden" name="member_id" value="<?php echo $user['user_id']; ?>" />
                        <input type="hidden" name="new_status" value="<?php echo ($user['status'] === 'active' ? 'inactive' : 'active'); ?>" />
                        <button type="submit" name="toggle_status" class="btn btn-toggle <?php echo $user['status'] === 'active' ? 'active' : 'inactive'; ?>">
                          <i class="fas fa-<?php echo $user['status'] === 'active' ? 'lock' : 'unlock'; ?>"></i>
                          <?php echo ($user['status'] === 'active' ? 'Deactivate' : 'Reactivate'); ?>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="status-badge status-active"><i class="fas fa-user-shield"></i> Current Admin</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="no-members">
          <i class="fas fa-user-slash"></i>
          <p>No users found. Add your first user using the form above.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function toggleStakeholderType() {
      const role = document.getElementById('role').value;
      const group = document.getElementById('stakeholder_type_group');
      if (role === 'external') {
        group.style.display = 'block';
      } else {
        group.style.display = 'none';
      }
    }

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('membersTable');
    const recordCount = document.getElementById('recordCount');

    if (searchInput && table) {
      searchInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        let visibleCount = 0;

        Array.from(rows).forEach(row => {
          const text = row.textContent.toLowerCase();
          const display = text.includes(filter) ? '' : 'none';
          row.style.display = display;
          if (display === '') visibleCount++;
        });

        recordCount.textContent = visibleCount;
      });
    }

    // Form validation
    const form = document.querySelector('form');
    if (form) {
      form.addEventListener('submit', function(e) {
        const fullName = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();

        if (!fullName || !email || !password) {
          e.preventDefault();
          alert('Please fill in all fields');
        }
      });
    }
  </script>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>
</body>
</html>


