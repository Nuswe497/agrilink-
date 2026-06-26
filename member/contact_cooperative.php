<?php
session_start();
require 'db.php';
require 'notif_count.php';
require_once '../mailer_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'member';
$error = '';
$success = '';

$stmt = $conn->prepare('SELECT full_name, email FROM users WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$sender = $stmt->get_result()->fetch_assoc();
$stmt->close();

$contactEmail = 'livingstoniaagrilink@gmail.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact'])) {
    $subject = trim($_POST['subject'] ?? '');
    $messageText = trim($_POST['message'] ?? '');

    if ($subject === '' || $messageText === '') {
        $error = 'Please enter both a subject and a message.';
    } else {
        $notificationTitle = 'Cooperative Contact: ' . $subject;
        $notificationBody = "{$sender['full_name']} ({$sender['email']}) has sent a cooperative message:\n\n" . $messageText;

        $notifStmt = $conn->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, 'admin')");
        if ($notifStmt) {
            $notifStmt->bind_param('ss', $notificationTitle, $notificationBody);
            $notifStmt->execute();
            $notifStmt->close();
        }

        $emailErr = '';
        if (sendCooperativeContactEmail($conn, $user_id, $subject, $messageText, $emailErr)) {
            $success = 'Your message has been sent to cooperative administration. Admin has been notified.';
        } else {
            $error = 'Failed to send message via email: ' . htmlspecialchars($emailErr);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contact Cooperative | Agrilink</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #f4a261;
      --bg: linear-gradient(150deg, #fffbe6 0%, #fef6d3 100%);
      --card: #ffffff;
      --text: #1f2937;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --success: #10b981;
      --danger: #ef4444;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); }
    a { color: var(--primary); text-decoration: none; }
    .header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; background: var(--primary); color: white; padding: 1rem 2rem; position: sticky; top: 0; z-index: 100; }
    .logo-area { display: flex; align-items: center; gap: 0.75rem; }
    .logo-img { height: 44px; width: auto; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.14)); }
    .logo-text { font-weight: 700; letter-spacing: 0.5px; }
    .navbar ul { display: flex; flex-wrap: wrap; list-style: none; gap: 0.75rem; align-items: center; }
    .navbar a { color: rgba(255,255,255,0.92); padding: 0.65rem 0.9rem; border-radius: 10px; transition: background 0.2s ease; }
    .navbar a:hover, .navbar a.active { background: rgba(255,255,255,0.2); }
    .notif-wrapper { position: relative; display: inline-flex; align-items: center; }
    .notif-badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 0.65rem; font-weight: 700; min-width: 18px; height: 18px; border-radius: 999px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
    .page-container { max-width: 980px; margin: 2rem auto 3rem; padding: 0 1rem; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 18px 55px rgba(15, 23, 42, 0.08); overflow: hidden; }
    .card-header { padding: 1.8rem 2rem; background: #eef8f6; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
    .card-header h1 { font-size: 1.5rem; line-height: 1.2; }
    .card-body { padding: 2rem; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .field { margin-bottom: 1.2rem; }
    label { display: block; font-weight: 600; margin-bottom: 0.6rem; color: var(--text-muted); }
    input, textarea { width: 100%; border: 1px solid var(--border); border-radius: 12px; background: #fff; padding: 0.95rem 1rem; font-size: 0.98rem; color: var(--text); }
    textarea { min-height: 170px; resize: vertical; }
    .btn { background: var(--primary); border: 0; color: white; padding: 0.95rem 1.4rem; font-size: 0.98rem; border-radius: 12px; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(42, 157, 143, 0.16); }
    .alert { border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; border: 1px solid transparent; }
    .alert.success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .alert.error { background: #ffe4e6; color: #991b1b; border-color: #fecdd3; }
    .help-box { background: #f8fafc; border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem 1.5rem; margin-top: 1.5rem; }
    .help-box p { margin-bottom: 0.75rem; color: var(--text-muted); line-height: 1.8; }
    @media (max-width: 820px) { .grid-2 { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php include 'glow_bg.php'; ?>
  <?php include 'notif_panel.php'; ?>
  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink" class="logo-img" />
      <span class="logo-text">Agrilink Cooperative</span>
    </div>
    <nav class="navbar">
      <ul>
        <li><a href="member.php">Home</a></li>
        <li><a href="Hives.php">Hives</a></li>
        <li><a href="member-inspection.php">Inspections</a></li>
        <li><a href="finances.php">Finances</a></li>
        <li><a href="training.php">Training Hub</a></li>
        <li><a href="contact_cooperative.php" class="active">Contact</a></li>
        <li><a href="#" onclick="toggleNotifPanel(event)" title="Notifications">
          <span class="notif-wrapper">
            <i class="fa-solid fa-bell"></i>
            <?php if ($notifCount > 0): ?>
              <span class="notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
            <?php endif; ?>
          </span>
        </a></li>
        <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i></a></li>
        <li><a href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
      </ul>
    </nav>
  </header>

  <main class="page-container">
    <div class="card">
      <div class="card-header">
        <div>
          <h1>Contact the Cooperative</h1>
          <p style="margin-top: 0.5rem; color: var(--text-muted);">Send your question, complaint or support request directly to cooperative administration.</p>
        </div>
        <div style="text-align: right; color: var(--text-muted);">
          <div><strong>Cooperative email:</strong></div>
          <div><a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a></div>
        </div>
      </div>
      <div class="card-body">
        <?php if (!empty($success)): ?>
          <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
          <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="contact_cooperative.php">
          <div class="field">
            <label for="subject">Subject</label>
            <input id="subject" name="subject" type="text" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" placeholder="Brief summary of your message" required />
          </div>
          <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" placeholder="Write your message here..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
          </div>
          <div class="field">
            <button class="btn" name="send_contact" type="submit">Send message</button>
          </div>
        </form>

        <div class="help-box">
          <p><strong>Tip:</strong> Use this form for any cooperative request, issue, or support need.</p>
          <p>Admin will receive an in-app notification and your message will be emailed to cooperative admin. If needed, you can also email directly to <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.</p>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
