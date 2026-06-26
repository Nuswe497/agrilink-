<?php
session_start();
require 'db.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id);
            $stmt->fetch();

            $token = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
            $ins->bind_param("iss", $user_id, $token, $expires);
            $ins->execute();
            $ins->close();

            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $link = "http://{$host}{$path}/reset_password.php?token={$token}";

            $subject = 'Agrilink password reset';
            $message = "We received a password-reset request for your account.\n\n" .
                       "If you requested this, open the link below to reset your password (expires in 1 hour):\n\n{$link}\n\n" .
                       "If you did not request a reset, you can ignore this email.";
            $headers = "From: no-reply@{$host}\r\n";

            @mail($email, $subject, $message, $headers);
        }

        // Always show same response to avoid account enumeration
        $sent = true;
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Forgot password</title>
  <style>
    body{font-family:Segoe UI, sans-serif;/* replaced *//* replaced */
      position: relative;
      overflow-x: hidden;
      display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .box{background:#fff;padding:24px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.08);max-width:440px;width:100%}
    input{width:100%;padding:10px;margin-top:6px;border:1px solid #ccc;border-radius:6px}
    button{width:100%;padding:10px;margin-top:12px;background: #f4a261;color:#fff;border:none;border-radius:6px}
    .alert{color:red;margin-bottom:10px}
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
  <div class="box">
    <h2>Forgot password</h2>
    <?php if ($error): ?>
      <div class="alert"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <?php if ($sent): ?>
      <p>If an account exists with that email, a password-reset link has been sent.</p>
      <p><a href="login.php">Return to login</a></p>
    <?php else: ?>
      <form method="post">
        <label for="email">Enter your email address</label>
        <input id="email" type="email" name="email" required />
        <button type="submit">Send reset link</button>
      </form>
      <p style="margin-top:12px"><a href="login.php">Back to login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
