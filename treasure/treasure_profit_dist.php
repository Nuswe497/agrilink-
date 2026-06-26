<?php
session_start();
require '../db.php';
require_once '../mailer_helper.php';
require_once '../paychangu_transfer_helper.php';

// ─── Helper: Calculate Total Payout (for Paychangu balance check) ───────────
function calculateTotalPayout($members, $profit_pool, $total_coop_shares, $total_coop_contribs, $additional_amounts) {
    $total = 0.0;
    
    foreach ($members as $member) {
        $uid = $member['user_id'];
        
        $multiplier = (isset($member['total_hives']) && $member['total_hives'] >= 5) ? 1.0 : 0.5;
        $eff_shares = $member['total_shares'] * $multiplier;
        $eff_contrib = $member['total_contribution'] * $multiplier;
        
        $share_profit = $total_coop_shares > 0 
            ? ($eff_shares / $total_coop_shares) * ($profit_pool * 0.5) 
            : 0;
            
        $contrib_profit = $total_coop_contribs > 0 
            ? ($eff_contrib / $total_coop_contribs) * ($profit_pool * 0.5) 
            : 0;
        
        $base_profit  = $share_profit + $contrib_profit;
        $extra        = floatval($additional_amounts[$uid] ?? 0);
        $final_payout = $base_profit + $extra;
        
        if ($final_payout > 0) {
            $total += $final_payout;
        }
    }
    
    return $total;
}

// ─── Stub: Get Paychangu Balance ────────────────────────────────────────────
function getPaychanguBalance() {
    // TODO: Replace this with your real Paychangu API balance check later
    return [
        'success' => true,
        'balance' => 1000000.00,     // Change this value to test insufficient balance
        'message' => 'Balance retrieved successfully'
    ];
}

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'treasurer'])) {
    header("Location: login.php");
    exit;
}

// AUTO-MIGRATE: Check and create Paychangu payout schema if needed
try {
    $checkPhone = $conn->query("SHOW COLUMNS FROM users LIKE 'payout_phone'");
    if (!$checkPhone || $checkPhone->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN payout_phone VARCHAR(20) NULL");
    }

    $checkMethod = $conn->query("SHOW COLUMNS FROM users LIKE 'payout_method'");
    if (!$checkMethod || $checkMethod->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN payout_method ENUM('mobile_money', 'bank_transfer') DEFAULT 'mobile_money'");
    }

    $checkOperator = $conn->query("SHOW COLUMNS FROM users LIKE 'payout_operator'");
    if (!$checkOperator || $checkOperator->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN payout_operator ENUM('airtel', 'tnm') DEFAULT 'airtel'");
    }

    $checkTable = $conn->query("SHOW TABLES LIKE 'transfer_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        $conn->query("
            CREATE TABLE transfer_history (
                transfer_id      INT AUTO_INCREMENT PRIMARY KEY,
                user_id          INT NOT NULL,
                amount           DECIMAL(10,2) NOT NULL,
                transaction_id   VARCHAR(255) UNIQUE,
                status           ENUM('pending','completed','failed') DEFAULT 'pending',
                payment_method   ENUM('mobile_money','bank_transfer') DEFAULT 'mobile_money',
                created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                confirmation_date TIMESTAMP NULL,
                error_message    VARCHAR(500) NULL,
                INDEX (user_id),
                INDEX (status),
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )
        ");
    }
} catch (Exception $e) {
    // Schema migration failed silently
}

$message = '';
$error   = '';

