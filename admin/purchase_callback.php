<?php
session_start();
require 'db.php';
require '../paychangu_checkout_helper.php';
require '../paychangu_transfer_helper.php';

// Verification check
$tx_ref = $_GET['tx_ref'] ?? $_GET['txid'] ?? null;
$status = $_GET['status'] ?? 'failed';

if (!$tx_ref) {
    die("Invalid transaction response.");
}

// 1. Verify with Paychangu API
$verification = verifyPaychanguTransaction($tx_ref);

if (isset($verification['status']) && $verification['status'] === 'success') {
    // Payment is verified
    $conn->begin_transaction();

    try {
        // 2. Fetch the pending purchase
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE paychangu_ref = ? AND status = 'pending'");
        $stmt->bind_param("s", $tx_ref);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($purchase) {
            $purchase_id = $purchase['id'];
            $supplier_id = $purchase['supplier_id'];
            $stock_id = $purchase['item_id'];
            $amount_to_payout = (float)$purchase['total_price'];
            $qty_bought = $purchase['quantity'];

            // 3. Update Purchase Status
            $stmt = $conn->prepare("UPDATE purchases SET status = 'completed' WHERE id = ?");
            $stmt->bind_param("i", $purchase_id);
            $stmt->execute();
            $stmt->close();

            // 4. Update Stock Quantity
            $stmt = $conn->prepare("UPDATE supplier_stock SET quantity = quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $qty_bought, $stock_id);
            $stmt->execute();
            $stmt->close();

            // 5. Trigger Automated Payout to Supplier
            // Fetch Supplier Payout Details
            $stmt = $conn->prepare("SELECT full_name, email, payout_phone, payout_operator FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $supplier_id);
            $stmt->execute();
            $supplier = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($supplier && !empty($supplier['payout_phone']) && !empty($supplier['payout_operator'])) {
                $payout = sendPaychanguTransfer(
                    $supplier['email'],
                    $amount_to_payout,
                    $supplier['payout_phone'],
                    $supplier['payout_operator']
                );

                // Record payout in history
                $payout_status = $payout['success'] ? 'completed' : 'failed';
                recordTransfer(
                    $conn,
                    $supplier_id,
                    $amount_to_payout,
                    $payout['transaction_id'],
                    $payout_status,
                    'mobile_money',
                    $payout['success'] ? null : $payout['message']
                );
            }

            // 6. Notifications
            $item_stmt = $conn->prepare("SELECT item_name FROM supplier_stock WHERE id = ?");
            $item_stmt->bind_param("i", $stock_id);
            $item_stmt->execute();
            $item_res = $item_stmt->get_result()->fetch_assoc();
            $item_name = $item_res['item_name'] ?? 'Stock Item';
            $item_stmt->close();
            
            // To Supplier
            $notif_title = "Payment Received: " . $item_name;
            $notif_msg = "The cooperative has purchased $qty_bought units of $item_name. A payout of MK " . number_format($amount_to_payout) . " has been initiated to your mobile wallet.";
            $target_supplier = "supplier_" . $supplier_id;
            
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $notif_title, $notif_msg, $target_supplier);
            $stmt->execute();
            $stmt->close();
            
            // To Admin
            $notif_title = "Purchase Successful";
            $notif_msg = "Successfully purchased $qty_bought units of $item_name from " . $supplier['full_name'] . ". Supplier payout triggered.";
            $target_admin = 'admin';
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $notif_title, $notif_msg, $target_admin);
            $stmt->execute();
            $stmt->close();

            // To Treasurer
            $target_treasurer = 'treasurer';
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $notif_title, $notif_msg, $target_treasurer);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $msg = "Success! Payment confirmed and supplier payout initiated.";
        } else {
            $msg = "Purchase already processed or not found.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Critical Error: " . $e->getMessage();
    }
} else {
    $msg = "Payment verification failed. Please contact support.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Result | Agrilink</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 3rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; max-width: 500px; }
        .icon { font-size: 4rem; margin-bottom: 1.5rem; }
        .success { color: #2a9d8f; }
        .error { color: #ef4444; }
        h1 { margin-bottom: 1rem; color: #1e293b; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
        .btn { background: #2a9d8f; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn:hover { background: #1e293b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon <?php echo ($status === 'success' || (isset($verification['status']) && $verification['status'] === 'success')) ? 'success' : 'error'; ?>">
            <?php echo ($status === 'success' || (isset($verification['status']) && $verification['status'] === 'success')) ? '✓' : '✕'; ?>
        </div>
        <h1>Notification</h1>
        <p><?php echo htmlspecialchars($msg); ?></p>
        <a href="admin.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
