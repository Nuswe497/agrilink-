<?php
/**
 * notif_panel.php  – Notification dropdown panel for members
 * Requires: $conn, $user_id, $notifCount (from notif_count.php)
 */

$role = $_SESSION['role'] ?? 'member';
if ($role === 'admin') {
    $target_role = 'admin';
} elseif ($role === 'treasurer') {
    $target_role = 'treasurer';
} else {
    $target_role = 'member';
}

$_panel_notifs = [];
$_panel_stmt = $conn->prepare("
    SELECT n.*, (CASE WHEN r.notification_id IS NOT NULL THEN 1 ELSE 0 END) as is_read
    FROM notifications n
    LEFT JOIN user_notif_read r ON n.id = r.notification_id AND r.user_id = ?
    WHERE n.target_role = ?
    ORDER BY n.created_at DESC
    LIMIT 30
");
$_panel_stmt->bind_param("is", $user_id, $target_role);
$_panel_stmt->execute();
$_panel_notifs = $_panel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$_panel_stmt->close();
?>

<!-- ─── Notification Dropdown Panel ─────────────────────────────────────── -->
<style>
/* Panel overlay backdrop */
#notifBackdrop {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 899;
  background: rgba(0,0,0,0.05);
}

/* The dropdown panel */
#notifPanel {
  display: none;
  position: fixed;
  top: 70px;
  right: 18px;
  width: 380px;
  max-width: calc(100vw - 24px);
  max-height: 520px;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 4px 12px rgba(0,0,0,0.08);
  z-index: 900;
  overflow: hidden;
  flex-direction: column;
  transform-origin: top right;
  animation: panelIn 0.22s cubic-bezier(0.34,1.56,0.64,1) forwards;
}
#notifPanel.closing {
  animation: panelOut 0.18s ease-in forwards;
}

@keyframes panelIn  { from { opacity:0; transform: scale(0.88) translateY(-12px); } to { opacity:1; transform: scale(1) translateY(0); } }
@keyframes panelOut { from { opacity:1; transform: scale(1) translateY(0);   } to { opacity:0; transform: scale(0.88) translateY(-12px); } }