// Fetch active members
$membersQuery = $conn->query("
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.payout_phone,
        u.payout_method,
        u.payout_operator,
        u.fee_status,
        COALESCE((SELECT SUM(s.shares)   FROM shares        s WHERE s.user_id = u.user_id), 0) AS total_shares,
        COALESCE((SELECT SUM(c.quantity) FROM contributions c WHERE c.user_id = u.user_id), 0) AS total_contribution,
        COALESCE((SELECT SUM(h.hive_count) FROM hives h WHERE h.user_id = u.user_id), 0) AS total_hives
    FROM users u
    WHERE u.role = 'member' AND u.status = 'active'
    ORDER BY u.full_name ASC
");
$members = $membersQuery->fetch_all(MYSQLI_ASSOC);

$total_coop_shares   = 0;
$total_coop_contribs = 0;
foreach ($members as $m) {
    $multiplier = ($m['total_hives'] >= 5) ? 1.0 : 0.5;
    $total_coop_shares   += ($m['total_shares'] * $multiplier);
    $total_coop_contribs += ($m['total_contribution'] * $multiplier);
}

// ─── Fetch Mobile Money Operators from Paychangu ────────────────────────────
$operatorsList = [];
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.paychangu.com/mobile-money/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'accept: application/json'
    ],
]);
$opRes = curl_exec($curl);
$opCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($opCode === 200 && $opRes) {
    $opData = json_decode($opRes, true);
    if (isset($opData['status']) && strtolower($opData['status']) === 'success' && !empty($opData['data'])) {
        $operatorsList = $opData['data'];
    }
}
// Fallback if API fails
if (empty($operatorsList)) {
    $operatorsList = [
        ['short_code' => 'airtel', 'name' => 'Airtel Money'],
        ['short_code' => 'tnm', 'name' => 'TNM Mpamba']
    ];
}

// ─── Handle Profit Distribution ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute_profits'])) {
    $profit_pool   = floatval($_POST['profit_amount'] ?? 0);
    $description   = trim($_POST['description'] ?? 'Profit Distribution');
    $use_paychangu = isset($_POST['use_paychangu_transfer']);

    $additional_amounts = $_POST['additional_amount'] ?? [];
    $payout_phones      = $_POST['payout_phone']      ?? [];
    $wallet_types       = $_POST['wallet_type']        ?? [];
    $operators          = $_POST['operator']           ?? [];

    if ($profit_pool <= 0) {
        $error = 'Total profit pool must be greater than 0.';
    } elseif ($total_coop_shares == 0 && $total_coop_contribs == 0) {
        $error = 'No shares or contributions found for active members.';
    } else {

        // Balance pre-check using Paychangu
        if ($use_paychangu) {
            $total_required = calculateTotalPayout(
                $members,
                $profit_pool,
                $total_coop_shares,
                $total_coop_contribs,
                $additional_amounts
            );

            $balanceCheck = getPaychanguBalance();

            if (!$balanceCheck['success']) {
                $error = 'Could not verify your Paychangu wallet balance: ' . $balanceCheck['message']
                       . '. Transfers have been aborted.';
            } elseif ($balanceCheck['balance'] < $total_required) {
                $error = 'Insufficient Paychangu balance — transfers aborted. '
                       . 'Required: <strong>MWK ' . number_format($total_required, 2) . '</strong>, '
                       . 'Available: <strong>MWK ' . number_format($balanceCheck['balance'], 2) . '</strong>. '
                       . 'Please top up your Paychangu wallet before proceeding.';
            }
        }

        if (empty($error)) {
            $all_success       = true;
            $processed_count   = 0;
            $total_distributed = 0;

            foreach ($members as $member) {
                $uid = $member['user_id'];

                $multiplier = ($member['total_hives'] >= 5) ? 1.0 : 0.5;
                $eff_shares = $member['total_shares'] * $multiplier;
                $eff_contrib = $member['total_contribution'] * $multiplier;

                $share_profit = $total_coop_shares > 0
                    ? ($eff_shares / $total_coop_shares) * ($profit_pool * 0.5)
                    : 0;
                $contrib_profit = $total_coop_contribs > 0
                    ? ($eff_contrib / $total_coop_contribs) * ($profit_pool * 0.5)
                    : 0;

                $base_profit  = $share_profit + $contrib_profit;
                $extra        = floatval($additional_amounts[$uid] ?? 0);
                $final_payout = $base_profit + $extra;

                if ($final_payout <= 0) continue;

                // Record profit
                $stmt = $conn->prepare("INSERT INTO profits (user_id, amount, distribution_date) VALUES (?, ?, NOW())");
                $stmt->bind_param('id', $uid, $final_payout);
                if (!$stmt->execute()) {
                    $all_success = false;
                    $stmt->close();
                    continue;
                }
                $stmt->close();

                $total_distributed += $final_payout;
                $processed_count++;

                // Email notification (from mailer_helper.php)
                $wallet  = $wallet_types[$uid] ?? 'Mobile Wallet';
                $mailErr = '';
                sendProfitNotification($conn, $uid, $final_payout, $wallet, $mailErr);

                // Paychangu transfer (from paychangu_transfer_helper.php)
                if ($use_paychangu && !empty($payout_phones[$uid])) {
                    $payout_phone  = $payout_phones[$uid];
                    $payout_method = $wallet_types[$uid] ?? 'mobile_money';
                    $operator      = $operators[$uid] ?? $member['payout_operator'] ?? 'airtel';

                    $transfer = sendPaychanguTransfer(
                        $member['email'],
                        $final_payout,
                        $payout_phone,
                        $operator
                    );

                    if ($transfer['success']) {
                        recordTransfer($conn, $uid, $final_payout, $transfer['transaction_id'], 'completed', $payout_method, null);
                    } else {
                        $errorMsgString = is_array($transfer['message']) ? json_encode($transfer['message']) : (string)$transfer['message'];
                        recordTransfer($conn, $uid, $final_payout, 'PC-FAIL-' . time() . '-' . $uid, 'failed', $payout_method, mb_substr($errorMsgString, 0, 490));
                        $all_success = false;
                    }
                }
            }

            if ($all_success) {
                $message = "Successfully distributed MWK " . number_format($total_distributed, 2) . " to $processed_count members.";
            } else {
                $error = "Distribution completed with some errors. Total distributed: MWK " . number_format($total_distributed, 2)
                       . ". Check the transfer history for details.";
            }
        }
    }
}

