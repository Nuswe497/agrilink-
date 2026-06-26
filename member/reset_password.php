<?php
session_start();
require 'db.php';

$token = $_GET['token'] ?? '';
$error = '';
$valid = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($password === '' || $password_confirm === '') {
        $error = 'Please fill both password fields.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare("SELECT id, user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($prid, $user_id);
            $stmt->fetch();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $up->bind_param("si", $hash, $user_id);
            $up->execute();
            $up->close();

            $del = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
            $del->bind_param("i", $prid);
            $del->execute();
            $del->close();

            $stmt->close();
            $conn->close();

            header('Location: login.php?reset=1');
            exit;
        } else {
            $error = 'Invalid or expired token.';
        }

        $stmt->close();
    }
} else {
    if ($token) {
        $stmt = $conn->prepare("SELECT id FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $valid = true;
        } else {
            $error = 'Invalid or expired token.';
        }
        $stmt->close();
    } else {
        $error = 'Missing token.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Reset password</title>
  <style>
    body{font-family:Segoe UI, sans-serif;background:#f7f7f7;/* replaced */
      position: relative;
      overflow-x: hidden;
      display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .box{background:#fff;padding:24px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.08);max-width:440px;width:100%}
    input{width:100%;padding:10px;margin-top:6px;border:1px solid #ccc;border-radius:6px}
    button{width:100%;padding:10px;margin-top:12px;background:#2a9d8f;color:#fff;border:none;border-radius:6px}
    .alert{color:red;margin-bottom:10px}
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
  <div class="box">
    <h2>Reset password</h2>
    <?php if ($error): ?>
      <div class="alert"><?=htmlspecialchars($error)?></div>
      <p><a href="forgot_password.php">Request a new link</a></p>
    <?php elseif ($valid): ?>
      <form method="post">
        <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>" />
        <label>New password</label>
        <input type="password" name="password" required />
        <label>Confirm new password</label>
        <input type="password" name="password_confirm" required />
        <button type="submit">Set new password</button>
      </form>
    <?php endif; ?>
    <p style="margin-top:12px"><a href="login.php">Back to login</a></p>
  </div>
</body>
</html>
