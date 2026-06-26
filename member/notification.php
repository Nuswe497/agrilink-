<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
require 'notif_count.php'; // provides $notifCount for this specific user

$clearMsg = '';

// ── Handle "Clear all notifications" ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_notifications'])) {
    // Bulk mark all AS READ in the mapping table
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_notif_read (user_id, notification_id)
        SELECT ?, id FROM notifications
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    $notifCount = 0; // reset badge
    header("Location: notification.php?cleared=1");
    exit;
}

// ── Fetch notifications JOINED with read status ──────────────────────────────
$role = $_SESSION['role'] ?? 'member';
if ($role === 'admin') {
    $target_role = 'admin';
} elseif ($role === 'treasurer') {
    $target_role = 'treasurer';
} else {
    $target_role = 'member';
}

$notifications = [];
$sql = "
    SELECT n.*, (CASE WHEN r.notification_id IS NOT NULL THEN 1 ELSE 0 END) as is_read
    FROM notifications n
    LEFT JOIN user_notif_read r ON n.id = r.notification_id AND r.user_id = ?
    WHERE n.target_role = ?
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $target_role);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$justCleared = isset($_GET['cleared']) && $_GET['cleared'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Member Notifications | Agrilink</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --green:     #2a9d8f;
      --green-dark:#247b73;
      --orange:    #f4a261;
      --bg:        #f8fafc;
      --card:      #ffffff;
      --text:      #1e293b;
      --text-light:#475569;
      --border:    #e2e8f0;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      /* replaced */
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Header ── */
    .header {
      background: var(--green);
      color: white;
      padding: 1rem 2.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
      position: sticky;
      top: 0;
      z-index: 100;
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
    .navbar ul { display: flex; list-style: none; gap: 2rem; align-items: center; }
    .navbar a {
      color: white;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.98rem;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .navbar a:hover, .navbar a.active { color: var(--orange); }

    .notif-wrapper { position: relative; display: inline-flex; align-items: center; }
    .notif-badge {
      position: absolute; top: -8px; right: -10px;
      background: #ef4444; color: white;
      font-size: 0.65rem; font-weight: 700;
      min-width: 18px; height: 18px; border-radius: 999px;
      display: flex; align-items: center; justify-content: center;
      padding: 0 4px; border: 2px solid var(--green);
      line-height: 1; animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }

    main { flex: 1; padding: 2.5rem; max-width: 900px; margin: 0 auto; width: 100%; }

    .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
    .page-title { font-size: 2rem; font-weight: 700; color: var(--green); display: flex; align-items: center; gap: 10px; }

    .btn-clear {
      display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
      background: white; border: 2px solid #e2e8f0; border-radius: 10px;
      font-size: 0.9rem; font-weight: 600; color: var(--text-light); cursor: pointer; transition: all 0.2s ease;
    }
    .btn-clear:hover { border-color: #ef4444; color: #ef4444; background: #fff5f5; }

    .notification-grid { display: grid; grid-template-columns: 1fr; gap: 1.2rem; }
    .notification-card {
      background: var(--card); border-radius: 14px; box-shadow: 0 3px 10px rgba(0,0,0,0.06);
      padding: 1.5rem 1.8rem; border-left: 5px solid var(--orange); transition: all 0.22s ease;
      position: relative; cursor: pointer;
    }
    .notification-card.unread { border-left-color: var(--green); background: #f0faf9; }
    .notification-card.unread::after {
      content: 'NEW'; position: absolute; top: 14px; right: 16px;
      background: var(--green); color: white; font-size: 0.65rem; font-weight: 700;
      padding: 2px 8px; border-radius: 999px; letter-spacing: 0.5px;
    }
    .notification-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.09); }
    .notification-card h3 { color: var(--green); font-weight: 600; margin-bottom: 0.5rem; font-size: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding-right: 40px; }
    .notification-card p { color: var(--text-light); line-height: 1.65; font-size: 0.96rem; }
    .notif-date { font-size: 0.82rem; color: #94a3b8; font-weight: 500; white-space: nowrap; }

    .confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); z-index: 200; justify-content: center; align-items: center; }
    .confirm-box { background: white; padding: 2.5rem; border-radius: 20px; max-width: 420px; width: 90%; text-align: center; box-shadow: 0 25px 60px rgba(0,0,0,0.18); }

    .toast { display: flex; align-items: center; gap: 10px; background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; padding: 12px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 1.5rem; animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }

    /* ── Member Notification Popup Panel ── */
    body.notif-panel-open {
      overflow: hidden;
    }
    body.notif-panel-open header,
    body.notif-panel-open main,
    body.notif-panel-open footer {
      visibility: hidden;
    }
    #memberNotifBackdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: #ffffff;
      z-index: 190;
    }
    #memberNotifPanel {
      display: none;
      position: fixed;
      top: 80px;
      right: 18px;
      width: 360px;
      max-width: calc(100vw - 32px);
      max-height: 520px;
      background: #ffffff;
      border-radius: 22px;
      box-shadow: 0 22px 80px rgba(15, 23, 42, 0.18);
      overflow: hidden;
      z-index: 200;
      flex-direction: column;
      transform-origin: top right;
      animation: panelIn 0.22s ease-out forwards;
    }
    #memberNotifPanel.closing { animation: panelOut 0.16s ease-in forwards; }
    @keyframes panelIn { from { opacity: 0; transform: scale(0.94) translateY(-12px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    @keyframes panelOut { from { opacity: 1; transform: scale(1) translateY(0); } to { opacity: 0; transform: scale(0.94) translateY(-12px); } }
    .mnp-header { background: var(--green); color: white; padding: 16px 18px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .mnp-header h4 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .mnp-close { background: none; border: none; color: rgba(255,255,255,0.92); font-size: 1.1rem; cursor: pointer; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; transition: background .2s ease; }
    .mnp-close:hover { background: rgba(255,255,255,0.2); }
    .mnp-list { overflow-y: auto; flex: 1; padding: 10px 0; }
    .mnp-list::-webkit-scrollbar { width: 6px; }
    .mnp-list::-webkit-scrollbar-thumb { background: rgba(42,157,143,0.25); border-radius: 999px; }
    .mnp-item { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; align-items: flex-start; cursor: pointer; transition: background .2s ease; }
    .mnp-item:last-child { border-bottom: none; }
    .mnp-item:hover { background: #f8fafc; }
    .mnp-item.unread { background: #f0faf9; }
    .mnp-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; background: var(--green); flex-shrink: 0; }
    .mnp-item.unread .mnp-dot { background: var(--green); }
    .mnp-title { font-weight: 600; font-size: 0.92rem; color: #1e293b; margin-bottom: 4px; }
    .mnp-msg { font-size: 0.84rem; color: var(--text-light); line-height: 1.5; max-height: 3.3rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .mnp-date { font-size: 0.75rem; color: #94a3b8; margin-top: 8px; }

    footer {
      background: #2a9d8f;
      color: white;
      text-align: center;
      padding: 2rem;
      margin-top: auto;
      font-size: 0.95rem;
      border-top: 4px solid #f4a261;
      font-weight: 500;
    }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>

  <header class="header">
    <div class="logo-area">
      <img src="../assets/logo.png" alt="Agrilink Logo" class="logo-img">
      <span class="logo-text">LIVINGSTONIA BEE KEEPING - AGRILINK</span>
    </div>
      <nav class="navbar">
        <ul>
          <?php if ($role === 'admin'): ?>
            <li><a href="../admin/admin.php">Home</a></li>
            <li><a href="../admin/admin_stakeholder.php">Stakeholders</a></li>
            <li><a href="../admin/admin_finances.php">Finances</a></li>
            <li><a href="../admin/admin_reports.php">Reports</a></li>
          <?php elseif ($role === 'treasurer'): ?>
            <li><a href="../treasure/treasure.php">Home</a></li>
            <li><a href="../treasure/treasure_finances.php">Finances</a></li>
          <?php else: ?>
            <li><a href="member.php">Home</a></li>
            <li><a href="Hives.php">Hive</a></li>
            <li><a href="member-inspection.php">Inspections</a></li>
            <li><a href="finances.php">Finances</a></li>
            <li><a href="view_suppliers.php">Suppliers</a></li>
            <li><a href="training.php">Training Hub</a></li>
          <?php endif; ?>
          <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i></a></li>
        <li>
          <a href="#" onclick="openMemberNotifPanel(event)" class="active" title="Notifications">
            <span class="notif-wrapper">
              <i class="fa-solid fa-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="notif-badge" id="headerNotifBadge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
              <?php endif; ?>
            </span>
          </a>
        </li>
        <li><a href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
      </ul>
    </nav>
  </header>

  <main>
    <?php if ($justCleared): ?>
      <div class="toast"><i class="fa-solid fa-check-circle"></i> All notifications have been marked as read.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-title">
        <i class="fa-solid fa-bell"></i> System Announcements
        <?php if ($notifCount > 0): ?>
          <span id="unreadStatsBadge" style="background:#ef4444;color:white;font-size:1rem;padding:2px 12px;border-radius:999px;font-weight:700;">
            <?= $notifCount ?> new
          </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($notifications)): ?>
        <div class="clear-form">
          <button type="button" class="btn-clear" onclick="openClearConfirm()"><i class="fa-solid fa-check-double"></i> Mark All as Read</button>
        </div>
      <?php endif; ?>
    </div>

    <div class="notification-grid">
      <?php if (empty($notifications)): ?>
        <div class="empty-state"><i class="fa-solid fa-inbox"></i><p>No announcements at the moment.</p></div>
      <?php else: ?>
        <?php foreach ($notifications as $note): ?>
          <div class="notification-card <?= $note['is_read'] ? '' : 'unread' ?>" 
               data-id="<?= $note['id'] ?>" 
               onclick="markRead(this)">
            <h3>
              <?php echo htmlspecialchars($note['title']); ?>
              <span class="notif-date"><i class="fa-regular fa-clock"></i> <?php echo date("d M Y, g:i a", strtotime($note['created_at'])); ?></span>
            </h3>
            <p><?php echo nl2br(htmlspecialchars($note['message'])); ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; 2026 LIVINGSTONIA BEE KEEPING COOPERATIVE - AGRILINK | <i class="fa-solid fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>

  <div id="memberNotifBackdrop"></div>
  <div id="memberNotifPanel">
    <div class="mnp-header">
      <h4><i class="fa-solid fa-bell"></i> Notifications</h4>
      <button class="mnp-close" onclick="closeMemberNotifPanel()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mnp-list">
      <?php if (empty($notifications)): ?>
        <div class="mnp-item"><div class="mnp-content"><div class="mnp-title">No notifications</div><div class="mnp-msg">You have no new notifications at this time.</div></div></div>
      <?php else: ?>
        <?php foreach ($notifications as $note): ?>
          <div class="mnp-item <?= $note['is_read'] ? '' : 'unread' ?>">
            <div class="mnp-dot"></div>
            <div class="mnp-content">
              <div class="mnp-title"><?= htmlspecialchars($note['title']) ?></div>
              <div class="mnp-msg"><?= htmlspecialchars($note['message']) ?></div>
              <div class="mnp-date"><?= date('d M Y, g:i a', strtotime($note['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div id="clearOverlay" class="confirm-overlay">
    <div class="confirm-box">
      <div class="icon"><i class="fa-solid fa-check-double"></i></div>
      <h3>Mark All as Read?</h3>
      <p>This will remove the "NEW" status from all notifications. Are you sure?</p>
      <div class="confirm-actions">
        <button class="btn-cancel" onclick="closeClearConfirm()">Cancel</button>
        <form method="POST" style="display:inline;"><button type="submit" name="clear_notifications" class="btn-confirm-clear">Yes, Mark All</button></form>
      </div>
    </div>
  </div>

  <script>
    function markRead(element) {
      if (!element.classList.contains('unread')) return;
      const notifId = element.getAttribute('data-id');

      fetch('mark_notif_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notif_id=' + notifId
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          element.classList.remove('unread');
          updateBadge();
        }
      });
    }

    function updateBadge() {
      const statsBadge = document.getElementById('unreadStatsBadge');
      const headerBadge = document.getElementById('headerNotifBadge');
      
      let current = parseInt(statsBadge.innerText) || 0;
      if (current > 0) {
        current--;
        if (current === 0) {
          statsBadge.style.display = 'none';
          if (headerBadge) headerBadge.style.display = 'none';
        } else {
          statsBadge.innerText = current + ' new';
          if (headerBadge) headerBadge.innerText = current > 99 ? '99+' : current;
        }
      }
    }

    function openClearConfirm() { document.getElementById('clearOverlay').style.display = 'flex'; }
    function closeClearConfirm() { document.getElementById('clearOverlay').style.display = 'none'; }

    function openMemberNotifPanel(event) {
      if (event) event.preventDefault();
      document.body.classList.add('notif-panel-open');
      document.getElementById('memberNotifBackdrop').style.display = 'block';
      const panel = document.getElementById('memberNotifPanel');
      panel.style.display = 'flex';
      panel.classList.remove('closing');
    }

    function closeMemberNotifPanel() {
      const panel = document.getElementById('memberNotifPanel');
      panel.classList.add('closing');
      document.getElementById('memberNotifBackdrop').style.display = 'none';
      setTimeout(() => {
        panel.style.display = 'none';
        panel.classList.remove('closing');
        document.body.classList.remove('notif-panel-open');
      }, 160);
    }

    document.getElementById('memberNotifBackdrop').addEventListener('click', closeMemberNotifPanel);

    <?php if ($notifCount > 0): ?>
      document.addEventListener('DOMContentLoaded', function() {
        openMemberNotifPanel();
      });
    <?php endif; ?>
  </script>
</body>
</html>