// Recent distributions
$recentDist    = $conn->query("SELECT p.*, u.full_name FROM profits p JOIN users u ON p.user_id = u.user_id ORDER BY p.distribution_date DESC LIMIT 15");
$distributions = $recentDist->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profit Distribution | Agrilink Treasurer</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --green:     #2a9d8f;
      --green-d:   #247b73;
      --orange:    #f4a261;
      --orange-d:  #e38b3a;
      --bg:        #f8fafc;
      --card:      #ffffff;
      --text:      #1e293b;
      --text-light:#475569;
      --border:    #e2e8f0;
      --shadow:    0 4px 14px rgba(0,0,0,0.07);
      --radius:    12px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Outfit', sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      line-height: 1.6;
    }
    .header {
      background: var(--green);
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

    .logo-area {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .logo-img {
      height: 50px;
      width: auto;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }
    .logo-text {
      color: white;
      font-weight: 800;
      font-size: 1.2rem;
      letter-spacing: 0.5px;
      text-transform: uppercase;
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

    .footer {
      background: #2a9d8f;
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 4rem;
      font-size: 0.95rem;
      border-top: 5px solid #f4a261;
      font-weight: 500;
    }

    .footer a {
      color: var(--orange);
      text-decoration: none;
      font-weight: 600;
    }
    main { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
    .card { background: white; border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow); margin-bottom: 30px; }
    h2 { color: var(--green-d); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
    .error   { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    label { display: block; font-weight: 600; margin-bottom: 8px; }
    input, select { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; }
    .btn { background: var(--green); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; }
    .btn:hover { background: var(--green-d); transform: translateY(-2px); }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th { text-align: left; background: #fafafa; padding: 15px; border-bottom: 2px solid var(--border); color: var(--green-d); }
    td { padding: 15px; border-bottom: 1px solid var(--border); }
    .calc-box { background: #f1f5f9; padding: 20px; border-radius: 10px; margin-bottom: 30px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .calc-stat { text-align: center; }
    .calc-stat span { display: block; color: var(--text-light); font-size: 0.9rem; }
    .calc-stat strong { font-size: 1.3rem; color: var(--green-d); }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .badge-shares { background: #e0f2fe; color: #0369a1; }
    .badge-contrib { background: #fef3c7; color: #92400e; }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>
  <!-- System Header -->
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
    <nav class="header-nav">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="../admin/admin.php"><i class="fas fa-home"></i> Home</a>
        <a href="../admin/admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
        <a href="../admin/admin_members.php"><i class="fas fa-users"></i> Members</a>
        <a href="../admin/admin_finances.php"><i class="fas fa-money-bill"></i> Finances</a>
        <a href="../admin/admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
        <a href="../admin/admin_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
      <?php else: ?>
        <a href="treasure.php"><i class="fas fa-home"></i> Home</a>
        <a href="../admin/admin_stakeholder.php"><i class="fas fa-handshake"></i> Stakeholders</a>
        <a href="treasure_profit_dist.php" class="active"><i class="fa-solid fa-users-gear"></i> Profit Distribution</a>
        <a href="treasure_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
      <?php endif; ?>
      <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </nav>
  </header>

  <main>
    <div class="card">
      <h2><i class="fas fa-calculator"></i> Profit Pool Settings</h2>
      <?php if($message): ?><div class="msg success"><?= $message ?></div><?php endif; ?>
      <?php if($error):   ?><div class="msg error"><?= $error ?></div><?php endif; ?>

      <form method="POST" id="distForm">
        <div class="form-row">
          <div>
            <label>Total Profit to Distribute (MWK)</label>
            <input type="number" name="profit_amount" id="profit_pool" step="0.01" required value="0" oninput="recalculate()">
          </div>
          <div>
            <label>Period / Description</label>
            <input type="text" name="description" value="Q1 Profit Payout <?= date('Y') ?>">
          </div>
        </div>

        <div class="calc-box">
          <div class="calc-stat">
            <span>Total Cooperative Shares</span>
            <strong><?= number_format($total_coop_shares) ?></strong>
          </div>
          <div class="calc-stat">
            <span>Total Member Contribs</span>
            <strong><?= number_format($total_coop_contribs, 2) ?></strong>
          </div>
          <div class="calc-stat">
            <span>Pool for Shares (50%)</span>
            <strong id="share_pool_val">MWK 0.00</strong>
          </div>
          <div class="calc-stat">
            <span>Pool for Contribs (50%)</span>
            <strong id="contrib_pool_val">MWK 0.00</strong>
          </div>
        </div>

        <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; border-radius: 8px; margin: 20px 0;">
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="use_paychangu_transfer" id="use_paychangu_transfer">
            <span><strong>Automate Payment Transfers via Paychangu</strong></span>
          </label>
          <small style="color: var(--text-light); display: block; margin-top: 8px;">
            <i class="fas fa-info-circle"></i> When enabled, funds will be automatically transferred from your Paychangu account to each member's registered phone number or bank account.
          </small>
        </div>

        <h2><i class="fas fa-users-viewfinder"></i> Member List &amp; Individual Payouts</h2>
        <table>
          <thead>
            <tr>
              <th>Member Name</th>
              <th>Shares / Contribs</th>
              <th>Reg Fee</th>
              <th>Calculated Base</th>
              <th>Additional / Bonus</th>
              <th>Payout Phone</th>
              <th>Operator</th>
              <th>Transfer Method</th>
              <th>Total Payout</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($members as $m):
                $uid    = $m['user_id'];
                $multiplier = ($m['total_hives'] >= 5) ? 1.0 : 0.5;
                $eff_shares = $m['total_shares'] * $multiplier;
                $eff_contrib = $m['total_contribution'] * $multiplier;
                
                $s_perc = $total_coop_shares   > 0 ? $eff_shares       / $total_coop_shares   : 0;
                $c_perc = $total_coop_contribs > 0 ? $eff_contrib / $total_coop_contribs : 0;
                $saved_operator = $m['payout_operator'] ?? 'airtel';
            ?>
              <tr data-user-id="<?= $uid ?>" data-shares-perc="<?= $s_perc ?>" data-contribs-perc="<?= $c_perc ?>">
                <td>
                  <strong><?= htmlspecialchars($m['full_name']) ?></strong><br>
                  <small><?= htmlspecialchars($m['phone'] ?: 'No Phone') ?></small>
                  <?php if ($m['total_hives'] < 5): ?>
                    <br><span class="badge" style="background:#fee2e2; color:#991b1b; font-size:0.7rem;"><i class="fa-solid fa-triangle-exclamation"></i> <?= $m['total_hives'] ?> Hives (50% Penalty)</span>
                  <?php else: ?>
                    <br><span class="badge" style="background:#dcfce7; color:#166534; font-size:0.7rem;"><i class="fa-solid fa-check-circle"></i> <?= $m['total_hives'] ?> Hives</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge badge-shares"><?= $m['total_shares'] ?> shares</span><br>
                  <span class="badge badge-contrib"><?= number_format($m['total_contribution'], 2) ?> contribs</span>
                </td>
                <td>
                  <span class="badge <?= $m['fee_status'] == 'paid' ? 'badge-shares' : 'badge-contrib' ?>"
                        style="background:<?= $m['fee_status'] == 'paid' ? '#dcfce7' : '#fee2e2' ?>;
                               color:<?= $m['fee_status'] == 'paid' ? '#166534' : '#991b1b' ?>;">
                    <i class="fas fa-<?= $m['fee_status'] == 'paid' ? 'check' : 'times' ?>"></i>
                    <?= strtoupper($m['fee_status']) ?>
                  </span>
                </td>
                <td><strong id="base_<?= $uid ?>">MWK 0.00</strong></td>
                <td>
                  <input type="number" name="additional_amount[<?= $uid ?>]" class="add_input"
                         step="0.01" value="0" oninput="recalculate()" style="width: 100px;">
                </td>
                <td>
                  <input type="text" name="payout_phone[<?= $uid ?>]"
                         placeholder="Phone number"
                         value="<?= htmlspecialchars($m['payout_phone'] ?? '') ?>"
                         style="width: 120px; padding: 8px; border: 1px solid var(--border); border-radius: 6px;">
                </td>
                <td>
                  <select name="operator[<?= $uid ?>]"
                          style="padding: 8px; border: 1px solid var(--border); border-radius: 6px;">
                    <?php foreach ($operatorsList as $opOption): ?>
                        <option value="<?= htmlspecialchars($opOption['short_code']) ?>" <?= $saved_operator === $opOption['short_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select name="wallet_type[<?= $uid ?>]"
                          style="padding: 8px; border: 1px solid var(--border); border-radius: 6px;">
                    <option value="mobile_money"  <?= ($m['payout_method'] ?? 'mobile_money') == 'mobile_money'  ? 'selected' : '' ?>>Mobile Money</option>
                    <option value="bank_transfer" <?= ($m['payout_method'] ?? '') == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                  </select>
                </td>
                <td><strong id="total_<?= $uid ?>" style="color: var(--green-d);">MWK 0.00</strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top: 30px; text-align: right;">
          <p style="margin-bottom: 15px; color: var(--text-light);">
            Total cooperative payout:
            <strong id="grand_total" style="font-size: 1.4rem; color: var(--orange-d);">MWK 0.00</strong>
          </p>
          <button type="submit" name="distribute_profits" class="btn" id="submit_btn"
                  onclick="return confirm('Ensure all payout details are correct. This will record distributions and notify members. If Paychangu transfers are enabled, funds will be sent immediately. Proceed?')">
            <i class="fas fa-paper-plane"></i> <span id="btn_text">Finalize and Notify Members</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Recent Distributions -->
    <div class="card">
      <h2><i class="fas fa-history"></i> Recent Distributions</h2>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Member</th>
            <th>Amount Distributed</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($distributions as $d): ?>
            <tr>
              <td><?= date('d M Y, H:i', strtotime($d['distribution_date'])) ?></td>
              <td><?= htmlspecialchars($d['full_name']) ?></td>
              <td><strong>MWK <?= number_format($d['amount'], 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paychangu Transfer History -->
    <?php
    $transfersQuery = $conn->query("
        SELECT th.*, u.full_name, u.email
        FROM transfer_history th
        JOIN users u ON th.user_id = u.user_id
        ORDER BY th.created_at DESC
        LIMIT 20
    ");
    if ($transfersQuery && $transfersQuery->num_rows > 0):
        $transfers = $transfersQuery->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="card">
      <h2><i class="fas fa-exchange-alt"></i> Paychangu Transfer Status</h2>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Member</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Transaction ID</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($transfers as $t): ?>
            <tr>
              <td><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
              <td><?= htmlspecialchars($t['full_name']) ?></td>
              <td><strong>MWK <?= number_format($t['amount'], 2) ?></strong></td>
              <td>
                <span class="badge" style="background: #e3e5e7; color: #323335;">
                  <?= ucfirst(str_replace('_', ' ', $t['payment_method'])) ?>
                </span>
              </td>
              <td>
                <span class="badge" style="
                  background: <?= $t['status'] === 'completed' ? '#dcfce7' : ($t['status'] === 'pending' ? '#fef3c7' : '#fee2e2') ?>;
                  color:      <?= $t['status'] === 'completed' ? '#166534' : ($t['status'] === 'pending' ? '#92400e' : '#991b1b') ?>;">
                  <?= ucfirst($t['status']) ?>
                </span>
              </td>
              <td style="max-width:250px; word-wrap:break-word;">
                <?php if ($t['status'] === 'failed' && !empty($t['error_message'])): ?>
                  <span style="color:#d32f2f; font-size:0.85em;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($t['error_message']) ?></span>
                <?php else: ?>
                  <code style="font-size: 0.85em;"><?= htmlspecialchars(substr($t['transaction_id'], 0, 20)) ?>...</code>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>

  <script>
    function recalculate() {
      const pool        = parseFloat(document.getElementById('profit_pool').value) || 0;
      const sharePool   = pool * 0.5;
      const contribPool = pool * 0.5;

      document.getElementById('share_pool_val').innerText  = 'MWK ' + sharePool.toLocaleString(undefined,  { minimumFractionDigits: 2 });
      document.getElementById('contrib_pool_val').innerText = 'MWK ' + contribPool.toLocaleString(undefined, { minimumFractionDigits: 2 });

      let grandTotal = 0;
      document.querySelectorAll('tbody tr[data-user-id]').forEach(row => {
        const uid   = row.getAttribute('data-user-id');
        const sPerc = parseFloat(row.getAttribute('data-shares-perc'))  || 0;
        const cPerc = parseFloat(row.getAttribute('data-contribs-perc')) || 0;

        const base  = (sPerc * sharePool) + (cPerc * contribPool);
        const add   = parseFloat(row.querySelector('.add_input').value) || 0;
        const total = base + add;

        document.getElementById('base_'  + uid).innerText = 'MWK ' + base.toLocaleString(undefined,  { minimumFractionDigits: 2 });
        document.getElementById('total_' + uid).innerText = 'MWK ' + total.toLocaleString(undefined, { minimumFractionDigits: 2 });

        grandTotal += total;
      });

      document.getElementById('grand_total').innerText = 'MWK ' + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    document.getElementById('use_paychangu_transfer').addEventListener('change', function () {
      document.getElementById('btn_text').innerText = this.checked
        ? 'Process & Send via Paychangu'
        : 'Finalize and Notify Members';
    });

    recalculate();
  </script>

  <footer class="footer">
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>
</body>
</html>
