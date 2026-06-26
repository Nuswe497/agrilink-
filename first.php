<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Livingstonia Bee Keeping Cooperative | Agrilink Management System</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome (for icons if needed later) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <style>
    :root {
      --primary:    #1e5c38;     /* deep forest green */
      --primary-light: #2c7d4e;
      --accent:     #d4a017;     /* rich honey gold */
      --dark:       #1f2937;
      --gray:       #4b5563;
      --light:      #f8fafc;
      --bg:         #ffffff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--dark);
      line-height: 1.6;
    }

    /* Header / Navbar */
    header {
      position: fixed;
      top: 0;
      width: 100%;
      background: rgba(255, 255, 255, 0.97);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(0,0,0,0.08);
      z-index: 1000;
      transition: background 0.3s;
    }

    .nav-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 1.2rem 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--primary);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }

    .logo span {
      color: var(--accent);
    }

    nav ul {
      display: flex;
      list-style: none;
      gap: 2.2rem;
    }

    nav a {
      color: var(--gray);
      text-decoration: none;
      font-weight: 500;
      font-size: 1rem;
      transition: color 0.25s;
    }

    nav a:hover,
    nav a.active {
      color: var(--primary);
    }

    .btn {
      display: inline-block;
      padding: 0.75rem 1.8rem;
      border-radius: 50px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--primary-light);
      transform: translateY(-2px);
    }

    .btn-outline {
      background: transparent;
      color: var(--primary);
      border-color: var(--primary);
    }

    .btn-outline:hover {
      background: var(--primary);
      color: white;
    }

    /* Hero */
    .hero {
      height: 100vh;
      min-height: 720px;
      background: linear-gradient(rgba(30, 92, 56, 0.55), rgba(30, 92, 56, 0.65)),
                  url('https://www.bootstrapfarmer.com/cdn/shop/articles/Flower_Farm_with_Cut_Flowers_1600x.jpg?v=1763572863') center/cover no-repeat;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 0 1.5rem;
      position: relative;
    }

    .hero-content {
      max-width: 900px;
    }

    .hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.8rem, 6vw, 4.8rem);
      margin-bottom: 1.2rem;
      line-height: 1.1;
    }

    .hero h1 span {
      color: var(--accent);
    }

    .hero p {
      font-size: 1.35rem;
      max-width: 720px;
      margin: 0 auto 2.5rem;
      opacity: 0.95;
    }

    .hero-cta {
      display: flex;
      gap: 1.5rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    /* Features teaser (optional subtle section) */
    .features {
      padding: 6rem 3rem;
      background: var(--light);
      text-align: center;
    }

    .features h2 {
      font-family: 'Playfair Display', serif;
      font-size: 2.8rem;
      color: var(--primary);
      margin-bottom: 3rem;
    }

    .feature-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2.5rem;
      max-width: 1200px;
      margin: 0 auto;
    }

    .feature-card {
      background: white;
      padding: 2.2rem;
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.06);
      transition: transform 0.3s;
    }

    .feature-card:hover {
      transform: translateY(-8px);
    }

    .feature-card h3 {
      color: var(--primary);
      margin-bottom: 1rem;
      font-size: 1.4rem;
    }

    /* Footer */
    footer {
      background: var(--primary);
      color: white;
      padding: 3rem 3rem 1.5rem;
      text-align: center;
    }

    footer p {
      margin: 0.6rem 0;
      opacity: 0.9;
    }

    @media (max-width: 992px) {
      .nav-container { padding: 1rem 1.5rem; }
      nav ul { gap: 1.2rem; font-size: 0.95rem; }
      .hero h1 { font-size: 3.2rem; }
      .hero p { font-size: 1.15rem; }
    }

    @media (max-width: 768px) {
      nav ul {
        flex-direction: column;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        padding: 1.5rem;
        display: none; /* add JS for hamburger later */
      }
      .hero-cta { flex-direction: column; }
    }
  </style>
</head>
<body>

  <header>
    <div class="nav-container">
      <a href="#" class="logo">Livingstonia <span>Bee</span> Cooperative</a>
      
      <nav>
        <ul>
          <li><a href="#" class="active">Home</a></li>
          <li><a href="login.php">Sign In</a></li>
          <li><a href="member_registration.php">Join Now</a></li>
          <li><a href="external_dashboard.php">External Portal</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="hero">
    <div class="hero-content">
      <h1>Empowering Beekeepers,<br><span>Sustainably</span></h1>
      <p>Agrilink — the digital platform transforming transparency, coordination, and market access for the Livingstonia Bee Keeping Cooperative and its partners.</p>
      
      <div class="hero-cta">
        <a href="member_registration.php" class="btn btn-primary">Become a Member</a>
        <a href="login.php" class="btn btn-outline">Sign In to Agrilink</a>
      </div>
    </div>
  </section>

  <!-- Optional: Value proposition section -->
  <section class="features">
    <h2>Why Agrilink?</h2>
    <div class="feature-grid">
      <div class="feature-card">
        <h3>Transparent Finances</h3>
        <p>Real-time tracking of contributions, sales, and profit distribution — no more delays or disputes.</p>
      </div>
      <div class="feature-card">
        <h3>Smart Hive Management</h3>
        <p>Schedule inspections, record health data, and ensure colony strength with digital tools.</p>
      </div>
      <div class="feature-card">
        <h3>Stronger Market Linkages</h3>
        <p>Buyers, suppliers, and NGOs access verified reports, schedules, and product insights.</p>
      </div>
      <div class="feature-card">
        <h3>Knowledge Hub</h3>
        <p>Training materials, best practices, and resources to build capacity and sustainability.</p>
      </div>
    </div>
  </section>

  <footer>
    <p>&copy; <?php echo date('Y'); ?> Livingstonia Bee Keeping Cooperative. All rights reserved.</p>
    <p>Agrilink Management System | Developed for sustainable beekeeping in Malawi</p>
    <p>Contact: support@agrilink.org</p>
  </footer>

</body>
</html>