/* Panel header */
.np-header {
  background: #2a9d8f;
  color: #fff;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.np-header h4 { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; margin: 0; }
.np-actions { display: flex; gap: 8px; align-items: center; }
.np-mark-all {
  background: rgba(255,255,255,0.18);
  border: none; color: #fff;
  padding: 5px 12px; border-radius: 8px;
  font-size: 0.78rem; font-weight: 600;
  cursor: pointer; transition: background .2s;
}
.np-mark-all:hover { background: rgba(255,255,255,0.3); }
.np-close {
  background: none; border: none; color: rgba(255,255,255,0.8);
  font-size: 1.1rem; cursor: pointer; line-height: 1;
  width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
  border-radius: 50%; transition: all .2s;
}
.np-close:hover { background: rgba(255,255,255,0.2); color: #fff; }

/* Panel unread count sub-line */
.np-subline { font-size: 0.75rem; opacity: .8; font-weight: 500; }

/* Scrollable list */
.np-list {
  overflow-y: auto;
  flex: 1;
  padding: 8px 0;
}
.np-list::-webkit-scrollbar { width: 5px; }
.np-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

/* Individual notification item */
.np-item {
  padding: 13px 18px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background .15s;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.np-item:last-child { border-bottom: none; }
.np-item:hover { background: #f8fafc; }
.np-item.unread { background: #f0faf9; }
.np-item.unread:hover { background: #e6f7f5; }

/* Dot indicator */
.np-dot {
  width: 9px; height: 9px; border-radius: 50%;
  margin-top: 5px; flex-shrink: 0;
  background: #2a9d8f;
  transition: opacity .3s;
}
.np-item:not(.unread) .np-dot { background: #cbd5e1; }

.np-content { flex: 1; min-width: 0; }
.np-title { font-weight: 600; font-size: 0.88rem; color: #1e293b; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.np-msg   { font-size: 0.82rem; color: #64748b; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.np-date  { font-size: 0.72rem; color: #94a3b8; margin-top: 4px; }

/* Empty state */
.np-empty { text-align: center; padding: 40px 20px; color: #94a3b8; }
.np-empty i { font-size: 2.5rem; display: block; margin-bottom: 10px; opacity: .35; }
.np-empty p { font-size: 0.88rem; }

/* Panel footer */
.np-footer {
  border-top: 1px solid #e2e8f0;
  padding: 10px 18px;
  text-align: center;
  flex-shrink: 0;
}
.np-footer a { color: #2a9d8f; font-size: 0.82rem; font-weight: 600; text-decoration: none; }
.np-footer a:hover { text-decoration: underline; }
</style>

<!-- Backdrop (click-outside closes panel) -->
<div id="notifBackdrop" onclick="closeNotifPanel()"></div>

<!-- The floating panel itself -->
<div id="notifPanel">
  <div class="np-header">
    <div>
      <h4><i class="fa-solid fa-bell"></i> Notifications
        <span id="npBadge" style="background:rgba(255,255,255,0.25);border-radius:999px;padding:1px 8px;font-size:.75rem;"
              <?php if ($notifCount <= 0) echo 'hidden'; ?>>
          <?= $notifCount ?>
        </span>
      </h4>
      <div class="np-subline" id="npSubline">
        <?= $notifCount > 0 ? "$notifCount unread" : "All caught up!" ?>
      </div>
    </div>
    <div class="np-actions">
      <?php if ($notifCount > 0): ?>
      <button class="np-mark-all" onclick="markAllRead()"><i class="fa-solid fa-check-double"></i> Mark all read</button>
      <?php endif; ?>
      <button class="np-close" onclick="closeNotifPanel()" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
  </div>

  <div class="np-list" id="npList">
    <?php if (empty($_panel_notifs)): ?>
    <div class="np-empty">
      <i class="fa-solid fa-inbox"></i>
      <p>No announcements yet.</p>
    </div>
    <?php else: ?>
      <?php foreach ($_panel_notifs as $n): ?>
      <div class="np-item <?= $n['is_read'] ? '' : 'unread' ?>"
           data-id="<?= $n['id'] ?>"
           onclick="markOneRead(this)">
        <div class="np-dot"></div>
        <div class="np-content">
          <div class="np-title"><?= htmlspecialchars($n['title']) ?></div>
          <div class="np-msg"><?= htmlspecialchars($n['message']) ?></div>
          <div class="np-date"><i class="fa-regular fa-clock"></i> <?= date('d M Y, g:i a', strtotime($n['created_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="np-footer">
    <a href="notification.php"><i class="fa-solid fa-arrow-right"></i> View all notifications</a>
  </div>
</div>

<script>
// ── Panel open / close ──────────────────────────────────────────────────────
let _npOpen = false;

function toggleNotifPanel(event) {
  event.stopPropagation();
  _npOpen ? closeNotifPanel() : openNotifPanel();
}

function openNotifPanel() {
  const panel   = document.getElementById('notifPanel');
  const backdrop = document.getElementById('notifBackdrop');
  panel.style.display   = 'flex';
  backdrop.style.display = 'block';
  panel.classList.remove('closing');
  _npOpen = true;
}

function closeNotifPanel() {
  const panel   = document.getElementById('notifPanel');
  const backdrop = document.getElementById('notifBackdrop');
  panel.classList.add('closing');
  backdrop.style.display = 'none';
  _npOpen = false;
  setTimeout(() => { panel.style.display = 'none'; panel.classList.remove('closing'); }, 180);
}

// ── Mark single notification as read ───────────────────────────────────────
function markOneRead(el) {
  if (!el.classList.contains('unread')) return;
  const id = el.getAttribute('data-id');
  fetch('mark_notif_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'notif_id=' + id
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') {
      el.classList.remove('unread');
      _npUpdateCount(-1);
    }
  });
}

// ── Mark all read ───────────────────────────────────────────────────────────
function markAllRead() {
  fetch('mark_notif_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'mark_all=1'
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') {
      document.querySelectorAll('#npList .np-item.unread').forEach(el => el.classList.remove('unread'));
      _npSetCount(0);
    }
  });
}

// ── Update count badges ─────────────────────────────────────────────────────
let _npCount = <?= (int)$notifCount ?>;

function _npUpdateCount(delta) {
  _npCount = Math.max(0, _npCount + delta);
  _npSetCount(_npCount);
}

function _npSetCount(n) {
  _npCount = n;

  // Panel badge
  const panelBadge = document.getElementById('npBadge');
  if (panelBadge) { panelBadge.textContent = n; panelBadge.hidden = (n <= 0); }

  // Subline
  const sub = document.getElementById('npSubline');
  if (sub) sub.textContent = n > 0 ? n + ' unread' : 'All caught up!';

  // All header bell badges on the page (class: notif-badge)
  document.querySelectorAll('.notif-badge').forEach(b => {
    if (n <= 0) { b.style.display = 'none'; }
    else { b.style.display = ''; b.textContent = n > 99 ? '99+' : n; }
  });
}
</script>
