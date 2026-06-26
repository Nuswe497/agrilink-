<?php
session_start();
require 'db.php';

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
                    header("Location:admin/admin.php");
                    break;
                case 'member':
                    header("Location:member/member.php");
                    break;
                     case 'treasurer':
                    header("Location:treasure/treasure.php");
                    break;
                     case 'secretary':
                    header("Location:secretary/secretary.php");
                    break;
                case 'external':
                    switch ($stakeholder_type) {
                        case 'supplier':
                            header("Location: stakeholder/supplier_dashboard.php");
                            break;
                        case 'buyer':
                            header("Location: stakeholder/buyer_dashboard.php");
                            break;
                        case 'ngo':
                            header("Location: stakeholder/ngo_dashboard.php");
                            break;
                        default:
                            header("Location: stakeholder/external_dashboard.php");
                            break;
                    }
                    break;
                default:
                    header("Location: default_dashboard.php");
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      /* replaced */
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .login-container {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 420px;
    }
    h2 {
      text-align: center;
      color: #2a9d8f;
      margin-bottom: 20px;
    }
    .form-group {
      margin-bottom: 15px;
      position: relative;
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
      padding: 10px 10px 10px 36px; /* extra left padding for icon */
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
    }
    button {
      width: 100%;
      padding: 12px;
      background-color: #f4a261;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
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
  <div class="login-container">
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
    <p><a href="forgot_password.php">Forgot password?</a></p>
    <p>Don't have an account? <a href="member_registration.php">Sign up here</a></p>
  </div>
</body>
</html>
