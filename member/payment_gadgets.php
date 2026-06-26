<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user for name/header
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Gadgets Data (Static for now, could be DB-driven)
$gadgets = [
    [
        'id' => 'g1',
        'category' => 'iPhone',
        'name' => 'iPhone 15 Pro Max',
        'price' => 2500000,
        'image' => 'https://images.unsplash.com/photo-1696446701796-da61225697cc?q=80&w=400',
        'desc' => 'Titanium design, A17 Pro chip, and the most advanced camera system.'
    ],
    [
        'id' => 'g2',
        'category' => 'Samsung',
        'name' => 'Samsung S24 Ultra',
        'price' => 2300000,
        'image' => 'https://images.unsplash.com/photo-1707150172658-00966a3a4073?q=80&w=400',
        'desc' => 'Galaxy AI is here. Epic camera with 200MP and built-in S Pen.'
    ],
    [
        'id' => 'g3',
        'category' => 'Laptops',
        'name' => 'MacBook Pro M3 Max',
        'price' => 4500000,
        'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?q=80&w=400',
        'desc' => 'The most advanced chips ever built for a personal computer.'
    ],
    [
        'id' => 'g4',
        'category' => 'Accessories',
        'name' => 'AirPods Pro (2nd Gen)',
        'price' => 350000,
        'image' => 'https://images.unsplash.com/photo-1588422333078-44ad73367bcb?q=80&w=400',
        'desc' => 'Magic like you\'ve never heard. Active Noise Cancellation.'
    ],
    [
        'id' => 'g5',
        'category' => 'Samsung',
        'name' => 'Samsung Z Fold 5',
        'price' => 2800000,
        'image' => 'https://images.unsplash.com/photo-1690983321528-76503930b8b5?q=80&w=400',
        'desc' => 'The ultimate 7.6" main display. Expansive, immersive viewing.'
    ],
    [
        'id' => 'g6',
        'category' => 'Laptops',
        'name' => 'Dell XPS 15',
        'price' => 3200000,
        'image' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?q=80&w=400',
        'desc' => 'Stunning 4K OLED display and powerful performance.'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Premium Gadgets | Agrilink Member</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="theme.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Paychangu popup script removed -->
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #264653;
      --accent: #f4a261;
      --light: #f8f9fa;
      --dark: #0d1b2a;
      --glass: rgba(255, 255, 255, 0.9);
      --shadow: 0 8px 32px rgba(0,0,0,0.1);
    }

    body {
      background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
      font-family: 'Inter', sans-serif;
      margin: 0;
      color: var(--dark);
    }

    .dashboard-container {
      display: grid;
      grid-template-columns: 280px 1fr;
      min-height: 100vh;
    }

    /* Sidebar Styles */
    .sidebar {
      background: var(--secondary);
      color: white;
      padding: 2rem 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }

    .brand {
      font-size: 1.8rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--primary);
      text-decoration: none;
    }

    .nav-menu {
      list-style: none;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .nav-item a {
      color: #cbd5e0;
      text-decoration: none;
      padding: 12px 16px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: all 0.3s ease;
    }

    .nav-item.active a, .nav-item a:hover {
      background: rgba(255,255,255,0.1);
      color: white;
      transform: translateX(5px);
    }

    .nav-item.active a {
      background: var(--primary);
    }

    /* Main Content */
    .main-content {
      padding: 2.5rem;
      overflow-y: auto;
    }

    .header-box {
      background: var(--glass);
      backdrop-filter: blur(10px);
      padding: 1.5rem 2.5rem;
      border-radius: 20px;
      box-shadow: var(--shadow);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2.5rem;
    }

    .welcome-text h1 {
      margin: 0;
      font-size: 1.8rem;
      font-weight: 700;
    }

    .welcome-text p {
      margin: 5px 0 0;
      color: #666;
    }

    /* Shop Grid */
    .section-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }

    .section-title h2 {
      font-size: 2rem;
      font-weight: 800;
      color: var(--secondary);
      position: relative;
    }

    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: -6px;
      left: 0;
      width: 50px;
      height: 4px;
      background: var(--primary);
      border-radius: 2px;
    }

    .gadget-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 2rem;
    }

    .gadget-card {
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      border: 1px solid rgba(0,0,0,0.05);
      display: flex;
      flex-direction: column;
    }

    .gadget-card:hover {
      transform: translateY(-12px);
      box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }

    .gadget-image {
      width: 100%;
      height: 220px;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .gadget-card:hover .gadget-image {
      transform: scale(1.05);
    }

    .gadget-info {
      padding: 1.5rem;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .gadget-category {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--primary);
      letter-spacing: 1px;
      margin-bottom: 8px;
    }

    .gadget-name {
      font-size: 1.25rem;
      font-weight: 700;
      margin-bottom: 12px;
      color: var(--secondary);
    }

    .gadget-desc {
      font-size: 0.9rem;
      color: #666;
      line-height: 1.5;
      margin-bottom: 1.5rem;
    }

    .gadget-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: auto;
      padding-top: 1.5rem;
      border-top: 1px solid #f0f0f0;
    }

    .gadget-price {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--secondary);
    }

    .btn-buy {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
    }

    .btn-buy:hover {
      background: var(--secondary);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
    }

    /* Polling Overlay */
    .polling-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.8);
      backdrop-filter: blur(8px);
      z-index: 9999;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: white;
      text-align: center;
    }

    .loader {
      width: 48px;
      height: 48px;
      border: 5px solid #FFF;
      border-bottom-color: var(--primary);
      border-radius: 50%;
      display: inline-block;
      box-sizing: border-box;
      animation: rotation 1s linear infinite;
      margin-bottom: 20px;
    }

    @keyframes rotation {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="member.php" class="brand">
      <i class="fa-solid fa-seedling"></i>
      <span>Agrilink</span>
    </a>
    <ul class="nav-menu">
      <li class="nav-item"><a href="member.php"><i class="fa-solid fa-house"></i> Overview</a></li>
      <li class="nav-item"><a href="Hives.php"><i class="fa-solid fa-box-archive"></i> My Hives</a></li>
      <li class="nav-item"><a href="finances.php"><i class="fa-solid fa-wallet"></i> Finances</a></li>
      <li class="nav-item active"><a href="payment_gadgets.php"><i class="fa-solid fa-mobile-screen"></i> Gadget Shop</a></li>
      <li class="nav-item"><a href="profile.php"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
      <li class="nav-item" style="margin-top:auto"><a href="logout.php" style="color:#ff6b6b"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <div class="header-box">
      <div class="welcome-text">
        <h1>Premium Gadget Portal</h1>
        <p>Enhance your farming efficiency with modern technology.</p>
      </div>
      <div class="user-profile">
        <img src="<?= '../admin/' . ($user['profile_picture'] ?? 'uploads/profile_pictures/default.png') ?>" 
             alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary);">
      </div>
    </div>

    <div class="section-title">
      <h2>Featured Devices</h2>
      <div class="filter-pills">
        <!-- Add filtering logic if needed -->
      </div>
    </div>

    <div class="gadget-grid">
      <?php foreach ($gadgets as $g): ?>
      <div class="gadget-card">
        <img src="<?= $g['image'] ?>" alt="<?= $g['name'] ?>" class="gadget-image">
        <div class="gadget-info">
          <div class="gadget-category"><?= $g['category'] ?></div>
          <div class="gadget-name"><?= $g['name'] ?></div>
          <p class="gadget-desc"><?= $g['desc'] ?></p>
          <div class="gadget-footer">
            <div class="gadget-price">MWK <?= number_format($g['price']) ?></div>
            <button class="btn-buy" onclick="initiatePurchase('<?= $g['id'] ?>', '<?= addslashes($g['name']) ?>', <?= $g['price'] ?>)">
              <i class="fa-solid fa-cart-shopping"></i> Buy Now
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<script>
function initiatePurchase(id, name, amount) {
    Swal.fire({
        icon: 'info',
        title: 'Purchase Inquiry',
        text: `To purchase the ${name} (MWK ${amount.toLocaleString()}), please contact the Agrilink Cooperative administration office.`,
        confirmButtonColor: '#2a9d8f'
    });
}
</script>

</body>
</html>
