<?php
session_start();
require 'db.php';
require 'notif_count.php';
require_once '../paychangu_checkout_helper.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id    = $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? '';
$full_name  = $_SESSION['full_name'] ?? 'Member';
$nameParts  = explode(' ', trim($full_name));
$firstName  = $nameParts[0];
$lastName   = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : $firstName;

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Payment return
if (($_GET['payment'] ?? '') === 'success' && !empty($_SESSION['pending_share_purchase'])) {
    $tx_ref  = $_GET['tx_ref'] ?? '';
    $pending = $_SESSION['pending_share_purchase'];
    $shares  = (int)$pending['shares'];
    $verified = true;
    if (!empty($tx_ref)) {
        $vResult  = verifyPaychanguTransaction($tx_ref);
        $verified = isset($vResult['status']) && strtolower($vResult['status']) === 'success';
    }
    if ($verified && $shares > 0) {
        $stmt = $conn->prepare("INSERT INTO shares (user_id, shares, date_purchased) VALUES (?, ?, CURDATE())");
        $stmt->bind_param("ii", $user_id, $shares);
        if ($stmt->execute()) {
            unset($_SESSION['pending_share_purchase']);
            $_SESSION['message']  = "Successfully purchased $shares shares!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message']  = "Payment received but failed to record shares. Contact support.";
            $_SESSION['msg_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['message']  = "Payment could not be verified. Please contact support.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: finances.php"); exit;
}

// Initiate Paychangu checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_paychangu'])) {
    // Requirement: Must have more than 5 hives
    $hiveCheck = $conn->query("SELECT SUM(hive_count) AS t FROM hives WHERE user_id=$user_id")->fetch_assoc()['t'] ?? 0;
    if ($hiveCheck <= 5) {
        $_SESSION['message']  = "Share purchase restricted. You need more than 5 hives (Current: $hiveCheck).";
        $_SESSION['msg_type'] = "error";
        header("Location: finances.php"); exit;
    }

    $shares = (int)($_POST['shares_count'] ?? 0);
    if ($shares < 1) {
        $_SESSION['message']  = "Please enter a valid number of shares.";
        $_SESSION['msg_type'] = "error";
        header("Location: finances.php"); exit;
    }
    $amount = $shares * 100;
    $tx_ref = 'SHARE-' . $user_id . '-' . time();
    $_SESSION['pending_share_purchase'] = ['user_id'=>$user_id,'shares'=>$shares,'amount'=>$amount,'tx_ref'=>$tx_ref];
    $callbackUrl = $base_url . '/finances.php?payment=success&tx_ref=' . urlencode($tx_ref);
    $result = initializePaychanguCheckout($amount, $user_email, $firstName, $lastName, $tx_ref, $callbackUrl);
    if ($result['success'] && !empty($result['checkout_url'])) {
        header("Location: " . $result['checkout_url']); exit;
    }
    $_SESSION['message']  = "Payment gateway error: " . ($result['message'] ?? 'Unknown error');
    $_SESSION['msg_type'] = "error";
    header("Location: finances.php"); exit;
}

// Honey contribution
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['contribute_honey'])) {
    $quantity = floatval($_POST['quantity'] ?? 0);
    if ($quantity > 0) {
        $stmt = $conn->prepare("INSERT INTO contributions (user_id, quantity, contribution_date) VALUES (?, ?, CURDATE())");
        $stmt->bind_param("id", $user_id, $quantity);
        $_SESSION['message']  = $stmt->execute() ? "Contributed {$quantity} kg of honey successfully." : "Error recording contribution.";
        $_SESSION['msg_type'] = $stmt->execute() ? "success" : "error";
        $stmt->close();
    } else {
        $_SESSION['message']  = "Please enter a valid quantity.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: finances.php"); exit;
}

// Fetch totals
$totalShares = $conn->query("SELECT SUM(shares) AS t FROM shares WHERE user_id=$user_id")->fetch_assoc()['t'] ?? 0;
$totalHoney  = $conn->query("SELECT SUM(quantity) AS t FROM contributions WHERE user_id=$user_id")->fetch_assoc()['t'] ?? 0;
$hiveCount   = $conn->query("SELECT SUM(hive_count) AS t FROM hives WHERE user_id=$user_id")->fetch_assoc()['t'] ?? 0;
$profit      = ($totalShares * 10) + ($totalHoney * 2);
$shareValue  = $totalShares * 100;

