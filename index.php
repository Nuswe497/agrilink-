<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Agrilink – Livingstonia Bee Keeping Cooperative</title>
  <meta name="description" content="Agrilink is the official digital management platform for the Livingstonia Bee Keeping Cooperative — empowering beekeepers with transparent ledgers, smart apiary management, and direct market access."/>
  <meta name="keywords" content="Agrilink, beekeeping, cooperative, honey, Malawi, Livingstonia, apiculture, organic"/>

  <!-- Open Graph -->
  <meta property="og:title" content="Agrilink – Livingstonia Bee Keeping Cooperative"/>
  <meta property="og:description" content="Premium digital ecosystem for beekeepers. Transparent ledgers, smart apiary management, and direct market access."/>
  <meta property="og:type" content="website"/>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    /* ═══════════════════════════════════════════════
       DESIGN TOKENS — matches theme.css + first.php
    ════════════════════════════════════════════════ */
    :root {
      --primary:        #2a9d8f;
      --primary-dark:   #247b73;
      --primary-light:  #42b7a8;
      --primary-glow:   rgba(42, 157, 143, 0.18);

      --accent:         #f4a261;
      --accent-hover:   #e89c4f;
      --accent-glow:    rgba(244, 162, 97, 0.25);

      --bg-1:           #fffbe6;
      --bg-2:           #fef6d3;

      --glass-bg:       rgba(255, 255, 255, 0.75);
      --glass-border:   rgba(255, 255, 255, 0.85);

      --text:           #1e293b;
      --text-muted:     #56677a;
      --text-light:     #8899aa;

      --card-shadow:    0 8px 32px rgba(42, 157, 143, 0.08);
      --hover-shadow:   0 20px 50px rgba(42, 157, 143, 0.16);
      --accent-shadow:  0 8px 30px rgba(244, 162, 97, 0.3);

      --radius-sm:  8px;
      --radius-md:  16px;
      --radius-lg:  24px;
      --radius-xl:  50px;
    }

    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: linear-gradient(150deg, var(--bg-1) 0%, var(--bg-2) 100%);
      color: var(--text);
      line-height: 1.65;
      overflow-x: hidden;
      min-height: 100vh;
    }

    /* ═══════ AMBIENT GLOWS ═══════ */
    .glow-layer {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      overflow: hidden;
    }
    .glow-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(120px);
      opacity: 0.6;
      animation: blobFloat 18s ease-in-out infinite alternate;
    }
    .glow-blob-1 {
      width: 55vw; height: 55vw;
      top: -15%; left: -12%;
      background: radial-gradient(circle, rgba(42,157,143,0.22), transparent 65%);
      animation-delay: 0s;
    }
    .glow-blob-2 {
      width: 65vw; height: 65vw;
      bottom: -20%; right: -15%;
      background: radial-gradient(circle, rgba(244,162,97,0.20), transparent 65%);
      animation-delay: -9s;
    }
    .glow-blob-3 {
      width: 40vw; height: 40vw;
      top: 45%; left: 30%;
      background: radial-gradient(circle, rgba(42,157,143,0.10), transparent 70%);
      animation-delay: -4s;
    }
    @keyframes blobFloat {
      0%   { transform: translate(0,0) scale(1); }
      100% { transform: translate(40px, -30px) scale(1.06); }
    }

    /* ═══════ NAVBAR ═══════ */
    .navbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 1000;
      background: var(--primary);
      box-shadow: 0 4px 20px rgba(0,0,0,0.12);
      transition: box-shadow 0.3s ease;
    }
    .navbar.scrolled {
      box-shadow: 0 4px 30px rgba(0,0,0,0.18);
    }
    .nav-inner {
      max-width: 1380px;
      margin: 0 auto;
      padding: 0 2.5rem;
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      text-decoration: none;
    }
    .logo-icon-wrap {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.18);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.25);
    }
    .logo-icon-wrap i { color: #fff; font-size: 1.05rem; }
    .logo-text {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: -0.3px;
    }
    .logo-text span { color: var(--accent); }

    .nav-links {
      display: flex;
      list-style: none;
      gap: 2.2rem;
      align-items: center;
    }
    .nav-links a {
      color: rgba(255,255,255,0.88);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.97rem;
      position: relative;
      transition: color 0.25s ease;
    }
    .nav-links a::after {
      content: '';
      position: absolute;
      left: 0; bottom: -4px;
      width: 0; height: 2px;
      background: var(--accent);
      border-radius: 2px;
      transition: width 0.3s ease;
    }
    .nav-links a:hover { color: var(--accent); }
    .nav-links a:hover::after { width: 100%; }

    .nav-cta {
      display: flex;
      gap: 0.9rem;
      align-items: center;
    }

    /* Hamburger */
    .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 6px; }
    .hamburger span {
      display: block; width: 22px; height: 2px;
      background: rgba(255,255,255,0.9); border-radius: 2px;
      transition: all 0.3s ease;
      margin: 5px 0;
    }

    /* Mobile Nav */
    .mobile-nav {
      display: none;
      position: fixed;
      inset: 0; top: 70px;
      background: var(--primary);
      z-index: 999;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2rem;
    }
    .mobile-nav.open { display: flex; }
    .mobile-nav a {
      font-size: 1.3rem;
      color: rgba(255,255,255,0.92);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s;
    }
    .mobile-nav a:hover { color: var(--accent); }

    /* ═══════ BUTTONS ═══════ */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.8rem;
      border-radius: var(--radius-xl);
      font-weight: 600;
      font-size: 0.97rem;
      text-decoration: none;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      border: none;
      transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
      box-shadow: var(--accent-shadow);
    }
    .btn-primary:hover {
      background: var(--accent-hover);
      box-shadow: 0 10px 35px rgba(244,162,97,0.4);
      transform: translateY(-2px);
    }
    .btn-outline {
      background: transparent;
      color: #fff;
      border: 2px solid rgba(255,255,255,0.7);
    }
    .btn-outline:hover {
      background: rgba(255,255,255,0.15);
      color: #fff;
      border-color: #fff;
      transform: translateY(-2px);
    }
    .btn-ghost {
      background: rgba(255,255,255,0.15);
      color: #fff;
      border: 1.5px solid rgba(255,255,255,0.4);
    }
    .btn-ghost:hover {
      background: rgba(255,255,255,0.25);
      border-color: rgba(255,255,255,0.7);
      transform: translateY(-1px);
    }
    .btn-lg {
      padding: 1rem 2.6rem;
      font-size: 1.05rem;
    }

    /* ═══════ HERO ═══════ */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      position: relative;
      z-index: 1;
      padding: 7rem 2rem 5rem;
    }
    .hero-inner {
      max-width: 1380px;
      margin: 0 auto;
      width: 100%;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 5rem;
      align-items: center;
    }
    .hero-content {}
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(42,157,143,0.1);
      color: var(--primary);
      border: 1px solid rgba(42,157,143,0.2);
      padding: 0.45rem 1.1rem;
      border-radius: 50px;
      font-size: 0.87rem;
      font-weight: 600;
      letter-spacing: 0.4px;
      text-transform: uppercase;
      margin-bottom: 1.8rem;
    }
    .hero-badge i { font-size: 0.8rem; }
    .hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(3rem, 5.5vw, 4.8rem);
      line-height: 1.08;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 1.6rem;
      letter-spacing: -1px;
    }
    .hero h1 em {
      font-style: italic;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero-desc {
      font-size: 1.18rem;
      color: var(--text-muted);
      max-width: 530px;
      margin-bottom: 2.8rem;
      font-weight: 400;
      line-height: 1.7;
    }
    .hero-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 3.5rem;
    }
    .hero-trust {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 0.88rem;
      color: var(--text-light);
      font-weight: 500;
    }
    .trust-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--primary-light);
    }

    /* Hero Visual */
    .hero-visual {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .hero-card-stack {
      position: relative;
      width: 100%;
      max-width: 480px;
    }
    .hcard {
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--card-shadow);
      padding: 2rem 2.2rem;
      transition: transform 0.4s ease;
    }
    .hcard:hover { transform: translateY(-5px); }
    .hcard-main {
      z-index: 3;
      position: relative;
      background: #fff;
      box-shadow: 0 24px 60px rgba(42,157,143,0.13);
    }
    .hcard-behind-1 {
      position: absolute;
      top: -18px; left: -18px; right: 18px; bottom: 0;
      z-index: 2;
      background: rgba(255,255,255,0.70);
      border-radius: var(--radius-lg);
      filter: blur(1px);
    }
    .hcard-behind-2 {
      position: absolute;
      top: -34px; left: -34px; right: 34px; bottom: 0;
      z-index: 1;
      background: rgba(255,255,255,0.45);
      border-radius: var(--radius-lg);
      filter: blur(2px);
    }

    .hcard-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.7rem;
      padding-bottom: 1.2rem;
      border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .hcard-logo-circle {
      width: 50px; height: 50px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 6px 18px rgba(42,157,143,0.25);
      flex-shrink: 0;
    }
    .hcard-logo-circle i { color: #fff; font-size: 1.3rem; }
    .hcard-title { font-size: 1rem; font-weight: 700; color: var(--text); }
    .hcard-sub   { font-size: 0.82rem; color: var(--text-muted); }

    .hcard-stat-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 1.7rem;
    }
    .hcard-stat {
      text-align: center;
      background: linear-gradient(135deg, var(--bg-1), var(--bg-2));
      border-radius: var(--radius-sm);
      padding: 0.85rem 0.5rem;
    }
    .hcard-stat-val {
      font-family: 'Playfair Display', serif;
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--primary);
      line-height: 1.1;
    }
    .hcard-stat-lbl {
      font-size: 0.72rem;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.7px;
      margin-top: 3px;
    }

    .hcard-portals {
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }
    .portal-pill {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.7rem 1rem;
      border-radius: 10px;
      border: 1.5px solid transparent;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.25s ease;
      cursor: pointer;
      background: rgba(42,157,143,0.05);
      color: var(--text);
      border-color: transparent;
    }
    .portal-pill:hover {
      background: rgba(42,157,143,0.10);
      border-color: var(--primary-light);
      transform: translateX(4px);
    }
    .portal-pill-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: 0.9rem;
    }
    .portal-pill-arrow { margin-left: auto; color: var(--text-light); font-size: 0.75rem; }
    .pp-member  .portal-pill-icon { background: rgba(42,157,143,0.15); color: var(--primary); }
    .pp-buyer   .portal-pill-icon { background: rgba(244,162,97,0.15); color: var(--accent); }
    .pp-supplier .portal-pill-icon { background: rgba(100,180,150,0.13); color: #4caf80; }
    .pp-ngo     .portal-pill-icon { background: rgba(99,102,241,0.12); color: #6366f1; }

    /* Status chips inside hero card */
    .hcard-chips {
      display: flex;
      gap: 0.6rem;
      margin-bottom: 1.2rem;
      flex-wrap: wrap;
    }
    .hcard-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.85rem;
      border-radius: 50px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.2px;
    }
    .hcard-chip-green {
      background: rgba(42,157,143,0.12);
      color: var(--primary);
      border: 1px solid rgba(42,157,143,0.25);
    }
    .hcard-chip-gold {
      background: rgba(244,162,97,0.13);
      color: var(--accent-hover);
      border: 1px solid rgba(244,162,97,0.28);
    }
    .hcard-chip i { font-size: 0.72rem; }

    /* ═══════ STATS BELT ═══════ */
    .stats-belt {
      position: relative; z-index: 1;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      padding: 3.5rem 2rem;
    }
    .stats-belt-inner {
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 2rem;
    }
    .stat-item {
      text-align: center;
      color: #fff;
    }
    .stat-num {
      font-family: 'Playfair Display', serif;
      font-size: 2.8rem;
      font-weight: 700;
      line-height: 1;
      margin-bottom: 0.3rem;
      color: #fff;
    }
    .stat-num span { color: var(--accent); }
    .stat-lbl {
      font-size: 0.85rem;
      opacity: 0.8;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      font-weight: 500;
    }

    /* ═══════ PORTAL CARDS ═══════ */
    .portals-section {
      position: relative; z-index: 1;
      padding: 7rem 2rem;
    }
    .section-header {
      text-align: center;
      margin-bottom: 4rem;
    }
    .section-eyebrow {
      display: inline-block;
      color: var(--accent);
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 0.8rem;
    }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 700;
      color: var(--text);
      margin-bottom: 1rem;
    }
    .section-desc {
      font-size: 1.05rem;
      color: var(--text-muted);
      max-width: 580px;
      margin: 0 auto;
    }
    .portals-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
      gap: 2rem;
    }
    .portal-card {
      background: #fff;
      border: 1px solid rgba(0,0,0,0.04);
      border-radius: var(--radius-lg);
      padding: 2.5rem 2.2rem;
      box-shadow: var(--card-shadow);
      transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: inherit;
    }
    .portal-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.35s ease;
    }
    .portal-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--hover-shadow);
    }
    .portal-card:hover::before { transform: scaleX(1); }

    .pc-member::before  { background: linear-gradient(90deg, var(--primary), var(--primary-light)); }
    .pc-buyer::before   { background: linear-gradient(90deg, var(--accent), #e76f51); }
    .pc-supplier::before { background: linear-gradient(90deg, #4caf80, #2ecc71); }
    .pc-ngo::before     { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
    .pc-admin::before   { background: linear-gradient(90deg, #e53e3e, #f56565); }

    .pc-icon-wrap {
      width: 62px; height: 62px;
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.6rem;
      font-size: 1.5rem;
      transition: transform 0.3s ease;
    }
    .portal-card:hover .pc-icon-wrap { transform: scale(1.12); }

    .pc-member  .pc-icon-wrap { background: rgba(42,157,143,0.12); color: var(--primary); }
    .pc-buyer   .pc-icon-wrap { background: rgba(244,162,97,0.15); color: var(--accent); }
    .pc-supplier .pc-icon-wrap { background: rgba(76,175,128,0.12); color: #4caf80; }
    .pc-ngo     .pc-icon-wrap { background: rgba(99,102,241,0.12); color: #6366f1; }
    .pc-admin   .pc-icon-wrap { background: rgba(229,62,62,0.10); color: #e53e3e; }

    .pc-label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 0.4rem;
    }
    .pc-member  .pc-label { color: var(--primary); }
    .pc-buyer   .pc-label { color: var(--accent); }
    .pc-supplier .pc-label { color: #4caf80; }
    .pc-ngo     .pc-label { color: #6366f1; }
    .pc-admin   .pc-label { color: #e53e3e; }

    .pc-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 0.75rem;
    }
    .pc-desc {
      font-size: 0.93rem;
      color: var(--text-muted);
      line-height: 1.6;
      flex: 1;
      margin-bottom: 1.6rem;
    }
    .pc-features {
      list-style: none;
      margin-bottom: 2rem;
    }
    .pc-features li {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: var(--text-muted);
      margin-bottom: 0.4rem;
    }
    .pc-features li i { font-size: 0.75rem; color: var(--primary); }
    .pc-buyer .pc-features li i { color: var(--accent); }
    .pc-supplier .pc-features li i { color: #4caf80; }
    .pc-ngo .pc-features li i { color: #6366f1; }
    .pc-admin .pc-features li i { color: #e53e3e; }

    .pc-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.8rem;
      border-radius: 10px;
      font-weight: 700;
      font-size: 0.92rem;
      text-decoration: none;
      transition: all 0.25s ease;
      border: 2px solid;
    }
    .pc-member  .pc-btn { border-color: var(--primary); color: var(--primary); }
    .pc-member  .pc-btn:hover { background: var(--primary); color: #fff; }
    .pc-buyer   .pc-btn { border-color: var(--accent); color: var(--accent); }
    .pc-buyer   .pc-btn:hover { background: var(--accent); color: #fff; }
    .pc-supplier .pc-btn { border-color: #4caf80; color: #4caf80; }
    .pc-supplier .pc-btn:hover { background: #4caf80; color: #fff; }
    .pc-ngo     .pc-btn { border-color: #6366f1; color: #6366f1; }
    .pc-ngo     .pc-btn:hover { background: #6366f1; color: #fff; }
    .pc-admin   .pc-btn { border-color: #e53e3e; color: #e53e3e; }
    .pc-admin   .pc-btn:hover { background: #e53e3e; color: #fff; }

    /* ═══════ FEATURES ═══════ */
    .features-section {
      position: relative; z-index: 1;
      padding: 6rem 2rem 7rem;
      background: linear-gradient(180deg, rgba(255,251,230,0.5) 0%, rgba(254,246,211,0.6) 100%);
    }
    .features-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 2rem;
    }
    .feature-card {
      background: #fff;
      border-radius: var(--radius-md);
      padding: 2.5rem 2rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(0,0,0,0.04);
      text-align: center;
      transition: all 0.35s ease;
      position: relative;
      overflow: hidden;
    }
    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--hover-shadow);
    }
    .feature-card::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      transform: scaleX(0);
      transition: transform 0.35s ease;
    }
    .feature-card:hover::after { transform: scaleX(1); }
    .fi-wrap {
      width: 68px; height: 68px;
      background: linear-gradient(135deg, var(--bg-1), var(--bg-2));
      border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 1.8rem;
      color: var(--primary);
      margin-bottom: 1.4rem;
      transition: all 0.3s ease;
      box-shadow: 0 4px 14px rgba(42,157,143,0.1);
    }
    .feature-card:hover .fi-wrap {
      background: var(--accent);
      color: #fff;
      box-shadow: 0 6px 20px rgba(244,162,97,0.3);
      transform: scale(1.1);
    }
    .feature-card h3 {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 0.75rem;
    }
    .feature-card p {
      font-size: 0.93rem;
      color: var(--text-muted);
      line-height: 1.65;
    }

    /* ═══════ REGISTER CTA ═══════ */
    .register-cta {
      position: relative; z-index: 1;
      padding: 7rem 2rem;
    }
    .cta-card {
      max-width: 900px;
      margin: 0 auto;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      border-radius: var(--radius-xl);
      padding: 5rem 4rem;
      text-align: center;
      color: #fff;
      position: relative;
      overflow: hidden;
      box-shadow: 0 30px 70px rgba(42,157,143,0.3);
    }
    .cta-card::before {
      content: '\f140'; /* leaf */
      font-family: 'Font Awesome 6 Free'; font-weight: 900;
      position: absolute;
      font-size: 22rem;
      right: -60px; bottom: -80px;
      color: rgba(255,255,255,0.04);
      line-height: 1;
    }
    .cta-card h2 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.2rem, 4vw, 3.2rem);
      font-weight: 700;
      margin-bottom: 1.2rem;
      line-height: 1.15;
      position: relative;
    }
    .cta-card p {
      font-size: 1.1rem;
      opacity: 0.88;
      max-width: 560px;
      margin: 0 auto 2.8rem;
      line-height: 1.65;
      position: relative;
    }
    .cta-btns {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
      position: relative;
    }
    .cta-btns .btn-primary {
      background: var(--accent);
      color: #fff;
    }
    .cta-btns .btn-white {
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(10px);
      color: #fff;
      border: 1.5px solid rgba(255,255,255,0.4);
    }
    .cta-btns .btn-white:hover {
      background: rgba(255,255,255,0.28);
      transform: translateY(-2px);
    }

    /* ═══════ FOOTER ═══════ */
    .footer {
      position: relative; z-index: 1;
      background: var(--primary);
      color: rgba(255,255,255,0.85);
      padding: 4rem 2rem 2rem;
    }
    .footer-inner {
      max-width: 1200px;
      margin: 0 auto;
    }
    .footer-top {
      display: grid;
      grid-template-columns: 1.8fr 1fr 1fr 1fr;
      gap: 3rem;
      margin-bottom: 3rem;
    }
    .footer-brand .logo-text { color: #fff; }
    .footer-brand-desc {
      font-size: 0.9rem;
      line-height: 1.7;
      margin-top: 1rem;
      max-width: 300px;
      opacity: 0.82;
    }
    .footer-col h4 {
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      color: #fff;
      margin-bottom: 1.2rem;
    }
    .footer-col ul { list-style: none; }
    .footer-col ul li { margin-bottom: 0.65rem; }
    .footer-col ul li a {
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      font-size: 0.92rem;
      transition: color 0.2s;
    }
    .footer-col ul li a:hover { color: var(--accent); }
    .footer-bottom {
      border-top: 1px solid rgba(255,255,255,0.08);
      padding-top: 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .footer-copy {
      font-size: 0.88rem;
      opacity: 0.78;
    }
    .footer-copy a { color: var(--accent); text-decoration: none; }
    .footer-badges {
      display: flex;
      gap: 0.6rem;
    }
    .fbadge {
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 6px;
      padding: 0.3rem 0.7rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: rgba(255,255,255,0.6);
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }
    .fbadge i { font-size: 0.7rem; color: var(--accent); }

    /* ═══════ SCROLL-TO-TOP ═══════ */
    .scroll-top {
      position: fixed;
      bottom: 2rem; right: 2rem;
      width: 48px; height: 48px;
      border-radius: 50%;
      background: var(--accent);
      color: #fff;
      border: none;
      cursor: pointer;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      box-shadow: var(--accent-shadow);
      z-index: 999;
      transition: all 0.3s ease;
    }
    .scroll-top:hover { background: var(--accent-hover); transform: translateY(-2px); }
    .scroll-top.visible { display: flex; }

    /* ═══════ UTILITY ═══════ */
    .divider-icon {
      text-align: center;
      padding: 1.5rem 0;
      color: rgba(42,157,143,0.25);
      font-size: 2rem;
    }

    /* ═══════ RESPONSIVE ═══════ */
    @media (max-width: 1100px) {
      .hero-inner { grid-template-columns: 1fr; text-align: center; gap: 4rem; }
      .hero-desc  { margin: 0 auto 2.5rem; }
      .hero-actions { justify-content: center; }
      .hero-trust   { justify-content: center; }
      .hero-card-stack { max-width: 420px; margin: 0 auto; }
      .float-badge-1 { top: -15px; right: 0; }
      .float-badge-2 { bottom: -15px; left: 0; }
      .stats-belt-inner { grid-template-columns: repeat(2, 1fr); }
      .footer-top { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 768px) {
      .nav-links, .nav-cta { display: none; }
      .hamburger { display: block; }
      .hero { padding: 6rem 1.2rem 4rem; }
      .hero h1 { font-size: 2.6rem; }
      .stats-belt-inner { grid-template-columns: repeat(2, 1fr); }
      .cta-card { padding: 3.5rem 2rem; }
      .footer-top { grid-template-columns: 1fr; }
      .footer-bottom { flex-direction: column; align-items: flex-start; }
      .hcard-stat-row { grid-template-columns: repeat(3,1fr); }
    }

    @media (max-width: 480px) {
      .stats-belt-inner { grid-template-columns: 1fr 1fr; }
      .hero-actions { flex-direction: column; align-items: center; }
      .hero-actions .btn { width: 100%; max-width: 280px; justify-content: center; }
    }

    /* ═══════ REVEAL ANIMATIONS ═══════ */
    .reveal {
      opacity: 0;
      transform: translateY(28px);
      transition: opacity 0.65s ease, transform 0.65s ease;
    }
    .reveal.visible {
      opacity: 1;
      transform: none;
    }
  </style>
</head>
<body>

  <!-- ══════ AMBIENT BACKGROUND ══════ -->
  <div class="glow-layer" aria-hidden="true">
    <div class="glow-blob glow-blob-1"></div>
    <div class="glow-blob glow-blob-2"></div>
    <div class="glow-blob glow-blob-3"></div>
  </div>

  <!-- ══════ NAVBAR ══════ -->
  <header class="navbar" id="navbar" role="banner">
    <div class="nav-inner">
      <a href="index.php" class="logo" aria-label="Agrilink Home">
        <img src="assets/logo.png" alt="Agrilink Logo" class="logo-img" style="height: 50px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); margin-right: 12px;">
        <span class="logo-text">livingstonia bee keeping cooperative Agri<span>link</span></span>
      </a>

      <ul class="nav-links" role="navigation" aria-label="Primary Navigation">
        <li><a href="#about" id="nav-about">About</a></li>
        <li><a href="#portals" id="nav-portals">Portals</a></li>
        <li><a href="#features" id="nav-features">Features</a></li>
      </ul>

      <div class="nav-cta">
        <a href="member/login.php"  class="btn btn-ghost" id="nav-signin"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a>
        <a href="member/member_registration.php" class="btn btn-primary" id="nav-join"><i class="fa-solid fa-user-plus"></i> Join Now</a>
      </div>

      <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- Mobile Nav -->
  <nav class="mobile-nav" id="mobileNav" aria-label="Mobile Navigation">
    <a href="#about"   id="mob-about">About</a>
    <a href="#portals" id="mob-portals">Portals</a>
    <a href="#features" id="mob-features">Features</a>
    <a href="member/login.php" id="mob-signin" class="btn btn-outline btn-lg">Sign In</a>
    <a href="member/member_registration.php" id="mob-join" class="btn btn-primary btn-lg">Join Now</a>
  </nav>

  <!-- ══════ HERO ══════ -->
  <section class="hero" id="hero" aria-labelledby="hero-heading">
    <div class="hero-inner">

      <!-- Left: Text -->
      <div class="hero-content">
        <div class="hero-badge">
          <i class="fa-solid fa-circle-check"></i>
          Livingstonia Bee Keeping Cooperative
        </div>

        <h1 id="hero-heading">
          Empowering<br>Beekeepers,<br><em>Sustainably.</em>
        </h1>

        <p class="hero-desc">
          Agrilink is the official digital management ecosystem for the Livingstonia Bee Keeping Cooperative — transparent ledgers, smart apiary management, and a direct marketplace, all in one platform.
        </p>

        <div class="hero-actions">
          <a href="member/member_registration.php" class="btn btn-primary btn-lg" id="hero-join">
            <i class="fa-solid fa-seedling"></i> Become a Member
          </a>
          <a href="member/login.php" class="btn btn-outline btn-lg" id="hero-signin">
            <i class="fa-solid fa-right-to-bracket"></i> Sign In
          </a>
        </div>

        <div class="hero-trust">
          <span><i class="fa-solid fa-shield-halved" style="color:var(--primary)"></i> Secure & Encrypted</span>
          <div class="trust-dot"></div>
          <span>99.9% Uptime</span>
          <div class="trust-dot"></div>
          <span>100% Transparent</span>
        </div>
      </div>

      <!-- Right: Card Visual -->
      <div class="hero-visual">
        <div class="hero-card-stack">
          <div class="hcard-behind-2"></div>
          <div class="hcard-behind-1"></div>
          <div class="hcard hcard-main">
            <div class="hcard-header">
              <div class="hcard-logo-circle"><img src="assets/logo.png" alt="Logo" style="width: 24px; height: auto;"></div>
              <div>
                <div class="hcard-title">Agrilink Platform</div>
                <div class="hcard-sub">Livingstonia Bee Cooperative</div>
              </div>
            </div>

            <div class="hcard-stat-row">
              <div class="hcard-stat">
                <div class="hcard-stat-val">E2E</div>
                <div class="hcard-stat-lbl">Encryption</div>
              </div>
              <div class="hcard-stat">
                <div class="hcard-stat-val">99.9%</div>
                <div class="hcard-stat-lbl">Uptime</div>
              </div>
              <div class="hcard-stat">
                <div class="hcard-stat-val">100%</div>
                <div class="hcard-stat-lbl">Transparent</div>
              </div>
            </div>

            <!-- Status chips -->
            <div class="hcard-chips">
              <span class="hcard-chip hcard-chip-green">
                <i class="fa-solid fa-circle-check"></i> Registration Open
              </span>
              <span class="hcard-chip hcard-chip-gold">
                <i class="fa-solid fa-honey-pot"></i> Marketplace Live
              </span>
            </div>

            <div class="hcard-portals">
              <a href="member/login.php" class="portal-pill pp-member" id="hcard-member">
                <span class="portal-pill-icon"><i class="fa-solid fa-users"></i></span>
                Member Portal
                <i class="fa-solid fa-chevron-right portal-pill-arrow"></i>
              </a>
              <a href="stakeholder/login.php" class="portal-pill pp-buyer" id="hcard-buyer">
                <span class="portal-pill-icon"><i class="fa-solid fa-store"></i></span>
                Buyer / NGO / Supplier
                <i class="fa-solid fa-chevron-right portal-pill-arrow"></i>
              </a>
              <a href="admin/login.php" class="portal-pill pp-ngo" id="hcard-admin">
                <span class="portal-pill-icon" style="background:rgba(229,62,62,0.1);color:#e53e3e"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                Admin Access
                <i class="fa-solid fa-chevron-right portal-pill-arrow"></i>
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ══════ STATS BELT ══════ -->
  <section class="stats-belt" aria-label="Platform Statistics">
    <div class="stats-belt-inner">
      <div class="stat-item">
        <div class="stat-num">100<span>%</span></div>
        <div class="stat-lbl">Ledger Transparency</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">5<span>+</span></div>
        <div class="stat-lbl">User Portals</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">E2E</div>
        <div class="stat-lbl">Data Encryption</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">24<span>/7</span></div>
        <div class="stat-lbl">System Access</div>
      </div>
    </div>
  </section>

  <!-- ══════ PORTALS ══════ -->
  <section class="portals-section" id="portals" aria-labelledby="portals-heading">
    <div class="section-header reveal">
      <span class="section-eyebrow">Access Portals</span>
      <h2 class="section-title" id="portals-heading">Choose Your Portal</h2>
      <p class="section-desc">Every user type has a dedicated, purpose-built portal tailored to their specific role within the cooperative ecosystem.</p>
    </div>

    <div class="portals-grid">

      <!-- Member Portal -->
      <div class="portal-card pc-member reveal">
        <div class="pc-icon-wrap"><i class="fa-solid fa-users"></i></div>
        <div class="pc-label">Cooperative Member</div>
        <div class="pc-title">Member Portal</div>
        <p class="pc-desc">Full access to your membership dashboard — track contributions, dividends, hive inspections, and training records.</p>
        <ul class="pc-features">
          <li><i class="fa-solid fa-check-circle"></i> View hive & inspection records</li>
          <li><i class="fa-solid fa-check-circle"></i> Track share contributions</li>
          <li><i class="fa-solid fa-check-circle"></i> Access training materials</li>
          <li><i class="fa-solid fa-check-circle"></i> Receive cooperative notifications</li>
        </ul>
        <a href="member/login.php" class="pc-btn" id="portal-member-login">
          <i class="fa-solid fa-right-to-bracket"></i> Member Sign In
        </a>
      </div>

      <!-- Buyer Portal -->
      <div class="portal-card pc-buyer reveal">
        <div class="pc-icon-wrap"><i class="fa-solid fa-store"></i></div>
        <div class="pc-label">External Buyer</div>
        <div class="pc-title">Buyer Storefront</div>
        <p class="pc-desc">Browse and purchase premium organic honey products directly from the Livingstonia cooperative at transparent prices.</p>
        <ul class="pc-features">
          <li><i class="fa-solid fa-check-circle"></i> Browse available products</li>
          <li><i class="fa-solid fa-check-circle"></i> Place & manage orders</li>
          <li><i class="fa-solid fa-check-circle"></i> View purchase history</li>
          <li><i class="fa-solid fa-check-circle"></i> Direct cooperative pricing</li>
        </ul>
        <a href="stakeholder/login.php" class="pc-btn" id="portal-buyer-login">
          <i class="fa-solid fa-right-to-bracket"></i> Buyer Sign In
        </a>
      </div>

      <!-- Supplier Portal -->
      <div class="portal-card pc-supplier reveal">
        <div class="pc-icon-wrap"><i class="fa-solid fa-truck"></i></div>
        <div class="pc-label">Supplier</div>
        <div class="pc-title">Supplier Portal</div>
        <p class="pc-desc">Manage supply relationships with the cooperative — submit quotations, track orders, and coordinate deliveries seamlessly.</p>
        <ul class="pc-features">
          <li><i class="fa-solid fa-check-circle"></i> Submit supply quotations</li>
          <li><i class="fa-solid fa-check-circle"></i> Track open purchase orders</li>
          <li><i class="fa-solid fa-check-circle"></i> Access cooperative schedules</li>
          <li><i class="fa-solid fa-check-circle"></i> Manage delivery confirmations</li>
        </ul>
        <a href="stakeholder/login.php" class="pc-btn" id="portal-supplier-login">
          <i class="fa-solid fa-right-to-bracket"></i> Supplier Sign In
        </a>
      </div>

      <!-- NGO Portal -->
      <div class="portal-card pc-ngo reveal">
        <div class="pc-icon-wrap"><i class="fa-solid fa-handshake-angle"></i></div>
        <div class="pc-label">NGO / Partner</div>
        <div class="pc-title">Partner Portal</div>
        <p class="pc-desc">NGO and development partners can coordinate programmes, monitor cooperative impact, and access verified reporting in real time.</p>
        <ul class="pc-features">
          <li><i class="fa-solid fa-check-circle"></i> Access impact reports</li>
          <li><i class="fa-solid fa-check-circle"></i> Coordinate field programmes</li>
          <li><i class="fa-solid fa-check-circle"></i> Monitor cooperative KPIs</li>
          <li><i class="fa-solid fa-check-circle"></i> Download verified data</li>
        </ul>
        <a href="stakeholder/login.php" class="pc-btn" id="portal-ngo-login">
          <i class="fa-solid fa-right-to-bracket"></i> Partner Sign In
        </a>
      </div>

      <!-- Admin Portal -->
      <div class="portal-card pc-admin reveal">
        <div class="pc-icon-wrap"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <div class="pc-label">Administration</div>
        <div class="pc-title">Admin Dashboard</div>
        <p class="pc-desc">Full system oversight for cooperative administrators — manage members, finances, markets, reports, and all platform settings.</p>
        <ul class="pc-features">
          <li><i class="fa-solid fa-check-circle"></i> Member management</li>
          <li><i class="fa-solid fa-check-circle"></i> Finance & ledger control</li>
          <li><i class="fa-solid fa-check-circle"></i> Markets & product listing</li>
          <li><i class="fa-solid fa-check-circle"></i> Full reports & audit logs</li>
        </ul>
        <a href="admin/login.php" class="pc-btn" id="portal-admin-login">
          <i class="fa-solid fa-right-to-bracket"></i> Admin Sign In
        </a>
      </div>

      <!-- New Member Registration -->
      <div class="portal-card pc-member reveal" style="background: linear-gradient(135deg, rgba(42,157,143,0.06), rgba(244,162,97,0.05));">
        <div class="pc-icon-wrap"><i class="fa-solid fa-user-plus"></i></div>
        <div class="pc-label">New to Agrilink?</div>
        <div class="pc-title">Join the Cooperative</div>
        <p class="pc-desc">Ready to become part of the Livingstonia Bee Keeping Cooperative? Register as a member and start your beekeeping journey today.</p>
        <ul class="pc-features">
          <li><i class="fa-solid fa-check-circle"></i> Simple 3-step registration</li>
          <li><i class="fa-solid fa-check-circle"></i> Membership fee: MWK 1,000</li>
          <li><i class="fa-solid fa-check-circle"></i> Immediate portal access</li>
          <li><i class="fa-solid fa-check-circle"></i> Join a growing community</li>
        </ul>
        <a href="member/member_registration.php" class="btn btn-primary" style="display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem;font-size:.93rem;font-weight:700;border-radius:10px;" id="portal-register">
          <i class="fa-solid fa-seedling"></i> Register as Member
        </a>
      </div>

    </div>
  </section>

  <!-- ══════ FEATURES ══════ -->
  <section class="features-section" id="features" aria-labelledby="features-heading">
    <div class="section-header reveal">
      <span class="section-eyebrow">Platform Capabilities</span>
      <h2 class="section-title" id="features-heading">Built for the Cooperative</h2>
      <p class="section-desc">Every feature is purpose-built for beekeeping cooperatives — nothing generic, everything relevant.</p>
    </div>

    <div class="features-grid">
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <h3>Ledger Transparency</h3>
        <p>Real-time tracking of membership fees, share contributions, profit distributions and all financial transactions across the cooperative.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-layer-group"></i></div>
        <h3>Smart Apiary Management</h3>
        <p>Schedule hive inspections, record health telemetry, and manage seasonal yield data with intelligent, notification-driven workflows.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-handshake"></i></div>
        <h3>Market Sync Access</h3>
        <p>Direct market integration for buyers, suppliers and NGO partners — real-time product listings and transparent pricing from the cooperative.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-book-open"></i></div>
        <h3>Knowledge Base</h3>
        <p>Centralized documentation with sustainable beekeeping protocols, training manuals, and continuously updated educational resources.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-chart-line"></i></div>
        <h3>Advanced Reporting</h3>
        <p>Comprehensive reports and analytics dashboards for administrators and stakeholders — printable, exportable and always up to date.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-shield-halved"></i></div>
        <h3>Data Security</h3>
        <p>End-to-end session management, salted password hashing, and role-based access control ensuring your cooperative data stays protected.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-bell"></i></div>
        <h3>Smart Notifications</h3>
        <p>Automated email and in-platform notifications keep members, treasurers, and secretaries aligned on critical cooperative activities.</p>
      </div>
      <div class="feature-card reveal">
        <div class="fi-wrap"><i class="fa-solid fa-mobile-screen"></i></div>
        <h3>Fully Responsive</h3>
        <p>Access Agrilink seamlessly from any device — desktop, tablet, or mobile — with an interface designed for clarity and ease of use.</p>
      </div>
    </div>
  </section>

  <!-- ══════ REGISTER CTA ══════ -->
  <section class="register-cta" id="about" aria-labelledby="cta-heading">
    <div class="cta-card reveal">
      <h2 id="cta-heading">Ready to Join the<br>Cooperative?</h2>
      <p>Become a member of the Livingstonia Bee Keeping Cooperative today. Registration is quick, simple, and opens the door to full platform access — hive management, finances, training, and more.</p>
      <div class="cta-btns">
        <a href="member/member_registration.php" class="btn btn-primary btn-lg" id="cta-register">
          <i class="fa-solid fa-user-plus"></i> Register Now — Free
        </a>
        <a href="member/login.php" class="btn btn-white btn-lg" id="cta-login">
          <i class="fa-solid fa-right-to-bracket"></i> Already a Member? Sign In
        </a>
      </div>
    </div>
  </section>

  <!-- ══════ FOOTER ══════ -->
  <footer class="footer" role="contentinfo">
    <div class="footer-inner">
      <div class="footer-top">
        <div class="footer-brand">
          <a href="index.php" class="logo" aria-label="Agrilink Home">
            <img src="assets/logo.png" alt="Agrilink Logo" class="logo-img" style="height: 45px; width: auto; filter: brightness(0) invert(1); margin-right: 12px;">
            <span class="logo-text">Agri<span>link</span></span>
          </a>
          <p class="footer-brand-desc">
            The official digital management platform for the Livingstonia Bee Keeping Cooperative — empowering sustainable apiculture across Malawi.
          </p>
        </div>

        <div class="footer-col">
          <h4>Member Access</h4>
          <ul>
            <li><a href="member/login.php" id="footer-member-login">Member Sign In</a></li>
            <li><a href="member/member_registration.php" id="footer-register">Member Registration</a></li>
            <li><a href="member/forgot_password.php" id="footer-forgot">Forgot Password?</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Partner Portals</h4>
          <ul>
            <li><a href="stakeholder/login.php" id="footer-buyer">Buyer Login</a></li>
            <li><a href="stakeholder/login.php" id="footer-supplier">Supplier Login</a></li>
            <li><a href="stakeholder/login.php" id="footer-ngo">NGO / Partner Login</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Administration</h4>
          <ul>
            <li><a href="admin/login.php" id="footer-admin">Admin Sign In</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p class="footer-copy">
          &copy; <?php echo date('Y'); ?> Livingstonia Bee Keeping Cooperative. Operated by <a href="admin/login.php">Agrilink System Administration</a>.
        </p>
        <div class="footer-badges">
          <div class="fbadge"><i class="fa-solid fa-shield-halved"></i> Secure</div>
          <div class="fbadge"><i class="fa-solid fa-lock"></i> Encrypted</div>
          <div class="fbadge"><i class="fa-solid fa-leaf"></i> Agrilink v2</div>
        </div>
      </div>
    </div>
  </footer>

  <!-- ══════ SCROLL TO TOP ══════ -->
  <button class="scroll-top" id="scrollTop" aria-label="Scroll to top">
    <i class="fa-solid fa-chevron-up"></i>
  </button>

  <script>
    /* ── Navbar shrink on scroll ─────────────────── */
    const navbar = document.getElementById('navbar');
    const scrollTopBtn = document.getElementById('scrollTop');

    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 50);
      scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });

    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ── Mobile nav toggle ───────────────────────── */
    const hamburger  = document.getElementById('hamburgerBtn');
    const mobileNav  = document.getElementById('mobileNav');
    let navOpen = false;

    hamburger.addEventListener('click', () => {
      navOpen = !navOpen;
      hamburger.setAttribute('aria-expanded', navOpen);
      mobileNav.classList.toggle('open', navOpen);
      document.body.style.overflow = navOpen ? 'hidden' : '';
    });

    /* Close mobile nav on link click */
    mobileNav.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        navOpen = false;
        mobileNav.classList.remove('open');
        document.body.style.overflow = '';
        hamburger.setAttribute('aria-expanded', false);
      });
    });

    /* Close on anchor smooth scroll */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) {
          e.preventDefault();
          const offset = 80;
          const top = target.getBoundingClientRect().top + window.scrollY - offset;
          window.scrollTo({ top, behavior: 'smooth' });
        }
      });
    });

    /* ── Reveal on scroll ────────────────────────── */
    const revealEls = document.querySelectorAll('.reveal');
    const observer  = new IntersectionObserver((entries) => {
      entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
          setTimeout(() => entry.target.classList.add('visible'), index * 80);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    revealEls.forEach(el => observer.observe(el));
  </script>

</body>
</html>
