<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'external' || $_SESSION['stakeholder_type'] != 'buyer') {
    header("Location: ../login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$buyer_info = $conn->query("SELECT full_name, email FROM users WHERE user_id = $buyer_id")->fetch_assoc();

if (!isset($_GET['product_id'])) {
    $_SESSION['msg_error'] = "Invalid request.";
    header("Location: buyer_dashboard.php");
    exit;
}

$product_id = (int)$_GET['product_id'];

// If tx_ref is present, it means the payment was completed and we are returning
if (isset($_GET['tx_ref'])) {
    $tx_ref = $_GET['tx_ref'];
    header("Location: buyer_dashboard.php?payment=success&product_id=$product_id&tx_ref=$tx_ref");
    exit;
}

// Fetch product
$product = $conn->query("SELECT * FROM products WHERE product_id = $product_id AND status = 'available' AND stock > 0")->fetch_assoc();

if (!$product) {
    $_SESSION['msg_error'] = "Product is not available or out of stock.";
    header("Location: buyer_dashboard.php");
    exit;
}

// Save pending purchase
$_SESSION['pending_purchase'] = [
    'product_id' => $product['product_id'],
    'amount'     => $product['price'],
    'tx_ref'     => 'BUY-' . uniqid() . '-' . time(),
    'name'       => $product['name']
];

// Base URLs
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
          . '://' . $_SERVER['HTTP_HOST'] 
          . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$success_url = $base_url . "/buyer_dashboard.php?payment=success";
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://in.paychangu.com/js/popup.js"></script>
</head>
<body>

    <script>
        // Open Paychangu popup instantly with no loading screen
        PaychanguCheckout({
            "public_key": "PUB-AfPBVlqDNeRU5hB0FtHQlVozVgVnfR1b",
            "tx_ref": "<?= $_SESSION['pending_purchase']['tx_ref'] ?>",
            "amount": <?= $product['price'] ?>,
            "currency": "MWK",
            "callback_url": "<?= $success_url ?>",
            "return_url": "<?= $success_url ?>",
            "customer": {
                "email": "<?= addslashes($buyer_info['email']) ?>",
                "first_name": "<?= addslashes(explode(' ', $buyer_info['full_name'])[0] ?? 'Buyer') ?>",
                "last_name": "<?= addslashes(implode(' ', array_slice(explode(' ', $buyer_info['full_name']), 1)) ?: 'Buyer') ?>"
            },
            "customization": {
                "title": "Agrilink Honey Purchase",
                "description": "Payment for <?= addslashes($product['name']) ?>"
            },
            "callback": function(response) {
                window.location.href = "<?= $success_url ?>&product_id=<?= $product_id ?>&tx_ref=" + (response.tx_ref || response.transaction_id || "");
            },
            "onClose": function() {
                window.location.href = "buyer_dashboard.php";
            }
        });
    </script>

  <footer style="background: #2a9d8f; color: white; text-align: center; padding: 1.5rem; margin-top: auto; font-size: 0.95rem;">
    &copy; 2026 Agrilink Cooperative | Premium Organic Products
  </footer>
</body>
</html>
