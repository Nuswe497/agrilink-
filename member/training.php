<?php
session_start();
require 'db.php';
require 'notif_count.php'; // provides $notifCount

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch training materials
$stmt = $conn->query("SELECT * FROM training_materials ORDER BY upload_date DESC");
$trainings = $stmt->fetch_all(MYSQLI_ASSOC);

// Expanded data for External Trends
$all_trends = [
    [
        "title" => "Climate Change Impact on Global Pollination",
        "category" => "Global News",
        "excerpt" => "New studies reveal shifting weather patterns are disrupting bee foraging behaviors, leading to lower yields.",
        "icon" => "fa-solid fa-earth-americas",
        "read_time" => "5 min read",
        "url" => "https://www.unep.org/news-and-stories/story/why-bees-are-essential-people-and-planet"
    ],
    [
        "title" => "Top 5 Sustainable Hive Designs for 2026",
        "category" => "Technology",
        "excerpt" => "Explore the latest innovations in hive structures that boost honey production and bee health naturally.",
        "icon" => "fa-solid fa-box",
        "read_time" => "4 min read",
        "url" => "https://www.beeculture.com/"
    ],
    [
        "title" => "Organic Pest Management Techniques",
        "category" => "Best Practices",
        "excerpt" => "Learn how to defend your colonies from mites and beetles without introducing harmful chemicals.",
        "icon" => "fa-solid fa-shield-halved",
        "read_time" => "8 min read",
        "url" => "https://extension.psu.edu/honey-bee-diseases-and-pests"
    ],
    [
        "title" => "Market Trends: The Rise of Raw Honey",
        "category" => "Economics",
        "excerpt" => "Consumer demand for unprocessed, raw honey is skyrocketing. See how your cooperative can capitalize.",
        "icon" => "fa-solid fa-arrow-trend-up",
        "read_time" => "3 min read",
        "url" => "https://www.globenewswire.com/news-release/2023/04/18/2648796/0/en/Honey-Market-Size-to-Surpass-USD-14-2-Billion-by-2030-exhibiting-a-CAGR-of-5-8.html"
    ],
    [
        "title" => "The Role of Bees in Global Food Security",
        "category" => "Agriculture",
        "excerpt" => "One third of the food we consume each day relies on pollination mainly by bees.",
        "icon" => "fa-solid fa-wheat-awn",
        "read_time" => "6 min read",
        "url" => "https://www.fao.org/world-bee-day/en/"
    ],
    [
        "title" => "Smart Apiary: IoT Sensors in Beekeeping",
        "category" => "Technology",
        "excerpt" => "How internet-connected sensors are helping beekeepers monitor hive temperature, humidity, and weight remotely.",
        "icon" => "fa-solid fa-microchip",
        "read_time" => "5 min read",
        "url" => "https://www.mdpi.com/1424-8220/20/11/3141"
    ],
    [
        "title" => "Planting for Pollinators: Best Floral Sources",
        "category" => "Environment",
        "excerpt" => "A comprehensive guide on what to plant around your apiary to ensure year-round nectar and pollen flows.",
        "icon" => "fa-solid fa-seedling",
        "read_time" => "7 min read",
        "url" => "https://www.fs.usda.gov/managing-land/wildflowers/pollinators"
    ],
    [
        "title" => "Understanding Bee Swarming Behavior",
        "category" => "Biology",
        "excerpt" => "Why do bees swarm, and how can beekeepers manage or prevent swarming effectively to maintain colony strength.",
        "icon" => "fa-brands fa-forumbee",
        "read_time" => "9 min read",
        "url" => "https://entomology.ca.uky.edu/ef602"
    ],
];

