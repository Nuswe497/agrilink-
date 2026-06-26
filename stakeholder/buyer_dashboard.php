<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'external' || $_SESSION['stakeholder_type'] != 'buyer') {
    header("Location: ../login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];

// Use prepared statement for buyer info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$buyer_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch products with prepared statement
$stmt = $conn->prepare("SELECT * FROM products WHERE status = 'available' AND stock > 0 ORDER BY created_at DESC");
$stmt->execute();
$products = $stmt->get_result();
$productsList = $products->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch purchase history with prepared statement
$stmt = $conn->prepare("
    SELECT o.*, COALESCE(p.name, 'Product Not Available') as product_name 
    FROM orders o 
    LEFT JOIN products p ON o.product_id = p.product_id 
    WHERE o.user_id = ? 
    ORDER BY o.order_date DESC
");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// Handle Paychangu return callback over GET parameters
if (($_GET['payment'] ?? '') === 'success' && isset($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
    $tx_ref = $_GET['tx_ref'] ?? 'PC-RET-' . time();
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND stock > 0");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($product) {
        $amount = $product['price'];

        $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, quantity, total_amount, payment_status, paychangu_ref, order_date) 
                                VALUES (?, ?, 1, ?, 'completed', ?, CURDATE())");
        $stmt->bind_param("iids", $buyer_id, $product_id, $amount, $tx_ref);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE products SET stock = stock - 1 WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->close();

        $description = "Honey Sale: " . $product['name'];
        $stmt_f = $conn->prepare("INSERT INTO finance (user_id, transaction_type, amount, date, description) VALUES (?, 'profit', ?, CURDATE(), ?)");
        $stmt_f->bind_param("ids", $buyer_id, $amount, $description);
        $stmt_f->execute();
        $stmt_f->close();

        // Notifications for admin and treasurer
        $buyer_name = $buyer_info['full_name'] ?? 'A Buyer';
        $notif_title = "Product Purchased: " . $product['name'];
        $notif_msg = "$buyer_name has purchased " . $product['name'] . " for MWK " . number_format($amount, 2) . ".";
        
        $stmt_notif = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
        
        $role_admin = 'admin';
        $stmt_notif->bind_param("sss", $notif_title, $notif_msg, $role_admin);
        $stmt_notif->execute();
        
        $role_treasurer = 'treasurer';
        $stmt_notif->bind_param("sss", $notif_title, $notif_msg, $role_treasurer);
        $stmt_notif->execute();
        
        $stmt_notif->close();

        $_SESSION['msg'] = "Payment successful! Order placed for " . $product['name'];
    } else {
        $_SESSION['msg_error'] = "Payment processing error or product out of stock.";
    }
    header("Location: buyer_dashboard.php");
    exit;
}

// Handle manual order (without payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $product_id = (int)$_POST['product_id'];
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND stock > 0");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($product) {
        $amount = $product['price'];
        $tx_ref = 'AUTO-' . uniqid();

        $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, quantity, total_amount, payment_status, paychangu_ref, order_date) 
                                VALUES (?, ?, 1, ?, 'completed', ?, CURDATE())");
        $stmt->bind_param("iidss", $buyer_id, $product_id, $amount, $tx_ref);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE products SET stock = stock - 1 WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->close();

        $description = "Honey Sale: " . $product['name'];
        $stmt_f = $conn->prepare("INSERT INTO finance (user_id, transaction_type, amount, date, description) VALUES (?, 'profit', ?, CURDATE(), ?)");
        $stmt_f->bind_param("ids", $buyer_id, $amount, $description);
        $stmt_f->execute();
        $stmt_f->close();

        // Notifications for admin and treasurer
        $buyer_name = $buyer_info['full_name'] ?? 'A Buyer';
        $notif_title = "Product Purchased: " . $product['name'];
        $notif_msg = "$buyer_name has purchased " . $product['name'] . " for MWK " . number_format($amount, 2) . ".";
        
        $stmt_notif = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
        
        $role_admin = 'admin';
        $stmt_notif->bind_param("sss", $notif_title, $notif_msg, $role_admin);
        $stmt_notif->execute();
        
        $role_treasurer = 'treasurer';
        $stmt_notif->bind_param("sss", $notif_title, $notif_msg, $role_treasurer);
        $stmt_notif->execute();
        
        $stmt_notif->close();

        $_SESSION['msg'] = "Order Successful! " . $product['name'];
    } else {
        $_SESSION['msg_error'] = "Product is out of stock.";
    }
    header("Location: buyer_dashboard.php");
    exit;
}

