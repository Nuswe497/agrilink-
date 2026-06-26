<?php
session_start();
require '../db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT user_id, password_hash, role, stakeholder_type FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userId, $hash, $role, $stakeholder_type);
        $stmt->fetch();

        if (password_verify($password, $hash)) {
            $_SESSION["user_id"] = $userId;
            $_SESSION["role"]    = $role;
            $_SESSION["stakeholder_type"] = $stakeholder_type;

            switch ($role) {
                case 'admin':
                    header("Location: ../admin/admin.php");
                    break;
                case 'member':
                    header("Location: ../member/member.php");
                    break;
                case 'treasurer':
                    header("Location: ../treasure/treasure.php");
                    break;
                case 'secretary':
                    header("Location: ../secretary/secretary.php");
                    break;
                case 'external':
                    switch ($stakeholder_type) {
                        case 'supplier':
                            header("Location: ../stakeholder/supplier_dashboard.php");
                            break;
                        case 'buyer':
                            header("Location: ../stakeholder/buyer_dashboard.php");
                            break;
                        case 'ngo':
                            header("Location: ../stakeholder/ngo_dashboard.php");
                            break;
                        default:
                            header("Location: ../stakeholder/external_dashboard.php");
                            break;
                    }
                    break;
                default:
                    header("Location: ../member/default_dashboard.php");
                    break;
            }
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found with that email.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Agrilink Login</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="stylesheet" href="theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary:        #2a9d8f;
      --accent:         #f4a261;
      --accent-hover:   #e89c4f;
    }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #fffbe6, #fef6d3);
      margin: 0;
      padding: 0;
      position: relative;
      overflow-x: hidden;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    /* Navbar Standardized */
    .navbar-std {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 1000;
      background: var(--primary);
      box-shadow: 0 4px 20px rgba(0,0,0,0.12);
      height: 70px;
      display: flex;
      align-items: center;
    }
    .nav-inner {
      max-width: 1380px;
      margin: 0 auto;
      padding: 0 2.5rem;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .logo-std {
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
    
    .nav-right a {
      color: white;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      transition: color 0.25s ease;
    }
    .nav-right a:hover { color: var(--accent); }

    .login-container {
      background: #fff;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 15px 40px rgba(42, 157, 143, 0.12);
      width: 92%;
      max-width: 420px;
      text-align: center;
      margin: 40px auto;
      position: relative;
      z-index: 1;
    }
    .logo-wrap {
      margin-bottom: 20px;
    }
    .logo-wrap img {
      max-width: 120px;
      height: auto;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }
    h2 {
      text-align: center;
      color: #2a9d8f;
      margin-bottom: 20px;
      margin-top: 0;
    }
    .form-group {
      margin-bottom: 15px;
      position: relative;
      text-align: left;
    }
    .form-group i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }
    input {
      width: 100%;
      padding: 10px 10px 10px 36px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
      font-size: 1rem;
    }
    input:focus {
      outline: none;
      border-color: #2a9d8f;
      box-shadow: 0 0 0 2px rgba(42, 157, 143, 0.2);
    }
    button {
      width: 100%;
      padding: 12px;
      background-color: #f4a261;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    button:hover {
      background-color: #e89c4f;
    }
    .alert {
      color: red;
      text-align: center;
      margin-bottom: 10px;
    }
    p {
      text-align: center;
      margin-top: 15px;
      margin-bottom: 5px;
    }
    a {
      color: #2a9d8f;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <?php include 'glow_bg.php'; ?>
  <div class="login-container">
    <div class="logo-wrap">
      <img src="../assets/logo.png" alt="Agrilink Logo">
    </div>
    <h2><i class="fa-solid fa-right-to-bracket"></i> Login</h2>
    <?php if (isset($error)) echo "<div class='alert'>$error</div>"; ?>
    <form method="POST">
      <div class="form-group">
        <i class="fa-solid fa-envelope"></i>
        <input type="email" name="email" placeholder="Enter email" required />
      </div>
      <div class="form-group">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" placeholder="Enter password" required />
      </div>
      <button type="submit"><i class="fa-solid fa-sign-in-alt"></i> Login</button>
    </form>
    <p><a href="../member/forgot_password.php">Forgot password?</a></p>
    <p>Don't have an account? <a href="../member/member_registration.php">Sign up here</a></p>
  </div>
</body>
</html>
