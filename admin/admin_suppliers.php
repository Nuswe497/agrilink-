<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'treasurer'])) {
    header('Location: login.php');
    exit;
}

// Fetch Supplier Statistics
$totalSuppliers = $conn->query("SELECT COUNT(*) as count FROM users WHERE stakeholder_type = 'supplier' AND role = 'external'")->fetch_assoc()['count'] ?? 0;
$totalInventoryItems = $conn->query("SELECT COUNT(*) as count FROM supplier_stock WHERE quantity > 0")->fetch_assoc()['count'] ?? 0;
$pendingStockPurchases = $conn->query("SELECT COUNT(*) as count FROM purchases WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;

// Fetch All Suppliers
$result = $conn->query("
    SELECT u.user_id, u.full_name, u.email, u.payout_phone, u.payout_operator, u.status,
    (SELECT COUNT(*) FROM supplier_stock ss WHERE ss.supplier_id = u.user_id AND ss.quantity > 0) as stock_count,
    (SELECT COALESCE(SUM(total_price), 0) FROM purchases p WHERE p.supplier_id = u.user_id AND p.status = 'completed') as total_payouts
    FROM users u 
    WHERE u.role = 'external' AND u.stakeholder_type = 'supplier'
    ORDER BY stock_count DESC, u.full_name ASC
");
$suppliers = $result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Supplier Hub | Agrilink Portal</title>
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

        .header-nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

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
        
        .header-nav a.active { color: var(--secondary); }

        .notif-badge {
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 2px;
            border: 2px solid var(--primary);
        }

        /* ── Content ── */
        main {
            flex: 1;
            padding: 3rem 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .page-header h1 { font-size: 2.2rem; font-weight: 800; color: var(--primary); }
        .page-header p { color: var(--text-soft); font-size: 1.1rem; }

        /* ── Stats ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
            position: relative;
            overflow: hidden;
        }

        .stat-card i {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 5rem;
            opacity: 0.03;
            color: var(--primary);
        }

        .stat-card h3 { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-soft); margin-bottom: 0.5rem; }
        .stat-value { font-size: 2.2rem; font-weight: 800; color: var(--dark); }

        /* ── Table Container ── */
        .table-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 1.2rem 1.5rem; text-align: left; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-soft); border-bottom: 2px solid var(--border); }
        td { padding: 1.5rem; border-bottom: 1px solid var(--border); vertical-align: middle; }

        .supplier-name { font-weight: 700; color: var(--dark); font-size: 1.05rem; }
        .supplier-email { font-size: 0.9rem; color: var(--text-soft); display: block; }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .badge-stock { background: #e0f2fe; color: #0369a1; }
        .badge-none { background: #f1f5f9; color: #64748b; }

        .payout-info { font-size: 0.9rem; font-weight: 600; }
        .payout-info i { color: var(--primary); width: 20px; }

        .action-btn {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .action-btn:hover { background: var(--dark); transform: translateY(-2px); }

        /* ── Footer ── */
        .footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding:3rem;
            margin-top: auto;
            border-top: 5px solid var(--secondary);
        }

        .footer a { color: var(--secondary); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
    <header class="header">
        <div class="header-logo">
            <img src="../assets/logo.png" alt="Agrilink Logo" style="height: 40px; width: auto; margin-right: 10px;">
            AGRILINK <?php echo strtoupper($_SESSION['role']); ?>
        </div>
        <nav class="header-nav">
            <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'admin.php' : '../treasure/treasure.php'; ?>"><i class="fa-solid fa-house"></i> Home</a>
            <a href="admin_suppliers.php" class="active"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
            <a href="admin_stakeholder.php"><i class="fa-solid fa-handshake"></i> Stakeholders</a>
            <a href="logout.php" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        </nav>
    </header>

    <main>
        <div class="page-header">
            <div>
                <h1>Supplier Hub</h1>
                <p>Manage agricultural inventory and purchase operations.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fa-solid fa-users"></i>
                <h3>Total Suppliers</h3>
                <div class="stat-value"><?php echo $totalSuppliers; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: var(--secondary);">
                <i class="fa-solid fa-warehouse"></i>
                <h3>Active Products</h3>
                <div class="stat-value"><?php echo $totalInventoryItems; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ef4444;">
                <i class="fa-solid fa-clock"></i>
                <h3>Pending Purchases</h3>
                <div class="stat-value"><?php echo $pendingStockPurchases; ?></div>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Supplier Partner</th>
                        <th>Payout Destination</th>
                        <th>Inventory Status</th>
                        <th>Total Volume</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($suppliers)): ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <span class="supplier-name"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                    <span class="supplier-email"><?php echo htmlspecialchars($s['email']); ?></span>
                                </td>
                                <td>
                                    <div class="payout-info">
                                        <i class="fa-solid fa-mobile-screen"></i> 
                                        <?php echo strtoupper($s['payout_operator'] ?: 'airtel'); ?>: 
                                        <?php echo $s['payout_phone'] ?: 'No phone set'; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $s['stock_count'] > 0 ? 'badge-stock' : 'badge-none'; ?>">
                                        <?php echo $s['stock_count']; ?> Items Available
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--primary);">MK <?php echo number_format($s['total_payouts']); ?></div>
                                </td>
                                <td>
                                    <a href="view_supplier_stock.php?id=<?php echo $s['user_id']; ?>" class="action-btn">
                                        <i class="fa-solid fa-eye"></i> View Stock
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-soft);">
                                No suppliers found in the system.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
    </footer>
</body>
</html>

