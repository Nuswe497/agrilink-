<?php
session_start();
require 'db.php';
require '../paychangu_transfer_helper.php';

// Check if user is logged in as admin or treasurer
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'treasurer')) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_stakeholder.php");
    exit;
}

$stock_id = (int)$_POST['stock_id'];
$supplier_id = (int)$_POST['supplier_id'];
$buyer_id = $_SESSION['user_id'];

// Fetch Item Details
$stmt = $conn->prepare("SELECT * FROM supplier_stock WHERE id = ? AND supplier_id = ? AND quantity > 0");
$stmt->bind_param("ii", $stock_id, $supplier_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    die("Item not found or out of stock.");
}

// Fetch Supplier Info
$stmt = $conn->prepare("SELECT full_name, email, payout_phone, payout_operator FROM users WHERE user_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier || empty($supplier['payout_phone']) || empty($supplier['payout_operator'])) {
    die("Error: This supplier has not configured their payout mobile money details yet. They need to update their profile first.");
}

// Calculation
$total_cost = (float)$item['price']; // Purchasing 1 unit for now as per UI flow
$quantity = 1;

$conn->begin_transaction();

try {
    // 1. Send Payout via Paychangu Payout API (deducts from Coop balance, sends to Supplier phone)
    $payout = sendPaychanguTransfer(
        $supplier['email'],
        $total_cost,
        $supplier['payout_phone'],
        $supplier['payout_operator']
    );

    if (!$payout['success']) {
        throw new Exception("Paychangu Payout Failed: " . $payout['message']);
    }

    // 2. Record Purchase (Skip Checkout, go straight to completed)
    $status = 'completed';
    $processing_fee = 0.00; // Cooperative absorbs fees for direct payouts
    $stmt = $conn->prepare("INSERT INTO purchases (buyer_id, supplier_id, item_id, quantity, total_price, processing_fee, paychangu_ref, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiddss", $buyer_id, $supplier_id, $stock_id, $quantity, $total_cost, $processing_fee, $payout['transaction_id'], $status);
    $stmt->execute();
    $stmt->close();

    // 3. Update Stock
    $stmt = $conn->prepare("UPDATE supplier_stock SET quantity = quantity - ? WHERE id = ?");
    $stmt->bind_param("ii", $quantity, $stock_id);
    $stmt->execute();
    $stmt->close();

    // 4. Record Transfer History
    recordTransfer(
        $conn,
        $supplier_id,
        $total_cost,
        $payout['transaction_id'],
        'completed',
        'mobile_money',
        null
    );

    // 5. Notifications
    $item_name = $item['item_name'];
    
    // To Supplier
    $notif_title = "Payment Received: " . $item_name;
    $notif_msg = "The cooperative has purchased $quantity units of $item_name. A payout of MK " . number_format($total_cost) . " has been sent directly to your mobile wallet.";
    $target_supplier = "supplier_" . $supplier_id;
    
    $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $notif_title, $notif_msg, $target_supplier);
    $stmt->execute();
    $stmt->close();
    
    // To Admin
    $notif_title = "Purchase Successful";
    $notif_msg = "Successfully purchased $quantity units of $item_name from " . $supplier['full_name'] . ". Funds wired from cooperative balance to supplier.";
    $target_admin = 'admin';
    $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $notif_title, $notif_msg, $target_admin);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Show success page
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Purchase Successful</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: \'Outfit\', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: white; padding: 3rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; max-width: 500px; }
            .icon { font-size: 4rem; margin-bottom: 1.5rem; color: #2a9d8f; }
            h1 { margin-bottom: 1rem; color: #1e293b; }
            p { color: #64748b; line-height: 1.6; margin-bottom: 2rem; font-size: 1.1rem; }
            .btn { background: #2a9d8f; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 12px; font-weight: 700; transition: 0.3s; display: inline-block; }
            .btn:hover { background: #1e293b; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">✓</div>
            <h1>Purchase Complete!</h1>
            <p>The inventory has been secured, and the funds have been successfully wired from the Cooperative balance straight to the supplier\'s mobile wallet.</p>
            <a href="admin.php" class="btn">Back to Dashboard</a>
        </div>
    </body>
    </html>';
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("Transaction Failed: " . $e->getMessage());
}
?>