// Shuffle the array and pick the first 4 elements to make it dynamic
shuffle($all_trends);
$trends = array_slice($all_trends, 0, 4);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Training Hub - Agrilink</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --green:    #2a9d8f;
      --green-dark:#247b73;
      --orange:   #f4a261;
      --bg:       #f8fafc;
      --card:     #ffffff;
      --text:     #1e293b;
      --text-light:#475569;
      --border:   #e2e8f0;
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

    .header {
      background: var(--green);
      color: white;
      padding: 1rem 2.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }
    .logo { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.5px; }
    .navbar ul { display: flex; list-style: none; gap: 2rem; align-items: center; }
    .navbar a { color: white; text-decoration: none; font-weight: 500; font-size: 0.98rem; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.4rem; }
    .navbar a:hover, .navbar a:focus { color: var(--orange); transform: translateY(-1px); }

    /* Notification badge */
    .notif-wrapper { position: relative; display: inline-flex; align-items: center; }
    .notif-badge {
      position: absolute; top: -9px; right: -11px;
      background: #ef4444; color: white;
      font-size: 0.6rem; font-weight: 700;
      min-width: 17px; height: 17px; border-radius: 999px;
      display: flex; align-items: center; justify-content: center;
      padding: 0 3px; border: 2px solid var(--green); line-height: 1;
      animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
      0%, 100% { transform: scale(1); }
      50%       { transform: scale(1.2); }
    }

    main {
      flex: 1;
      padding: 2.5rem;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .page-title {
      font-size: 2.1rem;
      font-weight: 700;
      color: var(--green);
      margin-bottom: 1.8rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--text);
      margin: 2rem 0 1rem 0;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--border);
    }

    /* Training Materials Grid */
    .training-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }
    
    .training-card {
      background: var(--card);
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
      padding: 1.6rem;
      display: flex;
      flex-direction: column;
      transition: all 0.25s ease;
      border-top: 4px solid var(--green);
    }
    .training-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    }
    .training-card h3 {
      font-size: 1.25rem;
      color: var(--text);
      margin-bottom: 0.5rem;
    }
    .training-card p {
      color: var(--text-light);
      font-size: 0.9rem;
      margin-bottom: 1.5rem;
      flex-grow: 1;
    }
    .training-card .btn {
      background: rgba(42, 157, 143, 0.1);
      color: var(--green-dark);
      text-decoration: none;
      padding: 0.8rem;
      text-align: center;
      border-radius: 6px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .training-card .btn:hover {
      background: var(--green);
      color: white;
    }

    /* External Trends Array */
    .trends-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }
    
    .trend-card {
      background: var(--card);
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: all 0.25s ease;
    }
    .trend-card:hover {
      transform: scale(1.02);
      box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    }
    .trend-header {
      background: linear-gradient(135deg, var(--green), var(--green-dark));
      color: white;
      padding: 2rem 1.5rem;
      text-align: center;
    }
    .trend-header i { font-size: 2.5rem; opacity: 0.9; }
    .trend-body {
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      flex: 1;
    }
    .badge {
      align-self: flex-start;
      background: #f1f5f9;
      color: var(--orange);
      padding: 0.3rem 0.8rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 1rem;
    }
    .trend-body h3 {
      font-size: 1.2rem;
      color: var(--text);
      margin-bottom: 0.8rem;
      line-height: 1.4;
    }
    .trend-body p {
      color: var(--text-light);
      font-size: 0.95rem;
      line-height: 1.5;
      margin-bottom: 1.5rem;
      flex: 1;
    }
    .trend-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid var(--border);
      padding-top: 1rem;
      font-size: 0.85rem;
      color: #94a3b8;
      font-weight: 500;
    }
    .read-more {
      color: var(--green);
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .read-more:hover { color: var(--orange); }

    footer { background: var(--green); color: white; text-align: center; padding: 1.4rem; margin-top: auto; font-size: 0.92rem; }
    @media (max-width: 768px) { .header { flex-direction: column; gap: 1.2rem; padding: 1.2rem 1.5rem; } .navbar ul { flex-wrap: wrap; justify-content: center; gap: 1.2rem; } main { padding: 1.5rem; } }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <header class="header">
    <div class="logo">Livingstonia BeeKeeping Agrilink</div>
    <nav class="navbar">
      <ul>
        <li><a href="member.php">Home</a></li>
        <li><a href="Hives.php">Hive</a></li>
        <li><a href="member-inspection.php">Inspections</a></li>
        <li><a href="finances.php">Finances</a></li>
        <li><a href="training.php">Training Hub</a></li>
        <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i></a></li>
        <li>
          <a href="#" onclick="toggleNotifPanel(event)" title="Notifications">
            <span class="notif-wrapper">
              <i class="fa-solid fa-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
              <?php endif; ?>
            </span>
          </a>
        </li>
        <li><a href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
      </ul>
    </nav>
  </header>

  <main>
    <div class="page-title">
      <i class="fa-solid fa-book-open-reader"></i> Training Hub
    </div>
    
    <div class="section-title">Cooperative Resources</div>
    <div class="training-grid">
      <?php if (empty($trainings)): ?>
        <p style="color: var(--text-light); grid-column: 1/-1;">No training materials available right now.</p>
      <?php else: ?>
        <?php foreach ($trainings as $item): ?>
          <div class="training-card">
            <h3><?php echo htmlspecialchars($item['title']); ?></h3>
            <p><i class="fa-regular fa-calendar"></i> <?php echo date("F j, Y", strtotime($item['upload_date'])); ?></p>
            <a href="../<?php echo htmlspecialchars($item['content']); ?>" class="btn" target="_blank" download>
              <i class="fa-solid fa-file-arrow-down"></i> Download Material
            </a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="section-title">Discover Trends</div>
    <div class="trends-grid">
      <?php foreach ($trends as $trend): ?>
        <div class="trend-card">
          <div class="trend-header">
            <i class="<?php echo $trend['icon']; ?>"></i>
          </div>
          <div class="trend-body">
            <span class="badge"><?php echo $trend['category']; ?></span>
            <h3><?php echo $trend['title']; ?></h3>
            <p><?php echo $trend['excerpt']; ?></p>
            <div class="trend-footer">
              <span><i class="fa-regular fa-clock"></i> <?php echo $trend['read_time']; ?></span>
              <a href="<?php echo $trend['url']; ?>" class="read-more" target="_blank" rel="noopener noreferrer">Read More <i class="fa-solid fa-arrow-right"></i></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </main>

  <footer>
    © 2026 Agrilink Cooperative | Support: livingstoniaagrilink@gmail.com
  </footer>

</body>
</html>

