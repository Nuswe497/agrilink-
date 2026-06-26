<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch Real Data for Dashboard
$memberCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")->fetch_assoc()['count'] ?? 0;
$totalHoney = $conn->query("SELECT SUM(quantity) as total FROM contributions")->fetch_assoc()['total'] ?? 0;

// Calculate Total Income (Honey Sales + NGO Donations + Member Fees + Shares)
$salesIncome = $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type = 'profit' AND amount > 0")->fetch_assoc()['total'] ?? 0;
$feeIncome = $conn->query("SELECT SUM(amount) as total FROM finance WHERE transaction_type = 'fee' AND amount > 0")->fetch_assoc()['total'] ?? 0;
$donationIncome = $conn->query("SELECT SUM(amount) as total FROM donations")->fetch_assoc()['total'] ?? 0;
$shareCount = $conn->query("SELECT SUM(shares) as total FROM shares")->fetch_assoc()['total'] ?? 0;
$shareIncome = $shareCount * 100; // 100 MWK per share
$totalIncome = $salesIncome + $feeIncome + $donationIncome + $shareIncome;

$hiveCount = $conn->query("SELECT COUNT(*) as count FROM hives")->fetch_assoc()['count'] ?? 0;
$inspectionCount = $conn->query("SELECT COUNT(*) as count FROM inspections")->fetch_assoc()['count'] ?? 0;
$trainingCount = $conn->query("SELECT COUNT(*) as count FROM training_materials")->fetch_assoc()['count'] ?? 0;
$stakeholderCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'external'")->fetch_assoc()['count'] ?? 0;

// Handle Product Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_product_status'])) {
    $product_id = (int)$_POST['product_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE product_id = ?");
    $stmt->bind_param("si", $new_status, $product_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['msg'] = "Product status updated successfully!";
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    
    $image_path = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $upload_dir = '../uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                $image_path = 'uploads/products/' . $new_filename;
            }
        }
    }
    
    if ($name && $price > 0 && $stock >= 0) {
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, image_path, status) VALUES (?, ?, ?, ?, ?, 'available')");
        $stmt->bind_param("ssdis", $name, $description, $price, $stock, $image_path);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['msg'] = "Product added successfully!";
        header("Location: admin.php");
        exit;
    }
}