$flashMsg  = $_SESSION['message']  ?? '';
$flashType = $_SESSION['msg_type'] ?? '';
unset($_SESSION['message'], $_SESSION['msg_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Finances | Agrilink Member</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary:#2a9d8f; --primary-dark:#21867a;
      --accent:#f4a261;  --accent-hover:#e76f51;
      --gold:#e9c46a;
      --text:#1e293b;    --text-muted:#64748b;
      --success:#10b981; --danger:#ef4444;
      --card-bg:rgba(255,255,255,0.82);
      --glass-border:rgba(255,255,255,0.5);
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;color:var(--text);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;}

    /* Header */
    .header{background:var(--primary);padding:1rem 3rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;box-shadow:0 4px 30px rgba(0,0,0,0.08);}
    .logo-area{display:flex;align-items:center;gap:14px;}
    .logo-img{height:50px;width:auto;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.15));}
    .logo-text{color:white;font-weight:800;font-size:1.05rem;letter-spacing:.5px;text-transform:uppercase;line-height:1.2;}
    .navbar ul{display:flex;list-style:none;gap:2rem;align-items:center;margin:0;padding:0;}
    .navbar a{color:white;text-decoration:none;font-weight:600;font-size:.9rem;transition:all .3s;position:relative;display:flex;align-items:center;gap:.35rem;}
    .navbar a:hover,.navbar a.active{color:var(--accent);}
    .navbar a::after{content:'';position:absolute;width:0;height:2px;bottom:-4px;left:0;background:var(--accent);transition:width .3s;}
    .navbar a:hover::after,.navbar a.active::after{width:100%;}
    .notif-wrapper{position:relative;display:inline-flex;align-items:center;}
    .notif-badge{position:absolute;top:-9px;right:-11px;background:#ef4444;color:white;font-size:.6rem;font-weight:700;min-width:17px;height:17px;border-radius:999px;display:flex;align-items:center;justify-content:center;padding:0 3px;border:2px solid var(--primary);animation:pulseBadge 2s infinite;}
    @keyframes pulseBadge{0%,100%{transform:scale(1);}50%{transform:scale(1.2);}}

    /* Hero banner */
    .hero-banner{background:linear-gradient(135deg,var(--primary),var(--primary-dark));margin:2.5rem auto 0;max-width:1200px;width:calc(100% - 3rem);border-radius:24px;padding:2.5rem 3rem;color:white;display:flex;justify-content:space-between;align-items:center;position:relative;overflow:hidden;box-shadow:0 20px 50px rgba(42,157,143,0.22);}
    .hero-banner::after{content:'\f51e';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:-10px;bottom:-50px;font-size:13rem;color:rgba(255,255,255,0.06);pointer-events:none;}
    .hero-text h1{font-size:2.2rem;font-weight:800;margin-bottom:.3rem;letter-spacing:-1px;}
    .hero-text p{font-size:1rem;opacity:.85;font-weight:300;}
    .hero-stats{display:flex;gap:2rem;z-index:1;}
    .hero-stat{text-align:center;background:rgba(255,255,255,0.12);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.25);border-radius:16px;padding:1rem 1.5rem;}
    .hero-stat .val{font-size:1.6rem;font-weight:800;display:block;}
    .hero-stat .lbl{font-size:.78rem;opacity:.8;font-weight:500;text-transform:uppercase;letter-spacing:.5px;}

    /* Flash message */
    .flash{max-width:1200px;margin:1.2rem auto 0;padding:1rem 1.5rem;border-radius:12px;font-weight:600;font-size:.95rem;display:flex;align-items:center;gap:.7rem;width:calc(100% - 3rem);}
    .flash.success{background:#d1fae5;color:#065f46;border-left:4px solid var(--success);}
    .flash.error{background:#fee2e2;color:#991b1b;border-left:4px solid var(--danger);}

    /* Grid */
    .finance-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.8rem;max-width:1200px;margin:2rem auto 1.5rem;padding:0 1.5rem;}
    @media(max-width:900px){.finance-grid{grid-template-columns:1fr;} .hero-stats{display:none;} .hero-banner{padding:2rem;} .header{flex-direction:column;gap:1rem;padding:1rem 1.5rem;} .navbar ul{flex-wrap:wrap;justify-content:center;gap:1rem;}}

    /* Cards */
    .card{background:var(--card-bg);backdrop-filter:blur(18px);border:1px solid var(--glass-border);border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.06);transition:transform .25s ease,box-shadow .25s ease;}
    .card:hover{transform:translateY(-5px);box-shadow:0 20px 45px rgba(0,0,0,0.1);}
    .card-header{padding:1.2rem 1.6rem;font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.6rem;border-bottom:1px solid rgba(0,0,0,0.06);}
    .card-header.green{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;}
    .card-header.amber{background:linear-gradient(135deg,var(--accent),var(--accent-hover));color:white;}
    .card-header.gold{background:linear-gradient(135deg,#e9c46a,#d4a843);color:#3d2c00;}
    .card-body{padding:1.8rem;}

    /* Form */
    .form-group{margin-bottom:1.2rem;}
    .form-group label{display:block;font-weight:600;margin-bottom:.45rem;font-size:.92rem;color:var(--text);}
    .form-group input[type="number"]{width:100%;padding:.85rem 1rem;border:2px solid #e2e8f0;border-radius:12px;font-size:1rem;font-family:'Outfit',sans-serif;transition:border-color .2s,box-shadow .2s;background:#fafafa;color:var(--text);}
    .form-group input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(42,157,143,0.1);background:white;}

    /* Price preview */
    .price-tag{background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #a7f3d0;border-radius:10px;padding:.8rem 1rem;font-weight:700;color:#065f46;font-size:.95rem;display:flex;align-items:center;gap:.5rem;margin-bottom:1.2rem;}

    /* Info note */
    .info-note{font-size:.8rem;color:var(--text-muted);display:flex;gap:.4rem;align-items:flex-start;margin-bottom:1.2rem;line-height:1.4;}
    .info-note i{margin-top:2px;flex-shrink:0;color:var(--primary);}

    /* Buttons */
    .btn{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.9rem;border:none;border-radius:12px;font-size:.97rem;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;transition:all .25s ease;}
    .btn-pay{background:linear-gradient(135deg,var(--accent),var(--accent-hover));color:white;box-shadow:0 5px 15px rgba(244,162,97,.35);}
    .btn-pay:hover{box-shadow:0 8px 22px rgba(244,162,97,.5);transform:translateY(-2px);}
    .btn-honey{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;box-shadow:0 5px 15px rgba(42,157,143,.3);}
    .btn-honey:hover{box-shadow:0 8px 22px rgba(42,157,143,.45);transform:translateY(-2px);}
    .btn:disabled{opacity:.7;cursor:not-allowed;transform:none !important;}

    /* Summary rows */
    .sum-row{display:flex;justify-content:space-between;align-items:center;padding:.9rem 0;border-bottom:1px solid #f0f0f0;}
    .sum-row:last-child{border-bottom:none;padding-bottom:0;}
    .sum-label{display:flex;align-items:center;gap:.6rem;font-size:.95rem;color:var(--text-muted);font-weight:500;}
    .sum-label i{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.85rem;}
    .icon-green{background:rgba(42,157,143,.12);color:var(--primary);}
    .icon-amber{background:rgba(244,162,97,.15);color:var(--accent-hover);}
    .icon-gold{background:rgba(233,196,106,.2);color:#b7860d;}
    .icon-teal{background:rgba(16,185,129,.12);color:var(--success);}
    .sum-val{font-weight:800;font-size:1.05rem;color:var(--text);}
    .sum-val.highlight{color:var(--primary);}
    .sum-val.profit{color:var(--accent-hover);}

    footer{background:var(--primary);color:white;text-align:center;padding:2rem;margin-top:auto;font-size:.9rem;border-top:4px solid var(--accent);font-weight:500;}
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>

<header class="header">
  <div class="logo-area">
    <img src="../assets/logo.png" alt="Agrilink" class="logo-img" onerror="this.style.display='none'">
    <span class="logo-text">Livingstonia Bee Keeping<br>— Agrilink</span>
  </div>
  <nav class="navbar">
    <ul>
      <li><a href="member.php">Home</a></li>
      <li><a href="Hives.php">Hives</a></li>
      <li><a href="member-inspection.php">Inspections</a></li>
      <li><a href="finances.php" class="active">Finances</a></li>
      <li><a href="training.php">Training Hub</a></li>
      <li><a href="contact_cooperative.php">Contact</a></li>
      <li>
        <a href="notification.php">
          <span class="notif-wrapper">
            <i class="fa-solid fa-bell"></i>
            <?php if (!empty($notifCount) && $notifCount > 0): ?>
              <span class="notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
            <?php endif; ?>
          </span>
        </a>
      </li>
      <li><a href="profile.php"><i class="fa-solid fa-circle-user" style="font-size:1.2rem"></i></a></li>
      <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket" style="font-size:1.1rem"></i></a></li>
    </ul>
  </nav>
</header>

<!-- Hero Banner -->
<div class="hero-banner">
  <div class="hero-text">
    <h1><i class="fa-solid fa-chart-line"></i> Financial Portal</h1>
    <p>Manage shares, honey contributions &amp; track your earnings.</p>
  </div>
  <div class="hero-stats">
    <div class="hero-stat">
      <span class="val"><?= number_format($totalShares) ?></span>
      <span class="lbl">Shares Owned</span>
    </div>
    <div class="hero-stat">
      <span class="val"><?= number_format($totalHoney, 1) ?> kg</span>
      <span class="lbl">Honey Given</span>
    </div>
    <div class="hero-stat">
      <span class="val">MWK <?= number_format($profit) ?></span>
      <span class="lbl">Est. Earnings</span>
    </div>
  </div>
</div>

<?php if ($flashMsg): ?>
<div class="flash <?= htmlspecialchars($flashType) ?>">
  <i class="fa-solid <?= $flashType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
  <?= htmlspecialchars($flashMsg) ?>
</div>
<?php endif; ?>

<!-- Finance Cards -->
<div class="finance-grid">

  <!-- Buy Shares -->
  <div class="card">
    <div class="card-header amber">
      <i class="fa-solid fa-coins"></i> Buy Shares
    </div>
    <div class="card-body">
      <?php if ($hiveCount <= 5): ?>
        <div class="info-note" style="color: var(--danger); background: #fee2e2; padding: 0.8rem; border-radius: 10px; border: 1px solid #fecaca; margin-bottom: 1.2rem;">
          <i class="fa-solid fa-circle-exclamation"></i>
          <strong>Restriction:</strong> You must own more than 5 hives to buy shares. (You currently have <?= $hiveCount ?>).
        </div>
      <?php endif; ?>
      <form method="POST" action="finances.php" id="buySharesForm">
        <input type="hidden" name="init_paychangu" value="1">
        <div class="form-group">
          <label for="shares_count">Number of Shares</label>
          <input type="number" id="shares_count" name="shares_count" min="1"
                 placeholder="e.g. 10" required oninput="updatePreview(this.value)"
                 <?= ($hiveCount <= 5) ? 'disabled' : '' ?>>
        </div>
        <div class="price-tag" id="priceTag">
          <i class="fa-solid fa-tag"></i>
          <span id="priceText">Enter shares to see total</span>
        </div>
        <div class="info-note">
          <i class="fa-solid fa-circle-info"></i>
          MWK 100 per share. Clicking Pay Now redirects you securely to Paychangu.
        </div>
        <button type="submit" class="btn btn-pay" id="payBtn" <?= ($hiveCount <= 5) ? 'disabled title="Requires > 5 hives"' : '' ?>>
          <i class="fa-solid fa-credit-card"></i> Pay Now via Paychangu
        </button>
      </form>
    </div>
  </div>

  <!-- Honey Contribution -->
  <div class="card">
    <div class="card-header green">
      <i class="fa-solid fa-jar"></i> Honey Contribution
    </div>
    <div class="card-body">
      <form method="POST" action="finances.php">
        <input type="hidden" name="contribute_honey" value="1">
        <div class="form-group">
          <label for="quantity">Quantity (kg)</label>
          <input type="number" id="quantity" name="quantity" min="0.1" step="0.1"
                 placeholder="e.g. 5.5" required>
        </div>
        <div class="info-note">
          <i class="fa-solid fa-circle-info"></i>
          Record honey you have contributed to the cooperative this season.
        </div>
        <br>
        <button type="submit" class="btn btn-honey">
          <i class="fa-solid fa-plus"></i> Record Contribution
        </button>
      </form>
    </div>
  </div>

  <!-- Financial Summary -->
  <div class="card">
    <div class="card-header gold">
      <i class="fa-solid fa-chart-pie"></i> Financial Summary
    </div>
    <div class="card-body">
      <div class="sum-row">
        <div class="sum-label">
          <span class="sum-label i icon-amber"><i class="fa-solid fa-coins"></i></span>
          Shares Owned
        </div>
        <span class="sum-val highlight"><?= number_format($totalShares) ?></span>
      </div>
      <div class="sum-row">
        <div class="sum-label">
          <span class="sum-label i icon-green"><i class="fa-solid fa-jar"></i></span>
          Honey Contributed
        </div>
        <span class="sum-val highlight"><?= number_format($totalHoney, 2) ?> kg</span>
      </div>
      <div class="sum-row">
        <div class="sum-label">
          <span class="sum-label i icon-teal"><i class="fa-solid fa-money-bill-wave"></i></span>
          Share Value
        </div>
        <span class="sum-val highlight">MWK <?= number_format($shareValue) ?></span>
      </div>
      <div class="sum-row">
        <div class="sum-label">
          <span class="sum-label i icon-gold"><i class="fa-solid fa-arrow-trend-up"></i></span>
          Est. Earnings
        </div>
        <span class="sum-val profit">MWK <?= number_format($profit) ?></span>
      </div>
    </div>
  </div>

</div>

<footer>
  &copy; <?= date('Y') ?> LIVINGSTONIA BEE KEEPING COOPERATIVE — AGRILINK &nbsp;|&nbsp;
  <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com
</footer>

<script>
function updatePreview(val) {
  const n = parseInt(val, 10);
  const txt = document.getElementById('priceText');
  txt.textContent = (n > 0)
    ? n + ' share' + (n !== 1 ? 's' : '') + ' = MWK ' + (n * 100).toLocaleString()
    : 'Enter shares to see total';
}
document.getElementById('buySharesForm').addEventListener('submit', function() {
  const btn = document.getElementById('payBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Redirecting to Paychangu…';
});
</script>
</body>
</html>