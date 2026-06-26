<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Ensure required columns exist (migration helper)
$check = $conn->query("SHOW COLUMNS FROM finance LIKE 'transaction_type'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE finance ADD COLUMN transaction_type VARCHAR(50) NOT NULL DEFAULT 'contribution'");
}
$check = $conn->query("SHOW COLUMNS FROM finance LIKE 'description'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE finance ADD COLUMN description TEXT NULL");
}

// Handle form submissions for adding finances
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_finance'])) {
        $user_id     = $_POST['user_id'];
        $type        = $_POST['type'];
        $amount      = $_POST['amount'];
        $date        = $_POST['date'];
        $description = trim($_POST['description'] ?? '');

        $stmt = $conn->prepare("INSERT INTO finance (user_id, transaction_type, amount, date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $type, $amount, $date, $description);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch all finances
$result = $conn->query("SELECT f.*, m.full_name FROM finance f JOIN users m ON f.user_id = m.user_id ORDER BY f.date DESC, f.finance_id DESC");
$finances = $result->fetch_all(MYSQLI_ASSOC);

// Fetch members for dropdown
$members_result = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'member'");
$members = $members_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Finances</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --green: #2a9d8f;
    --green-d: #247b73;
    --orange: #f4a261;
    --orange-d: #e38b3a;
    --bg: #fdfaf5;
    --text: #1f2937;
    --text-light: #6b7280;
    --border: #e5e7eb;
    --radius: 12px;
  }

  body {
    font-family: 'Outfit', sans-serif;
    margin: 0;
    background: #f8fafc;
    color: var(--text);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  .page-header {
    background: var(--green);
    color: white;
    padding: 15px 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
  }

  .page-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .header-nav {
    display: flex;
    gap: 20px;
    align-items: center;
  }

  .header-nav a {
    color: white;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    border-radius: 8px;
  }

  .header-nav a:hover {
    background: rgba(255, 255, 255, 0.15);
  }

  main {
    flex: 1;
    padding: 30px 5%;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
  }

  .finance-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 2rem;
  }

  @media (max-width: 992px) {
    .finance-grid {
      grid-template-columns: 1fr;
    }
  }

  .card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
  }

  .card h3 {
    color: var(--green);
    margin-top: 0;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 10px;
  }

  form label {
    display: block;
    margin-top: 1rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-light);
  }

  form input, form select, form textarea {
    width: 100%;
    padding: 0.75rem;
    margin-top: 0.4rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
    font-size: 1rem;
    transition: border-color 0.3s;
  }

  form input:focus, form select:focus {
    outline: none;
    border-color: var(--green);
  }

  .btn {
    margin-top: 1.5rem;
    background: var(--green);
    color: white;
    border: none;
    padding: 0.9rem 1.2rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    width: 100%;
    transition: 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
  }

  .btn:hover {
    background: var(--green-d);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(42, 157, 143, 0.2);
  }

  .btn-danger {
    background: #ef4444;
  }

  .btn-danger:hover {
    background: #dc2626;
  }

  .table-container {
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
  }

  table th, table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
  }

  table th {
    background: #f8fafc;
    color: var(--text-light);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
  }

  .type-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: capitalize;
  }

  .type-contribution { background: #dcfce7; color: #166534; }
  .type-fee { background: #fee2e2; color: #991b1b; }
  .type-sale { background: #e0f2fe; color: #075985; }
  .type-profit { background: #fef3c7; color: #92400e; }

  .page-footer {
    background: #2a9d8f;
    color: white;
    text-align: center;
    padding: 2.5rem;
    margin-top: 4rem;
    font-size: 0.95rem;
    border-top: 5px solid #f4a261;
    font-weight: 500;
  }

  .page-footer a {
    color: var(--orange);
    text-decoration: none;
  }
</style>
</head>
<body>
  <?php include 'glow_bg.php'; ?>
  <?php include 'notif_panel.php'; ?>

  <header class="page-header">
    <h1><i class="fas fa-wallet"></i> Financial Ledger</h1>
    <nav class="header-nav">
      <a href="admin.php"><i class="fas fa-home"></i> Home</a>
      <a href="admin_members.php"><i class="fas fa-users"></i> Members</a>
      <a href="#" onclick="toggleNotifPanel(event)" title="Notifications">
        <i class="fa-solid fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span style="background:#ef4444; color:white; font-size:0.65rem; border-radius:50%; padding:2px 6px; margin-left:-8px;"><?= $notifCount ?></span>
        <?php endif; ?>
      </a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </header>

  <main>
    <div class="finance-grid">
      <section class="card">
        <h3><i class="fas fa-plus-circle"></i> New Entry</h3>
        <form method="POST">
          <label>Member Name</label>
          <select name="user_id" required>
            <option value="">Select Member</option>
            <?php foreach ($members as $member): ?>
              <option value="<?php echo $member['user_id']; ?>"><?php echo $member['full_name']; ?></option>
            <?php endforeach; ?>
          </select>

          <label>Transaction Type</label>
          <select name="type" required>
            <option value="contribution">Contribution</option>
            <option value="fee">Fee</option>
            <option value="sale">Sale</option>
            <option value="profit">Profit</option>
          </select>

          <label>Description</label>
          <input type="text" name="description" placeholder="Optional notes...">

          <label>Amount (MWK)</label>
          <input type="number" step="0.01" name="amount" required placeholder="0.00">

          <label>Transaction Date</label>
          <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>

          <button type="submit" name="add_finance" class="btn"><i class="fas fa-save"></i> Save Entry</button>
        </form>
      </section>

      <section class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
          <h3 style="margin: 0;"><i class="fas fa-list"></i> Transaction History</h3>
          <button type="button" class="btn btn-danger" style="margin: 0; padding: 8px 15px; width: auto;" onclick="openPurgeModal()">
            <i class="fas fa-broom"></i> Purge Test Data
          </button>
        </div>

        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Member</th>
                <th>Type</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($finances as $finance): ?>
                <tr>
                  <td style="font-weight: 600;"><?php echo htmlspecialchars($finance['full_name']); ?></td>
                  <td><span class="type-badge type-<?php echo $finance['transaction_type']; ?>"><?php echo $finance['transaction_type']; ?></span></td>
                  <td style="color: var(--text-light); font-size: 0.9rem;"><?php echo htmlspecialchars($finance['description'] ?? '-'); ?></td>
                  <td style="font-weight: 700; color: var(--green);">MK <?php echo number_format($finance['amount'], 2); ?></td>
                  <td><?php echo date('M d, Y', strtotime($finance['date'])); ?></td>
                  <td>
                    <a href="delete_finance.php?id=<?php echo $finance['finance_id']; ?>" 
                       style="color: #ef4444;" 
                       onclick="return confirm('Permanently delete this record?')">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>

  <!-- Purge Modal -->
  <div id="purgeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index:2000; justify-content:center; align-items:center;">
    <div class="card" style="width:400px; padding:30px;">
      <h3 style="margin-top:0; color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> Purge Transactions</h3>
      <p style="font-size:0.9rem; color:var(--text-light); margin-bottom:20px;">Select a date range to permanently delete transaction records. This action is irreversible.</p>
      
      <form action="purge_transactions.php" method="POST">
        <label>Start Date</label>
        <input type="date" name="start_date" required>
        
        <label>End Date</label>
        <input type="date" name="end_date" required>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:20px;">
          <button type="button" class="btn" style="background:#6b7280; margin:0;" onclick="closePurgeModal()">Cancel</button>
          <button type="submit" class="btn btn-danger" style="margin:0;" onclick="return confirm('ARE YOU SURE? This will delete all financial records in this range.')">Purge Now</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openPurgeModal() { document.getElementById('purgeModal').style.display = 'flex'; }
    function closePurgeModal() { document.getElementById('purgeModal').style.display = 'none'; }
    window.onclick = function(event) { if (event.target == document.getElementById('purgeModal')) closePurgeModal(); }
  </script>

  <footer class="page-footer">
    <p>&copy; 2026 Agrilink Cooperative | <i class="fas fa-envelope"></i> livingstoniaagrilink@gmail.com</p>
  </footer>
</body>
</html>
