<?php /* Ambient background glow blobs — shared across all pages */ ?>
<style>
/* ── Ambient Glow Blobs ────────────────── */
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
  filter: blur(120px) !important;
  opacity: 0.6 !important;
  animation: glowFloat 18s ease-in-out infinite alternate;
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
@keyframes glowFloat {
  0%   { transform: translate(0,0) scale(1); }
  100% { transform: translate(40px, -30px) scale(1.06); }
}

/* Ensure all page content is visible above the background */
body {
  position: relative !important;
  overflow-x: hidden !important;
  background-color: transparent !important;
  background-image: linear-gradient(135deg, #fffbe6 0%, #fef6d3 100%) !important;
}

header, nav, main, section, .container,
.registration-form, .login-container,
.card, .dashboard-content, footer,
.main-content, .sidebar, .hero-panel,
.stats-grid, form, table, .table-container,
.side-panel, .action-card, .main-column {
  position: relative;
  z-index: 1;
}
</style>
<div class="glow-layer" aria-hidden="true">
  <div class="glow-blob glow-blob-1"></div>
  <div class="glow-blob glow-blob-2"></div>
  <div class="glow-blob glow-blob-3"></div>
</div>
