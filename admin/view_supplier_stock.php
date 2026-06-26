<?php
session_start();
require 'db.php';
require 'notif_count.php';

// Check if user is logged in as admin or treasurer
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'treasurer')) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: admin_stakeholder.php');
    exit;
}

// Fetch Supplier Info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND stakeholder_type = 'supplier'");
$stmt->bind_param("i", $id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    header('Location: admin_stakeholder.php');
    exit;
}

// Fetch Supplier Stock
$stmt = $conn->prepare("SELECT * FROM supplier_stock WHERE supplier_id = ? AND quantity > 0 ORDER BY date_added DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$stock_result = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Supplier Inventory | Agrilink Portal</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2a9d8f;
            --secondary: #f4a261;
            --dark: #1e293b;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #2c3e50;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --shadow: 0 10px 30px rgba(42, 157, 143, 0.05);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            color: var(--text);
            background: linear-gradient(135deg, #fffbe6, #fef6d3);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;
        }

        /* ── Header ── */
        .header {
            background: var(--primary);
            color: white;
            padding: 1rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .header-logo {
            font-size: 1.4rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

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
        .header-nav a:hover, .header-nav a.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
        }

        /* ── Main Content ── */
        main {
            flex: 1;
            padding: 3rem 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .supplier-hero {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 6px solid var(--secondary);
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .supplier-info h1 { font-size: 2rem; color: var(--primary); margin-bottom: 5px; }
        .supplier-info p { color: var(--text-soft); font-size: 1.1rem; }

        .payout-badge {
            background: #f1f5f9;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            text-align: right;
            border: 1px solid var(--border);
        }

        .payout-badge small { display: block; color: var(--text-soft); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-bottom: 5px; }
        .payout-badge span { font-weight: 800; color: var(--dark); font-size: 1.1rem; }

        /* ── Grid ── */
        .stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .stock-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stock-card:hover { transform: translateY(-8px); border-color: var(--primary); }

        .card-body { padding: 1.8rem; }
        .card-body h3 { font-size: 1.3rem; margin-bottom: 10px; color: var(--dark); }
        .card-body p { color: var(--text-soft); font-size: 0.95rem; min-height: 48px; margin-bottom: 20px; }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .price-val { font-size: 1.5rem; font-weight: 800; color: var(--primary); }
        .qty-val { font-weight: 700; color: var(--text-soft); }

        .btn-purchase {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 1.2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-purchase:hover { background: var(--dark); }

        .footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 3rem;
            margin-top: auto;
            border-top: 5px solid var(--secondary);
        }
    </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
    <!-- System Header -->
    <header class="header">
        <div class="header-logo">
            <img src="../assets/logo.png" alt="Agrilink Logo" style="height: 40px; width: auto; margin-right: 10px;">
            AGRILINK ADMIN
        </div>
        <nav class="header-nav">
            <a href="admin.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
            <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
            <a href="admin_finances.php"><i class="fas fa-money-bill"></i> Finances</a>
            <a href="admin_stakeholder.php" class="active"><i class="fas fa-handshake"></i> Stakeholders</a>
            <a href="admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
            <a href="logout.php" title="Logout" style="margin-left: 10px;"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        </nav>
    </header>

    <main>
        <div class="supplier-hero">
            <div class="supplier-info">
                <p>Browsing Inventory for</p>
                <h1><?php echo htmlspecialchars($supplier['full_name']); ?></h1>
                <p><?php echo htmlspecialchars($supplier['email']); ?></p>
            </div>
            <div class="payout-badge">
                <small>Supplier Payout Dest.</small>
                <span><?php echo strtoupper($supplier['payout_operator'] ?? 'N/A'); ?>: <?php echo $supplier['payout_phone'] ?? 'Unset'; ?></span>
            </div>
        </div>

        <?php if ($stock_result->num_rows > 0): ?>
            <div class="stock-grid">
                <?php while ($item = $stock_result->fetch_assoc()): ?>
                    <div class="stock-card">
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <p><?php echo htmlspecialchars($item['description'] ?: 'No description provided.'); ?></p>
                            
                            <div class="price-row">
                                <div class="price-val">MK <?php echo number_format($item['price'], 0); ?></div>
                                <div class="qty-val"><?php echo $item['quantity']; ?> Unit(s)</div>
                            </div>

                            <form action="process_stock_purchase.php" method="POST">
                                <input type="hidden" name="stock_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="supplier_id" value="<?php echo $id; ?>">
                                <button type="submit" class="btn-purchase">
                                    <i class="fa-solid fa-cart-shopping"></i> Purchase Now
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 5rem 0; color: var(--text-soft);">
                <i class="fa-solid fa-box-open" style="font-size: 4rem; opacity: 0.1; margin-bottom: 1.5rem; display: block;"></i>
                <h2>No active stock available.</h2>
                <p>This supplier hasn't listed any items for purchase yet.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <p>&copy; 2026 <a href="#">Agrilink Cooperative</a> | Administrative Management</p>
    </footer>
</body>
</html>
