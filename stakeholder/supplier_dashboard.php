 <?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'external' || $_SESSION['stakeholder_type'] != 'supplier') {
    header("Location: login.php");
    exit;
}

$supplier_id = $_SESSION['user_id'];

// Fetch supplier info
$supplier_info = $conn->query("SELECT * FROM users WHERE user_id = $supplier_id");
$supplier = $supplier_info->fetch_assoc();

// Fetch supplier stock
$stock_result = $conn->query("SELECT * FROM supplier_stock WHERE supplier_id = $supplier_id ORDER BY date_added DESC");
$total_items = $stock_result->num_rows;

// Fetch pending purchases
$pending_purchases = $conn->query("
    SELECT p.*, ss.item_name, u.full_name as buyer_name
    FROM purchases p
    JOIN supplier_stock ss ON p.item_id = ss.id
    JOIN users u ON p.buyer_id = u.user_id
    WHERE p.supplier_id = $supplier_id AND p.status = 'pending'
    ORDER BY p.purchase_date DESC
");

// Fetch completed purchases
$completed_purchases = $conn->query("
    SELECT p.*, ss.item_name, u.full_name as buyer_name
    FROM purchases p
    JOIN supplier_stock ss ON p.item_id = ss.id
    JOIN users u ON p.buyer_id = u.user_id
    WHERE p.supplier_id = $supplier_id AND p.status = 'completed'
    ORDER BY p.purchase_date DESC
    LIMIT 10
");

// Calculate total revenue
$revenue_result = $conn->query("
    SELECT SUM(total_price) as total_revenue 
    FROM purchases 
    WHERE supplier_id = $supplier_id AND status = 'completed'
");
$revenue_row = $revenue_result->fetch_assoc();
$total_revenue = $revenue_row['total_revenue'] ?? 0;

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    $purchase_id = (int)$_POST['purchase_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE purchases SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $purchase_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: supplier_dashboard.php");
    exit;
}

// Handle add stock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock'])) {
    $item_name = trim($_POST['item_name'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO supplier_stock (supplier_id, item_name, quantity, price, description) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Stock Prepare Error: " . $conn->error);
    }
    $stmt->bind_param("isids", $supplier_id, $item_name, $quantity, $price, $description);
    if (!$stmt->execute()) {
        die("Stock Execute Error: " . $stmt->error);
    }
    $stmt->close();
    
    // Add notifications for Admin and Treasurer separately
    $supplier_name = $supplier['full_name'];
    $notif_title = "New Stock: " . $item_name;
    $notif_msg = "Supplier $supplier_name has added $quantity units of '$item_name' to the inventory.";
    
    $stmt_notif = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
    if (!$stmt_notif) {
        die("Notif Prepare Error: " . $conn->error);
    }
    
    // Admin notification
    $role_admin = 'admin';
    $stmt_notif->bind_param("sss", $notif_title, $notif_msg, $role_admin);
    if (!$stmt_notif->execute()) {
        die("Notif Admin Execute Error: " . $stmt_notif->error);
    }
    
    // Treasurer notification
    $role_treasurer = 'treasurer';
    $stmt_notif->bind_param("sss", $notif_title, $notif_msg, $role_treasurer);
    if (!$stmt_notif->execute()) {
        die("Notif Treasurer Execute Error: " . $stmt_notif->error);
    }
    
    $stmt_notif->close();

    $_SESSION['success_msg'] = "Stock added successfully!";
    header("Location: supplier_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Supplier Hub | Agrilink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2a9d8f;
            --secondary: #f4a261;
            --dark: #2c3e50;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #2c3e50;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --radius: 12px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background-color: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* System Header */
        .navbar {
            background: var(--primary);
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
            padding: 0 2.5rem;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo {
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
        
        .logout-btn {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: 0.3s;
        }
        
        .logout-btn:hover { color: var(--secondary); transform: scale(1.1); }
        
        main {
            flex: 1;
            padding: 40px 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Rest of your custom dash styles but using var names from root */
        .welcome-hero { margin-bottom: 40px; }
        .welcome-hero h2 { font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-bottom: 8px; }
        .welcome-hero p { font-size: 1.1rem; color: var(--text-soft); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 30px; border-radius: var(--radius); box-shadow: var(--shadow); position: relative; overflow: hidden; transition: transform 0.3s ease; border: 1px solid var(--border); }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card::after { content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: var(--secondary); }
        .stat-card i { position: absolute; right: 20px; bottom: 20px; font-size: 3rem; opacity: 0.05; color: var(--primary); }
        .stat-card h3 { font-size: 0.85rem; text-transform: uppercase; color: var(--text-soft); margin-bottom: 15px; letter-spacing: 1px; font-weight: 700; }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: var(--dark); }

        .dashboard-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; }
        .panel { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 35px; border: 1px solid var(--border); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; }
        .panel-header h2 { font-size: 1.4rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 12px; }
        .panel-header h2 i { color: var(--secondary); }

        .order-list, .stock-list { display: flex; flex-direction: column; gap: 15px; }
        .order-card, .stock-card { background: #f8fafc; padding: 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s ease; border-left: 4px solid var(--primary); border: 1px solid var(--border); border-left: 4px solid var(--primary); }
        .order-card:hover, .stock-card:hover { border-left-color: var(--secondary); background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .item-main-info b { font-size: 1.1rem; color: var(--dark); display: block; margin-bottom: 5px; }
        .item-main-info p { font-size: 0.9rem; color: var(--text-soft); }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fee2e2; color: #991b1b; }
        .badge-success { background: #dcfce7; color: #166534; }

        .btn-mini { padding: 8px 15px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; background: var(--primary); color: white; }
        .btn-mini:hover { background: var(--dark); }

        .empty-state { text-align: center; padding: 50px 0; color: var(--text-soft); }
        .empty-state i { font-size: 4rem; opacity: 0.2; margin-bottom: 15px; display: block; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 450px; padding: 35px; border-radius: var(--radius); box-shadow: 0 20px 40px rgba(0,0,0,0.2); position: relative; }
        .modal-close { position: absolute; top: 20px; right: 20px; font-size: 1.5rem; cursor: pointer; color: var(--text-soft); }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 10px; font-size: 1rem; }
        
        .btn-submit { width: 100%; padding: 15px; background: var(--secondary); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #e89c4f; transform: translateY(-2px); }

        .footer { background: var(--primary); color: white; text-align: center; padding: 25px 30px; margin-top: auto; font-size: 0.95rem; border-top: 5px solid var(--secondary); }
        .footer a { color: var(--secondary); text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include 'glow_bg.php'; ?>
    
    <!-- System Header -->
    <nav class="navbar">
        <div class="nav-inner">
            <a href="../index.php" class="logo">
                <div class="logo-icon-wrap">
                    <i class="fas fa-bee"></i>
                </div>
                <div class="logo-text">Agri<span>link</span></div>
            </a>
            <div class="header-nav">
                <a href="supplier_dashboard.php" class="active"><i class="fa-solid fa-house"></i> Home</a>
                <a href="supplier_profile.php"><i class="fa-solid fa-user-circle"></i> Profile</a>
                <a href="../logout.php" class="logout-btn" title="Logout"><i class="fa-solid fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <main>
        <section class="welcome-hero">
            <h2>Welcome, <?php echo explode(' ', htmlspecialchars($supplier['full_name']))[0]; ?>!</h2>
            <p>Your agricultural logistics and inventory management dashboard.</p>
        </section>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Unique Items</h3>
                <div class="stat-value"><?php echo $total_items; ?></div>
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div class="stat-card">
                <h3>Pending Orders</h3>
                <div class="stat-value"><?php echo $pending_purchases->num_rows; ?></div>
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-card">
                <h3>Fulfilled</h3>
                <div class="stat-value"><?php echo $completed_purchases->num_rows; ?></div>
                <i class="fa-solid fa-check-double"></i>
            </div>
            <div class="stat-card">
                <h3>Total Earnings</h3>
                <div class="stat-value">MWK <?php echo number_format($total_revenue, 0); ?></div>
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>

        <div class="dashboard-layout">
            <!-- Left Column: Pending Orders -->
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fa-solid fa-basket-shopping"></i> Pending Purchase Orders</h2>
                </div>
                
                <div class="order-list">
                    <?php if ($pending_purchases->num_rows > 0): ?>
                        <?php while ($order = $pending_purchases->fetch_assoc()): ?>
                            <div class="order-card">
                                <div class="item-main-info">
                                    <b><?php echo htmlspecialchars($order['item_name']); ?></b>
                                    <p><i class="fa-solid fa-user"></i> Buyer: <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                                    <p><i class="fa-solid fa-calendar"></i> <?php echo date('D, M d, Y', strtotime($order['purchase_date'])); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 800; font-size: 1.1rem; color: var(--primary); margin-bottom: 10px;">
                                        MWK <?php echo number_format($order['total_price'], 2); ?>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span class="badge badge-pending">Qty: <?php echo $order['quantity']; ?></span>
                                        <form method="POST">
                                            <input type="hidden" name="purchase_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <input type="hidden" name="update_order" value="1">
                                            <button type="submit" class="btn-mini btn-complete">Mark Shipped</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p>No pending orders. Your inventory is ready for market!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Stock Management & History -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-solid fa-warehouse"></i> My Inventory</h2>
                        <button class="btn-mini" style="padding: 10px 20px;" onclick="openModal()">+ Add Stock</button>
                    </div>
                    
                    <div class="stock-list">
                        <?php if ($stock_result->num_rows > 0): ?>
                            <?php $stock_result->data_seek(0); while ($item = $stock_result->fetch_assoc()): ?>
                                <div class="stock-card">
                                    <div class="item-main-info">
                                        <b><?php echo htmlspecialchars($item['item_name']); ?></b>
                                        <p>MWK <?php echo number_format($item['price'], 0); ?> / Unit</p>
                                    </div>
                                    <span class="badge <?php echo $item['quantity'] > 5 ? 'badge-success' : 'badge-pending'; ?>">
                                        Stock: <?php echo $item['quantity']; ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="empty-state">No stock found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-solid fa-history"></i> Recent Fulfilled</h2>
                    </div>
                    <div class="order-list">
                        <?php if ($completed_purchases->num_rows > 0): ?>
                            <?php while ($order = $completed_purchases->fetch_assoc()): ?>
                                <div class="order-card" style="border-left-color: var(--success); opacity: 0.8;">
                                    <div class="item-main-info">
                                        <b><?php echo htmlspecialchars($order['item_name']); ?></b>
                                        <p><?php echo htmlspecialchars($order['buyer_name']); ?></p>
                                    </div>
                                    <span class="badge badge-success">Completed</span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="empty-state" style="padding: 20px 0;">No history yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- System Footer -->
    <footer class="footer">
        <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
    </footer>

    <!-- Add Stock Modal -->
    <div class="modal" id="stockModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <div style="margin-bottom: 25px;">
                <h2 style="font-size: 1.8rem; color: var(--primary);">Add New Inventory</h2>
                <p style="color: var(--text-soft);">List your agricultural produce for the cooperative.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Item Name / Product</label>
                    <input type="text" name="item_name" placeholder="e.g. Bee Suits, Honey Jars" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Available Quantity</label>
                        <input type="number" name="quantity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Price per Unit (MWK)</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Brief Description</label>
                    <textarea name="description" rows="3" placeholder="Condition, quality details..."></textarea>
                </div>
                <button type="submit" name="add_stock" class="btn-submit">List Product to Warehouse</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('stockModal');
        function openModal() { modal.style.display = 'flex'; }
        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) closeModal(); }
    </script>
</body>
</html>

