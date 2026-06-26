<?php
require 'session_config.php';
session_start();
require 'db.php';

$error   = '';
$message = '';
$step    = 1;
$open_payment_after_save = false;

// ── HANDLE PAYMENT CALLBACK from Paychangu ────────────────────────────────
if (($_GET['payment'] ?? '') === 'success' && !empty($_SESSION['pending_registration'])) {
    $message = 'payment_success';
    $step = 3;
}

if (($_GET['paynow'] ?? '') === '1' && !empty($_SESSION['pending_registration'])) {
    $open_payment_after_save = true;
}

// ── STEP 3: Finalize registration (POST after payment) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {

    if (empty($_SESSION['pending_registration'])) {
        $error = 'Please complete your information first.';
    } else {
        $password         = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($password === '' || $confirm_password === '') {
            $error = 'Please enter and confirm your password.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $pending       = $_SESSION['pending_registration'];
            $pending['username'] = $pending['username'] ?? $pending['email'];
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (username, full_name, national_id, email, phone, date_joined, fee, password_hash, role, fee_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'member', 'paid')");
            mysqli_stmt_bind_param($stmt, 'ssssssss',
                $pending['username'],
                $pending['full_name'],
                $pending['national_id'],
                $pending['email'],
                $pending['phone'],
                $pending['date_joined'],
                $pending['fee'],
                $password_hash
            );

            if (mysqli_stmt_execute($stmt)) {
                $newUserId = mysqli_insert_id($conn);
                require_once '../mailer_helper.php';
                $mailError = '';
                sendWelcomeEmail($conn, $newUserId, $password, $mailError);

                unset($_SESSION['pending_registration']);
                $message = 'success';
            } else {
                $error = 'Registration failed. The email or National ID may already be registered.';
            }
            mysqli_stmt_close($stmt);
        }
    }

    $step = 3;
}

// ── STEP 1 → save form data in session then trigger Paychangu ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_info') {
    $full_name   = trim($_POST['full_name']   ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $date_joined = trim($_POST['date_joined'] ?? '');

    if (!$full_name || !$national_id || !$email || !$phone || !$date_joined) {
        $error = 'Please complete all fields before proceeding.';
    } elseif (!preg_match('/^[A-Za-z0-9]{8}$/', $national_id)) {
        $error = 'National ID must be exactly 8 characters.';
    } elseif ($date_joined < date('Y-m-d')) {
        $error = 'Date of joining cannot be in the past.';
    } else {
        $tx_ref = 'AGRILINK-' . uniqid() . '-' . random_int(100000, 999999);

        $_SESSION['pending_registration'] = [
            'username'    => $email,
            'full_name'   => $full_name,
            'national_id' => strtoupper($national_id),
            'email'       => $email,
            'phone'       => $phone,
            'date_joined' => $date_joined,
            'fee'         => '1000.00',
        ];
        if (!empty($_POST['pay_after_save'])) {
            header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?paynow=1');
            exit;
        }
        $step = 3;
    }
}

// Step 2 is now bypassed automatically

$pending = $_SESSION['pending_registration'] ?? [];

