<?php
session_start();
require 'db.php';
require 'notif_count.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$uploadDir = __DIR__ . '/uploads/training';
$webDir = 'uploads/training';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle upload
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['upload_material'])
) {
    $title = trim($_POST['title'] ?? '');

    if ($title === '') {
        $error = 'Please enter a title for the material.';
    } elseif (empty($_FILES['material_file']['name'])) {
        $error = 'Please choose a file to upload.';
    } else {
        $file = $_FILES['material_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
        $targetName = $safeName . '-' . time() . '.' . $ext;
        $targetPath = $uploadDir . '/' . $targetName;
        $relativePath = $webDir . '/' . $targetName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $conn->prepare("INSERT INTO training_materials (title, content, uploaded_by) VALUES (?, ?, ?)");
            $stmt->bind_param('ssi', $title, $relativePath, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            $message = 'Training material uploaded successfully.';
        } else {
            $error = 'Unable to save the uploaded file. Please try again.';
        }
    }
}

// Handle deletion
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare('SELECT content FROM training_materials WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($path);
    if ($stmt->fetch()) {
        $stmt->close();
        $stmt2 = $conn->prepare('DELETE FROM training_materials WHERE id = ?');
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $stmt2->close();

        $fileToDelete = __DIR__ . '/' . $path;
        if ($path && file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
        $message = 'Training material removed.';
    } else {
        $stmt->close();
        $error = 'Material not found.';
    }
}

// Fetch materials
$materialsResult = $conn->query(
    'SELECT m.*, u.full_name AS uploader FROM training_materials m LEFT JOIN users u ON m.uploaded_by = u.user_id ORDER BY m.upload_date DESC'
);
$materials = $materialsResult ? $materialsResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Training Hub | Agrilink Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <style>
    :root {
      --primary: #2a9d8f;
      --secondary: #f4a261;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --info: #3b82f6;
      --light: #ecf0f1;
      --dark: #1e293b;
      --text: #2c3e50;
      --text-muted: #64748b;
      --glass-bg: rgba(255, 255, 255, 0.45);
      --glass-border: rgba(255, 255, 255, 0.6);
      --shadow: 0 8px 32px rgba(42, 157, 143, 0.08);
      --radius: 16px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', sans-serif;
      color: var(--text);
      background: linear-gradient(135deg, #fffbe6, #fef6d3);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow-x: hidden;
    }

    .header {
      background: var(--primary);
      color: white;
      padding: 1rem 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .header-logo { font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 0.8rem; }
    .header-nav { display: flex; align-items: center; gap: 1.5rem; }
    .header-nav a {
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      font-size: 0.95rem;
      font-weight: 600;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 0.8rem;
      border-radius: 8px;
    }
    .header-nav a:hover, .header-nav a.active { color: white; background: rgba(255, 255, 255, 0.15); }

    .main-content {
      flex: 1;
      padding: 3rem 5%;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .glass-panel {
      background: var(--glass-bg);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 2.5rem;
      margin-bottom: 2.5rem;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-bottom: 2.5rem;
    }
    .page-header h1 { font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-bottom: 5px; }
    .page-header p { color: var(--text-muted); font-size: 1.1rem; }

    /* Alerts */
    .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(16, 185, 129, 0.15); color: #065f46; border-left: 4px solid var(--success); }
    .alert-error { background: rgba(239, 68, 68, 0.15); color: #991b1b; border-left: 4px solid var(--danger); }

    /* Forms */
    .section-title { font-size: 1.3rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
    .form-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; align-items: end; }
    
    .form-group { position: relative; display: flex; flex-direction: column; }
    .form-group input, .form-group select {
      width: 100%; padding: 1.2rem 1rem 0.6rem; border: 2px solid rgba(255,255,255,0.8);
      background: rgba(255,255,255,0.5); border-radius: 10px; font-size: 1rem; font-family: inherit; color: var(--dark); font-weight: 500; transition: 0.3s;
    }
    .form-group input[type="file"] { padding: 0.8rem 1rem; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 4px 15px rgba(42, 157, 143, 0.1); }
    .form-group label {
      position: absolute; left: 1rem; top: 1rem; color: var(--text-muted); font-size: 0.95rem; font-weight: 500; transition: 0.2s ease all; pointer-events: none;
    }
    .form-group input:focus ~ label, .form-group input:not(:placeholder-shown) ~ label,
    .form-group select:focus ~ label, .form-group select:valid ~ label {
      top: 0.3rem; font-size: 0.7rem; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    }
    /* Special label handling for file input which doesn't support :placeholder-shown */
    .form-group.file-input label { top: -1.5rem; left: 0; font-size: 0.85rem; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }

    .btn { padding: 1rem 1.5rem; border: none; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; height: 56px; text-decoration: none;}
    .btn-primary { background: linear-gradient(135deg, var(--primary), #21867a); color: white; box-shadow: 0 4px 15px rgba(42, 157, 143, 0.3); }
    .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(42, 157, 143, 0.4); color: white;}
    .btn-secondary { background: rgba(255,255,255,0.8); color: var(--primary); border: 2px solid var(--primary); }
    .btn-secondary:hover { background: var(--primary); color: white; }
    .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
    .btn-danger:hover { background: var(--danger); color: white; }

    /* Table */
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    th { padding: 1rem 1.5rem; text-align: left; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); border-bottom: 2px solid rgba(0,0,0,0.05); }
    td { padding: 1.2rem 1.5rem; background: rgba(255,255,255,0.4); transition: 0.3s; vertical-align: middle; }
    td:first-child { border-radius: 12px 0 0 12px; font-weight: 700; color: var(--dark); }
    td:last-child { border-radius: 0 12px 12px 0; }
    tbody tr { transition: transform 0.2s, box-shadow 0.2s; }
    tbody tr:hover td { background: rgba(255,255,255,0.8); }
    tbody tr:hover { transform: scale(1.01); box-shadow: 0 4px 15px rgba(0,0,0,0.05); z-index: 10; position: relative; }

    .no-data { text-align: center; padding: 3rem; color: var(--text-muted); }
    .no-data i { font-size: 3rem; opacity: 0.3; margin-bottom: 1rem; }

    .footer { background: var(--primary); color: white; text-align: center; padding: 3rem; margin-top: auto; border-top: 5px solid var(--secondary); }
    .footer a { color: var(--secondary); text-decoration: none; font-weight: 700; }
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
<?php include 'notif_panel.php'; ?>

  <!-- Header -->
  <header class="header">
    <div class="header-logo">
      <img src="../assets/logo.png" alt="Agrilink Logo" style="height: 40px; width: auto; margin-right: 10px;">
      AGRILINK ADMIN
    </div>
    <nav class="header-nav">
      <a href="admin.php"><i class="fa-solid fa-house"></i> Home</a>
      <a href="admin_suppliers.php"><i class="fa-solid fa-boxes-packing"></i> Suppliers</a>
      <a href="admin_members.php"><i class="fa-solid fa-users"></i> Members</a>
      <a href="admin_finances.php"><i class="fa-solid fa-money-bill"></i> Finances</a>
      <a href="admin_stakeholder.php"><i class="fa-solid fa-handshake"></i> Stakeholders</a>
      <a href="admin_profile.php"><i class="fa-solid fa-user-cog"></i> Profile</a>
      
      <a href="#" onclick="toggleNotifPanel(event)" title="Notifications" style="position:relative; margin-right: 15px;">
        <i class="fa-solid fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span style="position:absolute; top:-5px; right:-10px; background:#ef4444; color:white; font-size:0.6rem; font-weight:800; padding:2px 5px; border-radius:10px; border:2px solid var(--primary);"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
        <?php endif; ?>
      </a>
      <a href="logout.php" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
    </nav>
  </header>

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <div>
        <h1>Manage Training Materials</h1>
        <p>Upload and distribute educational resources to cooperative members.</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Upload New Material -->
    <div class="glass-panel">
      <div class="section-title"><i class="fas fa-cloud-upload-alt"></i> Upload New Material</div>
      <form method="POST" enctype="multipart/form-data" class="form-container">
        <div class="form-group">
          <input type="text" id="title" name="title" placeholder=" " required>
          <label for="title">Title of Material</label>
        </div>
        <div class="form-group file-input">
          <label for="material_file" style="position:relative; top:0; left:5px; margin-bottom:5px;">File (PDF / DOC / PPT)</label>
          <input type="file" id="material_file" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx" required style="background: rgba(255,255,255,0.7);">
        </div>
        <button type="submit" name="upload_material" class="btn btn-primary">
          <i class="fas fa-upload"></i> Upload to Hub
        </button>
      </form>
    </div>

    <!-- Available Materials -->
    <div class="glass-panel">
      <div class="section-title"><i class="fas fa-book-open"></i> Available Materials Library</div>
      <?php if (empty($materials)): ?>
        <div class="no-data">
          <i class="fas fa-folder-open"></i>
          <p>No training materials uploaded yet. Add some resources using the form above.</p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>Uploaded By</th>
                <th>Date Added</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($materials as $mat): ?>
                <tr>
                  <td><i class="fa-solid fa-file-pdf" style="color: var(--primary); margin-right: 8px;"></i> <?= htmlspecialchars($mat['title']) ?></td>
                  <td><i class="fa-regular fa-circle-user" style="color: var(--text-muted); margin-right: 5px;"></i> <?= htmlspecialchars($mat['uploader'] ?: 'Unknown') ?></td>
                  <td><strong><?= date('d M Y', strtotime($mat['upload_date'])) ?></strong></td>
                  <td style="display: flex; gap: 10px;">
                    <?php if ($mat['content']): ?>
                      <a class="btn btn-secondary" style="height:40px; padding: 0.5rem 1rem;" href="<?= htmlspecialchars($mat['content']) ?>" target="_blank" rel="noreferrer"><i class="fas fa-download"></i> Download</a>
                    <?php endif; ?>
                    <a class="btn btn-danger" style="height:40px; padding: 0.5rem 1rem;" href="?delete=<?= $mat['id'] ?>" onclick="return confirm('Delete this material?');"><i class="fas fa-trash"></i> Delete</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2026 Agrilink Cooperative | <a href="mailto:livingstoniaagrilink@gmail.com">Support: livingstoniaagrilink@gmail.com</a></p>
  </footer>
</body>
</html>