// Get spending totals with prepared statements
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ? AND payment_status = 'completed'");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$spent = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$totalPurchases = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Agrilink Buyer Portal</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root { 
      --primary: #2a9d8f; 
      --primary-dark: #247b73; 
      --accent: #f4a261; 
      --accent-hover: #e76f51;
      --bg: #f8fafc;
      --text: #1e293b;
      --text-muted: #64748b;
      --card-bg: rgba(255, 255, 255, 0.9);
      --success: #10b981;
      --danger: #ef4444;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { 
      font-family: 'Outfit', sans-serif; 
      /* replaced */ 
      color: var(--text);
      min-height: 100vh; 
      display: flex;
      flex-direction: column;
    }
    
    /* Navbar Standardized */
    .navbar-standard { 
      background: #2a9d8f !important; 
      padding: 0;
      position: sticky; 
      top: 0; 
      z-index: 1000; 
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    }
    .nav-inner {
      max-width: 1380px;
      margin: 0 auto;
      padding: 0 3rem;
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
    .logo-text span { color: var(--accent); }
    
    .navbar-nav-std ul { display: flex; list-style: none; gap: 2.5rem; align-items: center; }
    .navbar-nav-std a { 
      color: white; 
      text-decoration: none; 
      font-weight: 600; 
      font-size: 0.95rem; 
      transition: all 0.3s ease; 
      display: flex;
      align-items: center;
      gap: 0.5rem;
      opacity: 0.85;
    }
    .navbar-nav-std a:hover, .navbar-nav-std a.active { color: var(--accent); opacity: 1; }

    /* Main Container */
    main { 
      flex: 1;
      max-width: 1300px; 
      margin: 2rem auto; 
      padding: 0 2rem; 
      width: 100%;
    }

    /* Hero Panel */
    .hero-panel {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      border-radius: 24px;
      padding: 3rem;
      color: white;
      box-shadow: 0 20px 40px rgba(42, 157, 143, 0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }
    .hero-panel::after {
      content: '\f434'; /* Bee/Bug icon */
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      position: absolute;
      right: -20px;
      bottom: -40px;
      font-size: 14rem;
      color: rgba(255,255,255,0.05);
      transform: rotate(-15deg);
    }
    .hero-content { z-index: 2; }
    .hero-content h1 {
      font-size: 2.8rem;
      margin-bottom: 0.5rem;
      font-weight: 700;
      letter-spacing: -1px;
    }
    .hero-content p {
      font-size: 1.1rem;
      opacity: 0.9;
      font-weight: 300;
    }
    .hero-profile {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      z-index: 2;
    }
    .avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      border: 4px solid rgba(255,255,255,0.3);
      background: white;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 2.5rem;
      color: var(--primary);
      overflow: hidden;
    }
    .status-badge {
      background: rgba(255,255,255,0.2);
      backdrop-filter: blur(5px);
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid rgba(255,255,255,0.4);
    }

    .section-title {
      font-size: 1.6rem; 
      color: var(--primary-dark); 
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
    }

    /* Product Grid */
    .product-grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
      gap: 2rem; 
      margin-bottom: 3rem;
    }
    .product-card { 
      background: var(--card-bg); 
      backdrop-filter: blur(20px);
      border: 1px solid white;
      border-radius: 20px; 
      overflow: hidden; 
      box-shadow: 0 10px 30px rgba(0,0,0,0.04); 
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    }
    .product-card:hover { 
      transform: translateY(-8px); 
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
      border-color: var(--accent);
    }
    .product-img { 
      width: 100%; 
      height: 220px; 
      object-fit: cover; 
    }
    .product-info { padding: 1.8rem; }
    .product-name { font-size: 1.3rem; font-weight: 700; color: var(--text); margin-bottom: 5px; }
    .product-desc { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 15px; line-height: 1.4; }
    .product-meta {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #e2e8f0;
    }
    .price { font-size: 1.4rem; font-weight: 800; color: var(--primary); }
    .stock-badge {
      font-size: 0.85rem; font-weight: 600; padding: 4px 10px; border-radius: 12px;
    }
    .btn-buy { 
      background: var(--accent); 
      color: white; 
      border: none; 
      padding: 1rem; 
      border-radius: 12px; 
      font-weight: 700; 
      font-size: 1rem;
      width: 100%; 
      cursor: pointer; 
      transition: 0.3s;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 10px;
      box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
    }
    .btn-buy:hover { background: var(--accent-hover); transform: scale(1.02); }

    /* Purchase History Table */
    .table-card { 
      background: var(--card-bg); 
      backdrop-filter: blur(20px);
      border: 1px solid white;
      border-radius: 20px; 
      padding: 2rem; 
      box-shadow: 0 10px 30px rgba(0,0,0,0.04); 
      overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; }
    th { padding: 1.2rem; text-align: left; color: var(--text-muted); font-weight: 600; border-bottom: 2px solid #e2e8f0; }
    td { padding: 1.2rem; border-bottom: 1px solid #f1f5f9; color: var(--text); }
    tr:last-child td { border-bottom: none; }
    .status-completed {
      background: #dcfce7; color: #166534; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;
    }

    .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #dcfce7; color: #166534; }
    .alert-error { background: #fee2e2; color: #991b1b; }

    /* Footer */
    footer {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 1.5rem;
      margin-top: auto;
      font-size: 0.95rem;
      box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.05);
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
        <ul>
          <li><a href="buyer_dashboard.php" class="active"><i class="fa-solid fa-store"></i> Shop</a></li>
          <li><a href="buyer_profile.php" class="profile-link" title="Profile"><i class="fa-solid fa-circle-user"></i> My Profile</a></li>
          <li><a href="../logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </ul>
      </nav>
    </div>
  </nav>

  <main>
    <?php if (isset($_SESSION['msg'])): ?>
      <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['msg_error'])): ?>
      <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $_SESSION['msg_error']; unset($_SESSION['msg_error']); ?></div>
    <?php endif; ?>

    <!-- Hero Banner -->
    <section class="hero-panel">
      <div class="hero-content">
        <h1>Welcome Back, <?= explode(' ', htmlspecialchars($buyer_info['full_name']))[0] ?>!</h1>
        <p>Discover premium cooperative organic honey products directly from the source.</p>
        <div style="margin-top: 20px; font-size: 0.95rem; opacity: 0.8;">
           <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($buyer_info['email']) ?>
        </div>
      </div>
      <div class="hero-profile">
        <div class="logo">
      <img src="../assets/logo.png" alt="Agrilink Logo" style="height: 40px; margin-right: 10px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
      LIVINGSTONIA BEE KEEPING
    </div>
        <span class="status-badge"><i class="fa-solid fa-star"></i> Verified Buyer</span>
      </div>
    </section>

    <!-- Available Products -->
    <h2 class="section-title"><i class="fa-solid fa-shopping-bag"></i> Available Products</h2>

    <div class="product-grid">
      <?php foreach ($productsList as $p): ?>
        <div class="product-card">
          <div class="product-img">
            <?php if (!empty($p['image_path'])): ?>
              <img src="../<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
              <div style="height:100%; background:#fef3c7; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-honey-pot" style="font-size:5rem; color:var(--accent);"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="product-info">
            <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
            <p class="product-desc"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 90)) ?>...</p>
            
            <div class="product-meta">
              <div class="price">MWK <?= number_format($p['price'], 2) ?></div>
              <?php $lowStock = $p['stock'] < 10; ?>
              <div class="stock-badge" style="background: <?= $lowStock ? '#fee2e2' : '#dcfce7' ?>; color: <?= $lowStock ? '#991b1b' : '#166534' ?>;">
                <?= $p['stock'] ?> left
              </div>
            </div>
            
            <button type="button" class="btn-buy" onClick="triggerPaychangu(<?= $p['product_id'] ?>, <?= $p['price'] ?>, '<?= addslashes($p['name']) ?>')">
              <i class="fa-regular fa-credit-card"></i> BUY NOW
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Purchase History -->
    <h2 class="section-title" style="margin-top: 4rem;"><i class="fa-solid fa-clock-rotate-left"></i> Purchase History</h2>
    <div class="table-card">
      <table>
        <thead>
          <tr style="background:#f8fafc;">
            <th>Date</th>
            <th>Product</th>
            <th>Total Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders->num_rows == 0): ?>
            <tr><td colspan="4" style="text-align:center; padding:2rem; color: var(--text-muted);">No purchases yet. Start shopping above!</td></tr>
          <?php endif; ?>
          <?php while ($o = $orders->fetch_assoc()): ?>
            <tr>
              <td><i class="fa-regular fa-calendar" style="color: var(--text-muted); margin-right: 8px;"></i> <?= date('M d, Y', strtotime($o['order_date'])) ?></td>
              <td><strong><?= htmlspecialchars($o['product_name']) ?></strong></td>
              <td style="font-weight: 600;">MWK <?= number_format($o['total_amount']) ?></td>
              <td><span class="status-completed"><i class="fa-solid fa-check"></i> COMPLETED</span></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </main>

  <footer>
    &copy; 2026 Agrilink Cooperative | Premium Organic Products
  </footer>
  
  <div id="wrapper"></div>
  <script src="https://in.paychangu.com/js/popup.js"></script>
  <script>
    function triggerPaychangu(productId, amount, productName) {
      if (amount < 100) {
        Swal.fire({
          icon: 'warning',
          title: 'Minimum Amount Not Met',
          text: 'The minimum purchase amount via Paychangu is MWK 100.00 to cover processing fees. This item is too low to process individually.',
          confirmButtonColor: '#2a9d8f'
        });
        return;
      }

      var tx_ref = '' + Math.floor((Math.random() * 1000000000) + 1);
      
      PaychanguCheckout({
        "public_key": "PUB-AfPBVlqDNeRU5hB0FtHQlVozVgVnfR1b",
        "tx_ref": tx_ref,
        "amount": amount,
        "currency": "MWK",
        "callback_url": window.location.origin + window.location.pathname.replace('buyer_dashboard.php', '') + "process_payment.php?product_id=" + productId,
        "return_url": window.location.href.split('?')[0] + "?payment=success&product_id=" + productId + "&tx_ref=" + tx_ref,
        "customer": {
          "email": "<?= htmlspecialchars($buyer_info['email']) ?>",
          "first_name": "<?= htmlspecialchars(explode(' ', $buyer_info['full_name'])[0]) ?>",
          "last_name": "Buyer",
        },
        "customization": {
          "title": "Buy " + productName,
          "description": "Payment for Agrilink product",
        },
        "meta": {
          "uuid": "<?= $buyer_id ?>",
          "response": "Response"
        }
      });
    }
  </script>
</body>
</html>