// Build base URL once — used for Paychangu callback/return URLs
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Agrilink – Member Registration</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --primary: #2a9d8f;
      --primary-light: #42b7a8;
      --accent: #f4a261;
      --accent-hover: #e89c4f;
      --text: #222;
      --muted: #666;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 40px 16px 0;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(42,157,143,0.10);
      width: 100%;
      max-width: 680px;
      padding: 40px 48px;
    }
    .card-header { text-align: center; margin-bottom: 28px; }
    .card-header .icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 12px;
    }
    .card-header .icon i { color: #fff; font-size: 1.7rem; }
    .card-header h2 { font-size: 1.7rem; color: var(--primary); margin-bottom: 4px; }
    .card-header p  { color: var(--muted); font-size: 0.95rem; }

    .steps { display: flex; justify-content: center; margin-bottom: 32px; }
    .step-item {
      display: flex; flex-direction: column; align-items: center;
      flex: 1; position: relative;
    }
    .step-item:not(:last-child)::after {
      content: ''; position: absolute;
      top: 18px; left: 50%;
      width: 100%; height: 2px;
      background: #e0e0e0; z-index: 0;
    }
    .step-item.done:not(:last-child)::after { background: var(--primary); }
    .step-circle {
      width: 36px; height: 36px; border-radius: 50%;
      border: 2px solid #e0e0e0; background: #fff;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 0.9rem; color: #bbb;
      z-index: 1; position: relative; transition: all 0.3s;
    }
    .step-item.active .step-circle,
    .step-item.done   .step-circle {
      border-color: var(--primary); background: var(--primary); color: #fff;
    }
    .step-label {
      margin-top: 6px; font-size: 0.75rem; color: #bbb;
      font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
    }
    .step-item.active .step-label,
    .step-item.done   .step-label { color: var(--primary); }

    .form-row   { display: flex; gap: 20px; margin-bottom: 18px; }
    .form-group { flex: 1; display: flex; flex-direction: column; }
    label { font-weight: 600; font-size: 0.9rem; color: var(--text); margin-bottom: 6px; }
    input {
      padding: 10px 14px; border: 1.5px solid #ddd; border-radius: 8px;
      font-size: 1rem; transition: border-color .25s, box-shadow .25s; outline: none;
    }
    input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(42,157,143,0.12); }
    input[readonly] { background: #f5f5f5; color: var(--muted); cursor: default; }
    .fee-badge {
      padding: 10px 14px; border: 1.5px solid #ddd; border-radius: 8px;
      background: #f9f9f9; font-weight: 700; color: var(--primary); font-size: 1.05rem;
    }
    .btn-primary {
      width: 100%; padding: 14px; margin-top: 8px;
      background: var(--accent); color: #fff; border: none; border-radius: 50px;
      font-size: 1.05rem; font-weight: 700; cursor: pointer;
      transition: background .25s, transform .2s, box-shadow .25s;
      box-shadow: 0 4px 16px rgba(244,162,97,0.25);
    }
    .btn-primary:hover {
      background: var(--accent-hover); transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(244,162,97,0.35);
    }
    .alert-box { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 0.97rem; }
    .alert-error { background: #fde8e8; color: #8b0000; border-left: 4px solid #e53e3e; border-radius: 0; }
    .alert-info  { background: #e6f7f5; color: #1a5f38; border-left: 4px solid var(--primary); border-radius: 0; }
    .back-link { text-align: center; margin-top: 18px; font-size: 0.9rem; }
    .back-link a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .back-link a:hover { text-decoration: underline; }

    @media(max-width: 600px) {
      .card { padding: 28px 18px; }
      .form-row { flex-direction: column; gap: 0; }
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<div class="card">

  <div class="card-header">
    <div class="icon"><i class="fa-solid fa-user-plus"></i></div>
    <h2>Cooperative Registration</h2>
    <p>Livingstonia Bee Keeping Cooperative</p>
  </div>

  <div class="steps">
    <div class="step-item <?= ($step == 1 || $step == 'pay') ? 'active' : 'done' ?>">
      <div class="step-circle"><?= ($step == 3) ? '<i class="fa-solid fa-check" style="font-size:.8rem"></i>' : '1' ?></div>
      <div class="step-label">Your Info</div>
    </div>
    <div class="step-item <?= ($step == 3) ? 'done' : '' ?>">
      <div class="step-circle"><?= $step == 3 ? '<i class="fa-solid fa-check" style="font-size:.8rem"></i>' : '2' ?></div>
      <div class="step-label">Account Setup</div>
    </div>
    <div class="step-item <?= $step == 3 ? 'active' : '' ?>">
      <div class="step-circle">3</div>
      <div class="step-label">Password</div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($message === 'payment_success'): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'success',
        title: 'Payment Successful!',
        text: 'Your payment has been received. Now, please set your password to complete your registration.',
        confirmButtonColor: '#2a9d8f',
        confirmButtonText: 'Continue'
      });
    });
  </script>

  <div class="alert-box alert-info">
    <i class="fa-solid fa-circle-check"></i>
    <strong>Information Saved!</strong> Almost there — just set your password to finish registration.
  </div>

  <form method="POST">
    <input type="hidden" name="action" value="register">
    <div class="form-row">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" value="<?= htmlspecialchars($pending['full_name'] ?? '') ?>" readonly>
      </div>
      <div class="form-group">
        <label>National ID</label>
        <input type="text" value="<?= htmlspecialchars($pending['national_id'] ?? '') ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($pending['email'] ?? '') ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Create Password</label>
        <input type="password" name="password" placeholder="Min. 6 characters" required autofocus>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Re-enter password" required>
      </div>
    </div>
    <button type="submit" class="btn-primary">
      <i class="fa-solid fa-user-check"></i>&nbsp; Finish Registration
    </button>
  </form>

  <?php elseif ($message === 'success'): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'success',
        title: 'Welcome to Agrilink!',
        text: 'Your registration is complete. You can now log in with your email and password.',
        confirmButtonColor: '#2a9d8f',
        confirmButtonText: 'Go to Login'
      }).then(() => { window.location.href = 'login.php'; });
    });
  </script>

  <?php elseif ($step == 3): ?>
  <div class="alert-box alert-info">
    <i class="fa-solid fa-circle-check"></i>
    <strong>Information Saved!</strong> Almost there — just set your password to finish registration.
  </div>

  <form method="POST">
    <input type="hidden" name="action" value="register">
    <div class="form-row">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" value="<?= htmlspecialchars($pending['full_name'] ?? '') ?>" readonly>
      </div>
      <div class="form-group">
        <label>National ID</label>
        <input type="text" value="<?= htmlspecialchars($pending['national_id'] ?? '') ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($pending['email'] ?? '') ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Create Password</label>
        <input type="password" name="password" placeholder="Min. 6 characters" required autofocus>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Re-enter password" required>
      </div>
    </div>
    <button type="submit" class="btn-primary">
      <i class="fa-solid fa-user-check"></i>&nbsp; Finish Registration
    </button>
  </form>

  <?php else: ?>
  <script src="https://in.paychangu.com/js/popup.js"></script>
  <script>
    function handlePayment(event) {
      event.preventDefault();

      const form = document.getElementById('infoForm');
      const fullName = form.querySelector('input[name="full_name"]').value;
      const nationalId = form.querySelector('input[name="national_id"]').value;
      const email = form.querySelector('input[name="email"]').value;
      const phone = form.querySelector('input[name="phone"]').value;
      const dateJoined = form.querySelector('input[name="date_joined"]').value;

      if (!fullName || !nationalId || !email || !phone || !dateJoined) {
        alert('Please fill in all fields');
        return;
      }

      if (!/^[A-Za-z0-9]{8}$/.test(nationalId)) {
        alert('National ID must be exactly 8 characters.');
        return;
      }

      let payInput = form.querySelector('input[name="pay_after_save"]');
      if (!payInput) {
        payInput = document.createElement('input');
        payInput.type = 'hidden';
        payInput.name = 'pay_after_save';
        payInput.value = '1';
        form.appendChild(payInput);
      }

      form.submit();
    }

    function openPaychangu() {
      const form = document.getElementById('infoForm');
      const fullName = form.querySelector('input[name="full_name"]').value;
      const nationalId = form.querySelector('input[name="national_id"]').value;
      const email = form.querySelector('input[name="email"]').value;
      const phone = form.querySelector('input[name="phone"]').value;
      const dateJoined = form.querySelector('input[name="date_joined"]').value;

      PaychanguCheckout({
        "public_key": "PUB-AfPBVlqDNeRU5hB0FtHQlVozVgVnfR1b",
        "tx_ref": 'AGRILINK-' + Math.floor((Math.random() * 1000000000) + 1),
        "amount": 100,
        "currency": "MWK",
        "callback_url": "<?= $base_url ?>/member_registration.php?payment=success",
        "return_url": "<?= $base_url ?>/member_registration.php?payment=success",
        "customer":{
          "email": email,
          "first_name": fullName.split(' ')[0],
          "last_name": fullName.split(' ').slice(1).join(' ') || fullName,
        },
        "customization": {
          "title": "Agrilink Registration Fee",
          "description": "Cooperative membership registration fee"
        },
        "meta": {
          "full_name": fullName,
          "national_id": nationalId,
          "phone": phone,
          "date_joined": dateJoined
        }
      });
    }
  </script>

  <?php if ($open_payment_after_save): ?>
  <script>
    document.addEventListener('DOMContentLoaded', openPaychangu);
  </script>
  <?php endif; ?>

  <form method="POST" id="infoForm">
    <input type="hidden" name="action" value="save_info">
    
    <div class="form-row">
      <div class="form-group">
        <label><i class="fa-solid fa-user" style="color:var(--primary)"></i> Full Name</label>
        <input type="text" name="full_name" placeholder="e.g. John Banda" required
               value="<?= htmlspecialchars($pending['full_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label><i class="fa-solid fa-id-card" style="color:var(--primary)"></i> National ID</label>
        <input type="text" name="national_id" placeholder="8-char NRB ID" required
               pattern="[A-Za-z0-9]{8}" title="National ID must be exactly 8 characters" style="text-transform: uppercase;"
               value="<?= htmlspecialchars($pending['national_id'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label><i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Email Address</label>
        <input type="email" name="email" placeholder="you@example.com" required
               value="<?= htmlspecialchars($pending['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label><i class="fa-solid fa-phone" style="color:var(--primary)"></i> Phone Number</label>
        <input type="tel" name="phone" placeholder="+265 99 000 0000" required
               value="<?= htmlspecialchars($pending['phone'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group" style="flex: 1;">
        <label><i class="fa-solid fa-calendar" style="color:var(--primary)"></i> Date of Joining</label>
        <input type="date" name="date_joined" required
               min="<?= date('Y-m-d') ?>"
               value="<?= htmlspecialchars($pending['date_joined'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="form-group" style="flex: 1;">
      </div>
    </div>

    <div class="form-row" style="align-items:center;">
      <div class="form-group">
        <label><i class="fa-solid fa-coins" style="color:var(--accent)"></i> Registration Fee</label>
        <div class="fee-badge">Livingstonia Bee Keeping Cooperative &nbsp;<small style="font-weight:400;color:#888;">— registration form (MWK 100)</small></div>
      </div>
    </div>

    <div id="wrapper"></div>
    <button type="submit" onClick="handlePayment(event)" class="btn-primary">
      <i class="fa-solid fa-credit-card"></i>&nbsp; Pay Registration Fee
    </button>
  </form>
  <?php endif; ?>

  <div class="back-link">
    Already a member? <a href="login.php">Sign in here</a>
  </div>
</div>

  <footer style="background: #2a9d8f; color: white; text-align: center; padding: 1.5rem; margin-top: 2.5rem; font-size: 0.95rem; width: 100%; flex-shrink: 0;">
    &copy; <?php echo date('Y'); ?> Agrilink Cooperative | Premium Organic Products
  </footer>
</body>
</html>