// Fetch Products
$products = $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 10");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard | Agrilink Portal</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary:    #2a9d8f;
      --secondary:  #f4a261;
      --dark:       #2c3e50;
      --bg:         #f8fafc;
      --card:       #ffffff;
      --text:       #2c3e50;
      --text-soft:  #64748b;
      --border:     #e2e8f0;
      --shadow:     0 4px 12px rgba(0, 0, 0, 0.08);
      --radius:     12px;
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

    /* System Header Standardized */
    .header {
      background: #2a9d8f !important;
      color: white;
      padding: 0;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      width: 100%;
      position: sticky;
      top: 0;
      z-index: 1000;
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
      font-weight: 600;
    }
    .header-nav a:hover, .header-nav a.active {
      color: var(--secondary);
    }

    .footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid var(--secondary);
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
    }

    .footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid var(--secondary);
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 40px 20px;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .dashboard-welcome {
      margin-bottom: 30px;
      background: white;
      padding: 40px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border-left: 6px solid var(--primary);
    }

    .dashboard-welcome h1 { font-size: 2.2rem; color: var(--primary); margin-bottom: 10px; }
    .dashboard-welcome p { color: var(--text-soft); font-size: 1.1rem; }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .stat-card {
      background: white;
      padding: 30px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: 20px;
      transition: transform 0.2s;
      border: 1px solid var(--border);
    }

    .stat-card:hover { transform: translateY(-5px); }

    .stat-icon {
      width: 60px;
      height: 60px;
      background: rgba(42, 157, 143, 0.1);
      color: var(--primary);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
    }

    .stat-icon.orange { background: rgba(244, 162, 97, 0.1); color: var(--secondary); }

    .stat-info .value { font-size: 1.8rem; font-weight: 800; color: var(--dark); }
    .stat-info .label { font-size: 0.9rem; color: var(--text-soft); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Admin Essentials / Quick Actions */
    .essentials-section {
      background: white;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 30px;
      margin-bottom: 40px;
    }

    .section-title {
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }

    .action-btn {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 20px;
      background: #f8fafc;
      border-radius: 10px;
      text-decoration: none;
      color: var(--dark);
      font-weight: 600;
      transition: 0.3s;
      border: 1px solid transparent;
    }

    .action-btn:hover {
      background: white;
      border-color: var(--primary);
      transform: translateY(-3px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .action-btn i { color: var(--primary); font-size: 1.3rem; }

    /* System Footer */
    .footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 25px 30px;
      margin-top: auto;
      font-size: 0.95rem;
      border-top: 5px solid var(--secondary);
    }

    .footer a {
      color: var(--secondary);
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <!-- System Header -->
  <header class="header">
    <div class="nav-inner">
      <a href="admin.php" class="logo-std">
        <div class="logo-icon-wrap">
          <i class="fas fa-bee"></i>
        </div>
        <div class="logo-text">Agri<span>link</span> Admin</div>
      </a>
      <nav class="header-nav">
        <a href="admin.php" class="active"><i class="fas fa-home"></i> Home</a>
        <a href="admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
        <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
        <a href="admin_finances.php"><i class="fas fa-money-bill"></i> Finances</a>
        <a href="admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
        <a href="admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
        
        <a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="text-decoration:none;">
          <span class="notif-wrapper" style="position:relative; display:inline-flex; align-items:center;">
            <i class="fa-solid fa-bell"></i>
            <?php if ($notifCount > 0): ?>
              <span class="notif-badge" style="position:absolute; top:-8px; right:-10px; background:#ef4444; color:white; font-size:0.65rem; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #2a9d8f;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
            <?php endif; ?>
          </span>
        </a>
        <a href="logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
      </nav>
    </div>
  </header>

  <main class="main-content">
    
    <div class="dashboard-welcome">
      <h1>Administrator Control Panel</h1>
      <p>Manage beekeepers, oversee cooperative finances, and monitor agricultural logistics.</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
          <div class="value"><?= number_format($memberCount) ?></div>
          <div class="label">Total Members</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-honey-pot"></i></div>
        <div class="stat-info">
          <div class="value"><?= number_format($totalHoney, 1) ?> kg</div>
          <div class="label">Honey Produced</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-info">
          <div class="value">MK <?= number_format($totalIncome) ?></div>
          <div class="label">Total Income</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-archive"></i></div>
        <div class="stat-info">
          <div class="value"><?= number_format($hiveCount) ?></div>
          <div class="label">Total Hives</div>
        </div>
      </div>
    </div>

    <!-- Admin Essentials -->
    <div class="essentials-section">
      <h2 class="section-title"><i class="fas fa-tasks"></i> Operational Essentials</h2>
      <div class="actions-grid">
        <a href="admin_members.php" class="action-btn">
          <i class="fas fa-user-plus"></i> Register Member
        </a>
        <a href="admin_inspections.php" class="action-btn">
          <i class="fas fa-calendar-check"></i> Field Inspections
        </a>
        <a href="admin_markets.php" class="action-btn">
          <i class="fas fa-truck-moving"></i> Manage Logistics
        </a>
        <a href="admin_training.php" class="action-btn">
          <i class="fas fa-book-reader"></i> Training Materials
        </a>
        <a href="admin_reports.php" class="action-btn">
          <i class="fas fa-chart-line"></i> Generate Reports
        </a>
        <a href="admin_stakeholder.php" class="action-btn">
          <i class="fas fa-handshake"></i> External Stakeholders
        </a>
        <a href="#products" class="action-btn" onclick="toggleProductsSection()">
          <i class="fas fa-box"></i> Manage Products
        </a>
      </div>
    </div>

    <!-- Product Management Section -->
    <div id="products-section" class="essentials-section" style="display:none;">
      <h2 class="section-title"><i class="fas fa-box"></i> Honey Product Management</h2>
      
      <?php if (isset($_SESSION['msg'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:1rem; border-radius:8px; margin-bottom:1rem;">
          <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
        </div>
      <?php endif; ?>

      <!-- Add New Product -->
      <div style="background:white; padding:2rem; border-radius:12px; margin-bottom:2rem; box-shadow: var(--shadow);">
        <h3 style="margin-bottom:1rem; color:var(--primary);">Add New Product</h3>
        <form method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div>
            <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Product Name</label>
            <input type="text" name="name" required style="width:100%; padding:0.75rem; border:1px solid var(--border); border-radius:8px;">
          </div>
          <div>
            <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Price (MWK)</label>
            <input type="number" name="price" step="0.01" min="0" required style="width:100%; padding:0.75rem; border:1px solid var(--border); border-radius:8px;">
          </div>
          <div style="grid-column:span 2;">
            <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Description</label>
            <textarea name="description" rows="3" style="width:100%; padding:0.75rem; border:1px solid var(--border); border-radius:8px;"></textarea>
          </div>
          <div>
            <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Initial Stock</label>
            <input type="number" name="stock" min="0" required style="width:100%; padding:0.75rem; border:1px solid var(--border); border-radius:8px;">
          </div>
          <div>
            <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Product Image</label>
            <input type="file" name="product_image" accept="image/*" style="width:100%; padding:0.75rem; border:1px solid var(--border); border-radius:8px;">
            <small style="color:#64748b; font-size:0.8rem;">Optional: JPG, PNG, GIF (Max 5MB)</small>
          </div>
          <div style="display:flex; align-items:flex-end;">
            <button type="submit" name="add_product" style="background:var(--primary); color:white; border:none; padding:0.75rem 2rem; border-radius:8px; font-weight:600; cursor:pointer;">Add Product</button>
          </div>
        </form>
      </div>

      <!-- Existing Products -->
      <div style="background:white; padding:2rem; border-radius:12px; box-shadow: var(--shadow);">
        <h3 style="margin-bottom:1rem; color:var(--primary);">Manage Existing Products</h3>
        <div style="overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse;">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:1rem; text-align:left; border-bottom:1px solid var(--border);">Product</th>
                <th style="padding:1rem; text-align:left; border-bottom:1px solid var(--border);">Price</th>
                <th style="padding:1rem; text-align:left; border-bottom:1px solid var(--border);">Stock</th>
                <th style="padding:1rem; text-align:left; border-bottom:1px solid var(--border);">Status</th>
                <th style="padding:1rem; text-align:left; border-bottom:1px solid var(--border);">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($p = $products->fetch_assoc()): ?>
                <tr>
                  <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; gap:1rem;">
                      <div style="width:50px; height:50px; border-radius:8px; overflow:hidden; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
                        <?php if (!empty($p['image_path']) && file_exists("../" . $p['image_path'])): ?>
                          <img src="../<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                          <i class="fas fa-image" style="color:#cbd5e1; font-size:1.5rem;"></i>
                        <?php endif; ?>
                      </div>
                      <div>
                        <strong style="display:block;"><?= htmlspecialchars($p['name']) ?></strong>
                        <small style="color:#64748b;"><?= htmlspecialchars(substr($p['description'], 0, 50)) ?>...</small>
                      </div>
                    </div>
                  </td>
                  <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">MWK <?= number_format($p['price'], 2) ?></td>
                  <td style="padding:1rem; border-bottom:1px solid #f1f5f9;"><?= $p['stock'] ?> units</td>
                  <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                    <span style="padding:0.25rem 0.75rem; border-radius:20px; font-size:0.8rem; font-weight:600; 
                      background:<?= $p['status'] === 'available' ? '#dcfce7' : '#fee2e2' ?>; 
                      color:<?= $p['status'] === 'available' ? '#166534' : '#991b1b' ?>;">
                      <?= ucfirst($p['status']) ?>
                    </span>
                  </td>
                  <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                      <input type="hidden" name="new_status" value="<?= $p['status'] === 'available' ? 'hidden' : 'available' ?>">
                      <button type="submit" name="toggle_product_status" style="background:<?= $p['status'] === 'available' ? '#ef4444' : '#10b981' ?>; color:white; border:none; padding:0.5rem 1rem; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        <?= $p['status'] === 'available' ? 'Hide' : 'Show' ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>

  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>

  <script>
    function toggleProductsSection() {
      const section = document.getElementById('products-section');
      section.style.display = section.style.display === 'none' ? 'block' : 'none';
      section.scrollIntoView({ behavior: 'smooth' });
    }
  </script>

</body>
</html>

