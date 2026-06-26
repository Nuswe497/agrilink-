<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>External Stakeholder Portal</title>
  <link rel="stylesheet" href="external_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <div class="nav-inner">
      <a href="../index.php" class="logo">
        <div class="logo-icon-wrap">
          <i class="fas fa-bee"></i>
        </div>
        <div class="logo-text">Agri<span>link</span></div>
      </a>
      
      <div class="role-switch">
        <label for="role">Stakeholder Portal:</label>
        <select id="role">
          <option value="ngo">NGO Partner</option>
          <option value="supplier">Supplier Portal</option>
          <option value="buyer">Buyer Storefront</option>
        </select>
      </div>

      <div class="nav-right">
        <a href="../index.php" title="Home"><i class="fa-solid fa-house"></i></a>
        <a href="../logout.php" title="Logout"><i class="fa-solid fa-sign-out-alt"></i></a>
      </div>
    </div>
  </nav>

  <!-- Main content -->
  <main id="main-content">
    <!-- Default welcome -->
 <section id="welcome" class="panel active">
  <h1>Empowering Beekeepers Through Digital Innovation</h1>
  <p>A comprehensive management system connecting cooperative members, buyers, suppliers, and NGOs 
     for transparent operations and sustainable growth.</p>

  <div class="actions">
    <a href="dashboard.php" class="btn btn-secondary">View Dashboard</a>
  </div>
</section>


    <!-- NGO dashboard -->
    <section id="ngo" class="panel">
      <h2>🌍 NGO Dashboard</h2>
      <div class="grid">
        <div class="card">
          <h3>📊 Cooperative Progress</h3>
          <p>Active members: 120</p>
          <p>Profit share distributed: MWK 2,500,000</p>
        </div>
        <div class="card">
          <h3>🔍 Inspection Reports</h3>
          <ul>
            <li>Jan 2026 — 95% hives healthy</li>
            <li>Dec 2025 — 92% hives healthy</li>
          </ul>
        </div>
        <div class="card">
          <h3>📑 Community Impact</h3>
          <p>10 training sessions funded</p>
          <p>3 local schools supported</p>
        </div>
      </div>
    </section>

    <!-- Supplier dashboard -->
    <section id="supplier" class="panel">
      <h2>📦 Supplier Dashboard</h2>
      <div class="grid">
        <div class="card">
          <h3>📋 Open Requests</h3>
          <ul>
            <li>Beekeeping Suits — Qty: 50 — Deadline: Jan 25, 2026</li>
            <li>Hive Frames — Qty: 200 — Deadline: Feb 10, 2026</li>
          </ul>
        </div>
        <div class="card">
          <h3>🚚 Active Deliveries</h3>
          <ul>
            <li>SO-002: Hive Frames — In Transit</li>
          </ul>
        </div>
        <div class="card">
          <h3>✅ Completed Orders</h3>
          <ul>
            <li>SO-001: Hive Frames — Delivered Jan 2, 2026</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Buyer dashboard -->
    <section id="buyer" class="panel">
      <h2>💰 Buyer Dashboard</h2>
      <div class="grid">
        <div class="card">
          <h3>🍯 Products Available</h3>
          <ul>
            <li>Pure Honey — 500kg</li>
            <li>Beeswax — 200kg</li>
          </ul>
        </div>
        <div class="card">
          <h3>📅 Market Schedule</h3>
          <p>Next sale: Feb 15, 2026</p>
          <p>Location: Livingstonia Market</p>
        </div>
        <div class="card">
          <h3>🧾 Purchase History</h3>
          <ul>
            <li>Dec 2025 — Honey 100kg</li>
            <li>Nov 2025 — Beeswax 50kg</li>
          </ul>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer>
    <p>&copy; 2026 Livingstonia BeeKeeping Agrilink Cooperative Management System | Support: support@coop.org</p>
  </footer>

  <!-- JS to switch panels -->
  <script>
    const roleSelect = document.getElementById('role');
    const panels = document.querySelectorAll('.panel');

    roleSelect.addEventListener('change', () => {
      panels.forEach(p => p.classList.remove('active'));
      const selected = roleSelect.value;
      document.getElementById(selected).classList.add('active');
    });
  </script>
</body>
</html>
