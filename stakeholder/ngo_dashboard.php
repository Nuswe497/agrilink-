<?php
session_start();
require 'db.php';
require_once '../paychangu_checkout_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'external' || $_SESSION['stakeholder_type'] != 'ngo') {
    header("Location: login.php"); exit;
}

$ngo_id    = $_SESSION['user_id'];

// Use prepared statement for NGO info
$stmt_ngo = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt_ngo->bind_param("i", $ngo_id);
$stmt_ngo->execute();
$ngo = $stmt_ngo->get_result()->fetch_assoc();
$stmt_ngo->close();

$ngo_email = $ngo['email'] ?? '';
$nameParts = explode(' ', trim($ngo['full_name'] ?? 'NGO'));
$firstName = $nameParts[0];
$lastName  = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : $firstName;

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST']
           . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$donate_msg = ''; $donate_type = '';

// ── Payment return from Paychangu ────────────────────────────────────────────
if (($_GET['payment'] ?? '') === 'success' && !empty($_SESSION['pending_donation'])) {
    $tx_ref   = $_GET['tx_ref'] ?? '';
    $pending  = $_SESSION['pending_donation'];
    $verified = true;
    if (!empty($tx_ref)) {
        $vr = verifyPaychanguTransaction($tx_ref);
        $verified = isset($vr['status']) && strtolower($vr['status']) === 'success';
    }
    if ($verified) {
        $amount  = floatval($pending['amount']);
        $purpose = $pending['purpose'];
        $notes   = $pending['notes'];
        
        $stmt_ins = $conn->prepare("INSERT INTO donations (ngo_id, amount, purpose, notes, tx_ref, donated_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt_ins->bind_param("idsss", $ngo_id, $amount, $purpose, $notes, $tx_ref);
        $ins = $stmt_ins->execute();
        $stmt_ins->close();
        
        unset($_SESSION['pending_donation']);
        $donate_msg  = $ins ? 'Donation of MWK ' . number_format($amount) . ' completed & recorded. Thank you!' : 'Payment received but failed to record.';
        $donate_type = $ins ? 'success' : 'error';
    } else {
        unset($_SESSION['pending_donation']);
        $donate_msg  = 'Payment could not be verified. Please contact support.';
        $donate_type = 'error';
    }
}

// ── Initiate Paychangu donation payment ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_donation'])) {
    $amount  = floatval($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    if ($amount < 1 || $purpose === '') {
        $donate_msg = 'Please enter a valid amount and select a purpose.';
        $donate_type = 'error';
    } else {
        $tx_ref = 'DONATE-' . $ngo_id . '-' . time();
        $_SESSION['pending_donation'] = [
            'amount'  => $amount,
            'purpose' => $purpose,
            'notes'   => $notes,
            'tx_ref'  => $tx_ref,
        ];
        $callbackUrl = $base_url . '/ngo_dashboard.php?payment=success&tx_ref=' . urlencode($tx_ref);
        $result = initializePaychanguCheckout($amount, $ngo_email, $firstName, $lastName, $tx_ref, $callbackUrl);
        if ($result['success'] && !empty($result['checkout_url'])) {
            header('Location: ' . $result['checkout_url']); exit;
        }
        $donate_msg  = 'Payment gateway error: ' . ($result['message'] ?? 'Unknown error');
        $donate_type = 'error';
    }
}

$members_result   = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='member'");
$members_count    = $members_result->fetch_assoc()['c'] ?? 0;
$suppliers_result = $conn->query("SELECT COUNT(*) as c FROM users WHERE stakeholder_type='supplier'");
$suppliers_count  = $suppliers_result->fetch_assoc()['c'] ?? 0;
$buyers_result    = $conn->query("SELECT COUNT(*) as c FROM users WHERE stakeholder_type='buyer'");
$buyers_count     = $buyers_result->fetch_assoc()['c'] ?? 0;
$stats_result     = $conn->query("SELECT COUNT(*) as total_tx, SUM(total_price) as total_val FROM purchases");
$stats            = $stats_result->fetch_assoc();
$profit_result    = $conn->query("SELECT SUM(amount) as tp FROM profits");
$total_profit     = $profit_result->fetch_assoc()['tp'] ?? 0;
$donations_stmt = $conn->prepare("SELECT SUM(amount) as td FROM donations WHERE ngo_id = ?");
$donations_stmt->bind_param("i", $ngo_id);
$donations_stmt->execute();
$total_donated = $donations_stmt->get_result()->fetch_assoc()['td'] ?? 0;
$donations_stmt->close();

$inspections      = $conn->query("SELECT * FROM inspections ORDER BY inspection_id DESC LIMIT 8");
$trainings        = $conn->query("SELECT * FROM training_materials ORDER BY id DESC LIMIT 8");

$recent_donations_stmt = $conn->prepare("SELECT * FROM donations WHERE ngo_id = ? ORDER BY donated_at DESC LIMIT 5");
$recent_donations_stmt->bind_param("i", $ngo_id);
$recent_donations_stmt->execute();
$recent_donations = $recent_donations_stmt->get_result();
// Note: We keep $recent_donations as a mysqli_result for the while/foreach loop in HTML
// But wait, it's better to fetch all or keep the stmt open? 
// Actually, keeping the result object is fine.
$recent_donations_stmt->close(); 
// Wait, closing stmt closes result set in some drivers, but in mysqli it's usually fine if we got the result object.
// Actually, it's safer to fetch_all.
$recent_donations_list = $recent_donations->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>NGO Dashboard | Agrilink</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--p:#2a9d8f;--pd:#1e7268;--a:#f4a261;--ad:#e76f51;--gold:#e9c46a;--text:#1e293b;--muted:#64748b;--success:#10b981;--danger:#ef4444;--card:rgba(255,255,255,0.88);--border:rgba(255,255,255,0.5);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;color:var(--text);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;}
/* Navbar Standardized */
.navbar-std {
  background: var(--p);
  color: white;
  padding: 0;
  position: sticky;
  top: 0;
  z-index: 1000;
  box-shadow: 0 4px 24px rgba(0,0,0,0.1);
}
.nav-inner-std {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 2.5rem;
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
.logo-icon-wrap-std {
  width: 38px; height: 38px;
  background: rgba(255,255,255,0.18);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255,255,255,0.25);
}
.logo-icon-wrap-std i { color: #fff; font-size: 1.05rem; }
.logo-text-std {
  font-family: 'Playfair Display', serif;
  font-size: 1.45rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.3px;
}
.logo-text-std span { color: var(--a); }

.hdr-right{display:flex;align-items:center;gap:1.2rem;}
.ngo-badge{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:12px;padding:.5rem 1rem;color:#fff;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.5rem;}
.btn-logout{background:var(--a);color:#fff;border:none;padding:.5rem 1.2rem;border-radius:10px;font-weight:700;font-size:.85rem;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .2s;}
.btn-logout:hover{background:var(--ad);transform:translateY(-1px);}
/* Main layout */
main{flex:1;max-width:1400px;margin:0 auto;width:100%;padding:2rem 2rem 3rem;}
/* Hero */
.hero{background:linear-gradient(135deg,var(--p),var(--pd));border-radius:24px;padding:2.5rem 3rem;color:#fff;display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;position:relative;overflow:hidden;box-shadow:0 16px 40px rgba(42,157,143,.25);}
.hero::after{content:'\f06c';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:-10px;bottom:-40px;font-size:12rem;color:rgba(255,255,255,.06);pointer-events:none;}
.hero h2{font-size:2rem;font-weight:800;margin-bottom:.3rem;}
.hero p{opacity:.85;font-weight:300;font-size:1rem;}
.hero-kpis{display:flex;gap:1rem;z-index:1;flex-shrink:0;}
.kpi{background:rgba(255,255,255,.14);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.25);border-radius:16px;padding:1rem 1.4rem;text-align:center;min-width:110px;}
.kpi b{display:block;font-size:1.5rem;font-weight:800;}
.kpi span{font-size:.72rem;opacity:.8;text-transform:uppercase;letter-spacing:.5px;}
/* Flash */
.flash{padding:.9rem 1.4rem;border-radius:12px;margin-bottom:1.5rem;font-weight:600;display:flex;align-items:center;gap:.6rem;}
.flash.success{background:#d1fae5;color:#065f46;border-left:4px solid var(--success);}
.flash.error{background:#fee2e2;color:#991b1b;border-left:4px solid var(--danger);}
/* Section header */
.sec-hdr{display:flex;align-items:center;gap:.6rem;font-size:1.1rem;font-weight:700;color:var(--p);margin-bottom:1.2rem;padding-bottom:.6rem;border-bottom:2px solid rgba(244,162,97,.35);}
/* Stats row */
.stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:2rem;}
.stat{background:var(--card);backdrop-filter:blur(16px);border:1px solid var(--border);border-radius:18px;padding:1.4rem 1rem;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.05);transition:transform .25s,box-shadow .25s;}
.stat:hover{transform:translateY(-4px);box-shadow:0 14px 32px rgba(0,0,0,.09);}
.stat-icon{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin:0 auto .8rem;}
.ic-green{background:rgba(42,157,143,.12);color:var(--p);}
.ic-amber{background:rgba(244,162,97,.14);color:var(--ad);}
.ic-gold{background:rgba(233,196,106,.18);color:#b7860d;}
.ic-teal{background:rgba(16,185,129,.12);color:var(--success);}
.ic-blue{background:rgba(59,130,246,.1);color:#3b82f6;}
.ic-purple{background:rgba(139,92,246,.1);color:#8b5cf6;}
.stat b{display:block;font-size:1.6rem;font-weight:800;color:var(--text);}
.stat small{font-size:.75rem;color:var(--muted);font-weight:500;}
/* Content grid */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
/* Card */
.card{background:var(--card);backdrop-filter:blur(16px);border:1px solid var(--border);border-radius:20px;padding:1.6rem;box-shadow:0 8px 24px rgba(0,0,0,.06);transition:transform .25s,box-shadow .25s;}
.card:hover{transform:translateY(-3px);box-shadow:0 16px 36px rgba(0,0,0,.09);}
/* List items */
.list-item{display:flex;align-items:flex-start;gap:.8rem;padding:.85rem 0;border-bottom:1px solid rgba(0,0,0,.05);}
.list-item:last-child{border-bottom:none;padding-bottom:0;}
.list-dot{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;}
.list-body{flex:1;}
.list-body strong{display:block;font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.list-body p{font-size:.8rem;color:var(--muted);line-height:1.4;}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;margin-top:4px;}
.badge-green{background:#d1fae5;color:#065f46;}
.badge-amber{background:#fef3c7;color:#92400e;}
.badge-blue{background:#dbeafe;color:#1e40af;}
.empty{text-align:center;padding:2rem;color:var(--muted);font-size:.9rem;}
.empty i{display:block;font-size:2rem;margin-bottom:.5rem;opacity:.3;}
/* Donation form */
.donate-card{background:linear-gradient(135deg,rgba(233,196,106,.15),rgba(244,162,97,.1));border:1px solid rgba(233,196,106,.4);border-radius:20px;padding:1.8rem;box-shadow:0 8px 24px rgba(0,0,0,.06);}
.donate-card .sec-hdr{color:#92400e;border-bottom-color:rgba(233,196,106,.5);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;}
.fg{display:flex;flex-direction:column;gap:.4rem;}
.fg label{font-size:.85rem;font-weight:600;color:var(--text);}
.fg input,.fg select,.fg textarea{padding:.75rem 1rem;border:2px solid rgba(0,0,0,.08);border-radius:12px;font-family:'Outfit',sans-serif;font-size:.9rem;color:var(--text);background:rgba(255,255,255,.8);transition:border-color .2s,box-shadow .2s;}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 4px rgba(233,196,106,.15);}
.fg textarea{resize:vertical;min-height:70px;}
.btn-donate{background:linear-gradient(135deg,var(--gold),#d4a843);color:#3d2c00;border:none;padding:.9rem 2rem;border-radius:12px;font-weight:800;font-size:.95rem;font-family:'Outfit',sans-serif;cursor:pointer;width:100%;transition:all .25s;box-shadow:0 4px 14px rgba(233,196,106,.4);margin-top:.5rem;}
.btn-donate:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(233,196,106,.5);}
/* Impact pillars */
.pillar{background:var(--card);backdrop-filter:blur(16px);border:1px solid var(--border);border-radius:18px;padding:1.4rem;box-shadow:0 6px 20px rgba(0,0,0,.05);}
.pillar-icon{width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:1rem;}
.pillar h4{font-size:.95rem;font-weight:700;margin-bottom:.6rem;color:var(--text);}
.pillar ul{list-style:none;display:flex;flex-direction:column;gap:.35rem;}
.pillar ul li{font-size:.82rem;color:var(--muted);display:flex;align-items:center;gap:.4rem;}
.pillar ul li::before{content:'✓';color:var(--success);font-weight:800;flex-shrink:0;}
/* Donation history table */
.dtable{width:100%;border-collapse:collapse;font-size:.85rem;}
.dtable th{text-align:left;padding:.6rem .8rem;color:var(--muted);font-weight:600;border-bottom:2px solid rgba(0,0,0,.06);}
.dtable td{padding:.7rem .8rem;border-bottom:1px solid rgba(0,0,0,.04);}
.dtable tr:last-child td{border-bottom:none;}
.dtable tr:hover td{background:rgba(42,157,143,.03);}
/* Footer */
footer{background:var(--p);color:#fff;text-align:center;padding:1.5rem;font-size:.85rem;border-top:4px solid var(--a);margin-top:auto;}
@media(max-width:1100px){.stats-row{grid-template-columns:repeat(3,1fr);}.grid-3{grid-template-columns:1fr 1fr;}.hero-kpis{display:none;}}
@media(max-width:768px){.grid-2,.grid-3{grid-template-columns:1fr;}.stats-row{grid-template-columns:repeat(2,1fr);}.form-row{grid-template-columns:1fr;}.hdr{flex-direction:column;gap:1rem;padding:1rem 1.5rem;}.hero{padding:1.8rem;}}
</style>
</head>
<body>
<?php include 'glow_bg.php'; ?>

<nav class="navbar-std">
  <div class="nav-inner-std">
    <a href="../index.php" class="logo-std">
      <div class="logo-icon-wrap-std">
        <i class="fas fa-bee"></i>
      </div>
      <div class="logo-text-std">Agri<span>link</span></div>
    </a>
    <div class="hdr-right">
      <div class="ngo-badge">
        <i class="fa-solid fa-building-ngo"></i>
        <?= htmlspecialchars($ngo['full_name'] ?? 'NGO User') ?>
      </div>
      <form method="POST" action="logout.php">
        <button type="submit" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
      </form>
    </div>
  </div>
</nav>

<main>

  <!-- Hero -->
  <div class="hero">
    <div>
      <h2><i class="fa-solid fa-handshake"></i> Welcome back, <?= htmlspecialchars(explode(' ', $ngo['full_name'] ?? 'Partner')[0]) ?>!</h2>
      <p>Monitor cooperative health, track impact, and support beekeeping communities.</p>
    </div>
    <div class="hero-kpis">
      <div class="kpi"><b><?= $members_count ?></b><span>Members</span></div>
      <div class="kpi"><b><?= number_format($stats['total_tx'] ?? 0) ?></b><span>Transactions</span></div>
      <div class="kpi"><b>MWK <?= number_format($total_donated) ?></b><span>Your Donations</span></div>
    </div>
  </div>

  <?php if ($donate_msg): ?>
  <div class="flash <?= $donate_type ?>">
    <i class="fa-solid fa-<?= $donate_type === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
    <?= htmlspecialchars($donate_msg) ?>
  </div>
  <?php endif; ?>

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat">
      <div class="stat-icon ic-green"><i class="fa-solid fa-users"></i></div>
      <b><?= $members_count ?></b><small>Active Members</small>
    </div>
    <div class="stat">
      <div class="stat-icon ic-amber"><i class="fa-solid fa-truck"></i></div>
      <b><?= $suppliers_count ?></b><small>Suppliers</small>
    </div>
    <div class="stat">
      <div class="stat-icon ic-blue"><i class="fa-solid fa-shopping-bag"></i></div>
      <b><?= $buyers_count ?></b><small>Buyers</small>
    </div>
    <div class="stat">
      <div class="stat-icon ic-teal"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
      <b><?= number_format($stats['total_tx'] ?? 0) ?></b><small>Transactions</small>
    </div>
    <div class="stat">
      <div class="stat-icon ic-gold"><i class="fa-solid fa-coins"></i></div>
      <b style="font-size:1rem">MWK <?= number_format($stats['total_val'] ?? 0) ?></b><small>Market Value</small>
    </div>
    <div class="stat">
      <div class="stat-icon ic-purple"><i class="fa-solid fa-hand-holding-heart"></i></div>
      <b style="font-size:1rem">MWK <?= number_format($total_donated) ?></b><small>Donated (You)</small>
    </div>
  </div>

  <!-- Inspections + Training -->
  <div class="grid-2">
    <div class="card">
      <div class="sec-hdr"><i class="fa-solid fa-clipboard-list"></i> Recent Hive Inspections</div>
      <?php if ($inspections && $inspections->num_rows > 0): ?>
        <?php while ($r = $inspections->fetch_assoc()): ?>
        <div class="list-item">
          <div class="list-dot ic-green" style="background:rgba(42,157,143,.12);color:var(--p)"><i class="fa-solid fa-magnifying-glass"></i></div>
          <div class="list-body">
            <strong><?= htmlspecialchars($r['apiary_location'] ?? 'Inspection #'.$r['inspection_id']) ?></strong>
            <p><?= htmlspecialchars(substr($r['notes'] ?? 'No notes recorded.', 0, 80)) ?></p>
            <?php if (!empty($r['hive_health_percentage'])): ?>
              <span class="badge badge-green"><i class="fa-solid fa-heart-pulse"></i>&nbsp;<?= $r['hive_health_percentage'] ?>% Healthy</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty"><i class="fa-solid fa-clipboard"></i>No inspection reports yet.</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="sec-hdr"><i class="fa-solid fa-graduation-cap"></i> Training Sessions</div>
      <?php if ($trainings && $trainings->num_rows > 0): ?>
        <?php while ($t = $trainings->fetch_assoc()): ?>
        <div class="list-item">
          <div class="list-dot" style="background:rgba(244,162,97,.14);color:var(--ad)"><i class="fa-solid fa-chalkboard-user"></i></div>
          <div class="list-body">
            <strong><?= htmlspecialchars($t['topic'] ?? 'Training Session') ?></strong>
            <p><?= htmlspecialchars(substr($t['description'] ?? '', 0, 80)) ?></p>
            <span class="badge badge-amber"><i class="fa-solid fa-calendar"></i>&nbsp;Scheduled</span>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty"><i class="fa-solid fa-book-open"></i>No training sessions listed.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Donate + Donation History -->
  <div class="grid-2" style="margin-bottom:1.5rem">
    <div class="donate-card">
      <div class="sec-hdr"><i class="fa-solid fa-hand-holding-dollar"></i> Make a Donation</div>
      <form method="POST" id="donateForm">
        <input type="hidden" name="init_donation" value="1">
        <div class="form-row">
          <div class="fg">
            <label>Amount (MWK)</label>
            <input type="number" id="donateAmount" name="amount" min="1" step="0.01"
                   placeholder="e.g. 50000" required oninput="updateDonatePreview(this.value)">
          </div>
          <div class="fg">
            <label>Purpose</label>
            <select name="purpose" required>
              <option value="">-- Select purpose --</option>
              <option>Equipment &amp; Tools</option>
              <option>Member Training</option>
              <option>Hive Expansion</option>
              <option>Health &amp; Inspections</option>
              <option>Market Development</option>
              <option>General Support</option>
            </select>
          </div>
        </div>
        <div class="fg" style="margin-bottom:.8rem">
          <label>Notes (optional)</label>
          <textarea name="notes" placeholder="Any specific instructions or remarks..."></textarea>
        </div>
        <div id="donatePreview" style="background:rgba(255,255,255,.6);border:1px solid rgba(233,196,106,.5);border-radius:10px;padding:.7rem 1rem;font-size:.85rem;font-weight:600;color:#92400e;margin-bottom:.8rem;display:flex;align-items:center;gap:.5rem;">
          <i class="fa-solid fa-tag"></i> <span id="donatePreviewText">Enter amount to see total</span>
        </div>
        <p style="font-size:.78rem;color:#92400e;margin-bottom:.8rem;display:flex;align-items:center;gap:.4rem;">
          <i class="fa-solid fa-circle-info"></i>
          You will be redirected to Paychangu to complete payment securely.
        </p>
        <button type="submit" id="donateBtn" class="btn-donate">
          <i class="fa-solid fa-credit-card"></i> Donate via Paychangu
        </button>
      </form>
      <script>
      function updateDonatePreview(val){
        const n=parseFloat(val);
        document.getElementById('donatePreviewText').textContent=
          (n>0)?'MWK '+n.toLocaleString()+' will be sent to Paychangu':'Enter amount to see total';
      }
      document.getElementById('donateForm').addEventListener('submit',function(){
        const b=document.getElementById('donateBtn');
        b.disabled=true;
        b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Redirecting to Paychangu...';
      });
      </script>
    </div>

    <div class="card">
      <div class="sec-hdr"><i class="fa-solid fa-clock-rotate-left"></i> Your Donation History</div>
      <?php if (!empty($recent_donations_list)): ?>
      <table class="dtable">
        <thead><tr><th>Date</th><th>Purpose</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($recent_donations_list as $d): ?>
          <tr>
            <td><?= date('d M Y', strtotime($d['donated_at'])) ?></td>
            <td><?= htmlspecialchars($d['purpose']) ?></td>
            <td style="font-weight:700;color:var(--p)">MWK <?= number_format($d['amount']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty"><i class="fa-solid fa-hand-holding-heart"></i>No donations recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Impact Pillars -->
  <div class="sec-hdr" style="margin-bottom:1rem"><i class="fa-solid fa-chart-bar"></i> Cooperative Performance Pillars</div>
  <div class="grid-3">
    <div class="pillar">
      <div class="pillar-icon ic-green"><i class="fa-solid fa-shield-halved"></i></div>
      <h4>Transparency &amp; Accountability</h4>
      <ul>
        <li>All transactions monitored</li>
        <li>Inspection reports maintained</li>
        <li>Profit distribution tracked</li>
        <li>NGO-verified oversight</li>
      </ul>
    </div>
    <div class="pillar">
      <div class="pillar-icon ic-amber"><i class="fa-solid fa-store"></i></div>
      <h4>Market Development</h4>
      <ul>
        <li><?= $suppliers_count ?> Suppliers connected</li>
        <li><?= $buyers_count ?> Buyers engaged</li>
        <li>Fair pricing mechanisms</li>
        <li>Market expansion ongoing</li>
      </ul>
    </div>
    <div class="pillar">
      <div class="pillar-icon ic-teal"><i class="fa-solid fa-star"></i></div>
      <h4>Quality &amp; Capacity</h4>
      <ul>
        <li>Regular hive inspections</li>
        <li>Member training programs</li>
        <li>Best practice documentation</li>
        <li>Equitable profit sharing</li>
      </ul>
    </div>
  </div>

</main>

<footer>&copy; <?= date('Y') ?> Livingstonia BeeKeeping Cooperative — Agrilink &nbsp;|&nbsp; NGO Oversight Portal</footer>
</body>
</html>
